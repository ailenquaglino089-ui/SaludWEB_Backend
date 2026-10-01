# Repositorio Backend — SaludWEB

Capa de **API y lógica de acceso a datos** del proyecto **SaludWEB** (Programación IV).
Expone únicamente endpoints **JSON**, desacoplado por completo del frontend (la SPA web y
la app móvil consumen esta misma API).

## Qué hacemos acá (visión general)

- **API REST** en PHP puro con arquitectura en capas
  (`routes.php` → `controllers/` → `services/` → `persistence/`), leída de arriba a abajo
  y comentada **línea por línea**.
- **Autenticación JWT** (HS256) con rutas *públicas*, *protegidas* (401 si falta token) y
  *solo-admin* (403 si el rol no corresponde).
- **Módulo de roles y permisos**: las cuentas tienen rol `admin | medico | paciente` y el
  backend valida cada operación, nunca el frontend.
- **Vinculación de cuentas con fichas** (turnera): al loguearse, el usuario debe quedar
  vinculado a una ficha — el **médico** con su **matrícula** y el **paciente** con su **DNI** —
  para saber *a quién* se le asigna cada turno.
- **Turnera (sistema de gestión de citas online)**: especialidades, disponibilidades,
  reserva/cancelación de turnos, agenda, notificaciones con token y estadísticas de gestión.
- **Tiempo real con Server-Sent Events (SSE)**: cuando cambia un turno, el servidor empuja el
  aviso al canal correspondiente y el panel se actualiza solo, sin polling. La documentación
  completa está en **[`GUIA_TIEMPO_REAL.md`](GUIA_TIEMPO_REAL.md)**.
- **SSO** con Google / Microsoft (PKCE, sin secret en el cliente).

## Contenido

```
routes.php                  →  todas las rutas de la API (leer primero)
index.php                   →  punto de entrada (single entry point)
.htaccess                   →  enrutamiento de Apache hacia index.php
db.php                      →  conexión y creación automática de la BD
core/                       →  Router, Response, JwtService, AuthMiddleware, Config, Secret
controllers/ services/      →  lógica HTTP y de negocio (una por recurso)
persistence/                →  consultas SQL (patrón Repositorio)
vendor/                     →  firebase/php-jwt (instalado con Composer)
GUIA_TIEMPO_REAL.md         →  guía del módulo de tiempo real (SSE): leer primero
sembrar_datos_demo.php      →  regenera las fichas de demostración (idempotente)
probar_roles.php            →  suite de pruebas de roles y permisos
probar_roles_http.php       →  idem, contra la API real por HTTP
probar_vinculacion.php      →  suite de pruebas de vinculación de fichas
probar_turnera.php          →  suite de pruebas del módulo turnera
probar_tiempo_real.php      →  verificación del módulo de tiempo real (18 pruebas + latencia)
verificar_turnera.php       →  verificación rápida de salud de la turnera
AGENDA_DE_TRABAJO.md        →  planificación, hitos y pasos del proyecto
PROJECT_BRIEF.md            →  brief del proyecto y entregables
```

## Tiempo real: cómo funciona

Esta API es REST y responde JSON, **con una excepción**: `GET /api/eventos` abre un canal de
Server-Sent Events y mantiene la conexión abierta escribiendo texto plano.

```
GET /api/eventos?token=<jwt>&canal=<canal>
```

| Canal | Quién puede | Recibe |
|---|---|---|
| `tablero` | admin, médico | Indicadores del consultorio |
| `mi-agenda` | médico | Cambios en su propia agenda |
| `agenda:<id>` | solo admin | Agenda de un profesional |
| `mis-turnos` | paciente | Sus propios turnos |

El cliente pide el canal por nombre y **el backend lo traduce con el token**: un paciente que
escriba `agenda:10` recibe 403. El identificador sale del token, nunca de la URL.

