# Reporte de incidentes — SaludWEB

Fichas de los defectos encontrados durante la revisión y prueba del 2026-10-14. Cada ficha tiene
id, severidad, estado, pasos para reproducir, resultado esperado, resultado obtenido y la
recomendación.

Severidad: **Alta** (seguridad/datos) · **Media** (funcionalidad) · **Baja** (herramienta o
documentación).

---

## INC-001 — El padrón de pacientes es público

| Campo | Detalle |
|---|---|
| **Id** | INC-001 |
| **Severidad** | Alta |
| **Estado** | **Resuelto** (corregido y re-probado) |
| **Módulo** | Seguridad / API de pacientes |
| **Dónde** | `routes.php` (rutas `GET /api/pacientes` y `GET /api/pacientes/{id}`) |

**Descripción.** `GET /api/pacientes` y `GET /api/pacientes/{id}` están declaradas sin
`$authMiddleware->verificarToken()`, así que devuelven datos personales sin autenticación.

**Pasos para reproducir**

```bash
php -S 127.0.0.1:8000 index.php        # en SaludWEB_Backend
curl -s -o NUL -w "%{http_code}\n" http://127.0.0.1:8000/api/pacientes
```

**Resultado esperado.** `401` (el padrón de pacientes no es información de catálogo).

**Resultado obtenido.** `200` con el listado paginado. Ejemplo del cuerpo:

```json
{"ok":true,"mensaje":"OK","data":{"items":[{"id":222,"dni":"31528249","nombre":"Acosta, Ana","id_obra_social":3,"activo":1,...}]}}
```

Expone **DNI + nombre** de los pacientes de la base (223 filas) a cualquiera que conozca la URL.

**Análisis.** El brief trata los datos de pacientes como datos a proteger (módulo de
autenticación con roles). El endpoint quedó público probablemente como herencia de una etapa
temprana del desarrollo, cuando el frontend listaba sin sesión. La auditoría anterior de
"Calidad Profesional" revisó archivos, no la tabla de rutas, y por eso no lo detectó.

**Recomendación.** Exigir token en ambos endpoints y restringir el alcance por rol (un paciente
no debería listar a todos; como mínimo `admin`/`medico`). Verificar que el frontend que consume
el listado lo haga con sesión iniciada antes de aplicar el cambio.

**Riesgo si no se corrige.** Divulgación de datos personales (nombre y DNI de toda la población
de pacientes). En un sistema de salud real esto es una brecha de privacidad.

**Corrección aplicada (2026-10-14).** Se agregó `$authMiddleware->verificarToken()` a ambas
rutas: sin token responden `401`. No se exigió rol a propósito (el formulario de prescripciones
usa el listado como selector para el médico y la pantalla de gestión, para el admin; ambos con
sesión iniciada). La regresión `php probar_seguridad.php` comprueba `401` sin token y `200` con
token válido.

---

## INC-002 — Falso negativo de `probar_turnera.php` por la hora del día

| Campo | Detalle |
|---|---|
| **Id** | INC-002 |
| **Severidad** | Baja (herramienta de prueba) |
| **Estado** | **Corregido y re-probado** |
| **Módulo** | Suite de la turnera |
| **Dónde** | `probar_turnera.php` (búsqueda del "próximo día con agenda") |

**Descripción.** La suite elegía el primer día con agenda empezando por **hoy**, sin descartar
los bloques ya terminados. Como todos los bloques son 08:00–12:00, al correr después del
mediodía el día de hoy aparecía "lleno" y la prueba fallaba.

**Pasos para reproducir (antes del fix).**

```bash
php probar_turnera.php      # a las 14:02, con agenda de mañana
```

**Resultado esperado.** Encontrar un turno libre y continuar con reserva y estados.

**Resultado obtenido (antes).** `14 OK / 1 falla`:

```
[FALLA] Hay al menos un turno libre
        La agenda del día está llena
```

Además, el fallo **salteaba** las secciones "Reserva de turnos" y "Estados de la cita" (4 casos
quedaban sin ejecutar).

