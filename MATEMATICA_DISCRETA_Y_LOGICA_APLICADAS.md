# Matemática discreta y lógica aplicadas a SaludWEB

Material de cátedra de **Erika R. Aab** sobre sistemas de numeración, lógica proposicional, conjuntos, relaciones, matrices, grafos y árboles, aplicado al proyecto SaludWEB.

Este documento no reemplaza el material teórico: lo traduce al proyecto. Cada concepto se explica, se vincula con una parte concreta del sistema (`archivo:línea`) y, cuando el material trae actividades, se resuelven con datos del proyecto o con un ejemplo mínimo. No se modificó el código de la aplicación: es documentación.

> **Aviso de revisión.** El material tiene errores de extracción de símbolos y un error aritmético en la suma binaria `1101 + 1101`. Las correcciones están en la sección 7. El proyecto no tiene problemas respecto de estos temas; las observaciones son sobre el apunte, no sobre el sistema.

---

## 1. Cómo se lee este documento

El recorrido sigue el orden del material:

1. **Sistemas de numeración** (sección 2): bases, conversiones, aritmética binaria y complemento a dos.
2. **Lógica proposicional** (sección 3): conectivos, tablas de verdad, leyes, condicionales, falacias y demostraciones.
3. **Conjuntos** (sección 4): operaciones, álgebra de conjuntos, producto cartesiano y su traducción a SQL.
4. **Relaciones** (sección 5): propiedades, relaciones de equivalencia y de orden, matrices y dígrafos.
5. **Matrices y grafos** (sección 6): adyacencia, incidencia, potencias, árboles, B-tree y el grafo de claves foráneas.
6. **Observaciones y correcciones** (sección 7), **trazabilidad** (sección 8) y **bibliografía** (sección 10).

Los símbolos que llegaron deformados en el texto pegado se reemplazan por los estándar:

| En el material | Significado | Símbolo correcto |
|---|---|---|
| `p ³ q` | implicación | `p → q` |
| `p ' q` | conjunción | `p ∧ q` |
| `p ( q` | disyunción | `p ∨ q` |
| `p · q` | disyunción exclusiva | `p ⊕ q` |
| `p c q` | bicondicional | `p ↔ q` |
| `¢` | subconjunto | `⊆` |
| `ª` | subconjunto propio | `⊂` |
| `b` | distinto | `≠` |
| `A * B` | unión | `A ∪ B` |
| `A + B` | intersección | `A ∩ B` |
| `A , B` | diferencia | `A − B` |
| `A . B` | producto cartesiano | `A × B` |

La evidencia se cita como `archivo:línea`. Cuando la relación es una analogía y no una equivalencia literal, se aclara: una tabla SQL no es un conjunto matemático y un pedido HTTP no es una proposición, pero los mismos conceptos permiten razonar sobre ambos.

---

## 2. Sistemas de numeración

### 2.1 Valor posicional

En una base `b`, un número se escribe con dígitos `d_i` y vale:

```
N = d_n · b^n + d_(n−1) · b^(n−1) + ... + d_1 · b^1 + d_0 · b^0
```

La base indica cuántos símbolos distintos existen. Las que usa el proyecto, directa o indirectamente, son:

| Base | Nombre | Dígitos | Uso en SaludWEB |
|---|---|---|---|
| 2 | binaria | 0, 1 | bits, permisos, máscaras, comprensión del hardware |
| 8 | octal | 0 a 7 | permisos de archivos en Linux (`0775`) |
| 10 | decimal | 0 a 9 | IDs, códigos HTTP, niveles de triage, paginación |
| 16 | hexadecimal | 0 a 9, A a F | colores CSS, identificadores de correlación, hashes |

Tabla de equivalencias para los primeros 16 valores:

| Decimal | Binario | Octal | Hex |
|---:|---:|---:|---:|
| 0 | 0000 | 0 | 0 |
| 1 | 0001 | 1 | 1 |
| 2 | 0010 | 2 | 2 |
| 3 | 0011 | 3 | 3 |
| 4 | 0100 | 4 | 4 |
| 5 | 0101 | 5 | 5 |
| 6 | 0110 | 6 | 6 |
| 7 | 0111 | 7 | 7 |
| 8 | 1000 | 10 | 8 |
| 9 | 1001 | 11 | 9 |
| 10 | 1010 | 12 | A |
| 11 | 1011 | 13 | B |
| 12 | 1100 | 14 | C |
| 13 | 1101 | 15 | D |
| 14 | 1110 | 16 | E |
| 15 | 1111 | 17 | F |

### 2.2 Conversiones

**Binario a decimal**: se suma el peso de cada bit encendido. `10110₂ = 16 + 4 + 2 = 22`.

**Decimal a binario**: se divide por 2 y se leen los restos de abajo hacia arriba. `25 = 11001₂`, porque `25 = 16 + 8 + 1`.

**Decimal a octal**: se divide por 8. `47 = 57₈`, porque `5 · 8 + 7 = 47`.

**Decimal a hexadecimal**: se divide por 16. `173 = AD₁₆`, porque `10 · 16 + 13 = 173`.

**Atajo entre binario, octal y hexadecimal**: octal agrupa de a 3 bits y hexadecimal de a 4 bits, siempre desde la derecha.

```
10110₂ = 010 110₂ = 26₈
10110₂ = 0001 0110₂ = 16₁₆
```

### 2.3 Dónde aparece cada base en el proyecto

**Decimal: identificadores y códigos.** Las claves primarias son enteras (`db.php:203`, `db.php:212`, `db.php:236`, `db.php:286`). SQLite las guarda como enteros con signo de 64 bits, y PHP maneja enteros de 64 bits en las versiones modernas. Hay un límite que conviene conocer: JavaScript representa los números como coma flotante de doble precisión y solo garantiza enteros exactos hasta `2^53 − 1 = 9.007.199.254.740.991`. Si un ID superara ese valor, el JSON lo entregaría redondeado al navegador. Hoy los IDs son chicos y no hay riesgo, pero el límite existe y es la razón por la que muchos sistemas usan IDs en texto para valores enormes.

**Hexadecimal: colores y trazabilidad.** En `src/tokens.css:66` el color primario es `#5a6fd0`: tres pares hexadecimales que representan rojo, verde y azul. `ver_contraste.php:15-32` convierte esos pares con `hexdec()` y calcula la luminancia relativa para verificar el contraste WCAG. Ese script es la aplicación directa de la conversión hex → decimal: `5a` = 90, `6f` = 111, `d0` = 208.

El identificador de correlación también es hexadecimal. `correlationId.js:35-38` genera 8 bytes aleatorios y `:74-94` los convierte a 16 caracteres hexadecimales con el prefijo `cid_`. Cada byte son 8 bits y cada dígito hexadecimal son 4 bits: 8 bytes = 64 bits = 16 dígitos. Un ejemplo válido es `cid_9f3a1c07b2d84e61`. Ese valor viaja en `X-Correlation-Id` (`client.js:52`) y permite reconstruir una petición completa en los logs.

**Octal: permisos de archivos.** `core/Secret.php:61` crea la carpeta de secretos con permisos `0775`. En octal, cada dígito son 3 bits:

```
0775₈ = 000 111 111 101₂
       = --- rwx rwx r-x
         dueño grupo otros
```

El dueño y el grupo pueden leer, escribir y ejecutar; los demás solo leer y ejecutar. El `0` inicial indica que el número es octal. En Windows los permisos POSIX se emulan y el valor no tiene el mismo efecto que en Linux; por eso el despliegue real se hace en un servidor Linux. El mismo criterio aparece en `core/RateLimiter.php:39` y `core/Logger.php:457`.

**Base64url: el JWT no es una base posicional.** Un JWT usa base64url, un alfabeto de 64 símbolos (`A-Z`, `a-z`, `0-9`, `-`, `_`). No es una base numérica con valor posicional como las anteriores: es una codificación de bytes a texto para que el token viaje en una cabecera HTTP. `AuthService.php:428-430` parte el token en tres, restaura `-` y `_` a `+` y `/`, y decodifica el encabezado. Confundir “base64” con “base 64 posicional” es un error frecuente: base64 no representa un número, representa una secuencia de bytes.

