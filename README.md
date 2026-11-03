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
- **Acceso de cada cuenta**: email + contraseña elegida al registrarse; las contraseñas
  se guardan como hash bcrypt y nunca se pueden recuperar en texto plano.
- **Vinculación de cuentas con fichas** (turnera): al loguearse, el usuario debe quedar
  vinculado a una ficha — el **médico** con su **matrícula** y el **paciente** con su **DNI** —
  para saber *a quién* se le asigna cada turno. Si el DNI del paciente todavía no tiene
  ficha, se crea automáticamente con el nombre de la cuenta; si ya existe, el nombre debe
  coincidir para evitar que alguien se apropie de una ficha ajena.
- **Turnera (sistema de gestión de citas online)**: especialidades, disponibilidades,
  reserva/cancelación de turnos, agenda, notificaciones con token y estadísticas de gestión.
- **Avisos de tiempo real con Server-Sent Events (SSE)**: cuando cambia un turno, el servidor
  puede empujar un aviso por canal. La Web usa sondeo cada 15 segundos por defecto con el
  servidor PHP integrado de un solo worker; SSE se habilita en despliegues multi-worker. La
  documentación completa está en **[`GUIA_TIEMPO_REAL.md`](GUIA_TIEMPO_REAL.md)**.
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
limpiar_especialidades_duplicadas.php → fusiona especialidades escritas con tildes o mayúsculas distintas
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
| `CORS_ORIGINS` | `http://localhost:5173,...` | Orígenes permitidos para el navegador, sin espacios. Además se aceptan automáticamente orígenes de loopback y de IPv4 privadas (`10.x`, `172.16-31.x`, `192.168.x`), para que la web funcione desde el celular en la red local sin fijar la IP. |
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
   El ingreso es siempre con email y la contraseña que eligió la persona.
2. Enviarlo en cada petición protegida: `Authorization: Bearer <jwt>`.
3. El secreto se toma de `JWT_SECRET` o se genera en `storage/secret.key`.
4. `POST /api/auth/sso` con `{ "provider": "google" | "microsoft", "id_token": "..." }`
   → valida la firma del token contra las claves públicas (JWKS) y devuelve el JWT de
   SaludWEB. **No auto-crea cuentas**: solo habilita cuentas locales existentes y activas
   (el email del proveedor debe coincidir). Sin credenciales configuradas responde `501`.
5. `POST /api/auth/vincular` con `{ "tipo": "medico", "documento": "<matrícula>" }` o
   `{ "tipo": "paciente", "documento": "<DNI>" }` → vincula la cuenta con su ficha.
   Sin ficha vinculada, el usuario no puede reservar turnos. En pacientes, si el DNI
   normalizado no existe, la ficha se crea en ese momento y queda vinculada a la cuenta;
   si el DNI ya existe, se exige que el nombre de la cuenta coincida con el de la ficha.

### Email y contraseña

El login no acepta DNI. Cada persona entra únicamente con su **email** y la **contraseña**
que eligió al registrarse o al cambiarla desde su cuenta.

El DNI se sigue usando en `POST /api/auth/vincular` para asociar una cuenta de paciente con
su ficha, pero **no sirve como credencial de acceso**.

#### Recorrido de registro e inicio de sesión

1. Cada persona puede crear su propia cuenta desde el formulario web o móvil; no necesita
   que la administradora la dé de alta. El cliente envía nombre, email y contraseña a
   `POST /api/auth/registro`. El alta pública crea cuentas normales de paciente o médico;
   nunca permite elegir el rol administrador ni reemplaza la cuenta de administración.
2. `core/Validador.php` normaliza el email y valida que la contraseña no esté vacía y no
   supere los 72 bytes que admite bcrypt. Cada usuario elige su propia clave al registrarse;
   en los siguientes accesos tiene que usar esa misma clave. No hay una clave universal ni
   se acepta cualquier clave al iniciar sesión.
3. `services/AuthService.php` consulta que el email no exista, genera el hash con
   `password_hash(..., PASSWORD_BCRYPT)` y guarda el hash, no la contraseña original.
4. `POST /api/auth/login` vuelve a normalizar el email, busca una cuenta activa y compara
   la clave ingresada con el hash usando `password_verify()`. Si coincide, devuelve un JWT;
   si no, responde con un error genérico de credenciales.
5. La interfaz incluye mostrar/ocultar para evitar errores de tipeo en teléfono o PC. Ese
   botón solo cambia la visibilidad del texto que se está escribiendo; no revela el hash ni
   permite leer contraseñas guardadas.
6. Para crear una cuenta con Google como proveedor de identidad se requiere configurar
   Google OAuth. Escribir una dirección `@gmail.com` en el registro normal no verifica que
   pertenezca a Google.

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

### Catálogo de especialidades sin duplicados

El alta y la edición de especialidades comparan los nombres **sin tildes, sin
mayúsculas y con los espacios colapsados**. Así `Cardiologia` y `Cardiología`
se reconocen como la misma y la API responde `409` antes de crear una fila
repetida. La regla vive en `services/EspecialidadService.php` y se explica
línea por línea en ese archivo.

Los datos viejos pueden tener duplicados creados antes de esa validación. Para
depurarlos sin tocar usuarios, contraseñas ni turnos:

```bash
php limpiar_especialidades_duplicadas.php
```

El script agrupa por nombre normalizado, elige la fila canónica (la que tiene
más médicos activos; si hay empate, la más antigua), reasigna los médicos de
las variantes y borra las filas sobrantes. Es **idempotente**: si se ejecuta
dos veces, la segunda informa que no hay duplicados. También se puede ver el
resultado en la turnera: el selector de especialidad deja de mostrar dos
entradas casi iguales, una de ellas con `(0)` profesionales.

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

