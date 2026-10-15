# Guía de código — Backend · core/

> Recorrido línea por línea de la infraestructura transversal.
> Índice general en ../GUIA_DE_CODIGO.md

## `core/bootstrap.php` (376 líneas)
- **Rol:** Punto de arranque. Carga entorno, autoload, configura errores, logger y observabilidad, incluye todas las clases (core, persistencia, servicios, controladores), ensambla las dependencias y deja `$router`, `$pdo` y los servicios listos para `routes.php`.
- **Recorrido línea por línea:**
  - L1–L9: `<?php` y comentario de cabecera que define a bootstrap como el primer archivo ejecutado.
  - L10–L13: separador y repetición del título del archivo (comentario duplicado).
  - L14–L24: bloque "CARGA DE VARIABLES DE ENTORNO"; explica por qué `Env` se carga antes que `Config`.
  - L25–L26: `require_once Env.php` y llamada a `Env::cargar()`.
  - L27–L29: comentario y `require_once` del autoload de Composer (firebase/php-jwt).
  - L30–L32: comentario y `require_once` de `Config.php`.
  - L33–L43: política de errores: `display_errors`, `display_startup_errors` según entorno y `error_reporting(E_ALL)`.
  - L44–L46: comentario y llamada a `Config::validar()`.
  - L47–L63: bloque de observabilidad; justifica el orden CorrelationId → Logger → Peticion.
  - L64–L66: `require_once` de `CorrelationId.php`, `Logger.php` y `Peticion.php`.
  - L67–L70: comentario y `Logger::configurar()`.
  - L71–L75: comentario y `Peticion::iniciar()`.
  - L76–L78: comentario y `session_start()`.
  - L79–L93: comentarios y `require_once` de `Router`, `Secret`, `JwtService`, `AuthMiddleware` y `RateLimiter`.
  - L94–L97: comentario y `require_once` de `../db.php` (crea `$pdo`).
  - L98–L100: comentario y `require_once` de `Response.php`.
  - L101–L106: comentario y `require_once` de `Validador.php`.
  - L107–L111: comentario y `require_once` de `CuerpoJson.php`.
  - L112–L119: `require_once` de las interfaces de persistencia y de `ObraSocialRepository`.
  - L120–L128: carga de `MedicoRepository`, `MedicoService` y `MedicoController`.
  - L129–L138: carga de repositorio, servicio y controlador de Pacientes y de Prescripciones.
  - L139–L142: carga de `AuthService` y `AuthController`.
  - L143–L158: módulo "Gestión de roles y permisos": requires de contrato, repositorio, servicio y controlador de Usuario.
  - L159–L201: módulo "Turnera": contratos, implementaciones, servicios, proveedor de notificaciones y controladores.
  - L202–L219: módulo "Primera funcionalidad en tiempo real" (SSE): requires de realtime.
  - L220–L227: cálculo de `$basePath` con `rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')`.
  - L228–L232: instanciación de `Router` con `$basePath`.
  - L233–L241: instanciación de `MedicoRepository` y `MedicoService` por inyección.
  - L242–L248: instanciación de repositorio/servicio de Pacientes y de `ObraSocialRepository`.
  - L249–L252: instanciación de repositorio/servicio de Prescripciones.
  - L253–L267: vigencia JWT (`JWT_TTL` con guarda `> 0`) e instanciación de `JwtService` con `Secret::obtener()`.
  - L268–L273: instanciación de `AuthMiddleware` y `RateLimiter` (storage/rate).
  - L274–L276: instanciación de `AuthService` con `$pdo` y `$jwtService`.
  - L277–L285: ensamblado de roles: solo se instancia `UsuarioRepository`.
  - L286–L312: ensamblado de la turnera: repositorios y `ProveedorNotificacionesStub`.
  - L313–L333: repositorio y publicador de tiempo real (`RealtimeRepository` y `RealtimeService`).
  - L334–L354: instanciación de `CitaService` con sus cinco dependencias (incluido `$realtimeService`).
  - L355–L368: instanciación de `DisponibilidadService`, `EspecialidadService`, `NotificacionService` y `EstadisticaService`.
  - L369–L376: cierre: aclara que realtime ya está construido y que las variables quedan para `routes.php`.
- **Se conecta con:** `Env`, `Config`, `Secret`, `JwtService`, `AuthMiddleware`, `RateLimiter`, `Router`, `Response`, `Validador`, `CuerpoJson`, `CorrelationId`, `Logger`, `Peticion`, `../db.php`, todos los repositorios/servicios/controladores y `routes.php`.

