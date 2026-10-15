# Guía de código — SaludWEB Backend

> Esta guía es el mapa del backend: qué hace cada archivo, cómo se conecta con los demás y por dónde leerlo. Es la puerta de entrada al código; para el detalle de cada módulo hay documentos hermanos:
>
> - [`README.md`](./README.md) — cómo levantar el proyecto y qué hace la API.
> - [`GUIA_TIEMPO_REAL.md`](./GUIA_TIEMPO_REAL.md) — el módulo de Server-Sent Events (SSE).
> - [`REVISAR_Y_PROBAR_EL_PROYECTO.md`](./REVISAR_Y_PROBAR_EL_PROYECTO.md) — cómo revisar y probar todo.
> - Carpeta [`QA/`](./QA/) — checklist, matriz de cobertura, incidentes y confirmación de calidad.

## Cómo usar esta guía

1. Si es la primera vez que ves el proyecto, leé **Arquitectura y flujo de una petición** y después **Mapa general (árbol)**. Con eso ya ubicás las piezas.
2. Buscá el archivo que te interesa en **Archivo por archivo**. Cada ficha tiene: **Rol**, **Recibe / devuelve**, **Se conecta con**, **Recorrido por bloques** (solo en archivos grandes) y **Notas**.
3. Los números citados (`Lxx`) son líneas **dentro de ese archivo**, no del proyecto. Para archivos de más de ~250 líneas hay un "Recorrido por bloques" con rangos `L1–Lx`.
4. Si querés arrancar por lo más importante, andá directo a **Orden de lectura recomendado**.
5. Los términos que no conozcas están en el **Glosario breve** del final.

Regla de esta guía: nada de números de línea ni de nombres inventados. Todo sale de leer el archivo.

## Arquitectura y flujo de una petición

El backend es PHP puro, sin framework, organizado en capas. La idea es que cada capa haga una sola cosa y que la de arriba no sepa cómo está hecha la de abajo.

```
Cliente (frontend / curl)
        |
        v
   .htaccess  (reescribe todo a index.php)
        |
        v
   index.php  ->  routes.php  (declara las rutas y arma el $router)
        |
        v
   core/Router  (recorre las rutas y despacha)
        |
        v
   core/AuthMiddleware  (verifica el JWT y el rol)
        |
        v
   controllers/*  (traduce HTTP <-> servicio; no tiene lógica de negocio)
        |
        v
   services/*     (reglas de negocio, validaciones, permisos)
        |
        v
   persistence/*  (único lugar que habla SQL)
        |
        v
   db.php  ->  SQLite (storage/saludweb.sqlite)
```

La vuelta se hace por `core/Response`, que arma el JSON `{ ok, mensaje, data/errores }`. El ciclo de vida de cada petición (correlación, tiempos, errores fatales) lo maneja `core/Peticion`, y los eventos en vivo salen por SSE desde `RealtimeService`/`RealtimeController`.

## Mapa general (árbol)

```
SaludWEB_Backend/
├── index.php                 Punto de entrada (Front Controller)
├── routes.php                Todas las rutas + ensamblado de dependencias
├── db.php                    Conexión a SQLite y CREATE TABLE
├── .htaccess                 Reescribe todo hacia index.php
├── composer.json             Dependencia firebase/php-jwt + scripts dev/test
├── migrar_mysql_a_sqlite.php Migra datos de MySQL a SQLite (idempotente)
├── sembrar_datos_demo.php    Carga datos de demostración
├── seed_pacientes.php        Genera N pacientes al azar (para paginado)
├── verificar_turnera.php     Chequea tablas y datos de la turnera
├── probar_vinculacion.php    Prueba la regla de vinculación de fichas
├── probar_roles.php          Prueba la gestión de roles (sin HTTP)
├── probar_roles_http.php     Prueba la gestión de roles por HTTP
├── probar_turnera.php        Prueba de extremo a extremo de la turnera
├── probar_tiempo_real.php    Prueba de extremo a extremo del tiempo real
│
├── core/                     Infraestructura transversal (sin negocio)
│   ├── bootstrap.php         Arranque: env, logging y ensamblado
│   ├── Config.php            Lectura de configuración + validación
│   ├── Env.php               Carga del .env sin dependencias
│   ├── Secret.php            Secreto JWT (env o archivo)
│   ├── JwtService.php        Emitir y verificar tokens (HS256)
│   ├── AuthMiddleware.php    Verifica token y rol
│   ├── Router.php            Registro y despacho de rutas
│   ├── CuerpoJson.php        Lectura y validación del body JSON
│   ├── Validador.php         Validación y saneamiento de entradas
│   ├── Response.php          Respuesta JSON uniforme
│   ├── RateLimiter.php       Límite de intentos por clave (IP)
│   ├── CorrelationId.php     Id de correlación por petición
│   ├── Logger.php            Logs JSON con enmascarado de datos
│   └── Peticion.php          Ciclo de vida y trazabilidad de la petición
│
├── controllers/              Capa HTTP (traduce, no decide)
│   ├── AuthController.php
│   ├── UsuarioController.php
│   ├── MedicoController.php
│   ├── PacienteController.php
│   ├── EspecialidadController.php
│   ├── DisponibilidadController.php
│   ├── CitaController.php
│   ├── PrescripcionController.php
│   ├── EstadisticaController.php
│   ├── NotificacionController.php
│   └── RealtimeController.php
│
├── services/                 Reglas de negocio
│   ├── AuthService.php
│   ├── UsuarioService.php
│   ├── MedicoService.php
│   ├── PacienteService.php
│   ├── EspecialidadService.php
│   ├── DisponibilidadService.php
│   ├── CitaService.php
│   ├── PrescripcionService.php
│   ├── EstadisticaService.php
│   ├── NotificacionService.php
│   ├── RealtimeService.php
│   └── Notificacion/
│       ├── ProveedorNotificacionesInterface.php
│       └── ProveedorNotificacionesStub.php
│
├── persistence/              Acceso a datos (Repository Pattern)
│   ├── *RepositoryInterface.php (contratos)
│   └── *Repository.php          (implementaciones)
│
└── tests/                    Suite de pruebas unitarias (sin framework)
    ├── run.php               Ejecutor
    ├── TestCase.php          Arnés mínimo de aserciones
    ├── CorrelationIdTest.php
    ├── JwtServiceTest.php
    ├── LoggerTest.php
    ├── RateLimiterTest.php
    └── ValidadorTest.php
```

> No se documentan `vendor/` (la librería `firebase/php-jwt` bajada por Composer) ni `storage/` (base SQLite, logs y contadores de rate limiting: son datos, no código).

## Archivo por archivo

### raíz

#### `index.php` (11 líneas)