### Qué hace `sembrar_datos_demo.php`, línea por línea

El script se ejecutó completo sobre la base de desarrollo para cerrar la
iteración de usuarios y fichas. Su comportamiento, en orden, es este:

1. **Casting de las cuentas de demostración**: recorre la lista fija de emails y,
   para cada uno, actualiza nombre, email y rol. No crea cuentas nuevas: si el
   email no existe, lo informa y sigue.
2. **Fichas de médico**: para cada cuenta con rol `medico`, busca una ficha por
   matrícula; si no existe la crea, y si existe la adopta. Asegura nombre,
   matrícula y especialidad, y guarda el `id_medico` en `usuarios`.
3. **Fichas de paciente**: hace lo mismo con DNI, nombre y obra social, y guarda
   el `id_paciente` en `usuarios`.
4. **Corrección de vínculos**: si una cuenta tenía un `id_medico` o
   `id_paciente` que no correspondía, lo reemplaza por el correcto.
5. **Fichas sin uso**: apaga (no borra) las fichas que quedaron huérfanas o que
   tienen nombre de médico en la tabla de pacientes.
6. **Resumen final**: muestra las cuentas, sus fichas y la cantidad de
   administradores activos.
7. **Contraseñas**: no las lee, no las muestra y no las modifica. Se verificó
   explícitamente con el mensaje final `contraseñas: NO se tocó ninguna`.

Resultado concreto de la corrida del 01/11/2026:

- `alonso.tramposo@saludweb.com` (id `2`) pasó de `rol = paciente` a
  `rol = medico` y quedó vinculado a la ficha `#10`.
- La ficha `pacientes #324` ("Dr. Alonso Tramposo") quedó **apagada**.
- Quedaron **10 cuentas** de demostración: **1 admin**, **4 médicos** y
  **5 pacientes**, todas activas y con ficha cuando corresponde.
- No se borró ningún usuario y no se modificó ninguna contraseña.

### Reglas de usuarios que se verificaron

- `GET /api/usuarios` devuelve
  `id, email, nombre, rol, id_paciente, id_medico, activo, creado_at, vinculado, nota_vinculacion`.
- `PATCH /api/usuarios/{id}/rol` solo acepta `paciente`, `medico` o `admin`.
  Un rol desconocido responde **422**.
- Degradar al **último administrador** o **a uno mismo** responde **409**, no
  403: el permiso existe, lo que no se puede es dejar el sistema sin admin.
- Un usuario inexistente responde **404**.
- `AuthService::registro` se revisó y está correcto: inserta las 7 columnas
  `email, password, nombre, tipo_usuario, id_paciente, id_medico, activo` con
  placeholders y `1` para `activo`, y recibe los 6 valores correspondientes
  (incluidos los `null` de las fichas).

### Estados activo/inactivo de médicos y pacientes

El alta respetaba el checkbox en el frontend, pero el backend lo pisaba con
`activo = 1`. Por eso un médico creado como inactivo aparecía activo.

- **`services/MedicoService.php` líneas 161-176** - `crear()` calcula
  `$activo` con `filter_var($data['activo'], FILTER_VALIDATE_BOOLEAN)` cuando
  la clave viene en el body y usa `1` solo si no viene. El valor se pasa al
  repositorio, que ya insertaba la columna `activo`.
- **`services/MedicoService.php` líneas 217-221** - `actualizar()` usa el mismo
  `filter_var` en lugar de `$data['activo'] ? 1 : 0`. La diferencia importa:
  la cadena `"false"` ahora se guarda como `0`; con un cast booleano se habría
  interpretado como activa.
- **`services/PacienteService.php` líneas 145-159 y 217-221** - Se aplicó el
  mismo criterio en el alta y en la edición de pacientes.
- **`persistence/MedicoRepository.php` y `persistence/PacienteRepository.php`** -
  No hicieron falta cambios: el `INSERT` ya incluía `activo` y el `UPDATE`
  dinámico ya lo tomaba cuando estaba presente.

### Verificación ejecutada

- `php tests/run.php`: **50/50** pruebas OK (correlación, JWT, logger,
  rate limiter y validador).
- `php probar_roles.php`: matriz completa de roles OK, incluida la escritura real
  con una cuenta descartable que se crea y se borra al final.
- `php probar_vinculacion.php`: **15/15** casos OK, tanto los que deben aceptar
  la vinculación como los que deben rechazarla.
- `php sembrar_datos_demo.php`: corrida completa sin errores y sin tocar
  contraseñas.
- Prueba HTTP directa (`POST /api/medicos` y `POST /api/pacientes` con
  `"activo": false`): ambos respondieron `activo = 0` y los registros de prueba
  se borraron con `DELETE`. El E2E de la web confirmó lo mismo desde la UI:
  `MEDICO_ALTA_INACTIVO_OK` y `PACIENTE_ALTA_INACTIVO_OK`.

### Vinculación de pacientes sin ficha: creación automática (02/11/2026)

Problema: una persona podía registrarse desde el enlace público y quedar
autenticada, pero no podía reservar. Su cuenta no tenía `id_paciente` y, si el
DNI no existía en el consultorio, la vinculación fallaba porque solo buscaba
fichas ya cargadas.

Archivo: `services/AuthService.php`, método `vincularPaciente()`.