---

## `core/Config.php` (84 líneas)
- **Rol:** Centraliza el acceso a variables de entorno, define el entorno (development/production) y valida en producción que los secretos obligatorios existan.
- **Recorrido línea por línea:**
  - L1–L11: cabecera y comentario del módulo "Seguridad Básica para APIs - Variables de Entorno".
  - L12–L13: separador y apertura `class Config`.
  - L14–L18: docblock de `appEnv()` (entorno con default development).
  - L19–L25: `appEnv()`: lee `APP_ENV`, devuelve minúsculas o `'development'`.
  - L26–L29: docblock de `esProduccion()`.
  - L30–L34: `esProduccion()`: compara `appEnv()` con `'production'`.
  - L35–L38: docblock de `get()`.
  - L39–L45: `get()`: lee una variable de entorno o devuelve su default.
  - L46–L53: docblock de `validar()` con `@throws RuntimeException`.
  - L54–L59: inicio de `validar()`; en desarrollo retorna sin exigir nada.
  - L60–L64: lista `$requeridas` (`JWT_SECRET`, `APP_ENV`) y arreglo `$faltantes`.
  - L65–L73: `foreach` que detecta variables ausentes o vacías.
  - L74–L84: lanza `RuntimeException` enumerando las faltantes y cierra clase.
- **Se conecta con:** `bootstrap.php` (validación de arranque), `Secret.php` (chequeo de producción), `Logger.php` (nivel por entorno y `LOG_*`).

---

## `core/Env.php` (250 líneas)
- **Rol:** Cargador de `.env` sin dependencias. Convierte `CLAVE=valor` en variables accesibles por `getenv()`, `$_ENV` y `$_SERVER`, respetando la jerarquía SO > `.env` > default.
- **Recorrido línea por línea:**
  - L1–L36: cabecera explicativa: qué hace, por qué no usa librería, jerarquía de valores y política de seguridad.
  - L37–L39: separador y apertura `class Env`.
  - L40–L51: docblock y propiedad `$cargado` (idempotencia).
  - L52–L60: docblock y propiedad `$rutaUsada`.
  - L61–L75: docblock de `cargar()` (contrato "mejor esfuerzo") y firma con `?string $ruta`.
  - L76–L82: guarda de idempotencia: si ya se cargó, retorna; marca `$cargado` antes de leer.
  - L83–L87: ruta por defecto `dirname(__DIR__) . '/.env'` y guardado en `$rutaUsada`.
  - L88–L94: si el archivo no es legible, retorna sin romper.
  - L95–L100: `file($ruta, FILE_IGNORE_NEW_LINES)` para leer sin saltos.
  - L101–L105: si `file()` devuelve `false`, retorna.
  - L106–L111: `foreach` que delega cada línea en `procesarLinea()`.
  - L112–L123: docblock de `procesarLinea()` y `trim()` inicial.
  - L124–L132: saltea líneas vacías y comentarios (`$linea[0] === '#'`).
  - L133–L141: patrón regex `CLAVE=valor` con `export ` opcional.
  - L142–L152: si no hay match, `trigger_error(E_USER_WARNING)` y retorna.
  - L153–L155: extrae `$clave` y limpia el `$valor`.
  - L156–L165: jerarquía: si `getenv($clave) !== false`, `.env` no pisa el valor existente.
  - L166–L174: aplica `putenv()`, `$_ENV[$clave]` y `$_SERVER[$clave]`.
  - L175–L193: docblock de `limpiarValor()`, `trim()` y caso de valor vacío.
  - L194–L198: valor vacío devuelve `''`.
  - L199–L217: manejo de comillas simples/dobles y desescapado de `\n` y `\t`.
  - L218–L225: sin comillas, corta comentario inline con `preg_split('/\s+#/')`.
  - L226–L238: docblock y método `rutaUsada()`.
  - L239–L250: docblock y método `fueCargado()`, cierre de clase.
- **Se conecta con:** `bootstrap.php` y `../db.php` (ambos llaman a `cargar()`), `Config.php` (lecturas con `getenv()`).

---

