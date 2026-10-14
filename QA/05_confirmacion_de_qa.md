# Confirmación de aseguramiento de calidad — SaludWEB

Documento de cierre del ciclo "Revisar y probar el proyecto: de checklist a evidencia".

| Campo | Detalle |
|---|---|
| **Proyecto** | SaludWEB (Backend + Web + Mobile) |
| **Materia** | Programación IV |
| **Fecha de la revisión** | 2026-10-14 |
| **Alcance probado** | API REST, reglas de negocio y roles, turnera, gestión de usuarios, PWA, cliente mobile |
| **Responsable de la revisión** | Equipo SaludWEB |
| **Documentos asociados** | `QA/01` a `QA/04`, `REVISAR_Y_PROBAR_EL_PROYECTO.md` |

## 1. Qué se probó

- **Funcional:** login/registro/SSO, CRUD de pacientes, médicos y prescripciones, turnera
  (agenda, reserva, estados, vinculación), gestión de roles, notificaciones, PWA.
- **Seguridad:** rutas protegidas (401), control por rol (403), regla "solo médicos recetan",
  reglas de integridad de roles, secretos y contraseñas.
- **Calidad de UI:** variables CSS, contraste, build de producción, PWA offline.
- **Observabilidad:** correlación error↔log, formato uniforme de errores.

## 2. Cómo se probó

| Capa | Herramienta | Volumen |
|---|---|---|
| Backend (unitarias) | `tests/run.php` | 50 pruebas |
| Backend (reglas) | `probar_roles.php`, `probar_vinculacion.php` | reglas + 15 casos |
| Backend (HTTP) | `probar_roles_http.php`, `probar_turnera.php` | flujo completo con códigos HTTP |
| Backend (tiempo real) | `probar_tiempo_real.php` | 15 verificaciones |
| Web | `npm test`, `verificar_variables.php`, `ver_contraste.php`, `verificar_pwa.mjs` | 32 + 12 E2E |
| Mobile | `npm test` | 27 pruebas |

Todas las suites crean sus propios datos y los borran; la base quedó **idéntica** al inicio
(usuarios: 10, citas: 1, pacientes: 223). MySQL permaneció **apagado**: el sistema corre con
PHP nativo + SQLite.

## 3. Resultado

**Aprobado con observaciones.**

- 14 de 15 verificaciones ejecutadas terminaron en verde (ver `QA/04`).
- La única no ejecutada (E2E de tiempo real con dos navegadores) depende de un servidor
  multihilo y de cuentas semilla con contraseña; queda documentada como limitación de entorno.
- Se corrigieron 2 defectos (INC-002, INC-003) y se re-probaron.
- Quedan **2 incidentes de seguridad abiertos** (INC-001, INC-004) que **no** bloquean la
  entrega académica, pero **sí** deben resolverse antes de cualquier uso con datos reales.

## 4. Incidentes y su tratamiento

| Id | Severidad | Estado | Tratamiento |
|---|---|---|---|
| INC-001 | Alta | Abierto | Recomendación escrita (exigir token en `/api/pacientes`). No aplicado para no romper el frontend sin verificar su consumo. |
| INC-002 | Baja | Corregido | Fix en `probar_turnera.php` + regresión (21/21). |
| INC-003 | Baja | Corregido | Conteo de pruebas actualizado (50 backend / 109 total). |
| INC-004 | Alta | Abierto por decisión | Documentado como demo insegura; recomendación escrita. |

## 5. Criterios de éxito del brief

| Criterio (`PROJECT_BRIEF.md` §9) | Estado |
|---|---|
| Las 5 correcciones del profesor resueltas y verificadas | Cubierto por las suites y la matriz (`QA/02`) |
| Reglas de negocio en backend (403) y UI | Cubierto (`probar_roles*`, `probar_turnera`) |
| Usable en escritorio, tablet y celular | Cubierto (PWA + responsivo; contraste/variables OK) |
| Código en `dev`, integración a `main` por PR | Proceso de Git del proyecto |
| Documentación acompaña al código | Brief, agenda, calidad y esta guía QA |
| Panel por SSE sin polling en reposo | Piezas OK; latencia E2E pendiente de entorno multihilo |

## 6. Aprobación

| Rol | Nombre | Fecha | Firma |
|---|---|---|---|
| Responsable de QA | Equipo SaludWEB | 2026-10-14 | _____ |
| Revisión funcional | (profesor / tutor) | | _____ |

> **Conclusión.** El sistema es **verificable y reproducible**: cualquiera puede clonar los tres
> repos, levantar el backend con PHP nativo y correr las 15 verificaciones de `QA/04`. La
> evidencia es honesta: muestra lo que pasa, lo que no se pudo probar y por qué, y deja los
> dos hallazgos de seguridad abiertos con su recomendación.