**Fechas ISO: decimales de ancho fijo.** Las fechas se guardan como texto `YYYY-MM-DD` y las horas como `HH:MM` (`db.php:396-397`). No son números, pero su representación decimal de ancho fijo hace que el orden lexicográfico coincida con el orden cronológico. Por eso `ORDER BY c.fecha ASC, c.hora ASC` (`persistence/CitaRepository.php:74`) funciona sin convertir nada. Si se guardara `1/2/2026` y `10/1/2026`, el orden alfabético sería incorrecto.

### 2.4 Aritmética binaria

**Suma.** Se suman bit a bit con acarreo:

```
  111₂  (7)
+ 011₂  (3)
------
 1010₂  (10)
```

**Corrección del material.** El apunte dice que `1101 + 1101 = 11000`. Es incorrecto:

```
  1101₂  (13)
+ 1101₂  (13)
--------
 11010₂  (26)
```

`11000₂` es 24, no 26. El acarreo final no se descarta en una suma sin restricción de ancho. El resultado correcto es `11010₂`.

**Complemento a dos.** Para representar negativos en 8 bits:

1. Se escribe el valor absoluto en binario.
2. Se invierten todos los bits.
3. Se suma 1.

```
+5 = 0000 0101
−5 = 1111 1010 + 1 = 1111 1011
```

El rango de 8 bits con signo es `−128` a `+127`. La suma `5 + (−5)` da `1 0000 0000`; el bit que se pasa de los 8 bits se descarta y queda `0000 0000`.

**Resta como suma.** `7 − 3 = 7 + (−3)`:

```
  0000 0111  (+7)
+ 1111 1101  (−3)
-------------
1 0000 0100  → se descarta el acarreo → 0000 0100 = +4
```

El material escribe “A + (2B)” para la resta. Esa notación es incorrecta: no se suma dos veces B, se suma **el complemento a dos de B**, es decir `−B`. La corrección está en la sección 7.

**Desbordamiento.** En 8 bits, `127 + 1 = 1000 0000`, que se interpreta como `−128`. El resultado “da la vuelta”. En 64 bits el mismo problema aparece muchísimo más lejos, pero existe. SQLite y PHP usan 64 bits; aun así, una aplicación no debe confiar en que el rango alcance para cualquier entrada: los IDs y los totales se validan.

**Multiplicación y división.** `101₂ × 110₂ = 11110₂` porque `5 × 6 = 30`. `1101₂ ÷ 11₂ = 100₂` con resto `1`, porque `13 ÷ 3 = 4` y sobra `1`. La división entera y el resto son la base de la paginación.

### 2.5 Paginación: división con resto

El proyecto pagina los listados de a 10. La cantidad de páginas se calcula con `ceil(total / porPagina)`:

- `services/CitaService.php:402-403`: 57 registros con 10 por página → 6 páginas.
- `services/MedicoService.php:77-78`: 37 → 4 páginas.
- `services/PacienteService.php:53-54`: 107 → 11 páginas.
- `services/PrescripcionService.php:55-56`: 57 → 6 páginas.
- `persistence/UsuarioRepository.php:154-157`: 21 → 3 páginas.

La división entera también dice cuántos registros entran antes de la página pedida: `offset = (pagina − 1) · porPagina`. Para 57 registros, 10 por página y página 6: `offset = 5 · 10 = 50`, quedan 7. Eso es exactamente `57 = 5 · 10 + 7`. El resto no se descarta: es el tamaño de la última página.

### 2.6 Actividades aplicadas de numeración

1. **Convertir `#0F766E` a decimal y binario.** `0F` = 15, `76` = 118, `6E` = 110. Binario: `0000 1111 0111 0110 0110 1110`.
2. **Convertir `0775` a permisos.** `111 111 101` → `rwx rwx r-x`.
3. **¿Cuántos bits tiene `cid_9f3a1c07b2d84e61`?** 16 dígitos hexadecimales × 4 bits = 64 bits = 8 bytes.
4. **Paginación.** 57 citas, 10 por página. `ceil(57/10) = 6`; la página 6 tiene `57 − 50 = 7` citas.
5. **Complemento a dos.** Calcular `7 − 3` en 8 bits: `0000 0111 + 1111 1101 = 1 0000 0100`; se descarta el acarreo y queda `0000 0100 = 4`.
6. **Detectar el error del apunte.** `1101₂ + 1101₂` no es `11000₂` (24); es `11010₂` (26).

---

## 3. Lógica proposicional

### 3.1 Proposiciones y conectivos

Una **proposición** es una oración que puede ser verdadera o falsa, pero no ambas. “El paciente existe” es una proposición; “reservá un turno” es una orden, no una proposición.

| Conectivo | Nombre | Se lee | Verdadero cuando |
|---|---|---|---|
| `¬p` | negación | no p | p es falso |
| `p ∧ q` | conjunción | p y q | ambos verdaderos |
| `p ∨ q` | disyunción | p o q | al menos uno verdadero |
| `p → q` | condicional | si p, entonces q | solo es falso si p es verdadero y q es falso |
| `p ↔ q` | bicondicional | p si y solo si q | ambos tienen el mismo valor |
| `p ⊕ q` | disyunción exclusiva | p o q, pero no ambos | exactamente uno es verdadero |

Precedencia habitual: `¬` > `∧` > `∨` > `→` > `↔`. Ante la duda, se usan paréntesis; en código, también.

### 3.2 Tablas de verdad y el condicional

| p | q | ¬p | p ∧ q | p ∨ q | p → q | p ↔ q | p ⊕ q |
|---|---|---|---|---|---|---|---|
| V | V | F | V | V | V | V | F |
| V | F | F | F | V | F | F | V |
| F | V | V | F | V | V | F | V |
| F | F | V | F | F | V | V | F |

El condicional es el conectivo que más se presta a confusión. `p → q` **no** dice que p sea la causa de q ni que q sea verdadero: solo prohíbe el caso `V → F`. Por eso `F → V` y `F → F` son verdaderos. Esa es la **verdad vacua**: “si llueve, la calle se moja” no se refuta porque no llueva.

En el proyecto, la implicación aparece en las reglas de autorización, pero hay que usarla con cuidado. La fórmula:

```
tokenVálido → peticiónProcesada
```

es falsa en el proyecto real, porque un token válido puede recibir `403` (rol insuficiente), `404` (recurso inexistente), `409` (conflicto) o `422` (datos inválidos). La implicación correcta es más larga:

```
(tokenVálido ∧ rolPermitido ∧ recursoExiste ∧ datosVálidos ∧ sinConflicto) → peticiónExitosa
```

Y el `401` no significa “token inválido” sin más: también se responde cuando el token falta, expiró o está mal formado. La distinción se ve en `core/AuthMiddleware.php:41-52` (sin token) y `:63-75` (token inválido o expirado).

### 3.3 La autorización como fórmula

Se pueden nombrar las proposiciones del camino de una petición:

- `T`: el token está presente y su firma es válida.
- `R`: el rol del usuario está entre los permitidos para la operación.
- `E`: el recurso pedido existe.
- `D`: los datos cumplen las validaciones.
- `C`: no hay conflicto con el estado actual (horario ocupado, transición inválida, etc.).

La respuesta se puede leer así:

| Situación | Fórmula | Código | Evidencia |
|---|---|---|---|
| Sin autenticación | `¬T` | 401 | `AuthMiddleware.php:41-52` |
| Autenticado sin permiso | `T ∧ ¬R` | 403 | `AuthMiddleware.php:86-106` |
| Recurso inexistente | `T ∧ R ∧ ¬E` | 404 | `CitaController.php:278` |
| Conflicto de estado | `T ∧ R ∧ E ∧ ¬C` | 409 | `CitaService.php:1084-1096` |
| Datos inválidos | `T ∧ R ∧ E ∧ ¬D` | 422 | `CitaService.php:727-739` |
| Éxito | `T ∧ R ∧ E ∧ D ∧ C` | 200/201 | controladores y servicios |

El orden importa. Primero se autentica, después se autoriza, después se busca el recurso y recién al final se validan los datos y el conflicto. Ese orden explica por qué un paciente que pide un recurso ajeno recibe `403` o `404` según el caso, y por qué no se debe devolver `409` cuando el problema real es de permisos. El comentario de `controllers/UsuarioController.php:18-42` desarrolla esa distinción.