**Rol**: Controlador frontal. Es lo único que Apache ejecuta de verdad: todas las URLs terminan acá por el `.htaccess`.

**Recibe / devuelve**: Recibe la petición HTTP del servidor. No devuelve nada: incluye las rutas y despacha.

**Se conecta con**: `routes.php` (que a su vez carga `core/bootstrap.php`) y `core/Router`.

**Notas**: Es a propósito tan chico. Toda la configuración y el ensamblado viven en `routes.php` y `bootstrap.php`; acá solo quedan las dos líneas que arrancan todo (`L7` incluye, `L11` despacha).

#### `routes.php` (922 líneas)

**Rol**: El corazón del arranque. Carga el bootstrap, configura CORS y seguridad, y declara **todas** las rutas de la API agrupadas por módulo.

**Recibe / devuelve**: No recibe nada; deja creada la variable `$router` lista para despachar.

**Se conecta con**: `core/bootstrap.php` (todas las clases y la conexión), todos los `controllers/*` y `core/Router`.

**Recorrido por bloques**:
- `L1–L19` Cabecera y presentación de la API.
- `L20–L92` **Configuración de headers (CORS + seguridad)**: whitelist de orígenes vía `CORS_ORIGINS`, manejo del preflight `OPTIONS`.
- `L93–L169` **Rutas públicas** generales (health, catálogos sueltos).
- `L170–L217` **Autenticación**: login y registro (públicos).
- `L218–L471` **Rutas protegidas** (requieren JWT): médicos, pacientes, prescripciones, etc.
- `L472–L508` **Sistema de gestión de citas (turnera)**: introducción del módulo.
- `L509–L527` **Turnera — catálogos públicos** (especialidades, obras sociales).
- `L528–L566` **Turnera — agenda pública** (disponibilidad y reserva).
- `L567–L592` **Turnera — acciones por token** (confirmar/cancelar un recordatorio sin login).
- `L593–L706` **Turnera — rutas protegidas** (requieren JWT).
- `L707–L720` **Turnera — panel de gestión** (médico o admin).
- `L721–L755` **Turnera — gestión del catálogo** (solo admin).
- `L756–L811` **Gestión de roles y permisos**.
- `L812–L894` **Primera funcionalidad en tiempo real (SSE)**.
- `L895–L922` Manejador de rutas no encontradas (404).

**Notas**: Acá se ve el patrón de ensamblado: cada ruta construye su controlador con las dependencias que necesita (inyección manual). Es el archivo que conviene mirar cuando no sabés a qué endpoint corresponde una URL.

#### `db.php` (643 líneas)

**Rol**: Abre la conexión SQLite con PDO, define la zona horaria y crea todas las tablas si no existen. Es la fuente de verdad del esquema.

**Recibe / devuelve**: No recibe nada; deja lista la variable `$pdo`.

**Se conecta con**: `core/Env.php` (variables de entorno) y todos los repositorios (reciben el `$pdo`).

**Recorrido por bloques**:
- `L11–L38` **Por qué SQLite y no MySQL**: justificación del cambio.
- `L39–L54` **Carga de variables de entorno** (lee el `.env`).
- `L55–L89` **Zona horaria de la aplicación**.
- `L90–L117` **Ubicación del archivo de base de datos** (`storage/saludweb.sqlite`).
- `L118–L131` **Conexión** PDO con `ERRMODE_EXCEPTION`.
- `L132–L184` **PRAGMA**: claves foráneas, WAL, etc.
- `L185–L297` **Creación de tablas** base (usuarios, pacientes, médicos, obras sociales…).
- `L298–L474` **Módulo turnera**: especialidades, disponibilidades, citas, notificaciones.
- `L475–L527` **Módulo tiempo real**: tabla de eventos para SSE.
- `L528–L596` **Inserción de datos de ejemplo** (solo si las tablas están vacías).
- `L597–L643` **Semilla del módulo turnera**.

**Notas**: Los `CREATE TABLE` usan `IF NOT EXISTS`. Los índices `UNIQUE` de `citas` son los que impiden doble reserva del mismo horario. No hay migraciones: el esquema se asegura en cada arranque.

#### `.htaccess` (4 líneas)

**Rol**: Config de Apache. Redirige toda petición que no sea un archivo o carpeta real hacia `index.php`.

**Recibe / devuelve**: No aplica.

**Se conecta con**: `index.php`.

**Notas**: Las 4 líneas son `RewriteEngine On`, dos condiciones (`!-f`, `!-d`) y la regla final con `[QSA,L]`. Por eso este backend funciona con URLs "lindas" sin framework.

#### `composer.json` (13 líneas)

**Rol**: Declara la única dependencia real (`firebase/php-jwt`) y dos scripts de conveniencia.

**Recibe / devuelve**: No aplica.

**Se conecta con**: `vendor/` y `tests/run.php`.

**Notas**: Script `dev` levanta el servidor embebido de PHP en `127.0.0.1:8000`; script `test` corre la suite. No hay autoload: las clases se incluyen a mano.

#### `migrar_mysql_a_sqlite.php` (249 líneas)

**Rol**: Herramienta de desarrollo. Copia los datos desde la base MySQL vieja hacia la base SQLite nueva.

**Recibe / devuelve**: Se ejecuta por consola. Imprime el progreso y devuelve código de salida.

**Se conecta con**: `db.php` (destino) y una conexión PDO MySQL (origen).

**Notas**: Es idempotente: correrlo dos veces no duplica filas. Es de un solo uso histórico; no forma parte de la API.

#### `sembrar_datos_demo.php` (466 líneas)

**Rol**: Carga datos de demostración completos (médicos, pacientes, disponibilidades, citas) para poder mostrar el sistema funcionando.

**Recibe / devuelve**: Se ejecuta por consola.

**Se conecta con**: `db.php`.

**Notas**: Usa la función `normalizarNombre()` (`L308`) para generar nombres consistentes. A diferencia de `seed_pacientes.php`, siembra un escenario entero, no solo pacientes.

#### `seed_pacientes.php` (93 líneas)

**Rol**: Genera N pacientes aleatorios para probar el **paginado** de los listados.

**Recibe / devuelve**: `php seed_pacientes.php 100` (sin argumento, crea 10).

**Se conecta con**: `db.php`.

**Notas**: Inserta con consultas preparadas y limita los intentos (`L64`) para no caer en bucle infinito si el azar repite muchos DNIs. Prefiere reusar `db.php` antes que tener su propia conexión.

#### `verificar_turnera.php` (106 líneas)

**Rol**: Herramienta de desarrollo que informa el estado real de las tablas y datos de la turnera.

