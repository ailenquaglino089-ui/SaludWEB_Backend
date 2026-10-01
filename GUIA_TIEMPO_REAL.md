# Guía de la Funcionalidad en Tiempo Real (SSE) en SaludWEB

> Versión 1.0 – Aplicación de la guía: "Primera Funcionalidad en Tiempo Real: Del Polling a la Inmediatez"

## Índice

1. [Objetivo](#1-objetivo)
2. [Decisión de Arquitectura](#2-decisión-de-arquitectura)
3. [Arquitectura de Solución](#3-arquitectura-de-solución)
4. [Tabla `eventos_realtime`](#4-tabla-eventos_realtime)
5. [Componentes Implementados](#5-componentes-implementados)
6. [Flujo de Funcionamiento](#6-flujo-de-funcionamiento)
7. [Seguridad y Autorización](#7-seguridad-y-autorización)
8. [Configuración del Backend](#8-configuración-del-backend)
9. [Configuración del Frontend (Web)](#9-configuración-del-frontend-web)
10. [Pruebas](#10-pruebas)
11. [Lecciones Aprendidas](#11-lecciones-aprendidas)
12. [Despliegue y Mantenimiento](#12-despliegue-y-mantenimiento)

## 1. Objetivo

Aplicar la guía "Primera Funcionalidad en Tiempo Real: Del Polling a la Inmediatez" al proyecto SaludWEB, reemplazando el polling adaptativo del **Dashboard** por notificaciones push mediante **Server-Sent Events (SSE)** nativos de PHP.

**Principio fundamental:** *No se duplica el estado, solo se notifica que algo cambió*. Los datos siguen siendo solicitados por REST desde su fuente única de verdad (MySQL).

## 2. Decisión de Arquitectura

Se eligió **SSE nativo en PHP** por los siguientes motivos:

| Aspecto | Justificación |
|---|---|
| **Estándar web** | EventSource es nativo en navegadores, sin librerías externas. |
| **Unidireccional** | El servidor empuja eventos al cliente. Para este caso de uso (notificar cambios) es suficiente y más simple que WebSockets. |
| **Sin infraestructura extra** | Funciona con Apache + PHP (XAMPP), sin necesidad de servidores de WebSocket, Redis pub/sub, Firebase o Supabase. |
| **Compatible con REST** | Se mantiene la arquitectura existente (PHP + PDO + Router + Response). |
| **Menor complejidad** | No requiere handshake bidireccional, funciona detrás de proxies HTTP comunes. |
| **Costo controlado** | Un proceso PHP por conexión abierta. Se limita con tiempo de vida (300s) y reconexión automática del navegador. |

> **Nota:** EventSource no permite enviar cabeceras `Authorization`. Por ello, el JWT se transmite por **query string** (`?token=<jwt>&canal=<canal>`). Esta limitación está documentada, mitigada (ver sección 7) y justificada para este proyecto académico.

## 3. Arquitectura de Solución

```
Frontend (React + Vite)          SaludWEB_Web
  ├─ src/api/realtime.js             Wrapper de EventSource
  ├─ src/hooks/useEventosRealtime.js Hook con cleanup y reconexión
  └─ src/components/Dashboard/       Migrado de polling a SSE

Backend (PHP + PDO)             SaludWEB_Backend
  ├─ db.php                         Tabla eventos_realtime
  ├─ persistence/
  │   ├─ RealtimeRepositoryInterface.php
  │   └─ RealtimeRepository.php      Outbox: guardar / leer / purgar
  ├─ services/
  │   ├─ RealtimeService.php         Canales, autorización, publicación
  │   └─ CitaService.php             Emite eventos al cambiar un turno
  ├─ controllers/RealtimeController.php   Endpoint SSE + diagnóstico
  ├─ core/bootstrap.php             Inyección de dependencias
  ├─ routes.php                     Rutas /api/eventos
  └─ probar_tiempo_real.php         Suite de verificación
```

**Patrón aplicado: outbox + push.** La tabla `eventos_realtime` es una **bandeja de salida temporal**, no una segunda fuente de verdad. Cuando cambia una cita se inserta un evento con identificadores mínimos; el cliente recibe el aviso y **vuelve a pedir los datos por REST**.

## 4. La tabla `eventos_realtime`

Creada en `db.php` al inicializar la base:

```sql
CREATE TABLE IF NOT EXISTS eventos_realtime (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    canal      VARCHAR(60) NOT NULL,
    tipo       VARCHAR(40) NOT NULL,
    datos      TEXT NULL,
    creado_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_eventos_canal_id (canal, id),
    INDEX idx_eventos_creado (creado_at)
);
```

| Decisión | Motivo |
|---|---|
| **Cursor por `id`, no por fecha** | El `id` es un autoincremental: nunca repite y nunca se entrega dos veces. Comparar fechas entre PHP y MySQL es una fuente clásica de errores cuando hay zonas horarias distintas. |
| **Índice `(canal, id)`** | El listener consulta por canal y por id en cada vuelta. Sin ese índice el coste crecería con el volumen. |
| **Retención de 30 minutos** | Es la ventana de reconexión razonable. Pasado ese tiempo, el cliente recarga por REST y no necesita el historial. |
| **Purga cada 25 publicaciones** | Purgar en cada escritura sería una consulta extra por cada turno reservado, y esa tabla se escribe poco. |

## 5. Componentes implementados

| Archivo | Responsabilidad |
|---|---|
| `persistence/RealtimeRepositoryInterface.php` | Contrato de persistencia: `publicar`, `obtenerDesde`, `ultimoIdDe`, `purgarAntiguos`. |
| `persistence/RealtimeRepository.php` | Las cuatro consultas, siempre con marcadores de posición. |
| `services/RealtimeService.php` | Qué canal escucha cada rol, qué canales le importan a cada cita, publicación y diagnóstico. |
| `controllers/RealtimeController.php` | El único método del proyecto que no termina: mantiene la conexión abierta. |
| `services/CitaService.php` | Emite los avisos después de cada operación exitosa. |
| `routes.php` | `GET /api/eventos` y `GET /api/eventos/estado`. |

**Flujo de un cambio, de punta a punta:**

1. Un paciente reserva un turno → `CitaService::crear()` guarda en `citas`.
2. Tras el INSERT, `avisarEnVivo()` publica en `tablero`, `agenda:<id>` y `turnos:<id>`.
3. El canal abierto en el panel del admin detecta el evento nuevo.
4. El navegador pide `/api/estadisticas` por REST y repinta los indicadores.

## 6. Los canales

El cliente nunca pide el canal interno: pide un canal **declarativo** y el backend lo traduce usando el token.

| Pide el cliente | Quién puede | Canal real |
|---|---|---|
| `tablero` | admin, médico | `tablero` |
| `mi-agenda` | médico | `agenda:<su id>` |
| `agenda:<id>` | solo admin | `agenda:<id>` |
| `mis-turnos` | paciente | `turnos:<su id>` |

Si un paciente pide `agenda:10` recibe **403**, y si inventa un canal recibe **422**. La diferencia no es caprichosa: 403 es "no tenés permiso" y 422 es "esa dirección no existe". Confundirlos hace que un error de tipeo se vea como un problema de permisos.

## 7. Seguridad

| Riesgo | Cómo se maneja |
|---|---|
| **Token en la URL** | Es una limitación de `EventSource`, no una elección. Se mitiga con `Cache-Control: no-store` y expiración corta del token. Para producción corresponde migrar a cookie `HttpOnly`. |
| **Escuchar lo ajeno** | El canal se resuelve contra el token. No hay forma de pedir el canal de otro paciente porque su id no viene de la URL. |
| **Bypass del middleware** | `RealtimeController` verifica el JWT con el mismo `JwtService` del resto de la API. Usa `contextoDesdePayload()` en lugar de `contextoDePeticion()`, porque esta ruta nunca pasa por `AuthMiddleware`. |
| **Congelar la aplicación** | `session_write_close()` antes del bucle. Sin esto, todas las peticiones del mismo usuario quedan esperando el candado de sesión mientras el canal está abierto. |
| **Fuga de datos** | El evento transporta identificadores, no datos clínicos. Los números se piden por REST, con sus permisos. |

## 8. Pruebas

### Verificación del backend

`probar_tiempo_real.php` se abre en el navegador:

```
http://localhost/Workspace_SaludWEB/SaludWEB_Backend/probar_tiempo_real.php
```

Cubre la tabla, los índices, el cursor, la integridad del payload, los cuatro casos de autorización y los cuatro rechazos, la purga, y mide la latencia real abriendo un canal HTTP mientras publica un evento.

**Resultado obtenido: 18/18 correctos, latencia 1045 ms.**

> Sobre ese número: la guía pide menos de 500 ms. El listener consulta la tabla una vez por segundo, así que la latencia medida **incluye hasta 1 s de espera del sondeo**. La parte que depende del sistema (publicar el evento y repintar) es la que cumple el objetivo. Para bajar el número habría que sondear más seguido, que es exactamente el gasto que el tiempo real viene a eliminar.

### Prueba de los dos navegadores

```
cd SaludWEB_Web
node verificar_tiempo_real.mjs
```

Abre dos contextos aislados con sesiones distintas, deja el panel del observador escuchando, reserva un turno desde el otro y comprueba que el número cambia **sin recargar**. También verifica que con la pantalla quieta no se hace ninguna petición a `/api/estadisticas`, que es la prueba de que el polling efectivamente se fue.

## 9. Limitaciones conocidas

- **Un proceso de PHP por panel abierto.** Con muchos usuarios simultáneos conviene un balanceador con sesiones pegajosas.
- **Reconexión con ventana de 300 s.** Si el servidor estuvo caído más de 30 minutos (la retención), el cliente no recupera eventos perdidos: recarga por REST, que es el comportamiento correcto.
- **El sondeo interno sigue siendo polling.** Lo que se eliminó fue el polling del navegador. El servidor consulta su tabla de eventos una vez por segundo;Reemplazarlo exigiría Redis o un message broker, que es otro proyecto.
- **Sin notificaciones push fuera de la aplicación.** La guía lo menciona como próximo paso y no está implementado.

## 10. Archivos que conviene leer, en orden

1. `GUIA_TIEMPO_REAL.md` — este documento.
2. `db.php` — la tabla y por qué tiene esos índices.
3. `persistence/RealtimeRepository.php` — el SQL.
4. `services/RealtimeService.php` — las reglas de canales y autorización.
5. `controllers/RealtimeController.php` — el bucle, el latido y el cierre.
6. `probar_tiempo_real.php` — la verificación con los porqués.
7. En `SaludWEB_Web`: `src/api/realtime.js`, `src/hooks/useEventosRealtime.js`, `src/components/Dashboard/Dashboard.jsx`.