### 3.4 Leyes y equivalencias

Las leyes que más se usan al leer y escribir condiciones:

| Ley | Expresión |
|---|---|
| Doble negación | `¬¬p ≡ p` |
| De Morgan | `¬(p ∧ q) ≡ ¬p ∨ ¬q`; `¬(p ∨ q) ≡ ¬p ∧ ¬q` |
| Distributiva | `p ∧ (q ∨ r) ≡ (p ∧ q) ∨ (p ∧ r)` |
| Absorción | `p ∨ (p ∧ q) ≡ p`; `p ∧ (p ∨ q) ≡ p` |
| Implicación | `p → q ≡ ¬p ∨ q` |
| Negación del condicional | `¬(p → q) ≡ p ∧ ¬q` |
| Contrapositiva | `p → q ≡ ¬q → ¬p` |
| Exportación | `(p ∧ q) → r ≡ p → (q → r)` |

**De Morgan en el código.** `AuthMiddleware.php:92` usa `!in_array($rol, $roles, true)`. La negación de “el rol está en la lista” es “el rol no está en la lista”; no hay que darle más vueltas. Cuando una condición crece, conviene escribirla en positivo y negar una sola vez al final:

```php
// En vez de una doble negación difícil de leer:
if (!(!$esAdmin && !$esPropietario)) { ... }

// Mejor:
$puede = $esAdmin || $esPropietario;
if (!$puede) { ... }
```

**De Morgan en SQL.** `NOT (a AND b)` equivale a `NOT a OR NOT b`, pero con `NULL` la historia cambia (ver 3.4.1). En el proyecto las condiciones se arman con parámetros y listas blancas, no con negaciones extensas, justamente para evitar ese problema.

**Disyunción exclusiva.** `p ⊕ q` es verdadera cuando exactamente una de las dos es verdadera. Sirve para dos estados mutuamente excluyentes, pero no para cinco. Los estados de una cita son `{pendiente, confirmada, cancelada, completada, ausente}` (`CitaService.php:853-855`); la pertenencia se expresa con `in_array(..., true)`, no con una cadena de XOR. Además, el XOR de tres o más proposiciones es la **paridad** (verdadero si hay una cantidad impar de verdaderas), no “exactamente una”. Es una fuente clásica de errores.

**Tautología, contradicción y contingencia.**

- Tautología: verdadera en todos los casos. `p ∨ ¬p`.
- Contradicción: falsa en todos los casos. `p ∧ ¬p`.
- Contingencia: depende de los valores. `p → q`.

#### 3.4.1 El detalle de SQL: lógica de tres valores

La lógica proposicional clásica tiene dos valores. SQL tiene tres: verdadero, falso y **desconocido** (`NULL`). Eso afecta a los conjuntos y a las negaciones:

- `NULL = NULL` no es verdadero: es desconocido.
- `NULL <> 5` es desconocido.
- `NOT (NULL = 5)` sigue siendo desconocido.
- `x NOT IN (1, NULL)` puede no devolver filas aunque `x` no sea 1, porque la comparación con `NULL` queda desconocida.

Por eso el proyecto usa `IS NULL` cuando corresponde y evita columnas `NULL` en las listas blancas de estados y roles. El caso más delicado está en `db.php:309-324`: el índice único de citas usa `slot_reservado` con `NULL` para las citas canceladas. SQLite considera que los `NULL` no son duplicados entre sí, y esa semántica es justamente la que permite cancelar y volver a reservar el mismo horario sin chocar con el índice.

### 3.5 Condicional, recíproco, inverso y contrapositivo

Dada la implicación `p → q`:

| Variante | Fórmula | ¿Equivale a la original? |
|---|---|---|
| Original | `p → q` | Sí |
| Recíproco | `q → p` | No |
| Inverso | `¬p → ¬q` | No |
| Contrapositivo | `¬q → ¬p` | Sí |

Ejemplo del proyecto: “si el token es válido, la petición se procesa”. El contrapositivo “si la petición no se procesa, el token no es válido” **también sería válido si la premisa original fuera verdadera**, pero la premisa original es falsa: un token válido puede no procesarse por falta de rol. De ahí que el contrapositivo no se pueda usar para diagnosticar `401` sin analizar el caso. La lógica no falla: falla la premisa.

### 3.6 Falacias aplicadas al proyecto

**Afirmar el consecuente.** Forma: `p → q`, `q`, por lo tanto `p`. Es inválida.

> “Si el token es inválido, el servidor responde 401. El servidor respondió 401. Entonces el token es inválido.”

Falso: el `401` también aparece cuando no hay token. La respuesta correcta es “el token falta, es inválido o expiró”; el código lo separa en dos ramas (`AuthMiddleware.php:41-52` y `:63-75`). Para diagnosticar, hay que leer el mensaje y el log, no invertir la implicación.

**Negar el antecedente.** Forma: `p → q`, `¬p`, por lo tanto `¬q`. Es inválida.

> “Si sos administrador, podés borrar la especialidad. No sos administrador. Entonces no podés borrarla.”

Falso: un médico puede modificar su propia disponibilidad y un paciente puede cancelar su propia cita. Los permisos no son una escalera lineal; son conjuntos (sección 4.3).

**Falso dilema.** “O sos administrador o no podés hacer nada” ignora la propiedad del recurso. El proyecto distingue `AdminRoute` de `ProtectedRoute` justamente porque hay operaciones que requieren sesión y operaciones que además requieren ser admin (`src/App.jsx:93-110`).

**Composición.** “Cada endpoint valida sus datos, entonces el sistema es seguro.” La conclusión no se sigue: además hacen falta rate limiting, CORS, cabeceras, control de secretos y logs. La seguridad es una conjunción de condiciones, no la suma de partes aisladas.

**Generalización apresurada.** “Las pruebas pasan en mi máquina, entonces funcionan en cualquier entorno.” Las pruebas son evidencia, no una demostración universal; dependen de la versión de PHP, de SQLite, del sistema operativo y de los datos.

### 3.7 Demostraciones

| Método | Idea | Ejemplo |
|---|---|---|
| Directa | Se supone p y se deduce q | `n` par → `n²` par |
| Contrapositiva | Se supone `¬q` y se deduce `¬p` | `n²` impar → `n` impar |
| Contradicción | Se supone `p ∧ ¬q` y se llega a un absurdo | `√2` no es racional |
| Inducción | Caso base + paso inductivo | suma `1 + ... + n` |
| Contraejemplo | Un caso que refuta una universal | un paciente pediátrico refuta “todos son adultos” |

**Demostración directa de que el cuadrado de un par es par.** Si `n` es par, existe un entero `k` tal que `n = 2k`. Entonces `n² = (2k)² = 4k² = 2(2k²)`, que es par porque es el doble de un entero.

**Contrapositiva.** “Si `n²` es impar, entonces `n` es impar.” Se supone que `n` es par; por la demostración anterior, `n²` es par, lo que contradice la hipótesis. Por lo tanto `n` es impar.

**El dominio importa.** `n` par → `n²` par es verdadero para enteros. Para reales no se puede hablar de “par”; para `√2`, `n² = 2` es par en algún sentido, pero `n` no es entero. Un teorema vale dentro de su dominio, y una validación vale dentro de sus supuestos.

**Qué aportan las pruebas del proyecto.** Las 50 pruebas unitarias, las 32 de Web y las 27 de Mobile son ejemplos sistemáticos, no una demostración formal. Ayudan a detectar contraejemplos; no prueban que el sistema sea correcto para toda entrada posible. La documentación de verificación del README es evidencia empírica, y se presenta como tal.

### 3.8 Actividades resueltas de lógica

**1. Identificar el conectivo.**

a) “Me gusta el café o el té.” → disyunción. En el lenguaje natural “o” a veces es exclusivo, pero la lógica proposicional usa `∨` inclusivo salvo que se diga lo contrario.

b) “Si estudias, entonces aprobarás.” → condicional `E → A`.

c) “No me gusta el frío, pero me gusta la nieve.” → conjunción `¬F ∧ N`.

d) “No es verdad que llueva.” → negación `¬L`.

**2. Valuar con `p = V`, `q = F`, `r = V`.**

a) `(p ∨ q) ∧ r = (V ∨ F) ∧ V = V ∧ V = V`.