**Recibe / devuelve**: Se ejecuta por consola. Devuelve 0 si todo está, 1 si falta algo (útil para pipelines).

**Se conecta con**: `db.php`.

**Notas**: No modifica nada: solo consulta. Lista las tablas de la turnera y cuenta filas; muestra los índices únicos de `citas` (los que evitan la doble reserva).

#### `probar_vinculacion.php` (109 líneas)

**Rol**: Congela el comportamiento esperado de la regla que decide si una cuenta puede vincularse a una ficha de paciente.

**Recibe / devuelve**: Se ejecuta por consola. Devuelve 0 si todos los casos se comportan como se espera.

**Se conecta con**: `db.php` y `services/AuthService.php`.

**Notas**: Como `coincidenNombres()` es privado, lo invoca por reflexión (`ReflectionMethod`, `L39`). Tiene dos listas: casos que deben aceptarse y casos que deben rechazarse.

#### `probar_roles.php` (420 líneas)

**Rol**: Prueba la gestión de roles **sin HTTP ni base real**, usando un repositorio falso.

**Recibe / devuelve**: Se ejecuta por consola.

**Se conecta con**: `services/UsuarioService.php`.

**Notas**: Define `class RepoFalso implements UsuarioRepositoryInterface` (`L46`) para no depender de SQLite, y la función `comprobar()` (`L96`) que imprime OK/FALLA.

#### `probar_roles_http.php` (386 líneas)

**Rol**: Prueba la gestión de roles de verdad, pegándole a la API por HTTP.

**Recibe / devuelve**: Se ejecuta por consola contra una API levantada.

**Se conecta con**: La API (`index.php`) y `db.php` (para crear/borrar cuentas de prueba).

**Notas**: Tiene helpers `pedir()` (`L34`), `comprobar()` (`L70`), `crearCuentaDescartable()` (`L143`) y `borrarCuentasDescartables()` (`L163`). Deja la base como la encontró.

#### `probar_turnera.php` (478 líneas)

**Rol**: Prueba de extremo a extremo de la turnera (reservar, cambiar estado, cancelar) contra la API real.

**Recibe / devuelve**: Se ejecuta por consola.

**Se conecta con**: La API y `db.php`.

**Notas**: Helpers `pedir()` (`L67`), `paso()` (`L109`) y `limpiar()` (`L141`). Recorre paso a paso el flujo de una cita.

#### `probar_tiempo_real.php` (589 líneas)

**Rol**: Prueba de extremo a extremo del módulo SSE: publica eventos y verifica que lleguen por el stream.

**Recibe / devuelve**: Se ejecuta por consola.

**Se conecta con**: La API (endpoint `/api/eventos`).

**Notas**: Helpers `seccion()` (`L69`), `prueba()` (`L81`) y `contar()` (`L113`). Es la contraparte en pruebas de `RealtimeController`.

#### (omitido) `listar_usuarios.php` (45 líneas)

**Motivo de la omisión**: existe en la raíz pero no formaba parte de la lista de archivos a documentar. Igual, para que no quede huérfano: es una utilidad de desarrollo que imprime los usuarios (sin contraseñas) para armar pruebas manuales. Nunca debe desplegarse en producción.

### core/

#### `core/bootstrap.php` (376 líneas)

**Rol**: Arranque del sistema. Carga el entorno, configura el logging y **ensambla todas las dependencias** (repositorios -> servicios -> middleware), de abajo hacia arriba.

**Recibe / devuelve**: No recibe nada; deja creadas las instancias que usan las rutas.

**Se conecta con**: `db.php`, `core/Env.php`, `core/Logger.php` y prácticamente todos los repositorios/servicios.

**Recorrido por bloques**:
- `L15–L47` **Carga de variables de entorno** (`.env`).
- `L48–L143` **Observabilidad**: inicializa logging y trazabilidad.
- `L144–L159` **Gestión de roles y permisos** (carga de su repositorio).
- `L160–L202` **Turnera**: contratos -> implementaciones -> servicios.
- `L203–L277` **Tiempo real (SSE)**.
- `L278–L286` **Ensamblado de la gestión de roles**.
- `L287–L376` **Ensamblado de la turnera** (inyección de dependencias por constructor).

**Notas**: Es el archivo que le muestra a un recién llegado cómo se conecta todo. El orden de carga importa: primero las interfaces, después las implementaciones, después los servicios.

#### `core/Config.php` (84 líneas)

**Rol**: Lectura tipada de configuración y validación de que no falte lo esencial.

**Recibe / devuelve**: `get($variable, $default)` devuelve un string; `appEnv()`/`esProduccion()` devuelven datos del entorno.

**Se conecta con**: `core/Env.php`.

**Notas**: `validar()` (`L54`) falla temprano si falta configuración crítica. `esProduccion()` se usa para activar/desactivar comportamientos de desarrollo.

#### `core/Env.php` (250 líneas)

**Rol**: Carga el archivo `.env` sin depender de Composer ni de librerías externas.

**Recibe / devuelve**: `cargar()` lee el archivo; expone el valor de cada variable vía `Config`/`getenv`.

**Se conecta con**: `core/Config.php`.

**Notas**: Jerarquía de precedencia: variables del sistema operativo > `.env` > valor por defecto. `procesarLinea()` (`L120`) interpreta cada línea y `limpiarValor()` (`L188`) quita comillas y espacios.

#### `core/Secret.php` (70 líneas)

**Rol**: Obtiene el secreto con el que se firman los JWT.

**Recibe / devuelve**: `obtener()` devuelve el secreto (string).

**Se conecta con**: `core/Env.php` y `core/JwtService.php`.

**Notas**: Si no hay `JWT_SECRET` en el entorno, lo genera con `random_bytes` y lo guarda en `storage/secret.key`. El secreto nunca debe estar en el repositorio.

#### `core/JwtService.php` (97 líneas)

**Rol**: Emitir y verificar tokens JWT (algoritmo HS256, vía `firebase/php-jwt`).

**Recibe / devuelve**: `generar($usuario)` devuelve `['token' => ..., 'expires_at' => ...]`; `verificar($token)` devuelve el payload.

**Se conecta con**: `core/Secret.php` (secreto) y `core/AuthMiddleware.php` (verificación).

**Notas**: Constructor (`L30`) recibe secreto, emisor y TTL (por defecto 3600 s). El payload lleva `iss`, `iat`, `exp`, `sub` y `rol`. `PruebaNoIncluyePassword` (ver `tests/JwtServiceTest.php`) garantiza que nunca viaje el hash de la contraseña.

#### `core/AuthMiddleware.php` (146 líneas)

**Rol**: Frontera de seguridad. Verifica el token (autenticación) y comprueba el rol (autorización).

