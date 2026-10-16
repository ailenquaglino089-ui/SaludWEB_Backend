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
.htaccess                   →  reescritura de URLs hacia index.php (front controller)
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
probar_seguridad.php        →  regresión de seguridad (401 sin token, 422 al registrar admin)
resetear_password.php       →  (re)establece la contraseña de una cuenta (solo consola/desarrollo)
listar_usuarios.php         →  lista las cuentas existentes sin mostrar contraseñas (desarrollo)
migrar_mysql_a_sqlite.php   →  importa datos de una base MySQL vieja a SQLite (opcional)
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
http://127.0.0.1:8000/probar_tiempo_real.php
```

Diagnostica tabla, índices, cursor, autorización de los cuatro canales, purga, y mide la
latencia real abriendo un canal HTTP y publicando un evento.

### Poner el tiempo real en marcha, paso a paso

1. **Levantar el backend**: con `composer run dev` (servidor nativo de PHP). No hace falta
   ningún motor de base de datos: SQLite es un archivo y se crea solo.
2. **Abrir el backend una vez** para que `db.php` cree la tabla `eventos_realtime`
   automáticamente (no hay que importar ningún SQL):
   ```
   http://127.0.0.1:8000/api/health
   ```
   Tiene que responder `200`.
3. **Confirmar que el canal está montado**:
   ```
   http://127.0.0.1:8000/api/eventos/estado
   ```
   Responde con los canales admitidos y los tipos de evento.
4. **Abrir un canal a mano** (queda la pantalla esperando eventos):
   ```
   http://127.0.0.1:8000/api/eventos?token=<JWT>&canal=tablero
   ```
   Aparece `retry: 3000` y un evento `conectado`. A partir de ahí, cada turno que se
   reserve aparece solo.
5. **Probar todo el módulo**:
   ```
   http://127.0.0.1:8000/probar_tiempo_real.php
   ```
   18 verificaciones, incluyendo los cuatro rechazos por rol.

> El paso 4 sirve para ver el stream crudo. Para verlo **aplicado a la interfaz** hay que
> levantar el frontend: ver la guía de la Web.

> **Nota sobre el servidor nativo:** `php -S` atiende de a una petición a la vez, así que
> mientras un canal SSE queda abierto, otra petición no avanza. Para ver el tiempo real con
> dos pestañas conviene usar un servidor con varios workers (PHP-FPM + nginx, u otro) o
> correr dos instancias en puertos distintos. En desarrollo, `composer run dev` alcanza para
> la API y las pruebas REST.

## Puesta en marcha

### Requisitos
- **PHP 8.x** (solo el intérprete de línea de comandos; **no** hace falta el panel de XAMPP,
  ni Apache, ni MySQL).
- **Composer**
- *(Nada más: la base es SQLite, viene con PHP y se crea sola. No hay que instalar ni
  iniciar ningún servidor de base de datos.)*

> **Sin XAMPP y sin MySQL.** La API usa el **servidor nativo de PHP** (`php -S`) y guarda en
> un **archivo SQLite** dentro del proyecto. No se levanta Apache ni el servicio de MySQL:
> XAMPP es totalmente opcional. Si ya tenés XAMPP, alcanza con usar su `php.exe` desde la
> terminal, sin abrir el panel de control ni iniciar ningún módulo; y si no tenés XAMPP,
> instalás un PHP propio (se explica abajo).

### Instalar un PHP propio (sin XAMPP)

Esta es la forma recomendada para no depender de XAMPP. En Windows:

1. **Descargar PHP 8** (build "Non Thread Safe", que es el recomendado para CLI) desde
   <https://windows.php.net/download/>. Elegí, por ejemplo,
   `php-8.3.x-nts-Win32-vs16-x64.zip`.
2. **Descomprimirlo** en una carpeta sin espacios, por ejemplo `C:\Users\<usuario>\php83`.
3. **Crear `php.ini`**: copiá `php.ini-development` a `php.ini` y **descomentá** (sacarle el
   `;` del principio) estas líneas, para que la API tenga SQLite y los utilitarios:
   ```ini
   extension_dir = "ext"
   extension=pdo_sqlite
   extension=sqlite3
   extension=mbstring
   extension=openssl
   extension=curl
   extension=fileinfo
   extension=zip
   date.timezone = America/Argentina/Buenos_Aires
   ```
4. **Verificar** que quedó bien:
   ```bash
   C:\Users\<usuario>\php83\php.exe -v          # muestra la versión
   C:\Users\<usuario>\php83\php.exe -m          # debe listar pdo_sqlite, sqlite3, mbstring, openssl...
   ```
5. Usá esa ruta para arrancar la API (ver el paso 4 más abajo). No hace falta agregar nada
   al PATH: alcanza con escribir la ruta completa, o definir la variable de entorno
   `SALUDWEB_PHP` con la ruta al `php.exe`.

> En macOS o Linux, normalmente alcanza con el PHP del sistema
> (`sudo apt install php-cli php-sqlite3` en Debian/Ubuntu) y la extensión `pdo_sqlite`.

> **¿Todo de una sola vez?** El repo **de la Web** trae scripts de arranque listos
> (`SaludWEB_Web\scripts\iniciar_saludweb.bat` y `detener_saludweb.bat`) que levantan la API
> y la web juntas, detectando el PHP solo. Ver "Cómo abrir todo el proyecto" en el README de
> la Web.

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

### 3. Configurar el entorno (opcional)

```bash
copy .env.example .env
```

El archivo `.env` lo lee `core/Env.php`, que se ejecuta al principio de
`core/bootstrap.php` (peticiones HTTP) y de `db.php` (scripts de la raíz).
**Si no lo creás, no pasa nada:** cada variable tiene un valor por defecto
escrito en el código. Crear el `.env` es opcional en desarrollo local y
obligatorio cuando la configuración se sale de ese caso base:

- querés mover el archivo de la base a otra ruta (`DB_DATABASE`);
- querés fijar la vigencia de los tokens (`JWT_TTL`);
- los scripts de prueba tienen que apuntar a otra URL (`API_BASE`);
- configurás SSO (`SSO_*`).

La lista completa está en [Variables de entorno](#variables-de-entorno).

### 4. Correr el backend

```bash
composer run dev
# equivalente a:
php -S 127.0.0.1:8000 index.php
```

El servidor nativo de PHP usa `index.php` como router (front controller), y las
rutas y los códigos de estado son idénticos a los de un servidor web tradicional.

> En el primer request, `db.php` crea automáticamente el archivo de base de datos, las
> tablas y los datos de ejemplo (no hace falta importar ningún SQL ni iniciar ningún
> servicio). La base es un solo archivo: por defecto `storage/saludweb.sqlite`, y se
> puede cambiar con `DB_DATABASE` en el `.env`.

> **Para el tiempo real (SSE) en vivo:** `php -S` atiende de a una petición a la vez.
> Alcanza para desarrollar la API y probar la SPA con el proxy de Vite, pero para
> mantener un canal `/api/eventos` abierto mientras hacés otras peticiones conviene un
> servidor con varios workers (por ejemplo PHP-FPM con nginx, o dos instancias en
> puertos distintos).

### 5. Verificar
```bash
curl http://127.0.0.1:8000/api/health
```
Debe responder `200` con `{"ok":true,"mensaje":"API SaludWEB - Programación IV","data":{"status":"ok",...}}`.

## Variables de entorno

Todas son opcionales en desarrollo: si no existen, manda el default. La jerarquía
es **variable real del sistema > `.env` > valor por defecto del código**, así que
en un servidor se pueden definir del panel o del servicio sin tocar el archivo.

| Variable | Default | Qué controla |
|---|---|---|
| `APP_ENV` | `development` | `development` muestra errores en pantalla y permite secretos generados; `production` los oculta y exige todas las obligatorias. |
| `API_BASE` | `http://127.0.0.1:8000` | URL de esta API para los scripts de prueba (`probar_roles_http.php`). |
| `APP_TIMEZONE` | `America/Argentina/Buenos_Aires` | Zona horaria de PHP. Se fija en código para no depender del `php.ini` (muchas instalaciones traen `Europe/Berlin`). |
| `DB_DATABASE` | `storage/saludweb.sqlite` | Ruta del archivo de la base (relativa a la raíz del backend). El archivo y su carpeta se crean solos en el primer request. |
| `JWT_SECRET` | generado en `storage/secret.key` | Secreto de firma de los JWT. Cambiarlo invalida los tokens vigentes. |
| `JWT_TTL` | `3600` | Vigencia del access token, en segundos. Valores `<= 0` se ignoran. |
| `CORS_ORIGINS` | `http://localhost:5173,...` | Orígenes permitidos para el navegador, sin espacios. |
| `LOG_LEVEL` | `DEBUG` en dev / `INFO` en prod | Nivel mínimo de log: `DEBUG`, `INFO`, `WARN`, `ERROR`. |
| `LOG_DIR` | `storage/logs` | Carpeta de logs (un JSON por línea, un archivo por día). |
| `LOG_SERVICE` | `saludweb-api` | Nombre del emisor en cada línea de log. |
| `SSO_GOOGLE_CLIENT_ID` | *(vacío)* | Client ID de Google. Vacío = `501` en `/api/auth/sso`. |
| `SSO_MICROSOFT_CLIENT_ID` | *(vacío)* | Application (client) ID de Microsoft Entra ID. |
| `SSO_MICROSOFT_TENANT` | `common` | Tenant de Microsoft. |
| `ROL_TEST_*` / `TEST_DNI` | valores de demostración | Credenciales que usa `probar_roles_http.php`. |