b) `¬(p → q) = ¬(V → F) = ¬F = V`.

c) `(q ∨ ¬r) → p = (F ∨ F) → V = F → V = V`.

**3. Modus tollens.**

a) “Si un animal es perro, entonces tiene cuatro patas. Fido no tiene cuatro patas. Por lo tanto, Fido no es perro.” Es **modus tollens** (`p → q`, `¬q`, entonces `¬p`): válido. La validez depende de que “tiene cuatro patas” se use como condición necesaria; si el perro tuviera una discapacidad, la premisa “todo perro tiene cuatro patas” sería falsa. La forma es válida; la verdad de las premisas es otra discusión.

b) “Si no voy a la fiesta, entonces Pedro irá. Pedro no fue. Por lo tanto, yo fui.” Con `A` = “voy” y `P` = “Pedro va”: `¬A → P`, `¬P`, entonces `A`. Es modus tollens sobre el contrapositivo `¬P → A`: válido.

**4. Tablas de verdad.**

a) `(p ∧ q) → ¬r`:

| p | q | r | p ∧ q | ¬r | Resultado |
|---|---|---|---|---|---|
| V | V | V | V | F | F |
| V | V | F | V | V | V |
| V | F | V | F | F | V |
| V | F | F | F | V | V |
| F | V | V | F | F | V |
| F | V | F | F | V | V |
| F | F | V | F | F | V |
| F | F | F | F | V | V |

Es una contingencia: solo es falsa cuando `p`, `q` y `r` son verdaderas.

b) `(p ∨ q) ∧ (¬p → q)`:

| p | q | p ∨ q | ¬p → q | Resultado |
|---|---|---|---|---|
| V | V | V | V | V |
| V | F | V | V | V |
| F | V | V | V | V |
| F | F | F | F | F |

Como `¬p → q ≡ p ∨ q`, la fórmula se reduce a `p ∨ q`. Es una contingencia, no una tautología.

c) `(p → q) ∧ (q → r)`:

| p | q | r | p → q | q → r | Resultado |
|---|---|---|---|---|---|
| V | V | V | V | V | V |
| V | V | F | V | F | F |
| V | F | V | F | V | F |
| V | F | F | F | V | F |
| F | V | V | V | V | V |
| F | V | F | V | F | F |
| F | F | V | V | V | V |
| F | F | F | V | V | V |

Es verdadera cuando `p` es falsa o cuando los tres son verdaderos. No es equivalente a `p → r`, porque `p → r` también es verdadera en casos como `p = V, q = F, r = V`, donde la conjunción es falsa. La conjunción es más fuerte: exige que se cumplan **las dos** implicaciones.

**5. Equivalencias.**

a) `p ↔ q ≡ (p → q) ∧ (q → p)`:

| p | q | p ↔ q | p → q | q → p | (p → q) ∧ (q → p) |
|---|---|---|---|---|---|
| V | V | V | V | V | V |
| V | F | F | F | V | F |
| F | V | F | V | F | F |
| F | F | V | V | V | V |

b) `¬(p ↔ q) ≡ p ⊕ q`:

| p | q | p ↔ q | ¬(p ↔ q) | p ⊕ q |
|---|---|---|---|---|
| V | V | V | F | F |
| V | F | F | V | V |
| F | V | F | V | V |
| F | F | V | F | F |

**6. Variantes del condicional.** “Si llueve, la calle se moja.”

- Recíproco: “Si la calle se moja, llueve.” No equivalente (puede haber un aspersor).
- Inverso: “Si no llueve, la calle no se moja.” No equivalente.
- Contrapositivo: “Si la calle no se moja, no llueve.” Equivalente.

**7. Tautología, contradicción o contingencia.**

a) `p ∨ ¬p`: tautología.

b) `p ∧ ¬p`: contradicción.

c) `(p → q) ↔ (¬q → ¬p)`: tautología, porque ambos lados son equivalentes por contrapositiva.

d) `p → (q → p)`: tautología. Si `p` es verdadera, `q → p` es verdadera; si `p` es falsa, el condicional exterior es verdadero.

**8. De Morgan.**

a) `¬(p ∧ q) → (¬p ∨ ¬q)`: el antecedente y el consecuente son equivalentes, así que la implicación es una tautología.

b) `¬(¬p ∨ q) → (p ∧ ¬q)`: `¬(¬p ∨ q) ≡ p ∧ ¬q`; la implicación es una tautología.

**9. Falacias.**

a) “Si uso lentes, soy inteligente. Uso lentes, luego soy inteligente.” Es **modus ponens** (`p → q`, `p`, entonces `q`): la forma es válida. Lo que puede ser falso es la premisa “si uso lentes, soy inteligente”; una forma válida con premisas falsas no es una falacia formal.

b) “Los gatos tienen cola. Mi perro tiene cola, luego es un gato.” Es **afirmar el consecuente**: inválido. Tener cola no es exclusivo de los gatos.

c) “Si estudio, apruebo. Aprobé, entonces estudié.” Afirmar el consecuente: inválido; pude aprobar por otro motivo.

d) “Si no estudio, no apruebo. Estudié, entonces aprobé.” Negar el antecedente: inválido; estudiar no garantiza aprobar.

**10. Ley y falacia.**

“Si sos menor de 18, no podés votar. Juan no vota. ¿Es menor?” No se puede concluir. La forma es afirmar el consecuente: Juan puede ser mayor y no votar, o estar inhabilitado. La fórmula correcta sería `menor → noVota`, y de `noVota` no se sigue `menor`.

**11. Demostración.** `n` par → `n²` par: ver 3.7. La recíproca “`n²` par → `n` par” también es verdadera para enteros, y se demuestra por contrapositiva: si `n` es impar, `n = 2k + 1`, entonces `n² = 4k² + 4k + 1 = 2(2k² + 2k) + 1`, que es impar. Por lo tanto, para enteros, `n` es par si y solo si `n²` es par.

**12. Bicondicional y XOR.** `(p ↔ q) ↔ ¬(p ⊕ q)` es una tautología: el bicondicional es verdadero exactamente cuando no hay discrepancia, y el XOR es verdadero exactamente cuando la hay.

---

## 4. Conjuntos

### 4.1 Definiciones y operaciones

Un conjunto es una colección de elementos sin orden y sin repetición. Notación:

- Pertenencia: `a ∈ A`; no pertenencia: `a ∉ A`.
- Extensión: `A = {1, 2, 3}`.
- Comprensión: `A = {x ∈ ℤ | x > 0}`.
- Conjunto vacío: `∅`.
- Subconjunto: `A ⊆ B` si todo elemento de A está en B.
- Subconjunto propio: `A ⊂ B` si `A ⊆ B` y `A ≠ B`.
- Igualdad: `A = B` si `A ⊆ B` y `B ⊆ A` (doble inclusión).
- Conjunto de partes: `P(A) = {S | S ⊆ A}`; `|P(A)| = 2^|A|`.

Operaciones:

| Operación | Definición | Ejemplo |
|---|---|---|
| Unión | `A ∪ B = {x | x ∈ A ∨ x ∈ B}` | `{1,2} ∪ {2,3} = {1,2,3}` |
| Intersección | `A ∩ B = {x | x ∈ A ∧ x ∈ B}` | `{1,2} ∩ {2,3} = {2}` |
| Diferencia | `A − B = {x | x ∈ A ∧ x ∉ B}` | `{1,2} − {2,3} = {1}` |
| Complemento | `A^c = U − A` | si `U = {1,2,3}`, `{1}^c = {2,3}` |
| Diferencia simétrica | `A △ B = (A − B) ∪ (B − A)` | `{1,2} △ {2,3} = {1,3}` |

Leyes: conmutativas, asociativas, distributivas, identidad (`A ∪ ∅ = A`, `A ∩ U = A`), dominación (`A ∪ U = U`, `A ∩ ∅ = ∅`), idempotencia, complemento (`A ∪ A^c = U`, `A ∩ A^c = ∅`), doble complemento, De Morgan (`(A ∪ B)^c = A^c ∩ B^c`, `(A ∩ B)^c = A^c ∪ B^c`) y absorción.

### 4.2 Conjuntos en SQL

Una tabla SQL no es exactamente un conjunto: es un **multiconjunto** de filas, salvo que una clave primaria o un `UNIQUE` impidan duplicados. Esa diferencia explica los operadores:

| Operador SQL | Operación de conjuntos | Comportamiento con duplicados |
|---|---|---|
| `UNION` | unión | elimina duplicados |
| `UNION ALL` | unión de multiconjuntos | conserva duplicados |
| `INTERSECT` | intersección | elimina duplicados |
| `EXCEPT` | diferencia | elimina duplicados |
| `IN` | pertenencia | compara contra una lista |
| `NOT EXISTS` | no pertenencia | ideal para diferencias con `NULL` |

En el proyecto:

- `db.php:636-637` usa `SELECT 1 UNION ALL SELECT 2 ...` para construir el conjunto de días `{1,2,3,4,5}`. `UNION ALL` es correcto y evita el costo de eliminar duplicados; los valores ya son distintos.
- `db.php:610-614` usa `SELECT DISTINCT especialidad FROM medicos` para construir el conjunto de especialidades sin repetir.
- `db.php:632-642` usa `CROSS JOIN` para formar el producto cartesiano `medicos × dias` y `NOT EXISTS` para restar los pares que ya existen. Es una diferencia de conjuntos disfrazada de inserción idempotente.
- `migrar_mysql_a_sqlite.php:85` y `:186` usan `array_intersect()` para quedarse con las tablas y columnas que están en ambos esquemas. Es la intersección de dos conjuntos de nombres.
- `IN (...)` aparece en las listas blancas de estados y roles; `NOT IN` se evita cuando la lista puede contener `NULL`, por la lógica de tres valores de 3.4.1.

SQLite soporta `INTERSECT` y `EXCEPT`, pero el proyecto no las necesita hoy. Un ejemplo válido sería “pacientes que tienen cita y también triage” con `INTERSECT`, o “profesionales que no tienen disponibilidad” con `EXCEPT`. No se usan porque las consultas actuales se resuelven con `JOIN` y `NOT EXISTS`, que son más directas.

### 4.3 Los conjuntos del proyecto

**Roles.** `R = {paciente, medico, admin}`. La tabla `usuarios` restringe el valor con `CHECK (tipo_usuario IN ('paciente', 'medico', 'admin'))` (`db.php:285-296`). Cada usuario tiene un rol; es una función `usuario → rol`, no una relación muchos-a-muchos.

**Permisos.** Cada rol tiene un conjunto de operaciones permitidas. Esos conjuntos forman un **orden parcial** por inclusión (`⊆`): reflexivo, antisimétrico y transitivo, pero no total, porque hay operaciones que no se comparan. La jerarquía de roles no alcanza para describirlos: además del rol hay reglas de **propiedad** (“el médico solo ve su agenda”, “el paciente solo ve sus citas”) que no se expresan como “más” o “menos” permisos. Por eso `AdminRoute` y `ProtectedRoute` son mecanismos distintos (`src/App.jsx:93-110`) y por eso el controlador compara el id del token con el id pedido (`CitaController.php:91-116`).

**Estados de una cita.** `S = {pendiente, confirmada, cancelada, completada, ausente}` (`CitaService.php:853-855`). Se parte en dos subconjuntos:

- Activos: `A = {pendiente, confirmada}`.
- Finales: `F = {cancelada, completada, ausente}`.

`S = A ∪ F` y `A ∩ F = ∅`: `{A, F}` es una partición de `S`. El servicio usa `F` para impedir transiciones desde estados finales (`CitaService.php:1084-1090`). Una partición no es una relación de equivalencia por sí misma, pero define una: “pertenecer al mismo bloque” (activo o final).

**Horarios disponibles.** Sea `D` el conjunto de slots que genera la agenda de un médico para una fecha, y `O` el conjunto de slots ocupados por citas vivas. El conjunto de disponibles es `D − O`. `CitaService.php:129-140` arma un mapa de ocupados y `:196-200` recorre los slots marcando libre u ocupado. Es una diferencia de conjuntos a nivel de código, no de SQL.

**Especialidades y obras sociales.** `especialidades` es un conjunto con `nombre UNIQUE` (`db.php:331-336`). Los médicos se agrupan por especialidad y los pacientes por obra social; “tener la misma especialidad” y “tener la misma obra social” son relaciones de equivalencia (sección 5.3). La diferencia con SQL es que un `NULL` no es igual a otro `NULL`, así que la equivalencia no se cumple para valores nulos sin `COALESCE` o `IS NULL`.

**Cardinalidad.** `|S| = 5`, entonces `|P(S)| = 2^5 = 32`. El proyecto no guarda los 32 subconjuntos: guarda el estado actual y deriva las reglas. Enumerar todos los subconjuntos sería innecesario y difícil de mantener.

### 4.4 Producto cartesiano

`A × B = {(a, b) | a ∈ A ∧ b ∈ B}` y `|A × B| = |A| · |B|`.

En SQL, `CROSS JOIN` produce el producto cartesiano. En `db.php:632-642`, el producto es `medicos × {1,2,3,4,5}`: cada médico activo se combina con cada día hábil. El `NOT EXISTS` elimina los pares que ya tienen disponibilidad, y el resultado se inserta. Sin el `NOT EXISTS`, volver a correr el script duplicaría filas; con él, la operación es idempotente.

El producto cartesiano también explica un riesgo clásico de SQL: si se olvida la condición de unión, dos tablas de 10.000 filas producen 100.000.000 de filas. El proyecto usa `JOIN ... ON` cuando quiere combinar por clave y `CROSS JOIN` solo cuando el producto es intencional. La paginación (`CitaService.php:402-403`) y los filtros por médico y fecha evitan materializar todos los slots posibles.

### 4.5 Actividades resueltas de conjuntos

**1.** `U = {a, b, c, d, e, f}`, `A = {a, c, d, e}`, `B = {b, d, e, f}`.

- `A ∪ B = {a, b, c, d, e, f} = U`.
- `A ∩ B = {d, e}`.
- `A − B = {a, c}`.
- `B − A = {b, f}`.
- `A^c = U − A = {b, f}`.
- `B^c = U − B = {a, c}`.

**2.** `A = {1, 2}`, `B = {x, y, z}`.

`A × B = {(1,x), (1,y), (1,z), (2,x), (2,y), (2,z)}`; `|A × B| = 2 · 3 = 6`.

**3.** `A = {1, 2, 3}`, `A × A` tiene 9 pares.

- “menor que”: `{(1,2), (1,3), (2,3)}`.
- “igual a”: `{(1,1), (2,2), (3,3)}`.
- “mayor que”: `{(2,1), (3,1), (3,2)}`.

Las tres relaciones son subconjuntos distintos de `A × A`. “Menor que” es transitiva y antisimétrica, pero no reflexiva; “igual a” es de equivalencia; “mayor que” es la inversa de “menor que”.

---

## 5. Relaciones

### 5.1 Definición y representaciones

Una relación binaria de `A` en `B` es un subconjunto `R ⊆ A × B`. Si `(a, b) ∈ R`, se escribe `a R b`.

- **Dominio**: `{a ∈ A | existe b con (a,b) ∈ R}`.
- **Codominio**: `B`.
- **Imagen o rango**: `{b ∈ B | existe a con (a,b) ∈ R}`. Es un subconjunto del codominio, no el codominio entero.
- **Inversa**: `R⁻¹ = {(b,a) | (a,b) ∈ R}`.
- **Composición**: `S ∘ R = {(a,c) | existe b con (a,b) ∈ R y (b,c) ∈ S}`.

Se representa con pares, con una matriz booleana o con un dígrafo (grafo dirigido). Una **función** es un caso particular de relación: cada elemento del dominio tiene exactamente una imagen. Toda función es una relación, pero no toda relación es una función.

### 5.2 Propiedades

Para `R ⊆ A × A`:

| Propiedad | Definición | Ejemplo |
|---|---|---|
| Reflexiva | `∀a ∈ A, (a,a) ∈ R` | “tener la misma obra social que” |
| Simétrica | `(a,b) ∈ R → (b,a) ∈ R` | “ser colega de” |
| Antisimétrica | `(a,b) ∈ R ∧ (b,a) ∈ R → a = b` | `≤` en números |
| Transitiva | `(a,b) ∈ R ∧ (b,c) ∈ R → (a,c) ∈ R` | `≤`, “ser ancestro de” |

