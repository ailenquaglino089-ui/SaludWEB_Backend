# Revisar y probar el proyecto: de checklist a evidencia — SaludWEB

Documento de aplicación de la guía **"Revisar y probar el proyecto: de checklist a evidencia"**
a los tres repositorios del sistema SaludWEB:

| Repo | Qué aporta al plan de pruebas |
|---|---|
| **SaludWEB_Backend** | API REST, reglas de negocio, seguridad (JWT/roles), suites `probar_*.php` y `tests/run.php`. |
| **SaludWEB_Web** | SPA React; pruebas unitarias (`npm test`), auditorías de CSS/contraste y E2E de PWA (Playwright). |
| **SaludWEB_Mobile** | App Expo; pruebas unitarias (`npm test`) del cliente y del registro de eventos. |

Este documento **cita cada paso de la guía y lo comenta** con lo que se hizo realmente en el
proyecto: la salida de las suites, los endpoints relevados y los hallazgos. No es teoría: cada
número que aparece sale de una corrida hecha el **2026-10-14** sobre la base SQLite local.

Los cinco entregables de evidencia viven en la carpeta **`QA/`**:

```
QA/01_checklist_de_calidad.md      # el checklist de calidad
QA/02_matriz_de_cobertura.md       # requisito -> endpoint -> caso -> resultado
QA/03_reporte_de_incidentes.md     # fichas de los defectos encontrados
QA/04_registro_de_resultados.md    # corridas, comando, salida y código de salida
QA/05_confirmacion_de_qa.md        # cierre: alcance, ambiente, veredicto y firma
```

---

## Paso 1 — Objetivo: distinguir verificar de validar

> **La guía dice:** una cosa es *verificación* ("¿construimos bien el sistema?") y otra es
> *validación* ("¿construimos el sistema correcto?"). Hay que dejar claro cuál se hace en cada
> momento.

**Comentario SaludWEB.** En este proyecto se ejercitan las dos, y conviene no mezclarlas:

- **Verificación (construimos bien):** las suites automáticas comprueban que el código hace lo
  que dice. Ejemplo: `tests/run.php` (50 pruebas), `probar_roles.php` (reglas de roles y
  permisos) y `probar_vinculacion.php` (15 casos de vinculación de fichas).
- **Validación (construimos lo correcto):** los **criterios de éxito** del `PROJECT_BRIEF.md`
  (sección 9) se mapean a endpoints y a casos en la matriz de cobertura (`QA/02`). Así se ve
  que cada objetivo del brief tiene al menos un caso que lo respalda.

La guía pide que esa trazabilidad no quede en la cabeza de quien prueba: por eso existe
`QA/02_matriz_de_cobertura.md`.

---

## Paso 2 — Crear el checklist de calidad

> **La guía dice:** antes de probar hay que acordar *qué* se considera "de calidad". El
> checklist cubre requisitos funcionales, reglas de negocio, seguridad y roles, manejo de
> errores/UX, rendimiento y documentación.

**Comentario SaludWEB.** El checklist se armó a partir de los módulos reales del sistema
(autenticación, CRUD de pacientes/médicos/prescripciones, turnera, gestión de roles, tiempo
real) y de las reglas de negocio del brief. Está en **`QA/01_checklist_de_calidad.md`**, con
una columna "cómo se verifica" que apunta a la suite o al verificador concreto. No se inventó
ninguna categoría: cada fila tiene evidencia asociada o queda marcada explícitamente como no
cubierta.

---

## Paso 3 — Revisar la estructura del código (revisión de caja blanca)

> **La guía dice:** leer el código "por dentro": organización de carpetas, estándares de
> nombres, responsabilidades por módulo, duplicación, comentarios y separación de capas.

**Comentario SaludWEB.** La revisión de caja blanca se apoya en lo ya auditado y documentado en
`CALIDAD_PROFESIONAL_SOFTWARE.md`, y se completó al relevar las rutas:

- **Capas separadas:** `routes.php` declara rutas finas; `controllers/` traduce HTTP a
  servicio; `services/` tiene la regla de negocio; `persistence/` es el patrón Repository y
  aísla SQL. La pila es Controller → Service → Repository, sin saltos.