**Recibe / devuelve**: `verificarToken()` devuelve el payload; `requireRol($payload, $roles)` no devuelve nada pero lanza error si el rol no alcanza.

**Se conecta con**: `core/JwtService.php`.

**Notas**: Separa a propósito autenticación (401) de autorización (403). `usuarioActual()` (`L112`) es estático para que cualquier capa consulte quién está logueado. El token viaja en `Authorization: Bearer <jwt>`.

#### `core/Router.php` (298 líneas)

**Rol**: Registro y despacho de rutas con soporte de parámetros y middlewares.

**Recibe / devuelve**: `dispatch($metodo, $uri)` no devuelve nada: ejecuta el controlador o responde 404.

**Se conecta con**: `routes.php` (registra) e `index.php` (despacha).

**Notas**: Métodos `get/post/put/patch/delete` (`L49–L93`) registran; `matchRoute()` (`L261`) hace el matching de parámetros tipo `{id}`. `notFound()` (`L104`) permite un manejador 404 propio.

#### `core/CuerpoJson.php` (148 líneas)

**Rol**: Leer y validar el cuerpo JSON de las peticiones que lo traen.

**Recibe / devuelve**: `leer($permitirVacio)` devuelve un array asociativo.

**Se conecta con**: Los controladores.

**Notas**: Distingue tres casos: body válido, body vacío y JSON mal formado (400). `esLista()` (`L137`) evita que llegue un array indexado donde se espera un objeto.

#### `core/Validador.php` (255 líneas)

**Rol**: Centraliza la validación y el saneamiento de entradas (principio DRY).

**Recibe / devuelve**: Devuelve el valor ya saneado o lanza `InvalidArgumentException` cuyo **código es el HTTP** (422, 401…).

**Se conecta con**: Todos los servicios.

**Notas**: `email()` (`L64`), `password()` (`L97`, entre 6 y 72 caracteres por bcrypt), `textoObligatorio()` (`L183`), `textoOpcional()` (`L210`), `acortar()` (`L236`). Sanea **antes** de validar vacío: por eso `<b></b>` se rechaza.

#### `core/Response.php` (117 líneas)

**Rol**: Uniforma todas las respuestas JSON.

**Recibe / devuelve**: `ok()` y `error()` escriben la respuesta y terminan.

**Se conecta con**: Todos los controladores.

**Notas**: Formato `{ ok, mensaje, data }` o `{ ok, mensaje, errores, code }`. `json()` (`L106`) es el único lugar que fija los headers de contenido y el código HTTP.

#### `core/RateLimiter.php` (220 líneas)

**Rol**: Limita intentos por clave (por ejemplo, login por IP) para frenar ataques de fuerza bruta.

**Recibe / devuelve**: `permitir($clave, $max, $ventana)` devuelve bool; `registrar()` anota un fallo y `limpiar()` borra el contador de una clave.

**Se conecta con**: `services/AuthService.php` y `controllers/AuthController.php`.

**Notas**: Guarda el estado en un JSON dentro de `storage/rate/`. Un archivo corrupto se descarta en vez de romper el login. La ventana expira sola: no es una condena perpetua. Un login exitoso debe llamar a `limpiar()`.

#### `core/CorrelationId.php` (171 líneas)

**Rol**: Genera o adopta el identificador de correlación de cada petición, para poder unir los logs del cliente con los del servidor.

**Recibe / devuelve**: `iniciar()` devuelve el id; `actual()` devuelve el vigente (y lo genera si no existe).

**Se conecta con**: `core/Logger.php` y `core/Peticion.php`.

**Notas**: Alfabeto restringido (letras, números, guion, guion bajo), hasta 64 caracteres. Un id del cliente con salto de línea o `<>` se **rechaza** porque permitiría inyectar líneas falsas en el log. Formato propio: `cid_` + 16 hex.

#### `core/Logger.php` (504 líneas)

**Rol**: Logs estructurados en formato **JSON Lines**, con enmascarado de datos sensibles.

**Recibe / devuelve**: `info/warn/error/debug` escriben; `linea()` arma la línea sin escribirla; `enmascarar()` devuelve los datos limpios.

**Se conecta con**: `core/CorrelationId.php` y `core/Peticion.php`.

**Recorrido por bloques**:
- `L42–L110` Constantes de nivel y estado interno.
- `L111–L162` `configurar()`: directorio, nivel mínimo y nombre de servicio.
- `L163–L217` Resolución del directorio de logs (ruta absoluta o relativa).
- `L218–L291` Getters y atajos `debug/info/warn/error`.
- `L292–L339` `log()` y `registro()`.
- `L340–L376` `linea()`: arma el JSON.
- `L377–L452` `enmascarar()` y `enmascararParcial()`.
- `L453–L485` `escribir()` en disco.
- `L486–L504` `ultimasLineas()`.

**Notas**: Los campos del contrato son `timestamp`, `level`, `message`, `service`, `correlationId` y `context`. El enmascarado es recursivo: un secreto anidado también se oculta, y los valores gigantes se truncan. Un nivel desconocido se descarta.

#### `core/Peticion.php` (329 líneas)

**Rol**: Ciclo de vida de la petición: correlación, resumen de tiempos y captura de errores fatales.

**Recibe / devuelve**: `iniciar()` arranca; `finalizar()` cierra y loguea el resumen.

**Se conecta con**: `core/Logger.php` y `core/CorrelationId.php`.

**Notas**: Usa `set_error_handler` y `register_shutdown_function` para que aun un error fatal quede registrado con su id de correlación. `avisarPhp()` (`L168`) traduce warnings; `registrarExcepcion()` (`L202`) loguea una excepción.

### controllers/

> Regla general: un controlador **no decide reglas de negocio**. Lee la entrada, llama al servicio y devuelve la respuesta. Los que necesitan saber quién actúa usan `AuthService` (por `contextoDesdeToken()`/`contextoDesdePayload()`).

#### `controllers/AuthController.php` (333 líneas)

**Rol**: Endpoints de autenticación.

**Recibe / devuelve**: Lee body/headers y responde JSON.

**Se conecta con**: `services/AuthService.php` y `core/RateLimiter.php`.

**Notas**: Métodos: `registro` (`L28`), `login` (`L60`, aplica rate limiting), `sso` (`L120`), `cambiarContrasena` (`L180`), `obtenerUsuarioActual` (`L228`), `vincular` (`L266`), `logout` (`L303`). `obtenerIdDesdeToken()` (`L318`) es auxiliar privado.

#### `controllers/CitaController.php` (423 líneas)

**Rol**: Endpoints de la turnera: disponibilidad, agenda, listado, alta y cambios de estado de citas.

**Recibe / devuelve**: Lee params, query y body; responde JSON.