- **Equivalencia**: reflexiva + simétrica + transitiva. Parte el conjunto en clases disjuntas.
- **Orden parcial**: reflexiva + antisimétrica + transitiva.
- **Orden total**: orden parcial en el que todo par es comparable.

### 5.3 Las relaciones del proyecto

**Cita como relación entre paciente y slot.** Un turno se puede modelar como `R ⊆ Pacientes × Slots`, donde un slot es `(médico, fecha, hora)`. `(p, (m, f, h)) ∈ R` significa que el paciente `p` tiene una cita con el médico `m` en `f` a las `h`. La relación no es reflexiva, simétrica ni transitiva: no tiene sentido que un paciente tenga una cita consigo mismo, ni que la relación se invierta, ni que se encadene.

Lo interesante es que la **relación inversa** `R⁻¹` es una función parcial: cada slot tiene como máximo un paciente. (Un paciente sí puede tener varias citas, así que `R` no es una función; su inversa sí lo es donde está definida.) El índice `UNIQUE (id_medico, fecha, hora, slot_reservado)` (`db.php:407`) impone esa unicidad para las citas vivas. Las citas canceladas ponen `slot_reservado` en `NULL`, y SQLite no considera duplicados los `NULL`, así que el mismo horario puede tener varias citas canceladas y una sola viva. Es una restricción de conjunto disfrazada de índice.

**Misma especialidad.** En el conjunto de médicos, `a ~ b` si `a.especialidad = b.especialidad`. Es reflexiva, simétrica y transitiva; las clases son las especialidades. Es una relación de equivalencia. El detalle SQL: si la especialidad es `NULL`, `NULL = NULL` es desconocido, así que la relación no se cumple para dos médicos sin especialidad. Para que sea equivalencia también en SQL habría que usar `COALESCE(especialidad, '')` o `IS NOT DISTINCT FROM` (no disponible en todas las versiones). El proyecto no usa esta relación para autorizar; solo para agrupar y filtrar.

**Nivel de triage.** `nivel_gravedad` es un entero (`db.php:229`). La relación `≤` sobre los niveles es un orden total, pero el orden natural de los números no coincide necesariamente con la urgencia: hay que decidir la convención (“1 es el más urgente” o “5 es el más urgente”) y documentarla. Al ordenar **pacientes**, dos con el mismo nivel quedan empatados; para obtener un orden total sobre pacientes hace falta un desempate (hora de llegada, edad, etc.). Es un ejemplo de cómo un orden total sobre los niveles no se traslada solo a un orden total sobre los elementos que los tienen.

**Usuario y rol.** Cada usuario tiene un `tipo_usuario` (`db.php:285-296`). Es una función del conjunto de usuarios al conjunto de roles. No es una relación muchos-a-muchos; si un usuario pudiera tener varios roles, haría falta una tabla intermedia `usuario_rol` y la función pasaría a ser una relación. El `CHECK` es la garantía de que la imagen está dentro del codominio.

**Permisos como orden parcial.** El conjunto de permisos de cada rol es un subconjunto de las operaciones. La inclusión `⊆` es reflexiva, antisimétrica y transitiva, pero no total: hay permisos que no se comparan. Decir “admin tiene más permisos que paciente” es una simplificación; lo correcto es comparar conjuntos concretos.

**Composición por JOIN.** `notificaciones → citas → pacientes` es una composición de relaciones: una notificación se vincula con una cita y la cita con un paciente. Un `JOIN` de las tres tablas devuelve los pares `(notificación, paciente)` que se alcanzan por un camino de longitud 2. Esa es la misma idea que `R²` en la sección 6.

### 5.4 Actividades resueltas de relaciones

**4.** `A = {1,2,3}`, `R = {(1,1), (2,2), (3,3), (1,2), (2,1)}`.

- Reflexiva: están `(1,1)`, `(2,2)`, `(3,3)`. Sí.
- Simétrica: `(1,2)` y `(2,1)` están ambos; los pares con la diagonal son simétricos consigo mismos. Sí.
- Transitiva: `(1,2)` y `(2,1)` obligan a `(1,1)`, que está; `(2,1)` y `(1,2)` obligan a `(2,2)`, que está. Sí.
- Es de equivalencia. Clases: `{1,2}` y `{3}`.

**5.** `A = {a,b,c}`, `R = {(a,a), (a,b), (b,a), (b,b), (c,c)}`.

Matriz de la relación (filas = origen, columnas = destino, orden `a,b,c`):

```
     a  b  c
a  [ 1  1  0 ]
b  [ 1  1  0 ]
c  [ 0  0  1 ]
```

Dígrafo: `a` y `b` tienen bucles y flechas en ambos sentidos; `c` tiene un bucle. Es reflexiva, simétrica y transitiva; clases `{a,b}` y `{c}`.

---

## 6. Matrices y grafos

### 6.1 Matrices

Una matriz `m × n` tiene `m` filas y `n` columnas. Se suman si tienen las mismas dimensiones, componente a componente. Se multiplican si el número de columnas de la primera coincide con el de filas de la segunda: `(A · B)[i][j] = Σ A[i][k] · B[k][j]`.

**Matriz de adyacencia.** Para un grafo con vértices `v_1, ..., v_n`, `A[i][j] = 1` si hay una arista de `v_i` a `v_j`, y `0` si no. En un grafo no dirigido la matriz es simétrica. La potencia `A^k` cuenta los **caminos de longitud k** entre cada par de vértices (con repetición de vértices y aristas). No cuenta caminos simples: un camino que vuelve sobre sus pasos también suma.

**Matriz de incidencia.** Filas = vértices, columnas = aristas. En un grafo no dirigido, `1` si el vértice es extremo de la arista. En un dígrafo se suele usar `+1` para el origen y `−1` para el destino (o `1` en ambos si solo interesa la incidencia).

**Corrección importante.** La suma de dos matrices de adyacencia **no** representa la unión de los grafos en general. Si ambos grafos tienen la misma arista, la suma da `2`, que en una matriz booleana no significa “hay dos aristas” sino “hay una”. Para la unión se usa el **OR lógico** componente a componente; para la intersección, el **AND lógico**. La suma aritmética sí sirve para multigrafos, donde dos aristas entre los mismos vértices son distintas. El material dice que la suma representa la unión; es cierto solo si las aristas son disjuntas o si se interpreta la matriz como conteo, no como booleana.

### 6.2 Grafos y árboles

Un grafo `G = (V, E)` tiene vértices `V` y aristas `E`. Puede ser dirigido o no dirigido. Un **camino** es una secuencia de vértices conectados por aristas; un **ciclo** es un camino que vuelve al inicio. Un grafo es **conexo** si hay camino entre todo par de vértices.

Un **árbol** es un grafo conexo y acíclico. Propiedades:

- Tiene `n − 1` aristas si tiene `n` vértices.
- Hay un único camino entre cada par de vértices.
- Agregar una arista crea un ciclo; quitar una arista lo desconecta.
- Tiene raíz, padre, hijo, hoja, altura y subárboles cuando se elige una raíz.

Cuidado con la recíproca: “tener `n − 1` aristas” **no** alcanza para ser árbol. Un grafo con 4 vértices y 3 aristas puede ser un triángulo más un vértice aislado: tiene `n − 1` aristas, pero no es conexo y tiene un ciclo. Lo correcto es: conexo y `n − 1` aristas ⇒ árbol; acíclico y `n − 1` aristas ⇒ conexo ⇒ árbol. Un **bosque** es un conjunto de árboles disjuntos.

Un **DAG** es un grafo dirigido sin ciclos. No todo DAG es un árbol: puede haber varios caminos entre dos vértices y vértices con varios padres. El grafo de dependencias y el de claves foráneas suelen ser DAG, no árboles.

### 6.3 Los grafos del proyecto

**Grafo de claves foráneas.** Las tablas son vértices y las claves foráneas son aristas dirigidas desde la tabla que referencia hacia la tabla referenciada:

- `citas → pacientes`, `citas → medicos` (`db.php:394-395`).
- `notificaciones → citas`, `notificaciones → usuarios` (`db.php:460-461`).
- `usuarios → pacientes`, `usuarios → medicos` con `ON DELETE SET NULL` (`db.php:292-293`).
- `disponibilidades → medicos` (`db.php:359`).
- `triages → pacientes` (`db.php:228`).