- **DRY:** las validaciones de email, contraseña y longitudes estaban repetidas en cinco
  servicios y hoy viven en `core/Validador.php` (una sola versión de cada mensaje).
- **Infraestructura transversal:** `core/CorrelationId.php`, `core/Logger.php`,
  `core/Peticion.php`, `core/Response.php` y `core/AuthMiddleware.php` cubren observabilidad,
  errores y autenticación de forma central.
- **Deuda consciente:** `CitaService.php` (~1142 líneas) concentra agenda, disponibilidad,
  estados y notificaciones. Se decidió **no** dividirlo en este ciclo (está justificado en
  `CALIDAD_PROFESIONAL_SOFTWARE.md`, sección 6) para no cambiar el comportamiento y la
  estructura a la vez.
- **Estándares:** comentarios en español que explican *por qué* de cada decisión (no solo el
  qué), consistente en PHP, React y Expo.

Hallazgo de esta revisión: dos endpoints del padrón de pacientes quedaron **públicos** (ver
`INC-001`), algo que no se veía en la auditoría de calidad anterior porque se hizo sobre
archivos, no sobre la tabla de rutas.

---

## Paso 4 — Documentar los endpoints

> **La guía dice:** cada endpoint debe quedar documentado con método, ruta, qué requiere
> (autenticación/rol), qué devuelve y qué errores puede dar. Es la base de la trazabilidad.

**Comentario SaludWEB.** Los ~60 endpoints se relevaron desde `routes.php`. La clasificación
real (confirmada por HTTP) es:

- **Públicas:** `health`, catálogos de obras sociales y médicos, `auth/registro|login|sso`,
  especialidades, `disponibilidades`, `citas/disponibilidad`, y las acciones por token de
  notificaciones.
- **Protegidas (JWT):** `auth/me|vincular|logout|cambiar-contrasena`, prescripciones (lectura),
  `citas/agenda`, `citas` (CRUD de reserva), `disponibilidades` (escritura), `notificaciones`,
  `estadisticas` y los canales `eventos` / `eventos/estado`.
- **Solo admin:** ABM de médicos, borrados de pacientes/prescripciones/citas, ABM de
  especialidades, `notificaciones/procesar` y todo el módulo `usuarios`.
- **Solo médico:** crear/editar prescripciones (`POST`, `PUT/PATCH`).

La matriz completa (método · ruta · acceso · caso · resultado esperado) está en
**`QA/02_matriz_de_cobertura.md`**. En el relevamiento se detectó el hallazgo `INC-001`
(pacientes públicos) y se confirmó que `GET /api/citas/agenda`, `/api/prescripciones`,
`/api/usuarios` y `/api/eventos` sí responden `401` sin token.

---

## Paso 5 — Preparar el ambiente

> **La guía dice:** las pruebas no se corren contra producción. Hay que aislar el entorno,
> declarar versiones y contar con datos de prueba.

**Comentario SaludWEB.** El ambiente es **nativo** (sin XAMPP, sin MySQL):

| Elemento | Valor |
|---|---|
| Motor de datos | SQLite (`storage/saludweb.sqlite`), base local de desarrollo |
| PHP | 8.2 (servidor embebido `php -S 127.0.0.1:8000 index.php`) |
| Node | 24 (runner de pruebas nativo, sin framework extra) |
| Config | `.env` (SQLite + `JWT_SECRET`); MySQL quedó documentado como LEGACY y **apagado a propósito** |

Para las suites HTTP que exigen credenciales de rol conocido se siguió lo que pide la guía:
**crear cuentas de prueba en el entorno**, no tocar la base a mano. Se registran por la API
(`POST /api/auth/registro`) al empezar y **se borran al terminar**, de modo que la base queda
exactamente como estaba (usuarios: 10, citas: 1, pacientes: 223 antes y después). Las suites
`probar_roles_http.php` y `probar_turnera.php` ya crean y limpian sus propios datos
descartables.

---