**Se conecta con**: `services/CitaService.php` y `services/AuthService.php`.

**Notas**: Métodos: `disponibilidad` (`L50`), `agenda` (`L83`), `index` (`L155`), `show` (`L266`), `store` (`L304`), `cambiarEstado` (`L341`), `cancelar` (`L371`), `destroy` (`L388`). `contextoDesdeToken()` (`L413`) arma el contexto de permisos.

#### `controllers/DisponibilidadController.php` (149 líneas)

**Rol**: Endpoints de los bloques de disponibilidad (agenda que publica cada médico).

**Se conecta con**: `services/DisponibilidadService.php` y `services/AuthService.php`.

**Notas**: `index` (`L40`), `store` (`L63`), `update` (`L87`), `destroy` (`L123`), `contextoDesdeToken()` (`L141`).

#### `controllers/EspecialidadController.php` (171 líneas)

**Rol**: Endpoints del catálogo de especialidades.

**Se conecta con**: `services/EspecialidadService.php` y `services/AuthService.php`.

**Notas**: `index` (`L45`), `show` (`L58`), `store` (`L73`), `update` (`L100`), `destroy` (`L128`). `verificarAdmin()` (`L149`) exige rol admin para las escrituras.

#### `controllers/EstadisticaController.php` (98 líneas)

**Rol**: Endpoint que devuelve las métricas de la turnera.

**Se conecta con**: `services/EstadisticaService.php` y `services/AuthService.php`.

**Notas**: `index` (`L50`) delega todo el cálculo en el servicio y solo formatea el resultado. `contextoDesdeToken()` (`L90`).

#### `controllers/MedicoController.php` (151 líneas)

**Rol**: CRUD de médicos.

**Se conecta con**: `services/MedicoService.php`.

**Notas**: `index` (`L41`), `show` (`L66`), `store` (`L82`), `update` (`L109`), `destroy` (`L135`). No maneja contexto de usuario: el acceso lo controla el middleware.

#### `controllers/PacienteController.php` (124 líneas)

**Rol**: CRUD de pacientes.

**Se conecta con**: `services/PacienteService.php`.

**Notas**: `index` (`L24`), `show` (`L38`), `store` (`L55`), `update` (`L82`), `destroy` (`L108`).

#### `controllers/PrescripcionController.php` (169 líneas)

**Rol**: CRUD de prescripciones, incluyendo el listado por paciente.

**Se conecta con**: `services/PrescripcionService.php`.

**Notas**: `index` (`L25`), `indexPorPaciente` (`L41`), `show` (`L51`), `store` (`L68`), `update` (`L95`), `destroy` (`L121`), `cambiarEstado` (`L143`).

#### `controllers/NotificacionController.php` (231 líneas)

**Rol**: Endpoints de recordatorios: listar, generar, procesar pendientes y las acciones públicas por token (confirmar/cancelar).

**Se conecta con**: `services/NotificacionService.php` y `services/AuthService.php`.

**Notas**: `index` (`L47`), `generarRecordatorios` (`L88`), `procesar` (`L121`), `reintentar` (`L147`), `resolverPorToken` (`L172`), `confirmarPorToken` (`L191`), `cancelarPorToken` (`L207`). `contextoDesdeToken()` (`L223`).

#### `controllers/RealtimeController.php` (589 líneas)

**Rol**: Capa HTTP del canal en vivo (SSE). Mantiene la conexión abierta y va escribiendo eventos.

**Se conecta con**: `services/RealtimeService.php`, `core/JwtService.php` y `services/AuthService.php`.

**Recorrido por bloques**:
- `L69–L110` La clase y su constructor (`RealtimeService`, `JwtService`, `AuthService`).
- `L111–L288` **GET /api/eventos**: abre el stream SSE (handshake, cabeceras, primer envío).
- `L289–L389` **El bucle de escucha**: en cada vuelta consulta eventos nuevos del canal y los escribe.
- `L390–L409` `datosDe()`: da forma a los datos del evento.
- `L410–L436` **GET /api/eventos/estado**: diagnóstico del módulo (JSON).
- `L437–L503` `verificarTokenDeQuery()`: como `EventSource` no manda headers, el token viaja por query.
- `L504–L556` Helpers `ultimoIdInicial()`, `escribirEvento()` y `escribirCrudo()`.

**Notas**: Es el controlador más largo porque el SSE obliga a manejar tiempo de vida, reconexión y "latidos". Para el detalle, ver `GUIA_TIEMPO_REAL.md`.

#### `controllers/UsuarioController.php` (200 líneas)

**Rol**: Endpoints de la gestión de roles y permisos.

**Se conecta con**: `services/UsuarioService.php`.

**Notas**: El constructor (`L55`) recibe el payload del admin que actúa. `index` (`L68`) lista usuarios, `roles` (`L128`) lista roles válidos y `cambiarRol` (`L146`) cambia el rol de una cuenta.

### services/

#### `services/AuthService.php` (979 líneas)

**Rol**: Toda la lógica de autenticación: registro, login local, login SSO (Google/Microsoft), cambio de contraseña y vinculación de cuenta con ficha de paciente/médico.

**Recibe / devuelve**: Recibe arrays de datos y devuelve arrays con usuario + token; lanza excepciones con código HTTP cuando algo falla.

**Se conecta con**: `persistence/UsuarioRepository`, `persistence/PacienteRepository`, `persistence/MedicoRepository`, `core/JwtService.php`.

**Recorrido por bloques** (por grupos de métodos):
- `L45–L157` **Registro**: valida, hashea la contraseña y crea la cuenta.
- `L158–L318` **Login** local y **login SSO**.
- `L319–L469` Validación de `id_token` de Google/Microsoft (descarga de certificados públicos).
- `L470–L569` Consultas por id/email y **cambio de contraseña**.
- `L570–L707` **Contexto de petición** (quién actúa) y **vinculación** de entidad.
- `L708–L958` Vinculación de paciente/médico y la comparación de nombres.
- `L919–L978` `palabrasSignificativas()` e `invertirApellidoNombre()`.

**Notas**: `coincidenNombres()` (`L845`) es la única barrera real de la vinculación; su comportamiento esperado está congelado en `probar_vinculacion.php`. El login nunca revela si falló el email o la contraseña.

#### `services/CitaService.php` (1138 líneas)

**Rol**: El servicio más grande. Calcula disponibilidad, gestiona la agenda, crea citas y controla las transiciones de estado.

**Recibe / devuelve**: Devuelve arrays con citas/horarios; lanza excepciones con código HTTP.

**Se conecta con**: `persistence/CitaRepository`, `persistence/DisponibilidadRepository`, `persistence/MedicoRepository` y `services/RealtimeService.php` (para avisar en vivo).

