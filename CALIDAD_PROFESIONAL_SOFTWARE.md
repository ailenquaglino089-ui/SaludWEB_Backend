# Calidad Profesional del Software — SaludWEB

Documento de referencia de la aplicación de calidad profesional a los tres repositorios del
proyecto: **Backend** (PHP), **Web** (React + Vite) y **Mobile** (React Native + Expo).

Este documento no es una lista de tareas pendientes: describe qué se auditó, qué se cambió,
por qué se cambió así, cómo se verifica y qué quedó deliberadamente fuera.

---

## 1. Objetivo

El objetivo era transformar el proyecto en algo mantenible y diagnosticable sin reescribirlo
desde cero. Eso se apoyó en cuatro decisiones de diseño, y ninguna dependencia nueva:

1. **Que se entienda solo.** Código comentado en el idioma del equipo, con el porqué de cada
   decisión y no solo el qué hace.
2. **Que no haya lógica repetida.** Las validaciones que estaban copiadas en varios servicios
   se movieron a un solo lugar.
3. **Que los errores se puedan encontrar.** Logs estructurados con identificador de
   correlación que une el error que ve el usuario con la línea de log del servidor.
4. **Que el cambio sea verificable.** Suites de pruebas automáticas que corren sin base de
   datos, sin servidor y sin datos sembrados.

---

## 2. Auditoría inicial

| Repositorio | Archivos de código | Hallazgos principales |
|---|---|---|
| SaludWEB_Backend | 75 archivos PHP | Sin logging estructurado, sin identificador de correlación, sin suite unitaria, sin documentación de configuración de logs. Validaciones de email, contraseña y longitudes repetidas en cinco servicios. `CitaService.php` (1142 líneas), `AuthService.php` (918) y `routes.php` (899) concentran responsabilidades incompatibles. |
| SaludWEB_Web | 65 archivos JS/JSX | Errores de red manejados con lógica ad hoc en cada pantalla. Sin forma de correlacionar un error del navegador con un log del servidor. Sin pruebas unitarias del código propio (solo verificación E2E con Playwright). |
| SaludWEB_Mobile | 26 archivos JS | Cliente `fetch` propio sin registro de eventos. Mensajes de error traducidos correctamente, pero sin rastro técnico. Sin pruebas. |

**Conclusión de la auditoría**: el mayor riesgo no era el código incorrecto, sino la
incapacidad de diagnosticar. Un error en producción se reportaba como "no me carga" y no
había forma de encontrar la petición que lo causó.

---

## 3. Qué se implementó

### 3.1 Backend — `SaludWEB_Backend`

#### `core/CorrelationId.php` (nuevo)

Genera y valida el identificador de correlación.

- Si la petición trae `X-Correlation-Id` y cumple el formato (`^[A-Za-z0-9_-]{1,64}$`), se adopta.
- Si no viene o es inválido, el servidor genera uno con el prefijo `cid_`.
- El formato se valida porque un salto de línea en ese encabezado permitiría **inyectar una
  línea falsa en el archivo de log**. Esa es la razón de la expresión regular y hay una prueba
  dedicada al caso.

#### `core/Logger.php` (nuevo)

Registro estructurado en JSON Lines: una línea por evento, con `ts`, `nivel`,
`correlation_id`, `mensaje` y `contexto`.

- Niveles `DEBUG`, `INFO`, `WARN`, `ERROR` comparados por peso, no como texto: comparar
  `"WARN" > "INFO"` con `>` es una comparación de cadenas que no significa nada.
- Un nivel desconocido **no** se escribe. Perder un mensaje es aceptable; registrar una
  severidad equivocada, no.
- Un `LOG_LEVEL` mal escrito cae al nivel por defecto en vez de dejar al sistema sin logs.
- **Redacción obligatoria** de secretos: contraseñas, tokens, cookies y texto clínico se
  reemplazan por `[oculto]`. Los datos personales (email, DNI, teléfono, nombre) se enmascaran
  parcialmente, dejando cuatro caracteres para poder correlacionar sin exponer el dato.
- La redacción es **recursiva**, porque en el uso real el token viaja en
  `config.headers.authorization` de un error de axios: una redacción que solo mirara el primer
  nivel lo dejaría pasar limpio.
- Un valor de más de 500 caracteres se trunca **con aviso**. Un log truncado en silencio hace
  creer que el dato terminaba ahí.
- Un archivo por día (`app-YYYY-MM-DD.log`). `storage/` está en `.gitignore`, así que los logs
  nunca se suben al repositorio.
- Si el destino de escritura falla, se avisa una sola vez por stderr y la aplicación sigue.

#### `core/Peticion.php` (nuevo)

Un único punto de observación del ciclo HTTP:

- Instala el manejador global de excepciones y el de errores fatales, que registran la
  excepción con su tipo y mensaje (nunca la traza completa: en el log no sirve y filtra rutas
  internas).
- Registra al final de cada petición método, ruta, estado y duración. Se escriben los 4xx y 5xx
  en `WARN`, y además del evento específico ya registrado por quien detectó el fallo.

#### `core/Validador.php` (nuevo)

Extracción DRY. Antes, la validación de email y contraseña estaba copiada en `AuthService`,
`MedicoService`, `PacienteService`, `PrescripcionService` y `CitaService`, con cinco versiones
distintas del mismo mensaje. Ahora hay un solo lugar:

| Método | Qué hace |
|---|---|
| `email()` | Limpia espacios, normaliza a minúsculas y valida el formato. |
| `password()` | Valida el rango de 6 a 72 caracteres (borde superior de bcrypt). |
| `textoLimpio()` | Quita HTML y recorta; es el saneo de entrada. |
| `textoOpcional()` | Igual que el anterior, pero devuelve `null` si no viene. |
| `acortar()` / `acortarOpcional()` | Acotan texto a un máximo sin lanzar excepción. |

El mensaje y el código HTTP son parámetros, no constantes. Eso permite que el login responda
`401` con un mensaje genérico ("Email o contraseña inválidos") mientras el registro responde
`422` con el detalle del campo. El módulo exige no filtrar información: el login no debe
revelar si el email existe.

#### Cableado de la observabilidad

| Archivo | Cambio |
|---|---|
| `core/bootstrap.php` | Configura el logger y arranca el identificador de correlación una sola vez. |
| `core/Response.php` | Registra cada error y agrega `requestId` al cuerpo de error, para que el frontend pueda mostrarlo. |
| `core/AuthMiddleware.php` | Registra token ausente, token inválido y rol insuficiente. **Nunca** el token. |
| `core/RateLimiter.php` | Registra bloqueos con el hash de la clave, nunca con el dato crudo (una IP es dato personal). |
| `routes.php` | El 404 pasa a ser una respuesta JSON con `requestId` en lugar de texto plano. |
| `services/AuthService.php` | Registro, login y SSO con eventos de auditoría: quién, cuándo, desde dónde, con qué resultado. |

#### `tests/` (nuevo)

Arnés de pruebas propio, sin dependencias nuevas. Corre sin base de datos, sin Apache y sin
datos sembrados.

```bash
php tests/run.php              # toda la suite
php tests/run.php Logger       # solo las clases cuyo nombre contenga "Logger"
php tests/run.php --lista      # ver las clases disponibles
```

`tests/TestCase.php` implementa las aserciones y una función `ejecutar()` que separa las tres
fases de una prueba (Arrange, Act, Assert) de forma explícita.

### 3.2 Web — `SaludWEB_Web`

#### `src/utils/correlationId.js` (nuevo)

Genera el identificador del navegador con el mismo formato que el backend, lo guarda en
`localStorage` y lo reescribe en cada petición.

Se persiste por dos razones concretas: una recarga de página no corta la correlación, y dos
pestañas del mismo navegador no inventan identificadores distintos para una sola acción.

#### `src/utils/logger.js` (nuevo)

Mismo contrato que el logger del backend, adaptado al navegador: escribe en la consola usando
el método que corresponde a cada nivel (Chrome permite filtrar por "error" y "warning", y ese
filtro solo funciona si el mensaje se escribió con el método correcto).

El destino de escritura se pasa por parámetro en `configurar()`. Eso no es un detalle: para
probar que un token **no** se escribe hace falta poder capturar lo que sí se escribe, y no
atamos la lógica a `console`.

#### `src/api/client.js` (modificado)

- Envía `X-Correlation-Id` en cada petición.
- Registra los fallos antes de reaccionar a ellos, para que quede la evidencia aunque la
  redirección al login se lleve la página entera.
- El contexto del error incluye método, ruta, estado y motivo. El token y los datos
  personales los redacta el logger, así que se puede pasar el error completo sin filtrar nada.
- Un 401 registra el cierre de sesión antes de borrar el token local.

```bash
npm test                      # 32 pruebas con el runner nativo de Node
npm run test:watch            # en modo vigilancia
npm run build                 # compilación de producción
```

### 3.3 Mobile — `SaludWEB_Mobile`

#### `src/utils/correlationId.js` (nuevo)

Igual que en la Web, con una diferencia deliberada: **no persiste** el identificador en el
dispositivo. Al abrir la app de nuevo hay una sesión nueva, y reutilizar el identificador de la
sesión anterior mezclaría en el log dos sesiones sin relación entre sí.

#### `src/utils/logger.js` (nuevo)

