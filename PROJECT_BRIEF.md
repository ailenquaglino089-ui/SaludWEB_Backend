# Project Brief — SaludWEB

| Campo | Detalle |
| --- | --- |
| **Nombre del proyecto** | SaludWEB |
| **Materia** | Programación IV |
| **Equipo** | Equipo SaludWEB (Ailen Quaglino) |
| **Fecha de emisión** | 22/09/2026 |
| **Estado** | Versión 1.2 — lista para entrega final (30/09/2026) |
| **Repos** | `SaludWEB_Backend` (API REST), `SaludWEB_Web` (SPA React), `SaludWEB_Mobile` (app móvil) |

---

## 1. Resumen ejecutivo

SaludWEB es un sistema de gestión de salud web que centraliza la administración de
**pacientes**, **médicos** y **prescripciones médicas** (recetas digitales) en una sola
plataforma accesible desde navegador (SPA) y dispositivos móviles.

## 2. Problema que resuelve

La gestión de pacientes, profesionales y recetas se realiza de forma manual o
desconectada (fichas en papel, planillas, sistemas por separado), lo que genera:

- Duplicación de datos y errores de tipeo.
- Dificultad para encontrar el historial de prescripciones de un paciente.
- Riesgo de que personas sin rol médico generen recetas (problema de seguridad clínica).
- Imposibilidad de consultar el sistema desde el celular.
- Datos desactualizados en pantalla: con polling adaptativo, el panel recién refleja una
  reserva nueva después del siguiente intervalo, y con la ventana abierta el usuario no
  termina de saber si lo que ve está al día.

## 3. Objetivos

**Objetivo general**

Desarrollar un sistema de gestión de salud integral y responsivo que permita
administrar pacientes, médicos y prescripciones con control de acceso por roles.

**Objetivos específicos**

1. Abanico de altas, bajas, modificaciones y listados (CRUD) de pacientes y médicos.
2. Emisión de prescripciones digitales respetando la regla de negocio del profesor:
   *"Las prescripciones sólo pueden ser hechas por Médicos"*.
3. Cambio de estado de prescripciones (activa, vencida, dispensada, cancelada).
4. Búsqueda y paginado de todos los listados desde el backend.
5. Autenticación JWT con roles (admin, médico, paciente) y rutas protegidas (401/403).
6. Interfaces responsivas (tablas adaptables a mobile) y experiencia CRUD completa
   (spinners, validación, confirmaciones, notificaciones).
7. Aplicación móvil que consuma la misma API.
8. Actualización del panel en vivo (Server-Sent Events), sin polling y sin recargar la
   pantalla cuando alguien reserva o cancela un turno.

## 4. Alcance

### Incluido

- Backend API REST: PHP + MySQL + JWT (Patrones Repository, Service y Controller).
- Frontend web SPA: React + Vite (axios, contexto de autenticación, RBAC en UI).
- Aplicación mobile que consume la API.
- Autenticación con roles y protección de rutas (pública / protegida / solo-admin / solo-médico).
- CRUD completo de pacientes, médicos y prescripciones.
- Búsqueda, filtros y paginado server-side.
- Notificaciones (toasts) y confirmaciones de eliminación.
- **Tiempo real en el panel** con SSE (`GET /api/eventos`), con canales autorizados por rol
  y sin duplicar estado: el evento avisa, el dato se sigue pidiendo por REST.

### Excluido (a futuro)

- Módulo de farmacia autónomo (rol *farmacéutico* que dispense recetas).
- Facturación / obras sociales integradas.
- Envío de correos o SMS.
- Hosting en producción con dominio propio.

## 5. Usuarios y roles

| Rol | Permisos destacados |
| --- | --- |
| **Admin** | Gestiona pacientes, médicos y prescripciones (alta, edición, baja). Cambia estado de prescripciones. **No receta** (regla de negocio: solo médicos). |
| **Médico** | Crea y edita prescripciones, cambia su estado. Consulta listados. |
| **Paciente** | Consulta sus datos y el estado de sus prescripciones. No receta ni edita médicos. |

## 6. Arquitectura y stack tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend (API REST) | PHP 8, MySQL, JWT (firebase/php-jwt), XAMPP |
| Frontend Web | React 18, Vite, Axios, CSS propio |
| Tiempo real | Server-Sent Events nativo del navegador (`EventSource`) sobre HTTP; sin librerías externas |
| Mobile | Aplicación en `SaludWEB_Mobile` (consume la misma API) |
| Persistencia | MySQL + SQL preparado (anti-inyección) |
| Control de versiones | Git / GitHub — rama principal protegida, trabajo en rama `dev` |

**Patrones backend:** Repository Pattern, Service Layer, Controller, Middleware de
autenticación, Rate Limiter.

## 7. Entregables

- Repositorios con código fuente (Backend, Web, Mobile) con rama `dev` e integración a `main`.
- **Project Brief** (este documento) y **Agenda de Trabajo** (`AGENDA_DE_TRABAJO.md`).
- Aplicación funcional verificada en vivo (endpoints probados con HTTP y suites `probar_*.php`).
- Módulos adicionales del ciclo final: **turnera** (agenda, reserva, mis turnos, estados),
  **gestión de roles desde la Web** (pantalla Usuarios), **PWA** (instalable + arranque
  offline del caparazón), **biometría** y **SSO** en la app mobile.
- **Tiempo real con SSE** (guía "Del Polling a la Inmediatez"): tabla outbox, repositorio,
  servicio de canales, controlador SSE, emisión desde la turnera, panel migrado y su guía
  (`GUIA_TIEMPO_REAL.md`).

## 8. Riesgos principales

| Riesgo | Mitigación |
| --- | --- |
| Violación de la regla "solo médicos recetan" | Restricción a nivel de ruta en backend (403) + ocultado en la UI por rol. |
| Tablas rotas en mobile / pantallas chicas | Diseño responsivo con `data-label` y media queries. |
| Listados muy grandes => rendimiento | Paginado y búsqueda resueltos en el backend (server-side). |
| Doble envío / borrado accidental | Modal de confirmación, botones deshabilitados durante la petición. |
| Errores opacos al usuario | `handleApiError` centralizado + toasts (éxito/info auto-ocultables, errores persistentes). |
| Que el canal SSE se use para enviar datos y termine siendo una segunda fuente de verdad | El evento solo transporta tipo + identificadores; los datos se piden por REST. |
| Que un canal leaks información de otro paciente | El canal se resuelve con el token: pedir un canal ajeno responde `403`. |
| Que una conexión SSE bloquee el resto de la aplicación | `session_write_close()` antes del bucle y cierre del canal a los 300 s. |
| Que un fallo al publicar el evento revierta una reserva | La publicación va en `try/catch` después de la escritura de negocio. |

## 9. Criterios de éxito

- Las 5 correcciones solicitadas por el profesor quedan resueltas y verificadas.
- Las reglas de negocio se cumplen tanto en backend (403) como en la UI (roles ocultos).
- La aplicación es usable desde escritorio, tablet y celular.
- Todo el código vive en la rama `dev` y se integra a `main` vía Pull Request.
- La documentación (Brief + Agenda) acompaña al código en el repositorio.
- El panel se actualiza por SSE al reservar o cancelar un turno, y la verificación confirma
  que en reposo no se repite ninguna petición REST al endpoint de estadísticas.

---

*Documento de carácter académico para la aprobación del proyecto de Programación IV.*