## `core/Secret.php` (70 líneas)
- **Rol:** Resuelve el secreto de firma JWT: variable de entorno en producción, archivo persistente `storage/secret.key` o generación aleatoria en desarrollo.
- **Recorrido línea por línea:**
  - L1–L12: cabecera y orden de resolución del secreto.
  - L13–L14: línea en blanco y apertura `class Secret`.
  - L15–L19: docblock de `obtener()`.
  - L20–L28: firma de `obtener()`; si `JWT_SECRET` existe con ≥48 chars, lo devuelve.
  - L29–L39: en producción sin secreto: responde 500 en JSON y hace `exit`.
  - L40–L52: fallback de desarrollo: lee `storage/secret.key` si existe y cumple longitud.
  - L53–L65: generación con `bin2hex(random_bytes(48))`, crea carpeta y guarda con `LOCK_EX`.
  - L66–L70: devuelve el secreto recién generado y cierra clase.
- **Se conecta con:** `bootstrap.php` (lo usa al crear `JwtService`), `JwtService.php` (secreto de firma/verificación), `Config.php` (chequeo de producción).

---

## `core/JwtService.php` (97 líneas)
- **Rol:** Emite y verifica tokens JWT con firebase/php-jwt (HS256), aplicando claims estándar (`iss`, `iat`, `exp`, `sub`) y privados (`rol`, `email`, `nombre`).
- **Recorrido línea por línea:**
  - L1–L12: cabecera, módulo y claims usados.
  - L13–L17: `use` de `Firebase\JWT\JWT` y `Key`, y apertura `class JwtService`.
  - L18–L23: propiedades `$secret`, `$issuer` y `$ttl`.
  - L24–L29: docblock del constructor con parámetros.
  - L30–L39: constructor con defaults (`saludweb-api`, `3600`) que asigna las propiedades.
  - L40–L47: docblock de `generar()`.
  - L48–L53: calcula `$issuedAt` (iat) y `$expiresAt` (exp).
  - L54–L64: arma el `$payload` con claims estándar y privados.
  - L65–L74: firma con `JWT::encode(..., 'HS256')` y devuelve token + `expires_at`.
  - L75–L87: docblock y `verificar()`: `JWT::decode` con `Key` y cast a array.
  - L88–L97: docblock y método `ttl()`, cierre de clase.
- **Se conecta con:** `Secret.php` (secreto), `AuthMiddleware.php` (verificación), `AuthService.php` (emisión en login), `bootstrap.php`.

---

## `core/AuthMiddleware.php` (146 líneas)
- **Rol:** Separa autenticación (verificar token → 401) de autorización por rol (RBAC → 403). Extrae el token del header `Authorization: Bearer`.
- **Recorrido línea por línea:**
  - L1–L12: cabecera, distinción autenticación/autorización y formato del token.
  - L13–L18: apertura `class AuthMiddleware`, comentario y propiedad `$jwt`.
  - L19–L26: propiedad estática `$usuarioActual` y constructor que guarda el `JwtService`.
  - L27–L34: docblock de `verificarToken()`.
  - L35–L53: extrae token; si es `null`, `Logger::warn` y `Response::error(401)`.
  - L54–L62: `try` que verifica el token, guarda el payload y lo devuelve.
  - L63–L77: `catch` que loguea el motivo y responde 401 sin detalles.
  - L78–L85: docblock de `requireRol()` (RBAC posterior a verificarToken).
  - L86–L107: lee el rol, valida con `in_array` estricto y responde 403 logueando.
  - L108–L116: docblock y `usuarioActual()` estático.
  - L117–L120: docblock de `extraerToken()`.
  - L121–L135: lee `HTTP_AUTHORIZATION`/`REDIRECT_...` o `getallheaders()`.
  - L136–L146: `preg_match` de `Bearer`, `trim()` del token, cierre de clase.
- **Se conecta con:** `JwtService` (verificación), `Response` (401/403), `Logger` y `Peticion` (contexto), `routes.php` (rutas protegidas).

---