- **Líneas 721-733** - El docblock deja explícita la regla nueva: si el DNI no
  existe, la ficha se crea en el momento con el nombre de la cuenta; si existe,
  se mantiene la verificación por nombre. Así una cuenta recién registrada puede
  reservar sin esperar a que recepción cargue la ficha.
- **Líneas 736-738** - `normalizarDocumento()` quita puntos, guiones y espacios
  antes de buscar. `"10.000.010"` y `"10000010"` tienen que encontrar la misma
  ficha y no generar duplicados.
- **Líneas 740-743** - Un DNI vacío se rechaza con `422` y el mensaje
  "Ingresá tu número de DNI": sin documento no hay forma de identificar la ficha.
- **Líneas 745-750** - `Validador::longitudMaxima()` limita el DNI a 50
  caracteres, que es el tamaño de la columna, antes de llegar al `INSERT`.
- **Líneas 752-761** - La búsqueda compara la columna `dni` sin puntos, guiones
  ni espacios (`REPLACE` sobre la columna). Las fichas viejas guardaron el DNI
  con formatos distintos y tienen que seguir siendo encontrables.
- **Líneas 763-770** - Si no hay ficha y la cuenta **ya estaba vinculada**, se
  responde `409`. Una cuenta no puede abandonar su ficha y crear otra con
  cualquier DNI: ese cambio requiere intervención administrativa.
- **Líneas 772-778** - El nombre de la cuenta se vuelve a validar con
  `Validador::textoObligatorio()` antes de copiarlo a `pacientes`; no se confía
  en un valor viejo aunque se haya validado en el registro.
- **Líneas 780-787** - Se inserta la ficha mínima: `dni` normalizado, `nombre`,
  `id_obra_social = 1` y `activo = 1`. `lastInsertId()` devuelve el id recién
  creado.
- **Líneas 789-792** - El `UPDATE usuarios SET id_paciente = ?` deja el vínculo
  en la misma operación lógica: la ficha pertenece a la cuenta que la originó.
- **Líneas 794-800** - Se registra un evento informativo (sin datos clínicos) y
  se devuelve el usuario actualizado para que la sesión traiga `id_paciente`.
- **Líneas 803-807** - Si la ficha existía pero está dada de baja (`activo = 0`),
  se rechaza con `422`. Vincularla solo generaría un error más adelante.
- **Líneas 809-818** - Los nombres se comparan sin tildes, mayúsculas ni espacios
  mediante `normalizarTexto()` y `coincidenNombres()`.
- **Líneas 819-824** - Si el nombre no coincide, se responde `422` con
  "El nombre de la cuenta no coincide con el de la ficha del DNI". Saber el DNI
  de otra persona no alcanza para apropiarse de su ficha.
- **Líneas 826-830** - Una cuenta vinculada a una ficha distinta de la que
  encontró por DNI recibe `409`: no puede "mudarse" de ficha sola.
- **Líneas 832-837** - Si otra cuenta ya usa esa ficha, se responde `409`. Dos
  cuentas sobre la misma ficha podrían ver y cancelar los mismos turnos.
- **Líneas 839-843** - Si todas las validaciones pasan, se guarda el vínculo con
  `UPDATE usuarios SET id_paciente = ?` y se devuelve el usuario actualizado.
- **Líneas 846-861** - `normalizarDocumento()` usa
  `preg_replace('/[\s.\-]+/u', '', trim($documento)) ?? ''`: quita espacios,
  puntos y guiones, conserva letras (matrículas o documentos extranjeros) y
  mantiene el tipo `string` aunque el reemplazo falle.

Reglas que quedaron fijas:

- El médico sigue necesitando una **matrícula existente**; no se auto-crean
  fichas profesionales.
- El paciente sin ficha obtiene una ficha nueva, pero solo si su cuenta todavía
  no está vinculada.
- El paciente con DNI existente debe superar la comparación de nombre.
- Una ficha y una cuenta se vinculan de a una: no se comparten entre cuentas.

Verificación ejecutada: `php -l services/AuthService.php` sin errores,
`php tests/run.php` 50/50, `php probar_vinculacion.php` 15/15. En el servidor
público, un DNI nuevo creó su ficha y reservó/canceló un turno; un DNI existente
vinculó la ficha correcta; un DNI ajeno respondió `422` con el mensaje de nombre
no coincidente. Los registros de prueba se borraron al terminar.

## Publicar cambios en GitHub (paso a paso)

Este repositorio se publica en
`https://github.com/ailenquaglino089-ui/SaludWEB_Backend.git`, rama `main`.
Desde la carpeta `SaludWEB_Backend`:

```bash
php tests/run.php                  # 1. Pruebas unitarias
git status                         # 2. Ver qué archivos cambiaron
git diff                           # 3. Revisar el contenido antes de subirlo
git add README.md services/AuthService.php  # 4. Preparar archivos
git commit -m "fix: crear ficha de paciente al vincular cuenta"  # 5. Crear commit
git push origin main               # 6. Subir a GitHub
git log --oneline -1               # 7. Confirmar el commit publicado
git status -sb                     # 8. Debe quedar sin cambios pendientes
```

Qué hace cada línea:

1. `php tests/run.php`: corre las 50 pruebas unitarias del backend.
2. `git status`: lista archivos modificados, nuevos y sin seguimiento.
3. `git diff`: revisa el cambio exacto antes de publicarlo.
4. `git add ...`: prepara únicamente README y el servicio modificado.
5. `git commit`: crea el commit local con el mensaje del fix.
6. `git push`: sube la rama `main` a GitHub.
7. `git log --oneline -1`: confirma el hash del commit publicado.
8. `git status -sb`: confirma que la rama local quedó igual a `origin`.