`PRAGMA foreign_keys = ON` (`db.php:141`) hace que cada arista apunte a un vértice existente: es la integridad referencial. No hay ciclos en este esquema; si los hubiera con `ON DELETE CASCADE`, borrar un vértice podría recorrer un ciclo. SQLite maneja la recursión, pero el diseño evita el problema.

**Borrado en cascada = recorrido de un árbol invertido.** Las aristas apuntan del hijo al padre. Al borrar un paciente, `ON DELETE CASCADE` borra sus citas y sus triages; las citas borran sus notificaciones. El motor recorre las aristas en sentido inverso (padre → hijos), como un recorrido en profundidad de un árbol con raíz en el paciente. No es un árbol en el sentido estricto si un hijo tiene dos padres (por ejemplo, una notificación referencia una cita y un usuario), pero el recorrido de cascada sigue siendo un recorrido de grafo.

**Índices B-tree.** Los índices de SQLite son árboles B, no árboles binarios. Un B-tree mantiene las claves ordenadas y permite búsquedas, inserciones y borrados en `O(log n)`, con un factor de ramificación alto para reducir la altura y los accesos a disco. `idx_citas_medico_dia` (`db.php:410`) acelera las consultas por médico y fecha; `idx_pacientes_nombre` (`db.php:221`) acelera los listados ordenados por nombre; `idx_citas_estado_fecha` (`db.php:411`) acelera los filtros por estado y fecha. La tabla en sí también es un B-tree keyed by `rowid` (salvo `WITHOUT ROWID`). El índice de eventos (`db.php:517-520`) usa `(canal, id)` para que el sondeo/SSE lea solo lo nuevo desde el último id.

**Árbol de archivos.** `storage/` es un árbol: `saludweb.sqlite`, `secret.key`, `logs/` y `rate/`. `core/Secret.php:61` crea directorios de forma recursiva con `mkdir(..., 0775, true)`, que es una operación típica de árbol: crear el camino completo hasta la hoja. Borrar `storage/` en producción sería un recorrido destructivo de todo el árbol; por eso la carpeta no se versiona (`db.php:97-99`) y se protege.

**Árbol de componentes y rutas.** React organiza la interfaz como un árbol de componentes. `src/App.jsx:164-308` define un árbol de rutas: `BrowserRouter` → `Routes` → cada `Route`. `ProtectedRoute` (`:58`) y `AdminRoute` (`:110`) son nodos que envuelven páginas; si la condición falla, redirigen. No es un grafo de navegación arbitrario: la jerarquía de layouts y guardas es un árbol.

**Grafo de dependencias.** `composer.json`, `package.json` y sus lockfiles forman un DAG: un paquete depende de otros, y puede haber dependencias compartidas. Los ciclos son problemáticos; los gestores los detectan o los resuelven. El orden de instalación es un orden topológico del DAG. Lo mismo vale para el orden de creación de tablas en `db.php`: primero las tablas referenciadas, después las que las referencian, para que las claves foráneas encuentren su destino.

**Lo que el proyecto no hace.** No implementa Dijkstra, BFS ni DFS propios. El recorrido en cascada y la búsqueda en el B-tree los hace SQLite. No hay optimización de rutas ni cálculo de caminos mínimos. Decir que el proyecto “usa Dijkstra” sería inventar una relación que no existe.

### 6.4 Ejercicios resueltos de árboles

**1.** Grafo con aristas `(A,B)`, `(A,C)`, `(B,D)`, `(B,E)`, `(C,F)`, `(C,G)`.

- Vértices: `A, B, C, D, E, F, G` → 7.
- Aristas: 6 → `n − 1`.
- Es conexo: desde `A` se llega a todos.
- Es acíclico: no hay camino que vuelva a un vértice ya visitado.
- Por lo tanto, es un árbol. Raíz `A`; nivel 0: `A`; nivel 1: `B, C`; nivel 2: `D, E, F, G`. Hojas: `D, E, F, G`. Altura: 2.

**2.** Con raíz `A`: padre de `B` y `C` es `A`; padre de `D` y `E` es `B`; padre de `F` y `G` es `C`. Hijos: `A → B, C`; `B → D, E`; `C → F, G`. Las hojas no tienen hijos.

**3.** Sí, cumple `n = 7` y `n − 1 = 6` aristas, es conexo y acíclico.

**4.** Un grafo que no es árbol: `V = {A, B, C, D}`, `E = {(A,B), (B,C), (C,A)}`. Tiene 4 vértices y 3 aristas (`n − 1`), pero el triángulo `A-B-C-A` es un ciclo y `D` está aislado: no es conexo ni acíclico. Otro ejemplo: `V = {A, B, C, D, E, F}`, `E = {(A,B), (B,C), (C,A), (D,E), (E,F)}`; tiene 6 vértices y 5 aristas, pero el triángulo `A-B-C-A` es un ciclo y el componente `D-E-F` está separado. La lección: `n − 1` aristas no alcanza si falta conexión o sobra ciclo.

**5.** Jerarquía: Directora → Vicedirectora, Jefa de Área; Jefa de Área → Docente 1, Docente 2; Vicedirectora → Preceptor.

- Vértices: 6. Aristas: 5. Conexo y acíclico: árbol.
- Raíz: Directora.
- Nivel 1: Vicedirectora, Jefa de Área.
- Nivel 2: Preceptor, Docente 1, Docente 2.
- Hojas: Preceptor, Docente 1, Docente 2.
- Altura: 2.

**6.** Matriz de adyacencia con orden `[Directora, Vicedirectora, Jefa, Docente1, Docente2, Preceptor]`:

```
              D  V  J  D1 D2 P
Directora   [ 0  1  1  0  0  0 ]
Vicedirect. [ 0  0  0  0  0  1 ]
Jefa        [ 0  0  0  1  1  0 ]
Docente1    [ 0  0  0  0  0  0 ]
Docente2    [ 0  0  0  0  0  0 ]
Preceptor   [ 0  0  0  0  0  0 ]
```

`A²` cuenta caminos de longitud 2. La fila de Directora queda `[0,0,0,1,1,1]`: llega a Docente1 y Docente2 por Jefa, y a Preceptor por Vicedirectora. Las demás filas son cero porque los nodos intermedios son hojas. Es la comprobación de que la altura es 2.

**Recorridos.** Preorden desde la raíz: Directora, Vicedirectora, Preceptor, Jefa, Docente1, Docente2. Postorden: Preceptor, Vicedirectora, Docente1, Docente2, Jefa, Directora. Por niveles (BFS): Directora; Vicedirectora, Jefa; Preceptor, Docente1, Docente2.

### 6.5 Matriz del grafo de claves foráneas

Orden `[pacientes, medicos, citas, notificaciones, usuarios]`. Aristas: `citas → pacientes`, `citas → medicos`, `notificaciones → citas`, `notificaciones → usuarios`, `usuarios → pacientes`, `usuarios → medicos`.

```
                 pac  med  cit  not  usu
pacientes      [  0    0    0    0    0 ]
medicos        [  0    0    0    0    0 ]
citas          [  1    1    0    0    0 ]
notificaciones [  0    0    1    0    1 ]
usuarios       [  1    1    0    0    0 ]
```

`A²` (caminos de longitud 2):

```
                 pac  med  cit  not  usu
pacientes      [  0    0    0    0    0 ]
medicos        [  0    0    0    0    0 ]
citas          [  0    0    0    0    0 ]
notificaciones [  2    2    0    0    0 ]
usuarios       [  0    0    0    0    0 ]
```

La fila de `notificaciones` tiene dos caminos a `pacientes`: `notificación → cita → paciente` y `notificación → usuario → paciente`. También dos a `medicos`: `notificación → cita → médico` y `notificación → usuario → médico`. La matriz cuenta **caminos**, no destinos distintos; por eso el valor es 2 y no 1. Esa es la diferencia entre “hay dos formas de llegar” y “hay dos pacientes”.

---

## 7. Observaciones y correcciones al material