**Análisis.** No es un defecto del producto: la API marca correctamente como no disponibles los
turnos que ya pasaron. El defecto está en el *fixture* del script, que era sensible a la hora.

**Corrección.** Si el día candidato es hoy y el bloque ya terminó (`strtotime($b['hora_fin']) <=
time()`), se salta al siguiente día con turnos futuros.

**Re-prueba (regresión).**

```bash
php probar_turnera.php
```

```
=== Resultado ===
Correctos: 21
Fallidos:  0
La turnera responde correctamente en todos los pasos probados.
```

Incluye ahora las secciones que antes se salteaban (reserva, reserva repetida → 409,
disponibilidad ocupada, estados permitidos y no permitidos).

---

## INC-003 — Conteo de pruebas desactualizado en la documentación

| Campo | Detalle |
|---|---|
| **Id** | INC-003 |
| **Severidad** | Baja (documentación) |
| **Estado** | **Corregido** |
| **Módulo** | Documentación |
| **Dónde** | `CALIDAD_PROFESIONAL_SOFTWARE.md` (sección 5) |

**Descripción.** El documento afirmaba 49 pruebas de backend y 108 en total. La suite real es de
**50** pruebas, y el total real es **109**.

**Verificación.**

```bash
php tests/run.php | Select-String "pruebas OK"
# 10/10 · 8/8 · 9/9 · 6/6 · 17/17  →  50
```

**Corrección.** Se actualizaron los conteos a 50 (backend) y 109 (total).

---

## INC-004 — El registro público permite crear administradores

| Campo | Detalle |
|---|---|
| **Id** | INC-004 |
| **Severidad** | Alta |
| **Estado** | **Resuelto** (corregido y re-probado) |
| **Módulo** | Autenticación / escalada de privilegios |
| **Dónde** | `routes.php` (`POST /api/auth/registro`) → `services/AuthService.php` (validación del `tipo_usuario`) |

**Descripción.** `POST /api/auth/registro` es pública y toma `tipo_usuario` del cuerpo. Enviando
`{"tipo_usuario":"admin"}` se crea una cuenta administradora sin ninguna autorización.

**Pasos para reproducir.**

```bash
curl -s -X POST http://127.0.0.1:8000/api/auth/registro \
  -H "Content-Type: application/json" \
  -d '{"email":"x@x.com","password":"Clave123","nombre":"X","tipo_usuario":"admin"}'
```

**Resultado esperado.** Un registro público nunca debería permitir elegir el rol `admin`.

**Resultado obtenido.** `{"ok":true,...,"tipo_usuario":"admin",...}` — se creó un admin.

**Análisis.** La interfaz Web (`Register.jsx`) solo ofrece `paciente`/`medico`, así que el agujero
no es visible desde la UI, pero sí explotable por API. Es una decisión de alcance heredada de la
etapa académica: el proyecto se apoya en el registro para poblar la base de demostración.

**Recomendación.** Fijar `tipo_usuario='paciente'` en el registro público y mover la creación de
otros roles a una ruta solo-admin. Si se mantiene como demo, dejarlo señalizado (ya está en el
README y aquí).

**Corrección aplicada (2026-10-14).** En `AuthService::registro()` la lista blanca de roles quedó
en `['paciente', 'medico']`: el alta pública **nunca** acepta `admin` (ni ningún otro valor) y
responde `422`, dejando un `WARN` en el log con el intento. La creación de otros roles sigue por
`PATCH /api/usuarios/{id}/rol` (solo admin). Lo comprueba `php probar_seguridad.php`.

---

## Resumen

| Id | Severidad | Estado |
|---|---|---|
| INC-001 | Alta | Resuelto |
| INC-002 | Baja | Corregido y re-probado |
| INC-003 | Baja | Corregido |
| INC-004 | Alta | Resuelto |

Los cuatro incidentes quedaron resueltos. Los dos de seguridad (INC-001 y INC-004) se corrigieron
el 2026-10-14 y quedan cubiertos por la regresión `php probar_seguridad.php` (401 sin token en el
padrón de pacientes; 422 al intentar crear un admin desde el registro público).