No subir `storage/saludweb.sqlite`, `vendor/` ni archivos `.env` con
credenciales; la base local y los secretos están en `.gitignore`.

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

## Comandos y verificaciones disponibles

Mapa completo de lo que se puede ejecutar en este repositorio, qué toca cada
comando y con qué cuidado. Regla general: **los `tests/` y los `verificar_*` no
escriben nada; los `probar_*` de integración necesitan la API levantada y se
limpian solos; los `seed_*`, `resetear_*`, `sembrar_*` y `migrar_*` sí escriben
la base**.

| Comando | Qué hace | Riesgo / requisito |
|---|---|---|
| `composer run dev` | Levanta la API con `php -S 127.0.0.1:8000 index.php` | Bloquea la terminal y atiende **una petición por vez**: sirve para todo salvo SSE. No usar en producción. |
| `composer test` / `php tests/run.php` | Corre las 50 pruebas unitarias (5 clases) | Ninguno: no abre la base ni necesita servidor. |
| `php -l archivo.php` | Revisa la sintaxis de un archivo | Solo lectura. |
| `php verificar_turnera.php` | Estado de las tablas de la turnera | Solo lectura. |
| `php probar_vinculacion.php` | Reglas de vinculación cuenta/ficha | Solo lectura (usa la conexión para construir el servicio). |
| `php probar_roles.php` | Matriz completa de roles y permisos | Crea una cuenta descartable y la borra; escribe y revierte un rol. |
| `php probar_roles_http.php` | Roles por HTTP (401/403/409/422) | Requiere la API; crea cuentas descartables y las borra. |
| `php probar_seguridad.php` | Regresión de INC-001 e INC-004 | Requiere la API; crea un paciente de prueba y lo borra. |
| `php probar_turnera.php` | Humo de la turnera de punta a punta | Requiere la API; crea usuario, ficha y cita temporales y los borra. |
| `php probar_tiempo_real.php` | Tabla, autorización y purga de SSE | Por consola omite la latencia; deja eventos en el canal `prueba_script`, que se purgan solos (30 min). |
| `php listar_usuarios.php` | Lista cuentas sin contraseñas | Solo lectura, pero **no debe desplegarse**: revela qué cuentas existen. |
| `php resetear_password.php <email> <clave>` | Reemplaza el hash de una clave | **Escribe la base.** Solo desarrollo local (se niega a correr desde el navegador). |
| `php seed_pacientes.php <N>` | Crea N pacientes aleatorios | **Escribe la base.** Sirve para probar el paginado. |
| `php sembrar_datos_demo.php` | Carga los datos de demostración | **Escribe la base** (idempotente; no toca usuarios ni claves). |
| `php migrar_mysql_a_sqlite.php` | Migra desde una base MySQL | **Escribe la base destino**; necesita MySQL accesible. |

Última corrida (02/11/2026): `services/AuthService.php` sin errores de
sintaxis, **50/50 pruebas unitarias** y **15/15 casos de vinculación** OK.
Las suites de integración de turnera, roles, seguridad y tiempo real ya estaban
en `EXIT=0`; los datos descartables de las pruebas se borraron al terminar.

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
- **Registro de cambios y pasos del proyecto** — la cronologia del trabajo reciente en los
  tres repositorios (seguridad, migracion a SQLite/PHP nativo, puesta en marcha, lanzador de
  escritorio y arreglo del panel en vivo), con el detalle de cada Pull Request:
  <https://github.com/ailenquaglino089-ui/SaludWEB_Web/blob/main/REGISTRO_DE_CAMBIOS.md>.

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

## Cómo presentar este proyecto a alguien que no entiende de tecnología

Discurso armado sin jerga, para una demo, una defensa o una charla con alguien del
rubro salud que no programa. La regla: hablar de problemas y beneficios, nunca de
tecnologías. Este repositorio es la capa que no se ve: la API que hace que todo
funcione.

**En una frase:** SaludWEB es un sistema para gestionar turnos médicos en línea, y
este repositorio es "el cerebro" que guarda la información, aplica las reglas y
atiende a la web y a la app de celular.

**El problema que resuelve:**

- Turnos tomados por teléfono, en horarios limitados.
- Agenda en papel o en Excel, con riesgo de turnos dobles y olvidos.
- El paciente espera sin saber si le quedó el turno.

**Qué gana cada persona:**

| Quién | Qué gana |
|---|---|
| Paciente | Reserva su turno las 24 horas desde el celular, sin llamar, y puede ver o cancelar los suyos. |
| Médico | Ve su agenda del día actualizada sola, sin llamadas ni anotaciones manuales. |
| Administrador | Controla usuarios, profesionales y turnos desde una sola pantalla, con estadísticas de gestión. |

**Respuestas a las preguntas típicas, en criollo:**

| Pregunta | Respuesta |
|---|---|
| ¿Hay que instalar algo? | Para el usuario final, no: entra por el navegador como a cualquier página. La única instalación es una sola vez, en la máquina del consultorio, y este README explica cómo hacerla sin XAMPP ni MySQL. |
| ¿Es una web o una app? | Las dos. Esta API es la misma que usan la web y la app de celular: se escribe una vez y sirve para las dos ventanas. |
| ¿Dónde están los datos? | En la propia máquina que hace de servidor, en un archivo de base de datos, sin depender de servicios de terceros. |
| ¿Es seguro? | El sistema exige usuario y contraseña, valida los permisos en el servidor (nunca confía en lo que manda la pantalla) y registra los errores con un identificador para poder rastrearlos. |
| ¿Cuánto cuesta? | El software no tiene licencias: está hecho con tecnología libre (PHP y SQLite) y corre en una computadora común. |