1. **Símbolos deformados.** El texto pegado perdió los símbolos lógicos y de conjuntos (`³`, `'`, `(`, `·`, `c`, `¢`, `ª`, `b`). La tabla de la sección 1 permite reconstruirlos. Sin esa reconstrucción, las fórmulas no se pueden leer.
2. **Suma binaria.** `1101₂ + 1101₂ = 11010₂` (26), no `11000₂` (24). El acarreo final no se descarta si no hay restricción de ancho.
3. **Notación de la resta.** “A + (2B)” no es una suma del doble de B; es `A + (−B)`, donde `−B` es el complemento a dos de B.
4. **Subconjunto y subconjunto propio.** Algunos textos usan `⊂` para “subconjunto” y otros para “subconjunto propio”. En este documento `⊆` es subconjunto y `⊂` es propio. La distinción importa en la doble inclusión.
5. **Codominio y rango.** En una relación `R ⊆ A × B`, el codominio es `B`; la imagen o rango es el subconjunto de `B` formado por los elementos que aparecen en algún par. No son lo mismo.
6. **Propiedades de las relaciones.** Reflexividad, simetría, antisimetría y transitividad se definen para `R ⊆ A × A`, no para una relación arbitraria `A × B`. Una relación entre conjuntos distintos puede ser invertible o funcional, pero no reflexiva.
7. **Suma de matrices de adyacencia.** La suma aritmética no es la unión de grafos; para la unión se usa el OR lógico. La suma cuenta aristas y solo coincide con la unión si no hay aristas repetidas.
8. **`n − 1` aristas no alcanza.** Un grafo con `n` vértices y `n − 1` aristas puede tener un ciclo y estar desconectado. Para afirmar que es árbol hay que verificar conexión y aciclicidad (o una de las dos más el conteo, según el teorema que se use).
9. **“O” inclusivo y exclusivo.** En lógica, `∨` es inclusivo por defecto. El “o” del lenguaje natural a veces es exclusivo; si se necesita exclusividad, se usa `⊕` y se aclara.
10. **XOR de más de dos.** `p ⊕ q ⊕ r` es verdadera con una cantidad impar de verdaderas, no con “exactamente una”. Para “exactamente una” hay que escribir la disyunción de los casos o usar cardinalidad.
11. **Condicional y causalidad.** `p → q` no afirma que p cause q. La verdad vacua (`F → V`, `F → F`) es correcta en lógica material, aunque suene extraña en el lenguaje natural.
12. **Prueba por ejemplo.** Un ejemplo no demuestra una proposición universal; un contraejemplo sí la refuta. Las pruebas del proyecto son evidencia, no demostración formal.
13. **SQL y `NULL`.** La lógica de SQL es de tres valores. `NULL = NULL` no es verdadero; `NOT IN` con `NULL` puede fallar; `NULL` no es duplicado en un índice único. El esquema de citas depende de esa última propiedad.
14. **401 no es sinónimo de “token inválido”.** También corresponde a token ausente o expirado. La respuesta del proyecto unifica el mensaje por seguridad, pero los logs distinguen los casos (`AuthMiddleware.php:41-52`, `:63-75`).
15. **El grafo de claves foráneas no es un árbol.** Es un DAG con vértices que pueden tener varios padres (por ejemplo, una notificación referencia una cita y un usuario). El borrado en cascada recorre un grafo, no un árbol estricto.

---

## 8. Trazabilidad: concepto, evidencia y aplicación

| Concepto | Evidencia en el proyecto | Aplicación |
|---|---|---|
| Base decimal, IDs | `db.php:203`, `:212`, `:236`, `:286` | Claves primarias enteras |
| Límite de 64 bits / JS | `db.php` (INTEGER), `client.js` (JSON) | IDs seguros hasta `2^53 − 1` en el navegador |
| Hexadecimal en colores | `src/tokens.css:66`; `ver_contraste.php:15-32` | Contraste WCAG |
| Hexadecimal en trazabilidad | `correlationId.js:35-38`, `:74-94`; `client.js:52` | 8 bytes = 16 dígitos = 64 bits |
| Octal en permisos | `core/Secret.php:61`; `core/RateLimiter.php:39`; `core/Logger.php:457` | `0775` → `rwx rwx r-x` |
| Base64url | `services/AuthService.php:428-430` | Codificación del JWT, no base posicional |
| Fechas ISO | `db.php:396-397`; `CitaRepository.php:74` | Orden lexicográfico = cronológico |
| División entera / paginación | `CitaService.php:402-403`; `MedicoService.php:77-78`; `PacienteService.php:53-54`; `PrescripcionService.php:55-56`; `UsuarioRepository.php:154-157` | `ceil(total/10)` y `offset` |
| Autenticación y autorización | `AuthMiddleware.php:8-9`, `:41-52`, `:63-75`, `:86-106` | `401` vs `403` |
| Condicional y contrapositivo | `AuthMiddleware.php`; `CitaController.php:91-116` | La premisa “token válido → procesa” es falsa |
| De Morgan | `AuthMiddleware.php:92`; condiciones de controladores | Negar una lista de roles |
| Falacia del consecuente | `AuthMiddleware.php:41-52` | `401` no implica token inválido |
| Conjunto de roles | `db.php:285-296` | `CHECK` y función usuario → rol |
| Conjunto de estados | `CitaService.php:853-855`, `:1084-1090` | Partición activos/finales |
| Diferencia de conjuntos | `CitaService.php:129-140`, `:196-200` | Slots disponibles = todos − ocupados |
| `UNION ALL` | `db.php:636-637` | Conjunto de días `{1..5}` |
| `CROSS JOIN` | `db.php:632-642` | Producto `medicos × días` |
| `NOT EXISTS` | `db.php:639-642` | Diferencia para inserción idempotente |
| `array_intersect` | `migrar_mysql_a_sqlite.php:85`, `:186` | Intersección de esquemas |
| Relación paciente–slot | `db.php:392-408` | `UNIQUE` como función parcial |
| Relación de equivalencia | `db.php:331-336`; `especialidades` | Misma especialidad |
| Orden de triage | `db.php:229` | `nivel_gravedad` y desempate |
| Claves foráneas | `db.php:141`, `:228`, `:263`, `:292-293`, `:359`, `:394-395`, `:460` | Integridad y cascada |
| Índices B-tree | `db.php:221`, `:409-411`, `:517-520` | Búsquedas `O(log n)` |
| Árbol de archivos | `core/Secret.php:61`; `db.php:97-99` | `storage/` no versionado |
| Árbol de rutas | `src/App.jsx:164-308`; `:58`, `:110` | `ProtectedRoute` y `AdminRoute` |
| Grafo de dependencias | `composer.json`, `package.json` | Orden topológico de instalación |

---

## 9. Verificación

- Este documento es solo documentación: no se modificó código de Backend, Web ni Mobile.
- Las referencias `archivo:línea` se verificaron contra el estado actual de los tres repositorios en la rama `dev`.
- Los cálculos de conjuntos, relaciones, tablas de verdad y matrices se resolvieron a mano y se contrastaron con los ejemplos del proyecto.
- La afirmación “no se modificó el código” se puede comprobar con `git diff --stat`: el único archivo nuevo es este documento, más los enlaces en los README y el registro de cambios.

---

## 10. Bibliografía

- Aab, Erika R. *Sistemas de numeración, lógica proposicional, conjuntos, relaciones, matrices, grafos y árboles*. Material de cátedra, UTN FR Paraná, Tecnicatura en Programación.
- Rosen, Kenneth H. *Matemática discreta y sus aplicaciones*. McGraw-Hill. Referencia general para los temas de lógica, conjuntos, relaciones y grafos.
- Documentación de SQLite: tipos de datos, índices B-tree, claves foráneas y semántica de `NULL`. <https://www.sqlite.org/docs.html>
- Documentación de PHP: enteros, `ceil()`, `array_intersect()`, `password_hash()` y `base64_decode()`. <https://www.php.net/manual/es/>
- Documentación de React Router: rutas anidadas y guardas. <https://reactrouter.com/>
- Documentos del proyecto: `ARQUITECTURA_Y_SISTEMAS_OPERATIVOS_APLICADO.md`, `TRABAJO_PRACTICO_2_E_INTEGRADOR_APLICADO.md`, `PREPARAR_SISTEMA_PARA_PRODUCCION_Y_DEFENSA.md` y `DOCUMENTAR_Y_PUBLICAR_EL_CAMINO_AL_EXITO.md`.