## Paso 6 — Colección de casos y matriz de cobertura

> **La guía dice:** diseñar casos **positivos**, **negativos** y **de borde**, y volcarlos a una
> matriz de cobertura que relacione requisito → caso → resultado.

**Comentario SaludWEB.** Los casos ya existen repartidos en las suites del proyecto; la guía los
ordena. Ejemplos reales:

- **Positivos:** login válido, reservar un turno libre, cambiar un rol a un valor permitido,
  vincular con el DNI correcto.
- **Negativos:** login con contraseña incorrecta (401), reservar sin ficha vinculada (403),
  cambiar el rol a un valor inexistente (404) o inválido (422), degradar al último admin (409).
- **De borde:** contraseña en el límite de 6 y 72 caracteres, JSON mal formado (400, no 500),
  `por_pagina` enorme (se acota), turno cuyo estado no permite la transición (422).

La consolidación está en **`QA/02_matriz_de_cobertura.md`**; el detalle fino, dentro de cada
archivo `probar_*.php` y de los `*.test.js` de Web y Mobile.

---

## Paso 7 — Probar login, CRUD y rutas protegidas

> **La guía dice:** probar en concreto el inicio de sesión, el alta/baja/modificación de
> entidades y el comportamiento de las rutas protegidas (401 sin token, 403 sin rol).

**Comentario SaludWEB.** Evidencia directamente ejecutada:

- **Login y rutas protegidas:** `probar_roles_http.php` → **todas las comprobaciones pasaron**.
  Cubre `401` sin token, `403` con rol insuficiente, `200` con admin, `422` con filtros
  inválidos, `404`/`409`/`400` en los bordes de PATCH.
- **CRUD:** la turnera (`probar_turnera.php`) ejercita alta de cuenta, vinculación de ficha,
  reserva, reserva repetida (409), cambio de estado permitido y no permitido (403/422).
- **Reglas de negocio:** `probar_roles.php` (último admin, autodegradación) y
  `probar_vinculacion.php` (15 casos de identidad) → **todos OK**.
- **Prescripciones (solo médico):** cubiertas por su suite y por la clasificación de rutas de
  `routes.php`, verificada por HTTP.

---

## Paso 8 — Registrar los errores encontrados (incidentes)

> **La guía dice:** cada defecto se registra con una ficha: id, severidad, pasos para
> reproducir, resultado esperado vs obtenido y estado.

**Comentario SaludWEB.** Las fichas están en **`QA/03_reporte_de_incidentes.md`**. En esta
corrida:

| Id | Severidad | Resumen | Estado |
|---|---|---|---|
| INC-001 | Alta | `GET /api/pacientes` y `/api/pacientes/{id}` eran públicas y exponían DNI + nombre de 223 pacientes sin token. | **Resuelto** (token obligatorio; `probar_seguridad`) |
| INC-002 | Baja (herramienta) | `probar_turnera.php` daba un falso negativo después del mediodía. | **Corregido y re-probado** |
| INC-003 | Baja (documentación) | `CALIDAD_PROFESIONAL_SOFTWARE.md` decía 49 pruebas de backend; son 50. | **Corregido** |
| INC-004 | Alta (aceptado) | El registro público permitía crear `admin` (`tipo_usuario` en el body). | **Resuelto** (solo `paciente`/`medico`; `probar_seguridad`) |

---

## Paso 9 — Regresión y control

> **La guía dice:** después de cada corrección hay que re-ejecutar lo que ya pasaba
> (regresión), y mantener control de versiones de código y de evidencia.

**Comentario SaludWEB.** Las suites son **idempotentes**: crean sus datos y los borran, así que
se pueden correr en cualquier orden y repetidamente (requisito para regresión). El control de
versiones es Git: el trabajo vive en la rama `dev` y se integra a `main` por Pull Request. La
evidencia (tabla de corridas) queda versionada junto al código en `QA/04_registro_de_resultados.md`,
con el comando exacto para reproducir cada resultado.

---

## Paso 10 — Corregir y volver a probar