Notas de formato del `.env`:

- `CLAVE=valor`, una por línea; las líneas con `#` al principio son comentarios.
- Los valores con espacios van entre comillas (`"` o `'`).
- Un `#` pegado a la palabra no abre comentario (`CLAVE=abc#1` se toma entero).
- Después de `CLAVE=` sin valor, la variable existe pero vacía, y los defaults con
  `?:` del código la tratan como ausente.
- `export CLAVE=valor` también se acepta, por si el mismo archivo se usa en una shell.


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
| Catálogos | `GET /api/medicos`, `/api/obras-sociales` | Públicos (datos no personales) |
| Pacientes | `GET /api/pacientes`, `GET /api/pacientes/{id}` | **Requieren token** (exponen nombre + DNI); ya no son públicos |
| Pacientes | `POST/PUT/PATCH /api/pacientes` (protegido); `DELETE` | Crear/editar con token; borrar solo admin |
| Médicos | `POST/PUT/PATCH/DELETE /api/medicos/{id}` | **Solo admin** (403 si no) |
| Prescripciones | `GET` (protegido, dato clínico); `POST/PUT/PATCH`; `DELETE`; `PATCH .../estado` | Crear/editar **solo médico**; borrar solo admin; cambiar estado con token |
| Turnera | `GET /api/especialidades`, `/api/disponibilidades`, `/api/citas/disponibilidad` | Catálogos y disponibilidad públicos |
| Turnera | `GET /api/citas/agenda`, `/api/citas`, `POST /api/citas`, `POST /api/citas/{id}/cancelar`, `PATCH /api/citas/{id}/estado` | Protegidas; el paciente solo ve sus turnos |
| Turnera | `DELETE /api/citas/{id}`, `POST/PUT/DELETE /api/especialidades` | Solo admin |
| Turnera | `POST /api/disponibilidades` (+ PUT/DELETE), `GET /api/estadisticas` | El profesional publica su propia agenda |
| Notificaciones | `GET /api/notificaciones`, `POST .../recordatorios`, `.../{id}/reintentar` | Protegidas |
| Notificaciones | `POST /api/notificaciones/procesar` | Solo admin |
| Usuarios | `GET /api/usuarios`, `GET /api/usuarios/roles`, `PATCH /api/usuarios/{id}/rol` | Solo admin |

