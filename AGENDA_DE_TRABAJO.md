# Agenda de Trabajo — SaludWEB

| Campo | Detalle |
| --- | --- |
| **Proyecto** | SaludWEB |
| **Materia** | Programación IV |
| **Equipo** | Equipo SaludWEB (Ailen Quaglino) |
| **Fecha de emisión** | 22/09/2026 |
| **Estado** | En curso — próximos hitos: Pruebas Mobile y entrega final |
| **Última actualización** | 24/09/2026 |

> Las fechas son propuestas y ajustables. Se marca el avance real hasta hoy.
> 24/09/2026: Fase E avanzada — guía **"Adaptar el sistema a mobile"** aplicada en `SaludWEB_Mobile` (commit `3fe974a`) y **CRUD completo** (alta/edición/baja) implementado en la app mobile.

---

## 1. Objetivo de la agenda

Ordenar por fases las actividades de desarrollo, verificación y entrega del sistema
SaludWEB, de modo que cada entregable se pueda validar de forma incremental con el
profesor y quede registro del estado de cada tarea.

## 2. Metodología de trabajo

- **Iterativa por hitos**: cada fase termina con algo verificable (repo con código, prueba en vivo, documentación).
- **Control de versiones**: trabajo sobre la rama `dev`, integración a `main` vía Pull Request (rama `main` protegida).
- **Verificación continua**: cada cambio se prueba contra la API real antes de cerrarse.
- **Documentación acompañando el código**: mantiene el repositorio como única fuente de verdad.

## 3. Fases y actividades

### Fase A — Inicio y aprobación del proyecto

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| A1 | Relevamiento de la consigna y definición del alcance | Equipo | 24/08/2026 | 28/08/2026 | ✔ Completado |
| A2 | Armado inicial del stack (Backend + Web + Mobile) | Equipo | 31/08/2026 | 04/09/2026 | ✔ Completado |
| A3 | Aprobación del proyecto por el profesor (issues a corregir) | Profesor + Equipo | 01/09/2026 | 01/09/2026 | ✔ Completado |

### Fase B — Corrección de los 5 issues de aprobación

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| B1 | Issue 1 — Lista de Pacientes sin paginado | Equipo | 04/09/2026 | 11/09/2026 | ✔ Completado |
| B2 | Issue 2 — Un Paciente puede Prescribir (regla de negocio) | Equipo | 04/09/2026 | 11/09/2026 | ✔ Completado |
| B3 | Issue 3 — Falta rama `dev` | Equipo | 04/09/2026 | 11/09/2026 | ✔ Completado |
| B4 | Issue 4 — Falta diseño responsivo para Tablas | Equipo | 04/09/2026 | 11/09/2026 | ✔ Completado |
| B5 | Issue 5 — Paciente puede editar los datos de un médico | Equipo | 04/09/2026 | 11/09/2026 | ✔ Completado |

### Fase C — Profundización de la guía CRUD y refuerzos

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| C1 | Aplicación de guía "CRUD Completo desde JavaScript" (modal de confirmación + toasts + doble clic) | Equipo | 14/09/2026 | 18/09/2026 | ✔ Completado |
| C2 | Paginado completo server-side (Backend + Web) y seed de datos | Equipo | 16/09/2026 | 18/09/2026 | ✔ Completado |
| C3 | Regla de negocio estricta: solo el rol médico receta (403 en backend + UI) | Equipo | 22/09/2026 | 22/09/2026 | ✔ Completado |
| C4 | Evidencias: verificación en vivo de endpoints (403/201) | Equipo | 22/09/2026 | 22/09/2026 | ✔ Completado |

### Fase D — Documentación exigida por el profesor

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| D1 | Project Brief (`PROJECT_BRIEF.md`) | Equipo | 22/09/2026 | 22/09/2026 | ✔ Completado |
| D2 | Agenda de Trabajo (`AGENDA_DE_TRABAJO.md`) | Equipo | 22/09/2026 | 22/09/2026 | ✔ Completado |

