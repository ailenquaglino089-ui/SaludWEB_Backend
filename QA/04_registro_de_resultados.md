# Registro de resultados — SaludWEB

Bitácora de las corridas ejecutadas el **2026-10-14** para respaldar la matriz de cobertura y el
reporte de incidentes. Cada fila es reproducible con el comando que figura.

## Ambiente

| Elemento | Valor |
|---|---|
| SO | Windows (x64) |
| PHP | 8.2.12 (`php -S 127.0.0.1:8000 index.php`, servidor embebido) |
| Node / npm | 24.17.0 / 11.13.0 |
| Base de datos | SQLite `storage/saludweb.sqlite` (358 filas, 13 tablas) |
| MySQL | **Apagado a propósito** (LEGACY) — nada depende de él |
| Navegador (E2E) | Chromium de Playwright (`chromium-1243`) |

## Backend

| # | Suite | Comando | Resultado | Salida |
|---|---|---|---|---|
| 1 | Pruebas unitarias | `php tests/run.php` | **50/50** (5 clases) | `RESULTADO: TODO OK` · exit 0 |
| 2 | Reglas de roles | `php probar_roles.php` | **TODO OK** | `TODAS LAS COMPROBACIONES PASARON` · exit 0 |
| 3 | Vinculación de fichas | `php probar_vinculacion.php` | **15/15** | `Fallos: 0` · exit 0 |
| 4 | Roles por HTTP | `php probar_roles_http.php` | **TODO OK** | `TODAS LAS COMPROBACIONES PASARON` · exit 0 |
| 5 | Turnera (smoke) | `php probar_turnera.php` | **21/21** | `Fallidos: 0` · exit 0 |
| 6 | Tiempo real (piezas) | `php probar_tiempo_real.php` | **15/15** | `Total: 15 · Pasaron: 15` · exit 0 |
| 7 | Salud de la turnera | `php verificar_turnera.php` | **OK** | `La turnera está lista` · exit 0 |
| 8 | Salud de la API | `curl /api/health` | **200** | `{"ok":true,...,"timestamp":"...-03:00"}` |

**Detalle de las clases del #1:** 10 + 8 + 9 + 6 + 17 = 50
(CorrelationId, JwtService, Logger, RateLimiter, Validador).

**Detalle del #4 (HTTP):** 401 sin token · 403 con rol insuficiente · 200 con admin · 422 en
filtros inválidos · 404/409/400 en los bordes de `PATCH`. Crea y borra sus cuentas descartables.

**Detalle del #6:** la medición de latencia de punta a punta se **omite por consola** (necesita
un servidor con varios workers); las 15 verificaciones de piezas y de seguridad de canales sí se
ejecutan.

## Web

| # | Verificación | Comando | Resultado | Salida |
|---|---|---|---|---|
| 9 | Pruebas unitarias | `npm test` | **32/32** | `pass 32 · fail 0` · exit 0 |
| 10 | Variables CSS | `php verificar_variables.php` | **0 rotas** | `Variables rotas: 0` · exit 0 |
| 11 | Contraste | `php ver_contraste.php` | **12/12** | `Con problemas: 0` · exit 0 |
| 12 | Build de producción | `npm run build` | **OK** | `✓ built in 4.14s` · exit 0 |
| 13 | PWA (Playwright) | `node verificar_pwa.mjs` | **12/12** | `Fallos: 0` · exit 0 |
| 14 | Tiempo real · 2 navegadores | `node verificar_tiempo_real.mjs` | **No ejecutado** | ver observaciones |

**Observaciones del #14.** Requiere (a) dos contextos de navegador, (b) el backend levantado con
varios workers —imposible con `php -S` mono-hilo en Windows— y (c) cuentas semilla con
contraseña conocida (`paciente@prueba.com`, `admin@salud.com`), que la base migrada no conserva
en claro. Se documenta como limitación de entorno, no como fallo.

## Mobile

| # | Verificación | Comando | Resultado | Salida |
|---|---|---|---|---|
| 15 | Pruebas unitarias | `npm test` | **27/27** | `pass 27 · fail 0` · exit 0 |

## Regresión (post-corrección INC-002)

| Momento | Suite | Resultado |
|---|---|---|
| Antes | `php probar_turnera.php` | 14 OK / **1 falla** (falso negativo) |
| Después | `php probar_turnera.php` | **21 OK / 0 fallas** (incluye reserva y estados) |

Tras la corrección se volvieron a correr `tests/run.php` (50/50), `probar_roles.php` (OK) y
`probar_vinculacion.php` (15/15) para confirmar que no hubo regresión.

## Resumen

| Métrica | Valor |
|---|---|
| Suites ejecutadas con éxito | 14 de 15 |
| Suites/verificaciones no ejecutadas | 1 (E2E de tiempo real, por entorno) |
| Pruebas con resultado verde | **50 + 15 + 32 + 12 + 27 = 136** (más las 21 de turnera, las 15 de roles HTTP y las 9 de seguridad) |
| Incidentes abiertos | 0 |
| Incidentes corregidos | 4 (INC-001, INC-002, INC-003, INC-004) |