**Lo que NO hace este módulo:** no guarda una copia de las citas ni de las estadísticas. La
tabla `eventos_realtime` es una bandeja de avisos que se purga a los 30 minutos. Los números
que se muestran siguen viniendo por REST desde la fuente única de verdad.

**Verificación:**

```
http://localhost/Workspace_SaludWEB/SaludWEB_Backend/probar_tiempo_real.php
```

Diagnostica tabla, índices, cursor, autorización de los cuatro canales, purga, y mide la
latencia real abriendo un canal HTTP y publicando un evento.

### Poner el tiempo real en marcha, paso a paso

1. **Levantar MySQL y Apache** desde el Control Panel de XAMPP.
2. **Abrir el backend una vez** para que `db.php` cree la tabla `eventos_realtime`
   automáticamente (no hay que importar ningún SQL):
   ```
   http://localhost/Workspace_SaludWEB/SaludWEB_Backend/api/health
   ```
   Tiene que responder `200`.
3. **Confirmar que el canal está montado**:
   ```
   http://localhost/Workspace_SaludWEB/SaludWEB_Backend/api/eventos/estado
   ```
   Responde con los canales admitidos y los tipos de evento.
4. **Abrir un canal a mano** (queda la pantalla esperando eventos):
   ```
   http://localhost/Workspace_SaludWEB/SaludWEB_Backend/api/eventos?token=<JWT>&canal=tablero
   ```
   Aparece `retry: 3000` y un evento `conectado`. A partir de ahí, cada turno que se
   reserve aparece solo.
5. **Probar todo el módulo**:
   ```
   http://localhost/Workspace_SaludWEB/SaludWEB_Backend/probar_tiempo_real.php
   ```
   18 verificaciones, incluyendo los cuatro rechazos por rol.

> El paso 4 sirve para ver el stream crudo. Para verlo **aplicado a la interfaz** hay que
> levantar el frontend: ver la guía de la Web.

## Puesta en marcha

### Requisitos
- PHP 8.x
- Composer
- MySQL corriendo (en XAMPP: iniciar el módulo `MySQL` desde el Control Panel)

### 1. Clonar el repositorio
```bash
git clone https://github.com/ailenquaglino089-ui/SaludWEB_Backend.git
cd SaludWEB_Backend
```

### 2. Instalar dependencias
```bash
composer install
```

Si Composer no está instalado globalmente, descargá `composer.phar` dentro de la carpeta
del proyecto y ejecutá:

```bash
php composer.phar install
```

### 3. Configurar entorno (opcional)
Copia `.env.example` a `.env` y ajustá los valores (base de datos, `JWT_SECRET`,
`CORS_ORIGINS`, `SSO_*`). En desarrollo, si no se configura nada, se usan los valores por
defecto de `db.php` y `core/Secret.php`.

### 4. Correr el backend

**Opción A: servidor de desarrollo de PHP**
```bash
php -S localhost:8000 index.php
```

**Opción B: Apache/XAMPP**
Copiá la carpeta dentro de `htdocs` (ej: `C:\xampp\htdocs\SaludWEB_Backend`) y abrí:
```
http://localhost/SaludWEB_Backend/api/health
```

> En el primer request, `db.php` crea automáticamente la base de datos, las tablas y los
> datos de ejemplo (no hace falta importar ningún SQL).

### 5. Verificar
```bash
curl http://localhost:8000/api/health
```
Debe responder `200` con `{"data":{"status":"ok",...},"message":"API SaludWEB - Programación IV"}`.

## Autenticación y SSO

1. `POST /api/auth/login` con `{ "email": "...", "password": "..." }` → devuelve un JWT.
2. Enviarlo en cada petición protegida: `Authorization: Bearer <jwt>`.
3. El secreto se toma de `JWT_SECRET` o se genera en `storage/secret.key`.
4. `POST /api/auth/sso` con `{ "provider": "google" | "microsoft", "id_token": "..." }`
   → valida la firma del token contra las claves públicas (JWKS) y devuelve el JWT de
   SaludWEB. **No auto-crea cuentas**: solo habilita cuentas locales existentes y activas
   (el email del proveedor debe coincidir). Sin credenciales configuradas responde `501`.