## `core/Router.php` (298 líneas)
- **Rol:** Enrutador HTTP: registra rutas por verbo, ejecuta middlewares, resuelve parámetros dinámicos `{id}` y despacha hacia controlador, closure o archivo.
- **Recorrido línea por línea:**
  - L1–L11: cabecera y responsabilidades del enrutador.
  - L12–L28: apertura `class Router` y propiedades `$routes`, `$middlewares`, `$notFoundHandler`, `$basePath`.
  - L29–L38: docblock y constructor que aplica `rtrim` a `$basePath`.
  - L39–L44: bloque de comentarios de los métodos de registro por verbo.
  - L45–L52: docblock y `get()` que delega en `addRoute`.
  - L53–L61: docblock y `post()`.
  - L62–L70: docblock y `put()`.
  - L71–L80: docblock y `patch()`.
  - L81–L88: docblock y `delete()`.
  - L89–L98: docblock y `middleware()` global.
  - L99–L107: docblock y `notFound()`.
  - L108–L129: docblock y `addRoute()`: normaliza método y guarda la ruta.
  - L130–L137: docblock de `dispatch()`.
  - L138–L143: `parse_url(..., PHP_URL_PATH)` para quedarse con el path.
  - L144–L151: recorta `$basePath` si la URI empieza con él.
  - L152–L159: quita `/index.php` final y normaliza `/`.
  - L160–L187: recorre rutas, filtra por método y ejecuta middlewares de la ruta.
  - L188–L218: ejecución del handler: closure o `Controlador@metodo`.
  - L219–L227: handler como archivo (`require_once`) y `return` al encontrar ruta.
  - L228–L238: ejecuta el handler 404 (callable o archivo).
  - L239–L246: 404 por defecto con `http_response_code(404)` y mensaje.
  - L247–L260: docblock de `matchRoute()`.
  - L261–L288: divide ruta/URI y compara parte por parte capturando `{param}`.
  - L289–L298: parte fija distinta devuelve `null`; devuelve `$params` y cierra clase.
- **Se conecta con:** `bootstrap.php` (crea `$router`), `routes.php` (registra rutas), controladores del proyecto.

---

## `core/CuerpoJson.php` (148 líneas)
- **Rol:** Lee y valida el cuerpo JSON de las peticiones, separando "no vino nada", "JSON roto", "JSON que no es objeto" y "objeto válido" con códigos 400 claros.
- **Recorrido línea por línea:**
  - L1–L41: cabecera que contrasta el patrón `?? []` con la solución del helper.
  - L42–L52: apertura `class CuerpoJson` y docblock/firma de `leer()`.
  - L53–L70: lee `php://input`; body vacío devuelve `[]` o lanza 400 según `$permitirVacio`.
  - L71–L82: comentarios sobre `json_decode` y `JSON_THROW_ON_ERROR`.
  - L83–L94: `try`/`catch JsonException` que traduce a `RuntimeException` 400.
  - L95–L104: si no es array, lanza 400 "debe ser un objeto JSON".
  - L105–L124: rechaza listas cuando hay elementos; devuelve `$datos` y cierra `leer()`.
  - L125–L137: docblock de `esLista()` y justificación por PHP 8.0.
  - L138–L148: usa `array_is_list` si existe, si no compara `array_keys` con `range`, cierre de clase.
- **Se conecta con:** controladores (lectura de body), `bootstrap.php` (require), `Validador.php` (validación posterior).

---

## `core/Validador.php` (255 líneas)
- **Rol:** Único lugar para validar y sanear entradas (DRY). Sanea primero (`strip_tags` + `trim`) y valida después, lanzando `InvalidArgumentException` con el código HTTP.
- **Recorrido línea por línea:**
  - L1–L40: cabecera: caso real de duplicación, responsabilidad única y orden de operaciones.
  - L41–L48: apertura `class Validador` y constantes `EMAIL_MAXIMO`, `PASSWORD_MINIMO`, `PASSWORD_MAXIMO`.
  - L49–L63: docblock de `email()`.
  - L64–L80: normaliza (trim + lowercase), valida vacío/formato/longitud y lanza 422.
  - L81–L96: docblock de `password()`.
  - L97–L107: valida rango 6–72 y lanza `InvalidArgumentException`.
  - L108–L118: docblock de `textoLimpio()`.
  - L119–L126: `strip_tags(trim((string)($valor ?? '')))`.
  - L127–L136: docblock de `obligatorio()`.
  - L137–L146: sanea y lanza si queda vacío.
  - L147–L157: docblock de `longitudMaxima()`.
  - L158–L167: compara `strlen` contra el máximo (bytes).
  - L168–L182: docblock de `textoObligatorio()`.
  - L183–L193: sanea y encadena `obligatorio` + `longitudMaxima`.
  - L194–L209: docblock de `textoOpcional()`.
  - L210–L221: `null` significa "no vino"; si viene, valida longitud.
  - L222–L235: docblock de `acortar()` (recorta sin error).
  - L236–L239: `mb_substr` sobre el texto limpio.
  - L240–L247: docblock de `acortarOpcional()`.
  - L248–L255: `null` si no viene, si no `acortar()`; cierre de clase.
- **Se conecta con:** servicios (reglas de negocio), controladores (traducción de excepciones), `tests/ValidadorTest.php`.

---