**Recorrido por bloques**:
- `L82–L333` **Disponibilidad**: horarios ofrecidos al paciente.
- `L334–L436` **Listado y detalle** (paginado).
- `L437–L696` **Alta de citas**: validaciones y control de doble reserva.
- `L697–L803` **Cambio de estado, cancelación y borrado**.
- `L804–L842` **Aviso en vivo** (publica en el canal SSE).
- `L843–L1138` **Helpers privados**: fechas, horas, transiciones válidas y control de acceso.

**Notas**: `estadosValidos()` (`L853`) y `verificarTransicion()` (`L1077`) definen la máquina de estados. `verificarAccesoACita()` (`L1027`) decide si el que actúa puede tocar esa cita. La doble reserva se evita combinando validación de negocio con el índice UNIQUE de la base.

#### `services/DisponibilidadService.php` (465 líneas)

**Rol**: Reglas de los bloques de disponibilidad que publica cada profesional.

**Recibe / devuelve**: Devuelve arrays de bloques; valida horas y pertenencia.

**Se conecta con**: `persistence/DisponibilidadRepository` y `persistence/MedicoRepository`.

**Recorrido por bloques**:
- `L44–L189` Consulta y **creación** de bloques.
- `L190–L321` **Actualización**.
- `L322–L373` **Activar/desactivar** y borrado.
- `L374–L464` Helpers: resolver médico objetivo, verificar acceso y validar horas.

**Notas**: `verificarAccesoAMedico()` (`L412`) garantiza que un profesional solo edite su propia agenda.

#### `services/EspecialidadService.php` (232 líneas)

**Rol**: Reglas del catálogo de especialidades.

**Se conecta con**: `persistence/EspecialidadRepository`.

**Recorrido por bloques**:
- `L51–L70` Listado y detalle.
- `L81–L131` **Crear** y **actualizar**.
- `L147–L162` **Eliminar** (bloquea si hay médicos asociados).
- `L177–L231` Normalización y detección de duplicados.

**Notas**: La comparación por `claveComparacion()` (`L219`) ignora acentos y mayúsculas, para que "Cardiología" y "cardiologia" no convivan como dos especialidades.

#### `services/EstadisticaService.php` (346 líneas)

**Rol**: Calcula las métricas de la turnera a partir de citas y disponibilidad.

**Se conecta con**: `persistence/CitaRepository` y `persistence/DisponibilidadRepository`.

**Notas**: `generarInforme()` (`L75`) arma el informe. No es tiempo real: es un cálculo REST bajo demanda. Métodos auxiliares en `L209–L346`.

#### `services/MedicoService.php` (249 líneas)

**Rol**: CRUD de médicos con paginado.

**Se conecta con**: `persistence/MedicoRepository`.

**Notas**: `obtenerPaginado()` (`L56`) es el método central. `eliminar()` (`L238`) se apoya en `desvincularPrescripciones()` para no dejar referencias colgando.

#### `services/NotificacionService.php` (758 líneas)

**Rol**: Recordatorios de citas. Genera notificaciones, las procesa por un proveedor y maneja las acciones por token.

**Se conecta con**: `persistence/NotificacionRepository`, `persistence/ContactoRepository`, `persistence/CitaRepository` y `services/Notificacion/ProveedorNotificacionesInterface`.

**Recorrido por bloques**:
- `L75–L354` **Generación de recordatorios** (patrón outbox: se generan y quedan pendientes).
- `L355–L510` **Confirmación y cancelación** desde el link del recordatorio (por token).
- `L511–L637` **Listado y reintentos**.
- `L638–L758` Helpers: pertenencia, armado del mensaje y generación de token.

**Notas**: `crearSiNoExiste()` (en el repositorio) evita recordatorios duplicados. `procesarPendientes()` (`L245`) es el que realmente "envía" lo que quedó en la cola.

#### `services/PacienteService.php` (242 líneas)

**Rol**: CRUD de pacientes con paginado.

**Se conecta con**: `persistence/PacienteRepository`.

**Notas**: `obtenerPaginado()` (`L43`), `obtenerPorDni()` (`L102`) y validaciones en `crear`/`actualizar`.

#### `services/PrescripcionService.php` (290 líneas)

**Rol**: CRUD de prescripciones con paginado y cambio de estado.

**Se conecta con**: `persistence/PrescripcionRepository`.

**Notas**: `obtenerPaginadas()` (`L43`), `cambiarEstado()` (`L273`). Valida la coherencia entre paciente y médico.

#### `services/RealtimeService.php` (405 líneas)

**Rol**: Lógica del tiempo real: a qué canales mandar cada evento y quién puede suscribirse.

**Se conecta con**: `persistence/RealtimeRepositoryInterface`.

**Recorrido por bloques**:
- `L77–L165` **Emisión de eventos** (`notificarCita`, `canalesDeCita`).
- `L166–L268` **Autorización de suscripciones** (`resolverCanal`).
- `L269–L312` **Nombres de canales** (`canalAgenda`, `canalTurnos`).
- `L313–L349` **Lectura para el stream**.
- `L350–L405` **Diagnóstico** (`purgarAhora`, `diagnostico`).

**Notas**: `resolverCanal()` (`L188`) es la frontera de seguridad del módulo: decide qué canal puede ver cada usuario según su rol.

#### `services/UsuarioService.php` (301 líneas)

**Rol**: Gestión de roles y permisos de usuarios.

**Se conecta con**: `persistence/UsuarioRepositoryInterface`.

**Recorrido por bloques**:
- `L91–L138` **Listado** paginado y listado de roles.
- `L155–L237` **Cambio de rol** con reglas de seguridad.
- `L256–L300` Helpers: `enriquecer()` y `notaVinculacion()`.

**Notas**: Reglas clave de `cambiarRol()` (`L155`): no se puede degradar al último admin; un admin no puede cambiarse el rol a sí mismo. Devuelve 409 cuando hay conflicto de estado y 403 cuando falta permiso.

#### `services/Notificacion/ProveedorNotificacionesInterface.php` (48 líneas)

**Rol**: Contrato para cualquier proveedor de notificaciones (email, SMS, etc.).

**Se conecta con**: `services/NotificacionService.php`.

**Notas**: `enviar(array $datos): array` (`L47`) es el único método. Esto permite cambiar de proveedor sin tocar el servicio.

#### `services/Notificacion/ProveedorNotificacionesStub.php` (59 líneas)

**Rol**: Implementación de desarrollo que **no envía nada**: solo registra que se "envió".

**Se conecta con**: `services/NotificacionService.php`.

