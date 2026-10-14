# Matriz de cobertura — SaludWEB

Relaciona cada **requisito / regla de negocio** con los **endpoints** que lo implementan, los
**casos** que lo prueban y el **resultado** obtenido. Los endpoints se relevaron de `routes.php`
y su acceso se confirmó por HTTP el 2026-10-14.

Leyenda de acceso: **P** = pública · **JWT** = requiere token (cualquier rol) · **ADMIN** = solo
administrador · **MED** = solo médico · **TOKEN** = token de notificación (sin sesión).

---

## A. Endpoints (método · ruta · acceso)

| # | Método | Ruta | Acceso | Caso asociado |
|---|---|---|---|---|
| 1 | GET | `/api/health` | P | Salud de la API (smoke) |
| 2 | GET | `/api/obras-sociales` | P | Catálogo para formularios |
| 3 | GET | `/api/medicos` | P | Catálogo de profesionales |
| 4 | GET | `/api/medicos/{id}` | P | Detalle de profesional |
| 5 | GET | `/api/pacientes` | P | **INC-001** (no debería ser pública) |
| 6 | GET | `/api/pacientes/{id}` | P | **INC-001** |
| 7 | POST | `/api/auth/registro` | P | Alta de cuenta (**INC-004**) |
| 8 | POST | `/api/auth/login` | P | Login válido / inválido |
| 9 | POST | `/api/auth/sso` | P | SSO (501 sin configurar) |
| 10 | GET | `/api/auth/me` | JWT | Datos del usuario actual |
| 11 | POST | `/api/auth/vincular` | JWT | Vincular cuenta↔ficha (RN4) |
| 12 | POST | `/api/auth/logout` | JWT | Cierre de sesión |
| 13 | POST | `/api/auth/cambiar-contrasena` | JWT | Cambio de contraseña |
| 14 | POST | `/api/medicos` | ADMIN | Alta de médico (403 sin rol) |
| 15 | PUT | `/api/medicos/{id}` | ADMIN | Edición de médico |
| 16 | PATCH | `/api/medicos/{id}` | ADMIN | Edición parcial |
| 17 | DELETE | `/api/medicos/{id}` | ADMIN | Baja de médico |
| 18 | POST | `/api/pacientes` | JWT | Alta de paciente |
| 19 | PUT | `/api/pacientes/{id}` | JWT | Edición de paciente |
| 20 | PATCH | `/api/pacientes/{id}` | JWT | Edición parcial |
| 21 | DELETE | `/api/pacientes/{id}` | ADMIN | Baja de paciente |
| 22 | GET | `/api/prescripciones` | JWT | Listado (dato clínico) |
| 23 | GET | `/api/prescripciones/{id}` | JWT | Detalle |
| 24 | POST | `/api/prescripciones` | MED | Emitir receta (RN1) |
| 25 | PUT | `/api/prescripciones/{id}` | MED | Editar receta |
| 26 | PATCH | `/api/prescripciones/{id}` | MED | Editar parcial |
| 27 | PATCH | `/api/prescripciones/{id}/estado` | JWT | Cambiar estado (F4) |
| 28 | DELETE | `/api/prescripciones/{id}` | ADMIN | Baja de receta |
| 29 | GET | `/api/especialidades` | P | Catálogo |
| 30 | GET | `/api/especialidades/{id}` | P | Detalle |
| 31 | POST | `/api/especialidades` | ADMIN | Alta |
| 32 | PUT | `/api/especialidades/{id}` | ADMIN | Edición |
| 33 | PATCH | `/api/especialidades/{id}` | ADMIN | Edición parcial |
| 34 | DELETE | `/api/especialidades/{id}` | ADMIN | Baja |
| 35 | GET | `/api/disponibilidades` | P | Agenda publicada |
| 36 | POST | `/api/disponibilidades` | JWT | Publicar bloque |
| 37 | PUT | `/api/disponibilidades/{id}` | JWT | Editar bloque |
| 38 | PATCH | `/api/disponibilidades/{id}` | JWT | Editar parcial |
| 39 | DELETE | `/api/disponibilidades/{id}` | JWT | Quitar bloque |
| 40 | GET | `/api/citas/disponibilidad` | P | Turnos libres/ocupados (sin datos de terceros) |
| 41 | GET | `/api/citas/agenda` | JWT | Agenda con datos (401 sin token) |
| 42 | GET | `/api/citas` | JWT | Mis turnos / agenda filtrable (RN5) |
| 43 | GET | `/api/citas/{id}` | JWT | Turno puntual |
| 44 | POST | `/api/citas` | JWT | Reservar (F5) |
| 45 | POST | `/api/citas/{id}/cancelar` | JWT | Cancelar y liberar |
| 46 | PATCH | `/api/citas/{id}/estado` | JWT | Estado según rol |
| 47 | DELETE | `/api/citas/{id}` | ADMIN | Borrar turno |
| 48 | GET | `/api/notificaciones` | JWT | Avisos del usuario |
| 49 | POST | `/api/notificaciones/recordatorios` | JWT | Generar recordatorios (idempotente) |
| 50 | POST | `/api/notificaciones/procesar` | ADMIN | Enviar avisos |
| 51 | POST | `/api/notificaciones/{id}/reintentar` | JWT | Reintentar aviso |
| 52 | GET | `/api/notificaciones/token/{token}` | TOKEN | Ver turno desde el aviso |
| 53 | POST | `/api/notificaciones/token/{token}/confirmar` | TOKEN | Confirmar asistencia |
| 54 | POST | `/api/notificaciones/token/{token}/cancelar` | TOKEN | Cancelar desde el aviso |
| 55 | GET | `/api/estadisticas` | JWT | Panel de gestión |
| 56 | GET | `/api/usuarios/roles` | ADMIN | Roles válidos |
| 57 | GET | `/api/usuarios` | ADMIN | Listado de cuentas |
| 58 | PATCH | `/api/usuarios/{id}/rol` | ADMIN | Cambiar rol (RN2/RN3) |
| 59 | GET | `/api/eventos` | JWT (post: token por query) | Canal SSE |
| 60 | GET | `/api/eventos/estado` | JWT (post: token por query) | Diagnóstico del canal |