## Primer acceso (importante al clonar)

`db.php` siembra automáticamente los **catálogos** (obras sociales, médicos, medicamentos,
pacientes, especialidades y disponibilidades) la primera vez que se abre la API, pero
**no crea ningún usuario**: un clon recién bajado arranca **sin cuentas**, así que nadie
puede iniciar sesión de entrada. Hay que crear la primera.

### Opción rápida: registrarse desde la interfaz

La SPA web trae pantalla de **Registro** (`/register`): creá una cuenta con email y
contraseña y ya podés entrar. La app móvil usa la misma API.

### Opción por API

`POST /api/auth/registro` es **público** y solo acepta dos roles: `paciente` (por defecto) y
`medico`. Cualquier otro valor —incluido `admin`— se rechaza con **HTTP 422** y queda anotado
como `WARN` en el log. Es decir, **no se puede escalar privilegios desde el alta pública**.

```bash
curl -X POST http://127.0.0.1:8000/api/auth/registro \
  -H "Content-Type: application/json" \
  -d '{"email":"paciente@ejemplo.com","password":"tu-clave","nombre":"Paciente","tipo_usuario":"paciente"}'
```

Después iniciá sesión con esas credenciales (`POST /api/auth/login`).

> **Cómo se crea un administrador.** El alta pública **nunca** crea `admin`. Un administrador
> se crea de dos maneras: (a) un administrador ya autenticado cambia el rol de una cuenta con
> `PATCH /api/usuarios/{id}/rol` (solo admin); o (b) por consola durante la puesta a punto.
> Este comportamiento se corrigió (incidente **INC-004**) y se verifica con
> `php probar_seguridad.php`.

