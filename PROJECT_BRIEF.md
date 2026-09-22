# Project Brief — SaludWEB

| Campo | Detalle |
| --- | --- |
| **Nombre del proyecto** | SaludWEB |
| **Materia** | Programación IV |
| **Equipo** | Equipo SaludWEB (Ailen Quaglino) |
| **Fecha de emisión** | 22/09/2026 |
| **Estado** | Versión 1.0 — entregado para aprobación |
| **Repos** | `SaludWEB_Backend` (API REST), `SaludWEB_Web` (SPA React), `SaludWEB_Mobile` (app móvil) |

---

## 1. Resumen ejecutivo

SaludWEB es un sistema de gestión de salud web que centraliza la administración de
**pacientes**, **médicos** y **prescripciones médicas** (recetas digitales) en una sola
plataforma accesible desde navegador (SPA) y dispositivos móviles.

## 2. Problema que resuelve

La gestión de pacientes, profesionales y recetas se realiza de forma manual o
desconectada (fichas en papel, planillas, sistemas por separado), lo que genera:

- Duplicación de datos y errores de tipeo.
- Dificultad para encontrar el historial de prescripciones de un paciente.
- Riesgo de que personas sin rol médico generen recetas (problema de seguridad clínica).
- Imposibilidad de consultar el sistema desde el celular.

## 3. Objetivos

**Objetivo general**

Desarrollar un sistema de gestión de salud integral y responsivo que permita
administrar pacientes, médicos y prescripciones con control de acceso por roles.

**Objetivos específicos**

1. Abanico de altas, bajas, modificaciones y listados (CRUD) de pacientes y médicos.
2. Emisión de prescripciones digitales respetando la regla de negocio del profesor:
   *"Las prescripciones sólo pueden ser hechas por Médicos"*.
3. Cambio de estado de prescripciones (activa, vencida, dispensada, cancelada).
4. Búsqueda y paginado de todos los listados desde el backend.
5. Autenticación JWT con roles (admin, médico, paciente) y rutas protegidas (401/403).
6. Interfaces responsivas (tablas adaptables a mobile) y experiencia CRUD completa
   (spinners, validación, confirmaciones, notificaciones).
7. Aplicación móvil que consuma la misma API.

## 4. Alcance

### Incluido

- Backend API REST: PHP + MySQL + JWT (Patrones Repository, Service y Controller).
- Frontend web SPA: React + Vite (axios, contexto de autenticación, RBAC en UI).
- Aplicación mobile que consume la API.
- Autenticación con roles y protección de rutas (pública / protegida / solo-admin / solo-médico).
- CRUD completo de pacientes, médicos y prescripciones.
- Búsqueda, filtros y paginado server-side.
- Notificaciones (toasts) y confirmaciones de eliminación.

### Excluido (a futuro)

- Módulo de farmacia autónomo (rol *farmacéutico* que dispense recetas).
- Facturación / obras sociales integradas.
- Envío de correos o SMS.
- Hosting en producción con dominio propio.

## 5. Usuarios y roles

| Rol | Permisos destacados |
| --- | --- |
| **Admin** | Gestiona pacientes, médicos y prescripciones (alta, edición, baja). Cambia estado de prescripciones. **No receta** (regla de negocio: solo médicos). |
| **Médico** | Crea y edita prescripciones, cambia su estado. Consulta listados. |
| **Paciente** | Consulta sus datos y el estado de sus prescripciones. No receta ni edita médicos. |

## 6. Arquitectura y stack tecnológico

| Capa | Tecnología |
| --- | --- |
| Backend (API REST) | PHP 8, MySQL, JWT (firebase/php-jwt), XAMPP |
| Frontend Web | React 18, Vite, Axios, CSS propio |
| Mobile | Aplicación en `SaludWEB_Mobile` (consume la misma API) |
| Persistencia | MySQL + SQL preparado (anti-inyección) |
| Control de versiones | Git / GitHub — rama principal protegida, trabajo en rama `dev` |

**Patrones backend:** Repository Pattern, Service Layer, Controller, Middleware de
autenticación, Rate Limiter.

## 7. Entregables

- Repositorios con código fuente (Backend, Web, Mobile) y rama `dev`.
- **Project Brief** (este documento).
- **Agenda de Trabajo** (`AGENDA_DE_TRABAJO.md`).
- Aplicación funcional verificada en vivo (endpoints probados con HTTP).

## 8. Riesgos principales

| Riesgo | Mitigación |
| --- | --- |
| Violación de la regla "solo médicos recetan" | Restricción a nivel de ruta en backend (403) + ocultado en la UI por rol. |
| Tablas rotas en mobile / pantallas chicas | Diseño responsivo con `data-label` y media queries. |
| Listados muy grandes => rendimiento | Paginado y búsqueda resueltos en el backend (server-side). |
| Doble envío / borrado accidental | Modal de confirmación, botones deshabilitados durante la petición. |
| Errores opacos al usuario | `handleApiError` centralizado + toasts (éxito/info auto-ocultables, errores persistentes). |

## 9. Criterios de éxito

- Las 5 correcciones solicitadas por el profesor quedan resueltas y verificadas.
- Las reglas de negocio se cumplen tanto en backend (403) como en la UI (roles ocultos).
- La aplicación es usable desde escritorio, tablet y celular.
- Todo el código vive en la rama `dev` y se integra a `main` vía Pull Request.
- La documentación (Brief + Agenda) acompaña al código en el repositorio.

---

*Documento de carácter académico para la aprobación del proyecto de Programación IV.*