## `core/Response.php` (117 líneas)
- **Rol:** Respuestas JSON consistentes para éxitos y errores; loguea el error, oculta detalles en 5xx y agrega `requestId` de correlación.
- **Recorrido línea por línea:**
  - L1–L14: cabecera y estructura uniforme de éxito/error.
  - L15–L24: apertura `class Response` y docblock/firma de `ok()`.
  - L25–L34: `ok()` llama a `json()` con `ok: true`, mensaje y data.
  - L35–L43: docblock y firma de `error()`.
  - L44–L54: arma el arreglo base del error.
  - L55–L64: deduce el nivel (`5xx` ERROR / `4xx` WARN).
  - L65–L74: `Logger::log` con status, code, campos y petición.
  - L75–L91: en 5xx oculta el mensaje y agrega `requestId` + `code`.
  - L92–L101: en 4xx agrega `requestId` y envía la respuesta.
  - L102–L117: docblock y `json()`: setea código, `Content-Type`, serializa y `exit`.
- **Se conecta con:** controladores y servicios, `AuthMiddleware` (401/403), `Logger` y `Peticion`, `CorrelationId` (`requestId`).

---

## `core/RateLimiter.php` (220 líneas)
- **Rol:** Limita intentos por clave/IP para mitigar fuerza bruta y credential stuffing, persistiendo contadores en archivos JSON de `storage/rate/`.
- **Recorrido línea por línea:**
  - L1–L17: cabecera y funcionamiento (`registrar`, `permitir`, `limpiar`).
  - L18–L25: apertura `class RateLimiter` y constante `AVISO_CADA_INTENTOS`.
  - L26–L33: docblock y propiedad `$dir` y firma del constructor.
  - L34–L42: `rtrim` de la ruta y `mkdir` recursivo si falta.
  - L43–L52: docblock y firma de `permitir()`.
  - L53–L72: lee estado, comprueba bloqueo activo y deniega logueando WARN.
  - L73–L84: `array_filter` que descarta intentos fuera de la ventana.
  - L85–L104: si supera el máximo, fija `bloqueado_hasta`, persiste y deniega.
  - L105–L109: docblock/firma de `registrar()`.
  - L110–L127: agrega timestamp, guarda y avisa al alcanzar el umbral de avisos.
  - L128–L132: docblock/firma de `limpiar()`.
  - L133–L141: elimina el archivo de contadores con `@unlink`.
  - L142–L149: docblock y `ruta()`: nombre por `hash('sha256', ...)`.
  - L150–L166: docblock y `huella()`: primeros 12 chars del hash.
  - L167–L189: docblock y `contexto()` para el log (huella, límites, intento, petición).
  - L190–L210: docblock y `leer()`: decodifica JSON o estado inicial.
  - L211–L220: docblock y `guardar()` con `LOCK_EX`, cierre de clase.
- **Se conecta con:** `bootstrap.php` (instancia), rutas de login (`AuthController`), `Logger` y `Peticion`.

---

## `core/CorrelationId.php` (171 líneas)
- **Rol:** Decide el identificador de correlación de la petición: adopta el del cliente si es válido o genera uno propio, con formato y alfabeto restringidos.
- **Recorrido línea por línea:**
  - L1–L31: cabecera: responsabilidad única, formato y por qué el alfabeto es lista blanca.
  - L32–L38: apertura `class CorrelationId` y constante `HEADER` (`X-Correlation-Id`).
  - L39–L45: comentario y constante `PREFIJO` (`cid_`).
  - L46–L50: comentario y constante `LONGITUD_MAXIMA`.
  - L51–L55: comentario y constante `PATRON`.
  - L56–L63: comentario y propiedad estática `$actual`.
  - L64–L74: docblock de `iniciar()`.
  - L75–L97: lee `HTTP_X_CORRELATION_ID`/`REDIRECT_...` y asigna válido o generado.
  - L98–L114: docblock y `actual()` con `??= generar()`.
  - L115–L141: docblock y `esValido()`: chequea string, longitud y patrón.
  - L142–L156: docblock y `generar()`: `bin2hex(random_bytes(8))` con prefijo.
  - L157–L171: docblock y `olvidar()` (para tests), cierre de clase.
- **Se conecta con:** `Peticion` (inicia la correlación), `Logger` (`correlationId`), `Response` (`requestId`), `bootstrap.php`, `tests/CorrelationIdTest.php`.

---