### ¿Olvidaste la contraseña de una cuenta? (desarrollo local)

Las contraseñas se guardan como **hash bcrypt** (`password_hash`), que es irreversible: no
se pueden leer ni recuperar, solo reemplazar. Para volver a entrar en un entorno local, el
repo trae `resetear_password.php`, que pone una clave nueva a la cuenta indicada:

```bash
php resetear_password.php admin@salud.com MiClaveNueva123
```

Qué hace y qué no:

- **Sí**: genera el hash bcrypt (igual que el registro) y lo guarda en la misma base SQLite
  que usa la API. Verifica el cambio antes de terminar.
- **No**: no crea cuentas (para eso, el registro) y **no corre desde el navegador**: solo por
  consola. En producción, el cambio de contraseña va por el endpoint autenticado
  `PATCH /api/usuarios/{id}/password`, que además exige la clave actual.

> Así se restauró el acceso a `admin@salud.com` en esta instalación: se le puso una clave
> conocida y se confirmó el login. La clave queda a elección; conviene cambiarla luego desde
> la app ("Cambiar contraseña").

## Datos de demostración

Si migraste la base MySQL vieja (ver `migrar_mysql_a_sqlite.php`), ya tenés estas cuentas
con ficha vinculada:

| Rol | Usuario | Contraseña |
|---|---|---|
| Médico | `medico@prueba.com` | `medico123` |
| Paciente | `paciente@prueba.com` | `paciente123` |
| Administradora | `admin@salud.com` | (contraseña personal, no está en el repo) |

En un clon nuevo estas cuentas **no existen**: hay que crearlas con el registro (ver
arriba).

Si los datos de demo se desarmaron, regeneralos con:
```bash
php sembrar_datos_demo.php
```
El script es **idempotente**: crea o adopta fichas según matrícula/DNI y vincula las
cuentas existentes. **No crea usuarios ni toca contraseñas**, así que las cuentas deben
existir de antes.

## Pruebas

Las suites están comentadas línea por línea y no tocan la base (crean sus propios datos y
los borran al terminar):

```bash
php probar_roles.php          # roles y permisos (matriz completa)
php probar_roles_http.php     # idem, contra la API por HTTP (requiere servidor)
php probar_vinculacion.php    # vinculación de fichas (médicos y pacientes)
php probar_turnera.php        # turnera completa (requiere API en 127.0.0.1:8000)
php verificar_turnera.php     # chequeo rápido de salud de la turnera
php probar_seguridad.php      # regresión de seguridad: 401 sin token en /api/pacientes y 422 al registrar admin
```