### Fase E — Aplicación Mobile

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| E1 | Revisión del estado actual de `SaludWEB_Mobile` (auditoría según guía) | Equipo | 23/09/2026 | 24/09/2026 | ✔ Completado |
| E2 | Aplicación de la guía "Adaptar el sistema a mobile": login onBlur + mostrar/ocultar contraseña, Bottom Navigation Bar sticky, targets táctiles 44px, tipografía 16px, contraste WCAG AA y rendimiento FlatList | Equipo | 24/09/2026 | 24/09/2026 | ✔ Completado |
| E3 | Pruebas en emulador / dispositivo | Equipo | 05/10/2026 | 06/10/2026 | ⏳ Pendiente |
| E4 | CRUD completo en mobile: formularios de alta/edición con pie fijo de Confirmar/Cancelar, confirmación de borrado, cambio de estado de prescripciones y gating por rol (mismas reglas que `routes.php`) | Equipo | 24/09/2026 | 24/09/2026 | ✔ Completado |
| E4.2 | Biometría en mobile: "Proteger con huella" (huella / Face ID) para desbloquear la sesión guardada con `expo-local-authentication` (candado `Bloqueo.jsx` antes de entrar; login con email+clave siempre desbloquea) | Equipo | 24/09/2026 | 24/09/2026 | ✔ Completado |
| E4.3 | SSO en mobile (Google / Microsoft): botones en el Login con `expo-auth-session` (cliente público/PKCE, sin secret) que envían el `id_token` a `POST /api/auth/sso`; el backend valida la firma contra las claves públicas del proveedor (JWKS) y emite el JWT propio. Solo habilita cuentas locales existentes; sin credenciales configurables (`SSO_*`) responde 501 y el botón se oculta | Equipo | 24/09/2026 | 24/09/2026 | ✔ Completado (pendiente cargar credenciales reales) |

### Fase F — Integración, pruebas finales y entrega

| N° | Actividad | Responsable | Inicio | Fin | Estado |
| --- | --- | --- | --- | --- | --- |
| F1 | Pruebas integrales (Backend + Web + Mobile) | Equipo | 07/10/2026 | 09/10/2026 | ⏳ Pendiente |
| F2 | Ajustes finales y revisión de reglas de negocio | Equipo | 12/10/2026 | 13/10/2026 | ⏳ Pendiente |
| F3 | Actualización de documentación y merge `dev → main` | Equipo | 14/10/2026 | 15/10/2026 | ⏳ Pendiente |
| F4 | Entrega final y defensa | Equipo | 16/10/2026 | 16/10/2026 | ⏳ Pendiente |

---

## 4. Hitos clave

| Hito | Fecha |
| --- | --- |
| Aprobación del proyecto (issues asignados) | 01/09/2026 ✔ |
| Issues 1–5 corregidos y verificados | 11/09/2026 ✔ |
| Guía CRUD + paginado + regla "solo médicos recetan" | 22/09/2026 ✔ |
| Project Brief y Agenda de Trabajo | 22/09/2026 ✔ |
| Guía "Adaptar el sistema a mobile" aplicada en `SaludWEB_Mobile` | 24/09/2026 ✔ |
| CRUD completo (alta/edición/baja) en `SaludWEB_Mobile` | 24/09/2026 ✔ |
| Biometría (huella / Face ID) en `SaludWEB_Mobile` | 24/09/2026 ✔ |
| SSO (Google / Microsoft) implementado en Mobile + Backend (`/api/auth/sso`) | 24/09/2026 ✔ |
| Aplicación Mobile funcional | 06/10/2026 |
| Pruebas integrales finalizadas | 09/10/2026 |
| Docs finales + merge a `main` | 15/10/2026 |
| **Entrega final y defensa** | **16/10/2026** |

---

## 5. Reglas de negocio verificables

1. **"Las prescripciones sólo pueden ser hechas por Médicos"** → backend responde `403 Forbidden` si no es rol `medico`; la UI oculta las acciones a pacientes/admin.
2. Editar y borrar prescripciones: **solo médico** edita; **solo admin** elimina.
3. Cambio de estado de prescripción: disponible para cualquier usuario autenticado.
4. Alta/edición de pacientes y médicos: operaciones administrativas (admin).

## 6. Herramientas de seguimiento

- **Git/GitHub**: historial de commits en `dev`, PR hacia `main`, issues de verificación.
- **Pruebas manuales**: con Postman/HTTP contra la API real (`http://localhost/Workspace_SaludWEB/SaludWEB_Backend/api`).
- **Credenciales de prueba**: `admin@prueba.com/admin123`, `medico@prueba.com/medico123`, `paciente@prueba.com/paciente123`.

---

*La agenda se actualiza al cerrar cada hito; la versión vigente vive en la rama `dev` del repositorio.*