> Nota: `GET /api/pacientes` (#5) y `GET /api/pacientes/{id}` (#6) son el hallazgo **INC-001**.
> Los endpoints #59/#60 reciben el token por *query string* porque `EventSource` no admite
> encabezados; el canal se resuelve contra el token y devuelve 403 si no corresponde al rol.

---

## B. Trazabilidad requisito → caso → resultado

| Requisito (brief / regla) | Endpoint(s) | Caso de prueba | Resultado |
|---|---|---|---|
| Autenticación JWT con roles | #8, #10 | Login válido devuelve token; `auth/me` con token → 200 | OK |
| Rutas protegidas 401 | #10, #22, #41, #57, #59 | Petición sin token → 401 | OK (`probar_roles_http`) |
| Rutas por rol 403 | #14, #24, #58 | Médico en `/api/usuarios` → 403; paciente recetando → 403 | OK |
| "Solo médicos recetan" | #24–#26 | Paciente/admin en `POST /api/prescripciones` → 403 | OK |
| ABM médicos admin-only | #14–#17 | Admin crea/edita/borra; no-admin → 403 | OK |
| Turnera: reservar | #35, #40, #44 | Reserva de turno libre → 201/200; repetido → 409 | OK (`probar_turnera`) |
| Turnera: vincular ficha | #11 | DNI inexistente → 422; DNI válido → vincula | OK |
| Turnera: ver solo lo propio | #42 | Paciente sin ficha no ve turnos de otros | OK |
| Turnera: estados por rol | #46 | Paciente no puede marcar "completada" → 403; sí confirma | OK |
| Roles: último admin | #58 | Degradar al último admin → 409 | OK |
| Roles: autodegradación | #58 | Admin se degrada a sí mismo → 409 | OK |
| Roles: rol inválido/inexistente | #58 | `superusuario` → 422; id inexistente → 404 | OK |
| Vinculación por identidad | (servicio) | 9 casos aceptados + 6 rechazados | OK (`probar_vinculacion`) |
| Paginado/búsqueda server-side | #42, #57 | `por_pagina` enorme se acota; búsqueda responde | OK |
| Validación de entrada DRY | (servicio) | Email/contraseña/textos en el borde | OK (`tests/run.php`) |
| Tiempo real (piezas + seguridad) | #59, #60 | Tabla, publicación, canales y 403 por canal | OK (`probar_tiempo_real`) |
| Tiempo real (latencia / 2 navegadores) | #59 | Reserva en A visible en B < 500 ms | No ejecutado (mono-hilo) |
| PWA offline | (build) | Manifest, SW, offline del caparazón | OK (`verificar_pwa`) |
| UI sin variables rotas | (CSS) | 0 `var(--x)` sin definir | OK (`verificar_variables`) |
| Contraste accesible | (CSS) | Tokens con contraste suficiente | OK (`ver_contraste`) |
| Mobile consume la API | (cliente móvil) | Registro y correlación de eventos | OK (`npm test` Mobile) |
| Padrón de pacientes no público | #5, #6 | Sin token debería ser 401 | **Falla → INC-001** |
| Registro no auto-promueve | #7 | `tipo_usuario=admin` no debería aceptarse | **Falla → INC-004** |

---

## C. Cobertura por tipo de caso

| Tipo | Ejemplos concretos | Dónde |
|---|---|---|
| Positivo | Login OK, reserva OK, rol válido, vinculación OK | `probar_*`, `tests/run.php` |
| Negativo | 401 sin token, 403 sin rol, 404/409/422 de roles | `probar_roles_http.php` |
| Borde | 6/72 caracteres, JSON malformado (400), `por_pagina` enorme | `tests/run.php`, `probar_turnera.php` |
| Regresión | Re-ejecución tras corregir INC-002 (21/21) | `QA/04` |

**Cobertura de endpoints:** 60 rutas relevadas y clasificadas. De ellas, las de seguridad
crítica (auth, roles, prescripciones, usuarios, agenda) están cubiertas por casos HTTP; los
catálogos públicos, por smoke test. Las dos excepciones de seguridad están registradas como
incidentes.