**Demo de 3 minutos (guion para mostrarlo en vivo):**

1. Abrir la turnera sin iniciar sesión: es pública y se ve de entrada.
2. Registrarse como paciente y reservar un turno.
3. Entrar como administrador y ver el panel: el contador de turnos cambia solo.
4. Si alguien pregunta "¿y esto dónde vive?", abrir
   `http://127.0.0.1:8000/api/health` en el navegador: es la forma más simple de
   ver que el cerebro está despierto.

**Analogía que funciona:** la API es como la caja registradora de un negocio. El
cliente ve el mostrador (la web y la app), pero quien registra cada movimiento,
aplica las reglas y guarda la información es la caja. Un mostrador lindo sin caja
no vende nada.

## ¿Se puede usar en otras computadoras?

**Sí.** Vale la pena distinguir dos situaciones:

### Otra computadora para desarrollo o demostración

Se levanta igual que esta:

1. Clonar este repositorio (o bajar el ZIP desde GitHub).
2. `composer install` (una sola vez, para las dependencias).
3. `composer run dev` (queda en `http://127.0.0.1:8000`).
4. Verificar con `http://127.0.0.1:8000/api/health`.

Requisitos de esa máquina: PHP 8 y Composer. **No hace falta MySQL ni XAMPP**: la
base de datos es un archivo SQLite que se crea solo en el primer arranque, dentro
de su propia carpeta `storage/`.

### Que otras computadoras usen el sistema (red local)

Si el backend va a atender a otras PCs o celulares desde una sola máquina:

1. Escuchar en la red y no solo en la propia máquina:
   `php -S 0.0.0.0:8000 index.php` (el script `composer run dev` ya lo hace).
2. Abrir los puertos en el firewall **solo para la subred local**:
   `SaludWEB_Mobile\scripts\permitir_firewall_saludweb.bat` como administrador.
3. No hace falta agregar la IP a `CORS_ORIGINS`: la API acepta automáticamente
   orígenes de loopback y de rangos IPv4 privados, así que sigue funcionando
   aunque la WiFi cambie la IP de la PC.

Desde cada dispositivo, la web se abre en `http://<IP-del-servidor>:5173` y la app
móvil se apunta a `http://<IP-del-servidor>:8000/api` (ver el README de Mobile).

> **Acceso desde otras redes:** las direcciones `192.168.x.x` y otros rangos privados
> solo funcionan dentro de la misma red. En este workspace, el acceso público del frontend
> está documentado en el README de Web y utiliza el enlace de Tailscale configurado allí;
> la PC debe permanecer encendida, en Internet y con los servicios activos. No abras el
> puerto 8000 directamente al router ni publiques una API sin HTTPS y configuración de
> producción. Para usar datos móviles u otra Wi‑Fi, abrí el enlace público de Web.
opencode --continue
>
> **Qué ocurre al usar el enlace público:** el lanzador de Web inicia este Backend en
> `0.0.0.0:8000`, pero Funnel no publica directamente ese puerto. Publica HTTPS en el
> puerto local `127.0.0.1:8001`; el router `SaludWEB_Web/scripts/servidor_publico.php`
> reconoce las rutas `/api/...` y carga el front controller de este repositorio (`index.php`).
> Así el teléfono usa la misma dirección HTTPS para descargar la web y llamar a la API:
> no necesita conocer la IP de la PC, instalar Tailscale ni solicitar CORS entre dominios.
> Las rutas y reglas de autorización siguen siendo las de esta API; las rutas protegidas
> requieren JWT y validan el rol en el servidor. El enlace público puede alcanzar las rutas
> sin autenticación que ofrece la aplicación, por lo que este modo es para demostraciones,
> no para publicar datos reales ni reemplazar un despliegue de producción.

El recorrido de una llamada desde el teléfono es:

1. El navegador abre la web por el dominio HTTPS de Tailscale Funnel.
2. Funnel reenvía la solicitud al gateway PHP local en `127.0.0.1:8001`; la conexión
   externa termina con HTTPS y el puerto del Backend no se publica al router de Internet.
3. Si la ruta empieza por `/api/`, `servidor_publico.php` incluye `SaludWEB_Backend/index.php`.
4. `index.php` carga `routes.php`, que despacha el endpoint REST y aplica CORS, autenticación
   JWT y permisos según corresponda.
5. Si la ruta no es de la API, el gateway devuelve un archivo estático de `dist/` o el
   `index.html` de la SPA para que React resuelva rutas como `/login`.