## `core/Logger.php` (504 líneas)
- **Rol:** Logs estructurados JSON Lines con niveles, contexto y enmascarado de secretos y PII; nunca rompe la API ante fallos de escritura.
- **Recorrido línea por línea:**
  - L1–L40: cabecera: niveles, estructura, qué no se loguea y que el log no rompe la API.
  - L41–L51: apertura `class Logger` y constantes `DEBUG`, `INFO`, `WARN`, `ERROR`.
  - L52–L60: `PESOS` para comparar severidad sin comparar strings.
  - L61–L63: `NIVELES` válidos.
  - L64–L73: `CLAVES_REDACTADAS` (secretos y texto clínico).
  - L74–L81: `CLAVES_PII` (enmascarado parcial).
  - L82–L88: `ETIQUETA_REDACTADO` y `LONGITUD_MAXIMA_VALOR`.
  - L89–L102: propiedades `$nivelMinimo`, `$directorio`, `$servicio`, `$avisoFalloEscritura`.
  - L103–L115: docblock y firma de `configurar()`.
  - L116–L149: resuelve directorio, nivel (por defecto o `LOG_LEVEL`) y servicio (`LOG_SERVICE`).
  - L150–L163: docblock de `resolverDirectorio()`.
  - L164–L187: prioriza parámetro, `LOG_DIR` o default; ancla rutas relativas a la raíz.
  - L188–L201: docblock de `esRutaAbsoluta()`.
  - L202–L215: detecta unidad Windows, rutas de red y raíz Unix.
  - L216–L221: docblock y `nivelMinimo()`.
  - L222–L230: docblock y `directorio()` con configuración perezosa.
  - L231–L242: docblock y `archivo()`: un archivo por día.
  - L243–L259: docblock y `habilitado()`: compara pesos y descarta niveles desconocidos.
  - L260–L283: atajos `debug()`, `info()`, `warn()`, `error()`.
  - L284–L301: docblock y `log()`: filtra por nivel y delega en `escribir()`.
  - L302–L330: docblock y `registro()` (función pura) que arma timestamp, level, service, correlationId y context.
  - L331–L369: docblock y `linea()`: serializa con flags, con fallback si `json_encode` falla.
  - L370–L425: docblock y `enmascarar()` recursivo: redacta/PII/objetos/strings acotados.
  - L426–L446: docblock y `enmascararParcial()`: conserva 1 carácter o redacta.
  - L447–L478: docblock y `escribir()`: crea carpeta, `FILE_APPEND | LOCK_EX` y aviso único por stderr.
  - L479–L503: docblock y `ultimasLineas()` con `array_slice` negativo.
  - L504: cierre de la clase.
- **Se conecta con:** `bootstrap.php` (`configurar()`), `CorrelationId` (`correlationId`), `Peticion`, `Response`, `RateLimiter`, `Config`, `tests/LoggerTest.php`.

---

## `core/Peticion.php` (329 líneas)
- **Rol:** Ciclo de vida de la petición: abre la correlación, devuelve el encabezado, captura errores/excepciones/fatales y escribe una línea de resumen con duración y estado.
- **Recorrido línea por línea:**
  - L1–L30: cabecera: las cuatro responsabilidades, nivel del resumen y nota sobre SSE.
  - L31–L41: apertura `class Peticion`, `$inicioMs` y `$iniciada`.
  - L42–L47: docblock y firma de `iniciar()`.
  - L48–L104: inicia guarda, mide `hrtime`, arranca correlación, omite handlers en CLI, emite header y registra handlers + log de apertura.
  - L105–L110: docblock y `metodo()`.
  - L111–L126: docblock y `ruta()` sin query string.
  - L127–L142: docblock y `ip()` del cliente.
  - L143–L157: docblock y `atributos()` (metodo, ruta, ip).
  - L158–L195: docblock y `avisarPhp()`: ignora `@`, elige WARN/ERROR y loguea sin ruta completa.
  - L196–L237: docblock y `registrarExcepcion()`: loguea ERROR y responde 500 genérico con `requestId`.
  - L238–L288: docblock y `finalizar()`: duración, estado, detecta fatales, elige nivel y loguea el resumen.
  - L289–L306: docblock y `esFatal()` con la lista de constantes E_*.
  - L307–L328: docblock y `nombreDelTipo()` que busca el nombre de la constante.
  - L329: cierre de la clase.
- **Se conecta con:** `bootstrap.php` (`iniciar()`), `CorrelationId`, `Logger`, `Response`, `AuthMiddleware` y `RateLimiter` (contexto `Peticion::atributos()`).
---