**Notas**: Existe para poder probar el flujo completo de recordatorios sin configurar un proveedor real. Se reemplaza por una implementación de verdad en producción.

### persistence/

> Acá vive **todo** el SQL. Cada repositorio recibe el `PDO` por constructor y no sabe nada de HTTP. La mayoría tiene una interfaz (`*RepositoryInterface.php`) que el servicio usa para poder cambiarse por un doble en las pruebas (ver `probar_roles.php`). Dos repositorios **no** tienen interfaz a propósito: `ContactoRepository` y `ObraSocialRepository`, porque su uso es puntual y no se mockean.

#### `persistence/CitaRepository.php` (598 líneas)

**Rol**: Acceso a datos de las citas.

**Se conecta con**: `services/CitaService.php`.

**Recorrido por bloques** (por métodos):
- `L52–L223` `obtenerPaginadas()`, `contar()` y `construirFiltros()` (filtros dinámicos).
- `L224–L343` Consultas puntuales y de rango, y `crear()`.
- `L344–L451` `actualizar()`, `cambiarEstado()`, `eliminar()` y `marcarRecordatorioEnviado()`.
- `L452–L597` Consultas de resumen y conteos para estadísticas (`obtenerResumen`, `contarPorDia`, `contarPorEspecialidad`, `contarPorMedico`).

**Notas**: `construirFiltros()` (`L138`) arma el `WHERE` de forma segura. La tabla `citas` tiene índices UNIQUE de slot: son la última barrera contra la doble reserva.

#### `persistence/CitaRepositoryInterface.php` (50 líneas)

**Rol**: Contrato del repositorio de citas (14 métodos, `L14–L49`).

#### `persistence/ContactoRepository.php` (117 líneas)

**Rol**: Obtiene el email de contacto de pacientes y médicos (para recordatorios).

**Se conecta con**: `services/NotificacionService.php`.

**Notas**: Sin interfaz a propósito. Métodos `obtenerEmailDePaciente()` (`L45`), `obtenerEmailDeMedico()` (`L66`) y `obtenerContactoDePaciente()` (`L90`).

#### `persistence/DisponibilidadRepository.php` (195 líneas)

**Rol**: Acceso a los bloques de disponibilidad.

**Se conecta con**: `services/DisponibilidadService.php` y `services/CitaService.php`.

**Notas**: `obtenerPorMedico()` (`L27`), `obtenerPorMedicoYDia()` (`L46`), `crear/actualizar/eliminar` y `esDeMedico()` (`L186`), que sostiene el control de pertenencia.

#### `persistence/DisponibilidadRepositoryInterface.php` (37 líneas)

**Rol**: Contrato del repositorio de disponibilidad (8 métodos, `L12–L36`).

#### `persistence/EspecialidadRepository.php` (135 líneas)

**Rol**: Acceso al catálogo de especialidades.

**Se conecta con**: `services/EspecialidadService.php`.

**Notas**: `obtenerPorNombre()` (`L69`) alimenta la detección de duplicados; `contarMedicos()` (`L123`) permite bloquear el borrado si hay médicos asociados.

#### `persistence/EspecialidadRepositoryInterface.php` (27 líneas)

**Rol**: Contrato del repositorio de especialidades.

#### `persistence/MedicoRepository.php` (373 líneas)

**Rol**: Acceso a datos de médicos.

**Se conecta con**: `services/MedicoService.php`, `services/CitaService.php` y `services/DisponibilidadService.php`.

**Notas**: `obtenerPaginado()` (`L94`) y `contar()` (`L164`) implementan la búsqueda con filtros; `obtenerPorMatricula()` (`L243`) se usa en la vinculación; `desvincularPrescripciones()` (`L339`) limpia referencias antes de borrar.

#### `persistence/MedicoRepositoryInterface.php` (41 líneas)

**Rol**: Contrato del repositorio de médicos.

#### `persistence/NotificacionRepository.php` (388 líneas)

**Rol**: Acceso a las notificaciones/recordatorios.

**Se conecta con**: `services/NotificacionService.php`.

**Notas**: Además del CRUD tiene piezas propias del patrón outbox: `crearSiNoExiste()` (`L261`, evita duplicados), `marcarEnviada`/`marcarFallida`, `registrarIntento()` (`L336`, cuenta reintentos) y `obtenerPendientes()` (`L357`, lo que aún no se envió).

#### `persistence/NotificacionRepositoryInterface.php` (50 líneas)

**Rol**: Contrato del repositorio de notificaciones (11 métodos, `L15–L49`).

#### `persistence/ObraSocialRepository.php` (39 líneas)

**Rol**: Acceso al catálogo de obras sociales.

**Notas**: Sin interfaz a propósito. Un único método, `obtenerTodas()` (`L26`).

#### `persistence/PacienteRepository.php` (239 líneas)

**Rol**: Acceso a datos de pacientes.

**Se conecta con**: `services/PacienteService.php` y `services/AuthService.php`.

**Notas**: `obtenerPaginado()` (`L55`), `obtenerPorDni()` (`L145`, usado en la vinculación) y el CRUD.

#### `persistence/PacienteRepositoryInterface.php` (27 líneas)

**Rol**: Contrato del repositorio de pacientes.

#### `persistence/PrescripcionRepository.php` (324 líneas)

**Rol**: Acceso a datos de prescripciones.

**Se conecta con**: `services/PrescripcionService.php`.

**Notas**: `obtenerPaginadas()` (`L61`), `obtenerPorPaciente()` (`L157`), `formatearFila()` (`L211`) y `cambiarEstado()` (`L317`).

#### `persistence/PrescripcionRepositoryInterface.php` (29 líneas)

**Rol**: Contrato del repositorio de prescripciones.

#### `persistence/RealtimeRepository.php` (149 líneas)

**Rol**: Persistencia de los eventos del tiempo real (tabla de eventos).

**Se conecta con**: `services/RealtimeService.php`.

**Notas**: `publicar()` (`L37`) inserta un evento; `obtenerDesde()` (`L69`) trae los nuevos a partir de un id (la base del "polling" del SSE); `ultimoIdDe()` (`L104`) y `purgarAntiguos()` (`L123`) mantienen la tabla sana.

#### `persistence/RealtimeRepositoryInterface.php` (69 líneas)

**Rol**: Contrato del repositorio de tiempo real (4 métodos, `L28–L68`).

#### `persistence/UsuarioRepository.php` (222 líneas)

**Rol**: Acceso a datos de usuarios.

**Se conecta con**: `services/UsuarioService.php` y `services/AuthService.php`.

**Notas**: `obtenerPaginado()` (`L36`) resuelve búsqueda + filtro por rol; `actualizarRol()` (`L186`) cambia el tipo de usuario; `contarActivosConRol()` (`L207`) es lo que permite no degradar al último admin.