> **La guía dice:** no alcanza con detectar: el defecto se corrige y **se vuelve a probar** para
> confirmar que no rompió otra cosa.

**Comentario SaludWEB.** Se aplicó en esta corrida:

- **INC-002.** Causa: el script elegía "hoy" como día con agenda sin descartar los turnos ya
  pasados; a las 14:02, con bloques de 08:00 a 12:00, la agenda aparecía "llena" y salteaba los
  casos de reserva y estados. Corrección: si el día candidato es hoy y el bloque ya terminó, se
  salta al siguiente día con turnos futuros. Re-prueba: **14 OK / 1 falla → 21 OK / 0 fallas**,
  y esta vez **sí** se ejecutaron reserva y estados.
- **INC-003.** Se actualizó el conteo a 50 pruebas (total del proyecto: 109, no 108).

La re-ejecución no rompió nada: las demás suites se volvieron a correr después del cambio y
siguen verdes (ver `QA/04`).

---

## Paso 11 — Entregable: evidencia de pruebas

> **La guía dice:** el cierre se entrega con evidencia: matriz de cobertura, reporte de
> incidentes, registro de resultados con aprobación y confirmación de aseguramiento de calidad.

**Comentario SaludWEB.** Los cinco entregables están en `QA/`:

1. **`QA/01_checklist_de_calidad.md`** — checklist por módulo, con cómo se verifica.
2. **`QA/02_matriz_de_cobertura.md`** — requisito → endpoint → caso → resultado.
3. **`QA/03_reporte_de_incidentes.md`** — fichas INC-001 a INC-004.
4. **`QA/04_registro_de_resultados.md`** — cada corrida con comando, resultado y código de salida.
5. **`QA/05_confirmacion_de_qa.md`** — alcance, ambiente, veredicto y aprobación.

Resumen de la evidencia (2026-10-14):

| Suite | Comando | Resultado |
|---|---|---|
| Backend · unitarias | `php tests/run.php` | **50/50** (5 clases) |
| Backend · roles (reglas) | `php probar_roles.php` | **todo OK** |
| Backend · vinculación | `php probar_vinculacion.php` | **15/15** |
| Backend · roles por HTTP | `php probar_roles_http.php` | **todo OK** (401/403/200/422/404/409/400) |
| Backend · turnera | `php probar_turnera.php` | **21/21** (tras INC-002) |
| Backend · tiempo real (piezas) | `php probar_tiempo_real.php` | **15/15** |
| Web · unitarias | `npm test` | **32/32** |
| Web · variables CSS | `php verificar_variables.php` | **0 rotas** |
| Web · contraste | `php ver_contraste.php` | **12 auditados, 0 problemas** |
| Web · PWA (Playwright) | `node verificar_pwa.mjs` | **12/12** |
| Mobile · unitarias | `npm test` | **27/27** |

---

## Paso 12 — Limitaciones conocidas (lo que la evidencia no cubre)

Ser explícito sobre lo que **no** se pudo correr es parte del trabajo:

- **SSE de punta a punta y "prueba de los dos navegadores".** Requieren un servidor con varios
  workers; el servidor embebido (`php -S`) es **mono-hilo** en Windows y no puede atender una
  conexión SSE mientras responde el resto de las peticiones. Por eso `probar_tiempo_real.php`
  corre sus 15 verificaciones de piezas y **omite la medición de latencia** por consola, y
  `verificar_tiempo_real.mjs` no se ejecutó. Con Apache/php-fpm (varios workers) estos dos
  checks se ejecutan completos.
- **Cuentas semilla.** La base migrada conserva los usuarios, pero no sus contraseñas en claro;
  el E2E de tiempo real, que usa `paciente@prueba.com` / `admin@salud.com`, necesita definir
  `PACIENTE_PASSWORD` / `ADMIN_PASSWORD` para esas cuentas.
- **`INC-001` e `INC-004`** quedaron **resueltos** el 2026-10-14 y cubiertos por la regresión
  `php probar_seguridad.php`. El detalle está en `QA/03_reporte_de_incidentes.md`.

---

*Documento de carácter académico para Programación IV — SaludWEB.*
