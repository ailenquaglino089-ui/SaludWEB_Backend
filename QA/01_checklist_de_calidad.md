# Checklist de calidad — SaludWEB

Lista de control usada para decidir qué se considera "de calidad" en este ciclo de revisión y
prueba. Cada fila tiene una columna **"cómo se verifica"** que apunta a la suite, al verificador
o al archivo donde está la evidencia. Si algo no tiene verificación, se dice.

Fecha de la corrida: **2026-10-14**.

## 1. Requisitos funcionales

| # | Ítem | Criterio de aceptación | Cómo se verifica | Estado |
|---|---|---|---|---|
| F1 | CRUD de pacientes | Alta, baja, modificación y listado con búsqueda y paginado server-side | `routes.php` + suites de pacientes; listado probado por HTTP | OK |
| F2 | CRUD de médicos | Ídem, y solo admin puede crear/editar/borrar | `probar_roles_http.php` (401/403), rutas `POST/PUT/PATCH/DELETE /api/medicos` | OK |
| F3 | Prescripciones digitales | Solo un médico puede crearlas/editar | Rutas `POST/PUT/PATCH /api/prescripciones` con `requireRol(['medico'])` | OK |
| F4 | Estados de prescripción | activa/vencida/dispensada/cancelada | `PATCH /api/prescripciones/{id}/estado` | OK |
| F5 | Turnera (agenda, reserva, mis turnos, estados) | Flujo completo de reserva y autogestión | `probar_turnera.php` (21/21) | OK |
| F6 | PWA instalable + offline del caparazón | Manifest, service worker, arranque sin red | `verificar_pwa.mjs` (12/12) | OK |
| F7 | Tiempo real del panel (SSE) | El panel se actualiza al reservar/cancelar | `probar_tiempo_real.php` (piezas 15/15); E2E de 2 navegadores: no ejecutado (ver limitaciones) | Parcial |
| F8 | App móvil consume la misma API | Cliente y registro de eventos correctos | `npm test` en Mobile (27/27) | OK |

## 2. Reglas de negocio

| # | Ítem | Criterio | Cómo se verifica | Estado |
|---|---|---|---|---|
| RN1 | "Solo médicos recetan" | 403 para paciente/admin en prescripciones | Rutas + suites de roles | OK |
| RN2 | No degradar al último admin | 409 | `probar_roles.php` y `probar_roles_http.php` | OK |
| RN3 | Nadie se degrada a sí mismo | 409 | `probar_roles.php` y `probar_roles_http.php` | OK |
| RN4 | Vinculación cuenta↔ficha por identidad | Acepta variantes, rechaza apellidos distintos | `probar_vinculacion.php` (15/15) | OK |
| RN5 | Un paciente solo ve SUS turnos | Alcance forzado desde el token | `probar_turnera.php` | OK |

## 3. Seguridad y roles

| # | Ítem | Criterio | Cómo se verifica | Estado |
|---|---|---|---|---|
| S1 | Ruta protegida sin token | 401 | `probar_roles_http.php` | OK |
| S2 | Ruta con rol insuficiente | 403 | `probar_roles_http.php` | OK |
| S3 | Contraseñas nunca en claro | hash bcrypt; login no revela si el email existe | `CALIDAD_PROFESIONAL_SOFTWARE.md` + `tests/run.php` | OK |
| S4 | Secretos fuera de los logs | redacción recursiva de tokens/datos | `tests/run.php` (Logger) | OK |
| S5 | `JWT_SECRET` robusto | mínimo 48 caracteres | `core/Secret.php` | OK |
| S6 | Padrón de pacientes no público | Requiere autenticación | **Falla**: `GET /api/pacientes` responde 200 sin token → `INC-001` | **Incidente** |
| S7 | Registro no permite auto-promoverse | `tipo_usuario` no debería aceptarse del cliente | **Falla**: acepta `admin` → `INC-004` | **Incidente aceptado** |

## 4. Manejo de errores y UX

| # | Ítem | Criterio | Cómo se verifica | Estado |
|---|---|---|---|---|
| E1 | Errores con formato uniforme | Envelope `{ok,mensaje,errores,requestId}` | `routes.php` (404) + `Response::error` | OK |
| E2 | JSON mal formado no rompe | 400, no 500 | `probar_turnera.php` y `probar_roles_http.php` | OK |
| E3 | Endpoint inexistente | 404 JSON con `requestId` | `routes.php` notFound | OK |
| E4 | Correlación error↔log | `X-Correlation-Id` de punta a punta | `CALIDAD_PROFESIONAL_SOFTWARE.md` + tests | OK |
| E5 | Sin errores de JS/peticiones fallidas en PWA | 0 errores | `verificar_pwa.mjs` | OK |

## 5. Rendimiento y calidad de UI

| # | Ítem | Criterio | Cómo se verifica | Estado |
|---|---|---|---|---|
| P1 | Listados paginados server-side | Paginado y búsqueda en el backend | `probar_roles_http.php` (`por_pagina` se acota) | OK |
| P2 | Sin variables CSS rotas | 0 `var(--x)` sin definir | `verificar_variables.php` (0 rotas) | OK |
| P3 | Contraste accesible | Tokens con contraste suficiente | `ver_contraste.php` (12/12) | OK |
| P4 | Carga inicial razonable | Bundle de producción sin errores | `npm run build` | OK |

## 6. Documentación

| # | Ítem | Criterio | Cómo se verifica | Estado |
|---|---|---|---|---|
| D1 | README con puesta en marcha nativa | Copiar, instalar, correr, verificar | los 3 `README.md` | OK |
| D2 | Brief y agenda al día | Brief + agenda acompañan al código | `PROJECT_BRIEF.md`, `AGENDA_DE_TRABAJO.md` | OK |
| D3 | Conteo de pruebas real | Coincide con la suite | **Corregido**: 49→50 backend (109 total) → `INC-003` | OK |
| D4 | Cómo se prueba documentado | Comandos de cada suite | README + este checklist + `QA/04` | OK |

---

### Leyenda de estado

- **OK**: verificado en esta corrida.
- **Parcial**: cubierto en parte; el resto depende del entorno (ver Paso 12 de la guía).
- **Incidente / Incidente aceptado**: defecto registrado en `QA/03_reporte_de_incidentes.md`.