La del módulo de tiempo real se abre en el navegador, porque la medición de latencia necesita
abrir una conexión HTTP contra el propio servidor:

```
http://127.0.0.1:8000/probar_tiempo_real.php
```

## Documentación del proyecto

- **`GUIA_DE_CODIGO.md`** — el mapa del código, archivo por archivo: qué hace cada uno, con
  quién se conecta y un recorrido por bloques (con números de línea) en los archivos grandes.
- **`GUIA_TIEMPO_REAL.md`** — el módulo de tiempo real completo: por qué SSE y no WebSocket,
  cómo funciona el canal, cómo se autoriza, las pruebas y las limitaciones conocidas.
- `AGENDA_DE_TRABAJO.md` — todas las fases, hitos, decisiones y resultados (F1 a F5).
- `PROJECT_BRIEF.md` — brief y entregables.
- **`REVISAR_Y_PROBAR_EL_PROYECTO.md`** — la guia "de checklist a evidencia" aplicada al
  proyecto: como se reviso y probo cada modulo, con la evidencia de las corridas (2026-10-14)
  y los incidentes. Los cinco entregables viven en **`QA/`**.

## Calidad del software

Aplicacion de "Calidad Profesional del Software": auditoria, refactorizacion DRY, logging
estructurado con identificador de correlacion y suite de pruebas. El detalle completo esta en
**`CALIDAD_PROFESIONAL_SOFTWARE.md`**.

### Archivos nuevos de infraestructura

| Archivo | Responsabilidad |
|---|---|
| `core/CorrelationId.php` | Genera y valida el `X-Correlation-Id` de cada peticion. |
| `core/Logger.php` | Logs en JSON Lines con niveles, redaccion de secretos y correlacion. |
| `core/Peticion.php` | Manejadores globales de excepcion y cierre de peticion con latencia. |
| `core/Validador.php` | Validaciones de email, contrasena y textos en un solo lugar (DRY). |
| `tests/TestCase.php` | Arnes de pruebas propio, sin dependencias. |
| `tests/run.php` | Runner con descubrimiento automatico de pruebas. |

### Correr las pruebas

```bash
php tests/run.php              # toda la suite (50 pruebas)
php tests/run.php Logger       # solo las clases que contengan "Logger"
php tests/run.php --lista      # ver las clases disponibles
```

La suite **no toca la base de datos**: corre sin base y sin datos sembrados, en cualquier
maquina con PHP 8. Devuelve codigo 0 si todo pasa y 1 si hay fallos, que es lo que necesita un
pipeline.

### Configuracion de logs

En `.env`:

```
LOG_LEVEL=INFO         # DEBUG | INFO | WARN | ERROR
LOG_DIR=storage/logs   # ruta relativa (se ancla a la raiz del backend) o absoluta
LOG_SERVICE=saludweb-api
```

Los archivos se escriben en `storage/logs/app-YYYY-MM-DD.log`. Esa carpeta esta en
`.gitignore`, asi que los logs nunca se suben al repositorio.

### Correlacion de punta a punta

1. El navegador manda `X-Correlation-Id` en cada peticion.
2. `CorrelationId` lo adopta si cumple el formato, o genera uno propio (`cid_...`).
3. Todas las lineas de log de esa peticion quedan con el mismo `correlation_id`.
4. `Response::error()` devuelve el id en el cuerpo del error, asi el usuario puede reportarlo.

Para encontrar una peticion concreta:

```bash
grep "cid_a1b2c3d4e5f60718" storage/logs/app-$(date +%F).log
```

### Reglas fijadas

- Ningun secreto en el log: contrasenas, tokens, cookies y texto clinico se redactan antes de
  escribir. Los datos personales se enmascaran dejando cuatro caracteres.
- Los errores de login no dicen que fallo: ni el email ni la contrasena.
- Todo error de negocio pasa por `Response::error()`, que ya registra y ya agrega el
  `requestId`. Un `echo` con `http_response_code` rompe la correlacion.