Mismo contrato que el de la Web, con el criterio de Metro: cada nivel se escribe con el método
de consola que le corresponde para que el filtrado funcione.

#### `src/api/client.js` (modificado)

- Envía `X-Correlation-Id` en cada petición.
- Registra el rechazo de la API con método, ruta, estado y motivo.
- Registra el 401 **antes** de cerrar sesión: si no, el 401 más difícil de diagnosticar
  (el del primer fallo después de una caída del servidor) se quedaría sin rastro.
- No registra el cuerpo completo de la respuesta de error: puede traer datos de otros
  pacientes y el log no es el lugar para eso.

```bash
npm test                      # 27 pruebas
```

---

## 4. Reglas de seguridad que quedan fijadas

1. **Ningún secreto se escribe en un log.** Contraseñas, tokens, cookies y texto clínico se
   redactan antes de escribir, no después. La redacción vive en el logger para que ningún
   módulo tenga que acordarse de hacerla.
2. **Ningún identificador de cabecera se acepta sin validar.** Un salto de línea en el
   `X-Correlation-Id` es un vector de inyección de log.
3. **Los identificadores de persona se enmascaran.** Se conservan cuatro caracteres para poder
   correlacionar, nunca el dato completo.
4. **Los errores de autenticación no dicen qué falló.** El login responde siempre lo mismo, haya
   o no usuario.
5. **Un log no puede ser la causa del problema que ayuda a diagnosticar.** Si el destino falla,
   la aplicación sigue.

---

## 5. Verificación

Todo lo anterior está cubierto por pruebas que se pueden correr en cualquier máquina:

| Suite | Comando | Cobertura |
|---|---|---|
| Backend | `php SaludWEB_Backend/tests/run.php` | 49 pruebas sobre 5 clases |
| Web | `cd SaludWEB_Web && npm test` | 32 pruebas |
| Mobile | `cd SaludWEB_Mobile && npm test` | 27 pruebas |

Total: **108 pruebas**, todas en verde, sin base de datos y sin servidor.

Los casos de prueba siguen la misma estructura en los tres repositorios: camino feliz, bordes
y fallas. Los bordes son los que importan: límites exactos, valores vacíos, archivos
corruptos, identificadores en el límite de longitud. Las fallas se comprueban con
`afirmarLanza`, que verifica no solo que se lanza la excepción sino también el código HTTP y el
mensaje, porque un 422 donde debía ir un 401 es un fallo de seguridad.

---

## 6. Qué NO se hizo, y por qué

Esto es deliberado y conviene dejarlo escrito:

1. **No se reescribieron los servicios grandes.** `CitaService.php` tiene 1142 líneas y mezcla
   agenda, disponibilidad, estados y notificaciones. Dividirlo es un trabajo con pruebas de
   caracterización primero; hacerlo a la vez que se agrega logging sería cambiar dos cosas
   a la vez, sin poder saber cuál rompió qué. La extracción segura (el validador) ya dejó el
   camino preparado.

2. **No se agregó un framework de pruebas.** El backend usa un arnés propio de 300 líneas y el
   frontend usa el runner nativo de Node. Ambos con dependencia cero: el proyecto es académico y
   agregar Jest o PHPUnit suma dependencias que hay que mantener sin ganar nada para pruebas de
   este tamaño.

3. **No se agregó integración continua.** El runner devuelve código de salida 0 o 1, que es lo
   que un pipeline necesita, pero la configuración del pipeline queda para cuando el proyecto
   tenga un servidor donde correrlo.

4. **No se rotan ni se comprimen los logs.** Se escribe un archivo por día, pero la rotación
   (y el envío a un servicio externo) corresponde a la infraestructura del servidor, no a la
   aplicación.

5. **Los datos personales se enmascaran, no se anonimizan.** Es una decisión consciente: el log
   sirve para diagnosticar y sin un identificador reconocible no se puede. Para datos clínicos
   sí se ocultan por completo.

---

## 7. Cómo extender esto

- **Un nuevo servicio con validación:** usar `Validador`, no reimplementar el control. Si falta
  una validación, se agrega en `Validador` con su prueba, y todos los servicios la heredan.
- **Un nuevo evento de log:** usar `Logger::info/warn/error` con el contexto ya limpio. Si el
  dato es sensible, la clave se agrega a las listas de redacción **y** se escribe la prueba que
  verifica que no se escribe.
- **Un nuevo error de negocio:** usar `Response::error()`, que ya registra y ya agrega el
  `requestId`. Un `echo` con `http_response_code` rompe la correlación.
- **Una prueba nueva:** crear `tests/NombreTest.php` con el runner por descubrimiento
  automático. No hay que tocar ningún archivo de configuración.