#### `persistence/UsuarioRepositoryInterface.php` (76 líneas)

**Rol**: Contrato del repositorio de usuarios (4 métodos, `L40–L75`). Es la interfaz que se implementa en `probar_roles.php` con `RepoFalso`.

### tests/

> La suite es un arnés mínimo, sin framework (ver la explicación en `TestCase.php`). Cubre la **lógica pura y crítica**: enmascarado de logs, validación, correlación, rate limiting y firma/expiración de JWT. No cubre controladores ni repositorios: eso se prueba con los scripts de la raíz (`probar_*.php`).

#### `tests/run.php` (164 líneas)

**Rol**: Ejecutor de la suite. Descubre las clases `*Test.php`, las corre y devuelve código de salida (0 = todo pasó, 1 = hubo fallos), que es lo que permite engancharlo a un pipeline.

**Notas**: Uso: `php tests/run.php` (todo), `php tests/run.php Logger` (filtra por nombre), `php tests/run.php --lista`. Incluye archivo por archivo a propósito (no usa `bootstrap.php`) para poder correr sin base de datos.

#### `tests/TestCase.php` (339 líneas)

**Rol**: Arnés mínimo de pruebas: clase base abstracta con las aserciones y el registro de resultados.

**Recorrido por bloques**:
- `L43–L96` `pruebas()` (abstracto) y `correr()` (ejecuta cada prueba por separado).
- `L98–L126` `ejecutar()`: fuerza la estructura **AAA** (Arrange, Act, Assert).
- `L140–L293` **Aserciones**: `afirmarQue`, `afirmarVerdadero/Falso`, `afirmarIgual` (usa `===`), `afirmarContiene/NoContiene`, `afirmarCoincide` y `afirmarLanza`.
- `L300–L338` `resumen()` y `describir()`.

**Notas**: Explica por qué no usa PHPUnit (una sola dependencia, sin autoload) y qué principios sí respeta (FIRST, AAA, auto-validante). `afirmarIgual` usa `===` a propósito: `"0" == 0` escondería bugs de tipo.

#### `tests/CorrelationIdTest.php` (200 líneas)

**Rol**: Pruebas de la correlación de peticiones.

**Notas**: 10 pruebas (`L30–L44`). La importante es la de seguridad: un id con salto de línea se rechaza (evita inyectar líneas falsas en el log). Cubre camino feliz, bordes (largo, espacios, `<>`) y fallas (array, número, vacío).

#### `tests/JwtServiceTest.php` (240 líneas)

**Rol**: Pruebas de emisión y verificación de JWT.

**Notas**: 8 pruebas (`L50–L61`). Verifica el camino feliz, que no viaje el hash de contraseña ni columnas de más, y las fallas de seguridad: firma distinta (`L151`), token manipulado (`L171`) y token vencido (`L197`). Usa un secreto de prueba fijo y público a propósito.

#### `tests/LoggerTest.php` (310 líneas)

**Rol**: Pruebas del log estructurado y del enmascarado.

**Notas**: 9 pruebas (`L51–L63`). La crítica: una contraseña o un token **nunca** deben llegar al archivo. También verifica el formato JSON con los campos del contrato, el filtro por nivel, el enmascarado recursivo, el truncado de valores gigantes y la escritura real en disco (con carpeta temporal que se limpia sola).

#### `tests/RateLimiterTest.php` (220 líneas)

**Rol**: Pruebas del limitador de intentos.

**Notas**: 6 pruebas (`L47–L56`). Usa una carpeta temporal propia. Cubre el camino feliz, el bloqueo al llegar al máximo, que `limpiar()` libere, que la ventana expire, que un archivo corrupto no rompa el login y que claves distintas no compartan contador.

#### `tests/ValidadorTest.php` (319 líneas)

**Rol**: Pruebas de la validación y el saneamiento de entradas.

**Notas**: 17 pruebas (`L34–L54`) agrupadas en Email (`L57–L132`), Contraseña (`L134–L172`), Textos (`L174–L306`) y `acortar` (`L308–L318`). El caso estrella es el borde del límite: 150 caracteres entran y 151 se rechazan; y el de "texto con solo HTML", que hay que sanear **antes** de validar vacío.

## Orden de lectura recomendado

Para entender el backend de punta a punta en unos doce archivos:

1. `index.php` — el punto de entrada (2 líneas útiles).
2. `routes.php` — qué endpoints existen y cómo se ensamblan.
3. `core/Router.php` — cómo se despacha una ruta.
4. `core/bootstrap.php` — cómo se conecta todo.
5. `db.php` — el esquema y la conexión.
6. `core/AuthMiddleware.php` + `core/JwtService.php` — la seguridad.
7. `services/AuthService.php` — el patrón de un servicio completo (registro/login).
8. `persistence/UsuarioRepository.php` — el patrón del acceso a datos.
9. `services/CitaService.php` — las reglas de negocio de la turnera.
10. `persistence/CitaRepository.php` — el acceso a datos de la turnera.
11. `services/RealtimeService.php` + `controllers/RealtimeController.php` — el módulo de tiempo real.
12. `tests/run.php` + `tests/TestCase.php` — cómo se prueba lo crítico.

## Glosario breve

- **Front Controller**: patrón donde una sola entrada (`index.php`) atiende todas las peticiones y decide a quién delegar.
- **Middleware**: código que corre entre la ruta y el controlador. Acá `AuthMiddleware` verifica token y rol.
- **Repository Pattern**: separar el acceso a datos en clases (`*Repository`) para que la lógica de negocio no escriba SQL ni sepa qué base se usa. Por eso existen las interfaces.
- **Inyección de dependencias**: en vez de que una clase cree lo que necesita, se lo pasan por constructor. `bootstrap.php` lo hace a mano.
- **JWT (JSON Web Token)**: token firmado que identifica al usuario. Acá es HS256 y lleva `iss`, `iat`, `exp`, `sub` y `rol`.
- **SSE (Server-Sent Events)**: canal unidireccional servidor -> cliente sobre HTTP. Es la base del módulo de tiempo real.
- **Rate limiting**: limitar la cantidad de intentos en una ventana de tiempo (por ejemplo, login por IP) para frenar la fuerza bruta.
- **Outbox**: patrón de las notificaciones: se generan y se guardan como pendientes; después un proceso aparte las envía. Evita perder avisos si el envío falla.
- **Idempotente**: una operación que se puede repetir sin cambiar el resultado. `migrar_mysql_a_sqlite.php` y `crearSiNoExiste()` lo son.
- **Correlation ID**: identificador único por petición que permite seguir una misma acción a través de todos los logs.