5. `POST /api/auth/vincular` con `{ "tipo": "medico", "documento": "<matrícula>" }` o
   `{ "tipo": "paciente", "documento": "<DNI>" }` → vincula la cuenta con su ficha.
   Sin ficha vinculada, el usuario no puede reservar turnos.

## Módulos y reglas de negocio

| Módulo | Rutas principales | Regla |
|---|---|---|
| Catálogos | `GET /api/medicos`, `/api/pacientes`, `/api/obras-sociales` | Públicos |
| Médicos | `POST/PUT/PATCH/DELETE /api/medicos/{id}` | **Solo admin** (403 si no) |
| Pacientes | `POST/PUT/PATCH` (protegido); `DELETE` | Crear/editar con token; borrar solo admin |
| Prescripciones | `GET` (protegido, dato clínico); `POST/PUT/PATCH`; `DELETE`; `PATCH .../estado` | Crear/editar **solo médico**; borrar solo admin; cambiar estado con token |
| Turnera | `GET /api/especialidades`, `/api/disponibilidades`, `/api/citas/disponibilidad` | Catálogos y disponibilidad públicos |
| Turnera | `GET /api/citas/agenda`, `/api/citas`, `POST /api/citas`, `POST /api/citas/{id}/cancelar`, `PATCH /api/citas/{id}/estado` | Protegidas; el paciente solo ve sus turnos |
| Turnera | `DELETE /api/citas/{id}`, `POST/PUT/DELETE /api/especialidades` | Solo admin |
| Turnera | `POST /api/disponibilidades` (+ PUT/DELETE), `GET /api/estadisticas` | El profesional publica su propia agenda |
| Notificaciones | `GET /api/notificaciones`, `POST .../recordatorios`, `.../{id}/reintentar` | Protegidas |
| Notificaciones | `POST /api/notificaciones/procesar` | Solo admin |
| Usuarios | `GET /api/usuarios`, `GET /api/usuarios/roles`, `PATCH /api/usuarios/{id}/rol` | Solo admin |

## Datos de demostración

Las cuentas de prueba (creadas por la base al arrancar) ya tienen ficha vinculada:

| Rol | Usuario | Contraseña |
|---|---|---|
| Médico | `medico@prueba.com` | `medico123` |
| Paciente | `paciente@prueba.com` | `paciente123` |
| Administradora | `admin@salud.com` | (contraseña personal, no está en el repo) |

Si los datos de demo se desarmaron, regeneralos con:
```bash
php sembrar_datos_demo.php
```
El script es **idempotente**: crea o adopta fichas según matrícula/DNI, vincula las cuentas
y no toca contraseñas.

## Pruebas

Las suites están comentadas línea por línea y no tocan la base (crean sus propios datos y
los borran al terminar):

```bash
php probar_roles.php          # roles y permisos (matriz completa)
php probar_roles_http.php     # idem, contra la API por HTTP (requiere servidor)
php probar_vinculacion.php    # vinculación de fichas (médicos y pacientes)
php probar_turnera.php        # turnera completa (requiere API en 127.0.0.1:8080)
php verificar_turnera.php     # chequeo rápido de salud de la turnera
```

La del módulo de tiempo real se abre en el navegador, porque la medición de latencia necesita
abrir una conexión HTTP contra el propio servidor:

```
http://localhost/Workspace_SaludWEB/SaludWEB_Backend/probar_tiempo_real.php
```

## Documentación del proyecto

- **`GUIA_TIEMPO_REAL.md`** — el módulo de tiempo real completo: por qué SSE y no WebSocket,
  cómo funciona el canal, cómo se autoriza, las pruebas y las limitaciones conocidas.
- `AGENDA_DE_TRABAJO.md` — todas las fases, hitos, decisiones y resultados (F1 a F5).
- `PROJECT_BRIEF.md` — brief y entregables.