El lanzador, el gateway y el apagado están explicados paso a paso en
[`SaludWEB_Web/README.md`](https://github.com/ailenquaglino089-ui/SaludWEB_Web#cómo-abrir-todo-el-proyecto-backend--frontend).

### ¿Y en un celular?

Sí: con el backend escuchando en la red (`0.0.0.0`), la app móvil de SaludWEB se
conecta desde cualquier celular **Android o iPhone**, sin importar la marca
(Redmi, Motorola, Samsung, etc.), con **Expo Go** o con un build instalable,
además de un emulador Android o el simulador de iOS. En iPhone, verificá que
Expo Go sea compatible con el SDK 53; si no, usá un development build con EAS o
actualizá el SDK. El paso a paso está en el README de Mobile, sección
["Cómo usarlo en teléfonos"](https://github.com/ailenquaglino089-ui/SaludWEB_Mobile/blob/main/README.md#cómo-usarlo-en-teléfonos-paso-a-paso).

### Lo que NO hace falta

- MySQL u otro servidor de bases de datos: la base es un archivo que se crea solo.
- Instalar algo en la PC del usuario final: solo navegador.
- Licencias de software: PHP y SQLite son libres.

## Base de datos de desarrollo (dónde está guardada)

El proyecto tiene **base de datos propia desde el desarrollo**: no usa MySQL ni
ningún motor instalado, sino **SQLite**, que guarda todo en un solo archivo.

| Dato | Detalle |
|---|---|
| Qué es | Archivo SQLite con el esquema y los datos (usuarios, médicos, obras sociales, turnos, notificaciones, etc.) |
| Dónde vive | `SaludWEB_Backend/storage/saludweb.sqlite` (relativo a la raíz de este repositorio) |
| Cómo se crea | Solo, en el **primer request** a la API: `db.php` crea el archivo, las tablas y los datos de ejemplo. No hay que importar ningún SQL. |
| ¿Se sube a GitHub? | **No.** `storage/` está entero en `.gitignore`, y además los patrones `*.sqlite` protegen el caso de apuntar la base a otra carpeta. Cada clone arranca con una base nueva (sin cuentas: hay que registrarse). |
| Cómo regenerar los datos de demo | `php sembrar_datos_demo.php` (idempotente: crea o adopta las fichas; **no** crea usuarios ni toca contraseñas) |
| Cómo moverla de lugar | Variable `DB_DATABASE` en el `.env` (por defecto `storage/saludweb.sqlite`) |
| Qué más guarda esa carpeta | `storage/secret.key` (secreto de firma de los JWT), `storage/logs/` (logs diarios) y `storage/rate/` (control de intentos) — todo local y tampoco versionado |

> Para respaldar la base de desarrollo alcanza con **copiar el archivo**
> `saludweb.sqlite`. La frecuencia y el destino de los respaldos en producción
> están en la guía de producción y defensa (sección de backups).

## Guía para preparar el sistema para producción y defensa

Guía práctica, comentada línea por línea, para llevar un proyecto al entorno de producción
con garantías de calidad y defenderlo con solidez: ambientes (desarrollo, prueba y
producción), variables de configuración, deploy, CI/CD, backups, README profesional,
documentación funcional y técnica, y los diagramas necesarios (ER, casos de uso,
arquitectura y flujo), con una lista de verificación final para la defensa. Está en la raíz
de los tres repositorios del proyecto (Backend, Web y Mobile):

- [PREPARAR_SISTEMA_PARA_PRODUCCION_Y_DEFENSA.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/PREPARAR_SISTEMA_PARA_PRODUCCION_Y_DEFENSA.md)

## Documentar y Publicar: El Camino al Éxito del Proyecto

Guía práctica comentada línea por línea para documentar, limpiar, preparar y publicar el proyecto con calidad profesional y lista para defensa. Incluye objetivos, revisión técnica, README, manual de instalación, arquitectura, diagrama de base de datos, preparación para el deploy, verificación y publicación, con el entregable El Paquete.

- [DOCUMENTAR_Y_PUBLICAR_EL_CAMINO_AL_EXITO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/DOCUMENTAR_Y_PUBLICAR_EL_CAMINO_AL_EXITO.md)

## Arquitectura y Sistemas Operativos aplicada al proyecto

Correspondencia entre las consignas del trabajo práctico de Arquitectura y Sistemas Operativos y la implementación real de SaludWEB, con los ejercicios de planificación corregidos y la bibliografía de referencia.

- [ARQUITECTURA_Y_SISTEMAS_OPERATIVOS_APLICADO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/ARQUITECTURA_Y_SISTEMAS_OPERATIVOS_APLICADO.md)

## Trabajos prácticos 2 e Integrador aplicados al proyecto

Resolución comentada del TP2 (planificación FCFS, SJF, SRTF, Round Robin y prioridad, y Algoritmo del Banquero) y del TP Integrador (19 consignas de arquitectura, sistemas operativos, memoria, archivos, interrupciones y redes), con la aplicación a SaludWEB y las correcciones para la entrega.

- [TRABAJO_PRACTICO_2_E_INTEGRADOR_APLICADO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/TRABAJO_PRACTICO_2_E_INTEGRADOR_APLICADO.md)

## Matemática discreta y lógica aplicadas al proyecto

Aplicación del material de cátedra de Erika R. Aab (sistemas de numeración, lógica proposicional, conjuntos, relaciones, matrices, grafos y árboles) a SaludWEB: bases y conversiones en colores, permisos y trazabilidad; tablas de verdad y reglas de autorización; conjuntos y SQL; relaciones y claves foráneas; matrices, B-tree y árboles. Incluye las actividades resueltas, las correcciones al material y la trazabilidad `archivo:línea`.

- [MATEMATICA_DISCRETA_Y_LOGICA_APLICADAS.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/MATEMATICA_DISCRETA_Y_LOGICA_APLICADAS.md)

## Probabilidad y estadística aplicadas al proyecto

Aplicación del material de cátedra de Probabilidad y Estadística a SaludWEB: estadística descriptiva e inferencial, tablas de frecuencia, medidas de tendencia central y dispersión, boxplot, probabilidad, sucesos, probabilidad condicional, independencia, probabilidad total, Bayes, variables aleatorias y distribuciones binomial, normal y Poisson. Incluye la Guía N°1 verificada, la Guía N°2 resuelta con las 500 respuestas del formulario, el análisis de los datos reales de la base de desarrollo y el segundo recuperatorio, con trazabilidad de las consultas y las limitaciones de los datos.

- [PROBABILIDAD_Y_ESTADISTICA_APLICADAS.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/PROBABILIDAD_Y_ESTADISTICA_APLICADAS.md)

## Organización empresarial aplicada al proyecto

Aplicación del material de Organización Empresarial a SaludWEB: administración como ciencia, técnica y arte; proceso administrativo y previsión; organizaciones, recursos y clasificación; organigramas de Mercería SA y Design & Harmony; documentos comerciales; roles y habilidades del administrador; emprendedor; eficacia, eficiencia y economicidad; diseño de estructuras según Mintzberg; Trabajo Integrador Final y plan de negocios. Incluye la Actividad 1, la Actividad 2 y una propuesta de estructura para la etapa inicial del proyecto.

- [ORGANIZACION_EMPRESARIAL_APLICADA.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/ORGANIZACION_EMPRESARIAL_APLICADA.md)

## Bases de Datos I: gestión de pedidos y stock aplicada al proyecto

Aplicación del Parcial II de Bases de Datos I a SaludWEB: modelo de referencia de rubros, proveedores, clientes, productos, pedidos, ítems y movimientos de stock; DER y diccionario de datos; normalización hasta 3FN; DDL de referencia; trigger que impide stock negativo; índices; vistas `vw_stock_actual` y `vw_reportes_pedidos`; las 15 consultas obligatorias; carga masiva reproducible y verificación. Explica qué tablas actuales de SaludWEB cumplen cada rol y qué extensión de farmacia e insumos haría falta. No incluye el archivo `.sql` ni el PDF de entrega del parcial.

- [BASES_DE_DATOS_I_GESTION_DE_PEDIDOS_APLICADA.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/BASES_DE_DATOS_I_GESTION_DE_PEDIDOS_APLICADA.md)

## Metodología de Sistemas II aplicada al proyecto

Aplicación del material de Metodología de Sistemas II a SaludWEB: calidad de diseño y los diez principios, SOLID, arquitectura en capas, lógica de negocio, acceso a datos, patrones, UML, evaluación heurística, prueba de usabilidad, estrategia de pruebas y resolución de los TP 1 a 3. Incluye diagramas Mermaid, trazabilidad `archivo:línea` y una deuda prioritaria de autorización.

- [METODOLOGIA_DE_SISTEMAS_II_APLICADA.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/METODOLOGIA_DE_SISTEMAS_II_APLICADA.md)

## Análisis de Datos aplicado al proyecto

Aplicación del recorrido de Análisis de Datos a SaludWEB: fuentes, inventario, calidad, estadística descriptiva, probabilidad, indicadores, visualización y límites. Analiza la base real de desarrollo (223 pacientes, 8 médicos, 25 bloques, 1 cita y 17 triages), la Guía N°2 de 500 estudiantes y las precauciones para un tablero honesto.

- [ANALISIS_DE_DATOS_APLICADO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/ANALISIS_DE_DATOS_APLICADO.md)

## Gestión de Desarrollo de Software aplicada al proyecto

Aplicación de Gestión de Desarrollo de Software a SaludWEB: alcance, EDT, fases, interesados, riesgos, calidad, configuración y el TP **Reportes e Indicadores en Proyectos de Software**. Audita el tablero publicado (85 %, 2 días, 1 bug, 9 historias y velocidad 8/10/9), lo contrasta con la evidencia del repositorio y propone un tablero con fórmula, fuente, fecha y dueño.

- [GESTION_DE_DESARROLLO_DE_SOFTWARE_APLICADA.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/GESTION_DE_DESARROLLO_DE_SOFTWARE_APLICADA.md)

## Derecho Aplicado a la Informática aplicado al proyecto

Aplicación de Derecho Aplicado a la Informática a SaludWEB: derecho público y privado, jerarquía normativa, derechos digitales, Ley 25.326, datos sensibles de salud, consentimiento, firma electrónica y digital, responsabilidad civil, prueba digital, trabajo informático, propiedad intelectual, consumidor, ciberdelitos, IA y casos de análisis. Incluye matriz legal y plan de cumplimiento. No constituye asesoramiento jurídico.

La sección **19.5** del documento deja la trazabilidad del material de cátedra: qué aportó cada archivo de la carpeta `LEGISLACIÓN`, los cinco grupos de contenido, los parciales, los trabajos prácticos y el repaso. También aclara que el PDF del Módulo 1 es un duplicado y que el archivo de asistencia es administrativo. Los archivos fuente no se copiaron al repositorio; la aplicación queda en el documento publicado.

| Archivo o grupo | Aplicación en SaludWEB | Secciones |
|---|---|---|
| Módulo 1 | Derecho público y privado, jerarquía normativa y estructura del Estado | 1, 3, 4 y 12 |
| Módulo 2 | Derecho civil y comercial aplicado a la informática | 5, 7, 10 y 11 |
| Unidad 2 | Personas, capacidad, obligaciones, contratos y responsabilidad; familia, sucesiones y derechos reales fuera de alcance | 5.1 a 5.3 y 7 |
| Unidad 3 | Subordinación, principios protectores, teletrabajo, pasantías y propiedad del código | 9.1 a 9.6 |
| Unidades 4 y 5 | Derecho informático, firma, prueba, ciberdelitos, propiedad intelectual, licencias, marcas, comercio electrónico e intermediarios | 2, 4, 6, 8, 10, 11, 12 y 13 |
| Examen conceptual Unidad 4 | Autoevaluación de conceptos | 17 |
| Trabajo áulico Unidad 4 | Libertad de expresión, medidas cautelares, intermediarios y habeas data | 14.4 a 14.6 |
| Caso 3 | IA en el empleo público | 14.2 |
| Caso 4 | Competencias nacionales y provinciales en ciberdelitos | 14.3 |
| Clase 25/09 | Tareas de cuidado, privacidad y reforma laboral Ley 27.742 | 9.2 y 9.3 |
| Actividad CloudNet | Nube, clickwrap, firma electrónica, responsabilidad y ciberseguridad | 14.1 |
| Repaso | Repaso integrador del Módulo 1 y jerarquía normativa | 1, 3, 4, 17 y 19.3 |

`ASISTENCIA Y CRITERIOS DE EVALUACIÓN` es material administrativo y no aporta contenido jurídico. `modulo 1 Alumnos (1).pdf` es el duplicado del Módulo 1.

- [DERECHO_APLICADO_A_LA_INFORMATICA_APLICADO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/DERECHO_APLICADO_A_LA_INFORMATICA_APLICADO.md)

## Cómo defender un proyecto profesional aplicado al proyecto

Guía para preparar la defensa oral de SaludWEB: qué evalúa un tribunal, cómo explicar el problema antes del código, la arquitectura cliente-servidor, la justificación de las tecnologías, la seguridad y la autenticación, la base de datos, la integridad y el tiempo real, las decisiones por pantalla, un guion de 10 minutos, las preguntas difíciles, la lista de verificación, los límites y la deuda técnica, y la trazabilidad de cada afirmación. Incluye diagramas Mermaid, un plan B para la demo y una matriz de evidencia; complementa el discurso no técnico y la demo de 3 minutos del README y la guía de producción y defensa.

- [COMO_DEFENDER_UN_PROYECTO_PROFESIONAL_APLICADO.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/COMO_DEFENDER_UN_PROYECTO_PROFESIONAL_APLICADO.md)

## Simulación de Defensa Final

Guía de ensayo comentada línea por línea para llegar a la defensa con un recorrido probado: tres rondas de práctica, guion cronometrado de 12 minutos, transiciones memorizables, demo por roles, plan B, cuatro fragmentos de código explicados línea por línea, resultados verificados, límites declarados, preguntas técnicas y metodológicas, video de respaldo y checklist final. Se apoya en la evidencia del repositorio y no inventa métricas: distingue lo medido, lo propuesto y la deuda pendiente. Está en la raíz de los tres repositorios:

- [SIMULACION_DE_DEFENSA_FINAL.md](https://github.com/ailenquaglino089-ui/SaludWEB_Backend/blob/main/SIMULACION_DE_DEFENSA_FINAL.md)

## Resumen del trabajo documental

En esta etapa se agregaron a la raíz de los tres repositorios y se enlazaron desde este README:

- La guía **Documentar y Publicar: El Camino al Éxito del Proyecto**, comentada línea por línea.
- **Arquitectura y Sistemas Operativos aplicada al proyecto**, con las consignas de la cátedra vinculadas al código y las tablas de planificación corregidas.
- **Trabajos prácticos 2 e Integrador aplicados**, con el TP2 resuelto (planificación y Algoritmo del Banquero) y las 19 consignas del TP Integrador corregidas y aplicadas.
- **Matemática discreta y lógica aplicadas**, con los sistemas de numeración, la lógica proposicional, los conjuntos, las relaciones, las matrices, los grafos y los árboles vinculados al código, las actividades resueltas y las correcciones al material.
- **Probabilidad y estadística aplicadas**, con la estadística descriptiva del proyecto, las probabilidades de los pacientes y la agenda, la Guía N°1 verificada, la Guía N°2 resuelta y las distribuciones binomial, normal y Poisson.
- **Organización empresarial aplicada**, con el proceso administrativo, los organigramas de la Actividad 2, los documentos comerciales, el diseño de estructuras, el Trabajo Integrador y el plan de negocios.
- **Bases de Datos I: gestión de pedidos y stock aplicada**, con el modelo relacional de referencia, la normalización, el DDL, las vistas, el control de stock y las 15 consultas del parcial.
- **Metodología de Sistemas II aplicada**, con calidad, SOLID, arquitectura, patrones, UML, UI/UX, pruebas y los TP 1 a 3.
- **Análisis de Datos aplicado**, con inventario, calidad, estadística descriptiva, probabilidad, indicadores y visualización sobre la base real y la Guía N°2.
- **Gestión de Desarrollo de Software aplicada**, con planificación, riesgos, calidad y la auditoría del tablero de reportes e indicadores.
- **Derecho Aplicado a la Informática aplicado**, con protección de datos de salud, consentimiento, firma, responsabilidad, prueba digital, propiedad intelectual, ciberdelitos y matriz de cumplimiento.
- **Cómo defender un proyecto profesional aplicado**, con el guion de 10 minutos, las preguntas difíciles, la matriz de evidencia, el plan B de la demo y la deuda técnica declarada.
- **Simulación de Defensa Final**, con tres rondas de ensayo, guion cronometrado, demo por roles, código explicado línea por línea, preguntas frecuentes y checklist de presentación.

No se modificó el código de la aplicación: los cambios son de documentación. También se corrigieron los acentos de las secciones nuevas de este README y se dejaron marcados los datos del TP2 que conviene confirmar con la tabla original. El detalle cronológico y los números de publicación están en el [registro de cambios](https://github.com/ailenquaglino089-ui/SaludWEB_Web/blob/main/REGISTRO_DE_CAMBIOS.md).
