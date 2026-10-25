# Arquitectura y Sistemas Operativos aplicada a SaludWEB

Trabajo práctico de la cátedra **Arquitectura y Sistemas Operativos** (docente Gabriela Haberkorn, entrega 23/04/2025) aplicado al proyecto SaludWEB.

Este documento no reemplaza la entrega del TP: la complementa. Cada concepto se explica y después se vincula con una parte concreta del sistema, con el archivo y la línea donde se puede verificar. No se reproduce el contenido de la bibliografía: se la cita al final.

> **Aviso de revisión.** El proyecto no tiene problemas respecto de los conceptos del TP. El TP, en cambio, tenía errores en el ejercicio de planificación (consigna 20) y en la conclusión (consigna 21): los promedios no respetaban los tiempos de llegada y la conclusión elegía SJF cuando, con los datos dados, el mejor tiempo de espera promedio lo obtiene SRTF. Las tablas corregidas están en la sección 4.

---

## 1. Cómo se lee este documento

Las consignas se agrupan en cuatro bloques:

1. **Arquitectura** (consignas 1 a 5): ciclo de instrucción, Von Neumann, Intel 8086 y tipos de instrucciones.
2. **Sistema operativo y memoria** (consignas 6 a 10): funciones del SO, relación con el hardware, jerarquía de memoria, modelos de ejecución y llamadas al sistema.
3. **Procesos y concurrencia** (consignas 11 a 17 y 19): PCB, proceso, estados, concurrencia, métricas y deadlock. En la entrega no aparece la consigna 18.
4. **Planificación** (consignas 20 y 21): algoritmos FCFS, SJF, SRTF, Round Robin y prioridad, con las tablas corregidas y la conclusión.

La evidencia se cita como `archivo:línea`. Cuando la relación es una analogía y no una equivalencia literal, se aclara: un pedido HTTP no es un proceso del sistema operativo, y el proyecto no es un planificador. La comparación sirve para entender qué problema resuelve cada mecanismo y cómo se resuelve el mismo problema en la aplicación.

---

## 2. Arquitectura: del ciclo de instrucción al ciclo de una petición

### 2.1 Ciclo de instrucción (consigna 1)

La respuesta de la entrega menciona tres etapas: **fetch**, **decode** y **execute**. Faltan dos, que son las que explican por qué el resultado persiste y por qué el sistema atiende varias cosas:

1. **Fetch**: la CPU trae la instrucción desde memoria. El registro PC indica dónde está.
2. **Decode**: la unidad de control interpreta la instrucción (qué operación, qué operandos).
3. **Execute**: la ALU o la unidad correspondiente la ejecuta.
4. **Write-back**: el resultado se escribe en un registro o en memoria.
5. **Chequeo de interrupciones**: entre instrucciones, la CPU revisa si hay un evento pendiente (teclado, disco, red) y atiende al manejador antes de continuar.

En SaludWEB el equivalente es el ciclo de una petición HTTP:

| Etapa | Qué hace la CPU | Equivalente en SaludWEB | Evidencia |
|---|---|---|---|
| Fetch | trae la instrucción de memoria | `.htaccess` redirige todo a `index.php`; se carga `routes.php` y el autoload | `index.php:7` |
| Decode | interpreta la instrucción | el router lee método y URI y elige la ruta y el controlador | `index.php:11` |
| Execute | ejecuta la operación | el controlador valida la entrada y el servicio aplica las reglas de negocio | `controllers/CitaController.php`, `services/CitaService.php` |
| Write-back | guarda el resultado | el repositorio escribe en SQLite y se arma la respuesta JSON | `persistence/` |
| Interrupción | atiende un evento pendiente | la próxima petición que espera en la cola del servidor | cola de PHP-FPM |

**Por qué importa la etapa que falta.** Sin *write-back* no hay persistencia: el turno se calcula, se responde "ok" y se pierde. En el proyecto el write-back es el `INSERT` en `citas`; sin él, la confirmación al paciente sería falsa. Y sin el chequeo de interrupciones no se explica cómo el servidor alterna entre pedidos: en desarrollo `php -S` no alterna (un solo hilo), y por eso el panel usa sondeo en lugar de SSE (ver 2.6).

### 2.2 Modelo de Von Neumann (consigna 2)

Von Neumann describe una computadora con **una sola memoria** para instrucciones y datos, una CPU que las lee por el mismo bus y un ciclo fetch-decode-execute. Su límite es el **cuello de botella**: buscar la instrucción y buscar el dato compiten por el mismo camino. Las arquitecturas modernas lo mitigan con cachés y con cierto paralelismo, pero el modelo sigue siendo la base conceptual.

En el proyecto la misma idea aparece en las capas:

- **Instrucciones** = código PHP y JavaScript.
- **Datos** = filas de SQLite, archivos de `storage/`, respuestas JSON.
- **Bus** = CPU, disco y red, que son recursos compartidos.

El cuello de botella se mueve según la operación: una consulta sin índice recorre la tabla completa (disco), una respuesta con miles de filas satura la red, y un servidor de un solo hilo encola los pedidos (CPU). Las mitigaciones concretas son índices (`db.php:221`, `db.php:409`), paginación (`POR_PAGINA = 10` en `SaludWEB_Mobile/src/config.js`) y WAL (`db.php:154`). La analogía tiene un límite: en Von Neumann el cuello está en el bus; acá suele estar en la base o en el proceso PHP que atiende.

### 2.3 Intel 8086 (consignas 3 y 4)

El 8086 separa la **BIU** (Bus Interface Unit: busca instrucciones y datos) de la **EU** (Execution Unit: decodifica y ejecuta), tiene registros de 16 bits, segmentación para direccionar 20 bits (1 MB) y una cola de instrucciones que permite solapar la búsqueda con la ejecución. Su importancia histórica es haber fijado la línea x86 y el estándar de la PC, con compatibilidad hacia atrás que llega hasta los procesadores actuales.

Aplicado al proyecto, la separación BIU/EU se parece a la división entre **transporte** y **lógica**:

- La BIU "busca y decodifica" el pedido: `routes.php` y los controladores leen método, ruta, token y cuerpo.
- La EU "ejecuta": los servicios aplican reglas (disponibilidad, solapamiento, anticipación) y los repositorios hablan con SQLite.

El solapamiento de la cola del 8086 se parece a la multiprogramación: mientras una petición espera al disco, otra puede usar la CPU. En desarrollo eso no ocurre porque `php -S` es de un solo hilo; en producción se resuelve con PHP-FPM y varios workers. La compatibilidad hacia atrás se parece a mantener una sola API para dos clientes distintos (Web y Mobile): cambia el cliente, no el contrato.

### 2.4 Tipos de instrucciones (consigna 5)

La clasificación habitual es: **transferencia de datos** (MOV, LOAD, STORE), **aritmética y lógica** (ADD, SUB, AND), **control de flujo** (JMP, CALL, RET, saltos condicionales) y **entrada/salida** (IN, OUT). En el 8086 además hay instrucciones de cadena y de manejo de pila.

No hay una traducción literal al proyecto, pero la misma clasificación ayuda a leer el código:

| Tipo | En el 8086 | En SaludWEB |
|---|---|---|
| Transferencia | MOV, PUSH, POP | asignaciones, paso de parámetros, `json_encode` |
| Aritmética/lógica | ADD, CMP, AND | cálculos de horarios, comparación de fechas, validaciones |
| Control de flujo | JMP, CALL, RET | `if`, `switch`, llamadas a servicios, retorno de controladores |
| Entrada/salida | IN, OUT | lectura de `$_SERVER`, `php://input`, escritura de la respuesta |

La diferencia importante es que el programador de PHP no emite instrucciones de máquina: escribe código que el intérprete traduce. Aun así, el costo de un `SELECT` sin índice o de un `foreach` sobre miles de filas se explica con las mismas ideas de acceso a memoria y de operaciones por elemento.

---

## 3. Sistema operativo, memoria y modelos de ejecución

### 3.1 Qué es un sistema operativo (consigna 6)

El SO es el software que administra los recursos del hardware (CPU, memoria, disco, dispositivos) y ofrece abstracciones para que los programas no hablen directamente con el hardware: procesos, archivos, sockets, memoria virtual. Cumple dos roles a la vez: **gestor de recursos** y **máquina extendida**.

SaludWEB no incluye un SO: corre **sobre** uno. El backend es un proceso PHP que pide archivos, sockets y tiempo; la base SQLite es una biblioteca que escribe un archivo; la web es JavaScript que corre dentro del navegador; la app móvil corre sobre Android o iOS. El SO es el que decide cuándo corre cada proceso, cuánta memoria le da y qué permisos tiene sobre los archivos de `storage/`.

### 3.2 Relación SO-hardware (consigna 7)

El SO media entre la aplicación y el hardware mediante **drivers** y **llamadas al sistema**. La aplicación no programa el disco: pide "abrí este archivo" o "enviá estos bytes por el socket".

| Necesidad | Llamada al sistema (idea) | Uso en SaludWEB |
|---|---|---|
| Abrir/leer/escribir archivos | `open`, `read`, `write` | logs, `storage/secret.key`, archivos temporales |
| Sockets | `socket`, `connect`, `send` | API HTTP, canal SSE, envío de notificaciones |
| Tiempo | `sleep`, `nanosleep` | `RealtimeController` espera entre consultas (`controllers/RealtimeController.php:364`) |
| Memoria | `brk`, `mmap` | memoria del proceso PHP y del navegador |
| Procesos | `fork`, `exec` | PHP-FPM crea workers; en desarrollo se lanza `php -S` a mano |

**Matiz importante:** SQLite no es una llamada al sistema. Es una biblioteca que abre un archivo y lo lee/escribe; el SO solo ve operaciones de archivo. Por eso dos procesos que escriben el mismo `.sqlite` compiten por un lock de archivo, y por eso existen `busy_timeout` y WAL.

### 3.3 Jerarquía de memoria (consigna 8)

La jerarquía ordena por velocidad y costo: **registros → caché (L1/L2/L3) → RAM → SSD/HDD → red**. Cada nivel es más grande y más lento; el rendimiento depende de la **localidad** (usar datos cercanos en tiempo y espacio).

| Nivel | En el proyecto |
|---|---|
| Registros/caché de CPU | variables y estructuras del proceso PHP; opcache si está habilitado |
| RAM | proceso PHP, proceso Node de Vite, navegador |
| Caché de página del SO | archivos de SQLite y logs ya leídos |
| Disco | `storage/saludweb.sqlite`, `.sqlite-wal`, logs |
| Red | API entre Web/Mobile y backend; respuestas JSON |
| Caché del cliente | service worker y caché de la PWA; datos ya cargados en React |

Consecuencias visibles: un índice hace que la consulta lea menos páginas (mejor localidad en disco); la paginación reduce el volumen que viaja por red; el `timeout` de 15 s evita que un pedido quede esperando indefinidamente (`SaludWEB_Web/src/api/client.js:46`, `SaludWEB_Mobile/src/config.js`); el sondeo cada 5 s cambia latencia por tráfico.

### 3.4 Batch, multiprogramación y multitarea (consigna 9)

- **Batch**: se ejecutan trabajos sin interacción, uno detrás de otro; el objetivo es terminar el lote.
- **Multiprogramación**: varios trabajos en memoria y la CPU pasa al siguiente cuando el actual se bloquea (por ejemplo, espera disco). El objetivo es no dejar la CPU ociosa.
- **Multitarea (time-sharing)**: varios usuarios/procesos interactivos comparten la CPU por turnos (quantum); el objetivo es la respuesta rápida y la sensación de simultaneidad.

En SaludWEB conviven los tres:

- **Batch**: `sembrar_datos_demo.php`, `seed_pacientes.php`, `migrar_mysql_a_sqlite.php` y la generación de recordatorios. Se ejecutan a pedido o por tarea programada, sin usuario esperando.
- **Multiprogramación**: PHP-FPM con varios workers atiende pedidos distintos; mientras uno espera a SQLite, otro usa CPU.
- **Multitarea**: el navegador y el celular mantienen la interfaz viva mientras llegan respuestas; la API tiene timeouts para no bloquear la interacción.

**El caso de desarrollo es la excepción documentada.** `php -S` atiende un pedido a la vez. Si el panel abriera una conexión SSE larga (`/api/eventos`), esa conexión ocuparía el único hilo y el resto de la API quedaría esperando. Por eso `useEventosRealtime.js` usa sondeo cada 5 s cuando `import.meta.env.DEV` es verdadero (`SaludWEB_Web/src/hooks/useEventosRealtime.js:33`, `:36`, `:147-157`). En producción, con PHP-FPM, el canal SSE real es viable; el script `verificar_tiempo_real.mjs` lo comprueba contra Apache/nginx + PHP-FPM.

### 3.5 Procesos, PCB y tabla de procesos (consigna 11)

El **PCB** (Process Control Block) es la estructura que el SO mantiene **por proceso**: identificador (PID), estado, program counter, registros, límites de memoria, archivos abiertos, prioridad y contabilidad de CPU. La **tabla de procesos** es la colección de todos los PCB; no es un PCB único ni una tabla de la aplicación.

La entrega mezclaba ambos: hablaba de "tabla de procesos" como si fuera el PCB. La corrección es simple: el PCB describe **un** proceso; la tabla los agrupa; y las tablas `pacientes`, `citas` o `usuarios` son datos del dominio, no estructuras del SO.

En el proyecto, el equivalente conceptual es el **contexto de un pedido**: quién lo hace (JWT), qué pide (ruta y cuerpo), qué lleva hecho (tiempo, logs) y en qué estado está (en cola, ejecutando, esperando la base, respondido). El `X-Correlation-Id` que envía el cliente (`client.js:52`) permite seguir ese contexto en los logs, igual que el PID permite seguir un proceso.

### 3.6 Proceso y programa (consigna 12)

Un **programa** es código estático almacenado (un archivo, un binario, un repositorio). Un **proceso** es ese programa en ejecución, con estado propio: memoria, registros, archivos abiertos y un ciclo de vida. El mismo programa puede dar lugar a muchos procesos.

En SaludWEB: el código de `CitaService.php` es el programa; cada pedido que lo ejecuta es una instancia con datos distintos (paciente, médico, fecha). El repositorio es estático; el pedido que corre tiene estado. Del mismo modo, `php -S` puede atender un pedido por vez, pero el programa es el mismo.

### 3.7 Estados y transiciones (consignas 13 y 14)

Estados clásicos: **nuevo**, **listo**, **ejecutando**, **bloqueado/en espera** y **terminado**. Transiciones: admitido (nuevo → listo), despachado (listo → ejecutando), expropiado o por quantum (ejecutando → listo), espera por E/S (ejecutando → bloqueado), fin de E/S (bloqueado → listo) y salida (ejecutando → terminado).

| Estado del SO | Equivalente en un pedido HTTP |
|---|---|
| Nuevo | la conexión llegó y el servidor la aceptó |
| Listo | está en la cola de PHP-FPM esperando un worker |
| Ejecutando | un worker está corriendo el controlador/servicio |
| Bloqueado | espera la respuesta de SQLite o de un servicio externo |
| Terminado | se envió la respuesta y se liberó el worker |

La transición clave para el rendimiento es **ejecutando → bloqueado**: si el pedido espera la base, el worker queda ocupado. Con pocos workers, muchos pedidos bloqueados agotan el pool; por eso importan los índices, las consultas acotadas y los timeouts.

### 3.8 Concurrencia (consigna 15)

La concurrencia es la existencia de varias actividades en progreso a la vez, aunque la CPU física ejecute una por vez. Introduce problemas clásicos: **condiciones de carrera**, **exclusión mutua**, **inanición** y **deadlock**.

En el proyecto el caso concreto es la doble reserva: dos pacientes eligen el mismo médico, fecha y hora al mismo tiempo. La aplicación valida y devuelve un mensaje claro, pero la garantía real está en la base: el índice `UNIQUE (id_medico, fecha, hora, slot_reservado)` (`db.php:407`) hace que solo una de las dos inserciones prospere, aun en el mismo milisegundo. Es exactamente la idea de exclusión mutua, resuelta por el motor y no por una bandera en memoria.

### 3.9 Métricas de planificación (consigna 16)

Tres métricas que la entrega no distinguía con precisión:

- **Tiempo de retorno (turnaround, TAT)**: desde que el proceso llega hasta que termina. `TAT = fin - llegada`.
- **Tiempo de espera**: todo el tiempo que estuvo en cola, sin usar CPU. `espera = TAT - ráfaga`.
- **Tiempo de respuesta**: desde que llega hasta su **primera** ejecución. `respuesta = primer inicio - llegada`.

En un algoritmo no expropiativo, espera y respuesta coinciden (el proceso corre de una sola vez). En uno expropiativo no: un proceso puede esperar varias veces, pero su respuesta se mide solo con la primera vez que recibe CPU.

Aplicado al proyecto: el **retorno** es el tiempo total de un pedido (desde que sale del cliente hasta que llega la respuesta completa); la **espera** es el tiempo en cola del servidor o del pool; la **respuesta** es el tiempo hasta el primer byte. El cliente corta a los 15 s y el panel refresca cada 5 s: son decisiones de planificación y de timeout, no de lógica de negocio.

### 3.10 Deadlock (consigna 19)

Un deadlock requiere las cuatro condiciones de Coffman a la vez: **exclusión mutua**, **retención y espera**, **no expropiación** y **espera circular**. Se enfrenta con prevención (romper una condición), evitación (asignación segura), detección y recuperación, o simplemente con timeouts.

En SaludWEB:

- SQLite permite **un escritor a la vez**. Con WAL, las lecturas no bloquean al escritor (`db.php:154`), y `busy_timeout = 5000` hace que la segunda conexión espere hasta 5 s en lugar de fallar de inmediato (`db.php:161`). Ese "database is locked" es **contención de lock**, no necesariamente un deadlock.
- Un deadlock real necesitaría dos conexiones esperando recursos que la otra retiene, en ciclo. El proyecto lo evita manteniendo transacciones cortas (`migrar_mysql_a_sqlite.php:201`), sin locks anidados y con un orden fijo de acceso.
- La doble reserva no se resuelve con locks de aplicación sino con la restricción `UNIQUE` (`db.php:407`), que es más simple y no puede quedar desactualizada.
- Los avisos fallidos se pueden reintentar (`NotificacionService::reintentar`), lo que evita que un fallo transitorio se convierta en pérdida definitiva.

---

## 4. Ejercicio de planificación corregido (consigna 20)

### 4.1 Datos y criterios

| Proceso | Llegada | Ráfaga | Prioridad |
|---|---|---|---|
| A | 0 | 8 | 2 |
| B | 1 | 4 | 1 |
| C | 2 | 9 | 3 |
| D | 3 | 5 | 2 |

Criterios usados: menor número = mayor prioridad; en empate de prioridad se atiende al que llegó antes; Round Robin con quantum 2; los tiempos se cuentan en unidades enteras.

### 4.2 Por qué las tablas de la entrega no cerraban

- En SJF y en prioridad no expropiativa, la entrega hizo empezar B en el instante 0, pero B **llega en 1**. Un proceso no puede ejecutarse antes de llegar.
- En SRTF, el diagrama mostraba expropiaciones, pero los totales de espera y retorno no coincidían con esos intervalos.
- En Round Robin, el orden de la cola no respetaba las llegadas de C y D.
- La conclusión comparaba promedios calculados con esos errores.

Si el enunciado hubiera pedido suponer que **todos llegan en 0**, los resultados de SJF y prioridad no expropiativa coincidirían con los de la entrega; pero como el ejercicio da tiempos de llegada y FCFS los usa, corresponde respetarlos en todos los algoritmos. Esa es la corrección de fondo.

### 4.3 FCFS (por orden de llegada)

Orden: A (0-8), B (8-12), C (12-21), D (21-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 8 | 8 | 0 | 0 |
| B | 1 | 4 | 8 | 12 | 11 | 7 | 7 |
| C | 2 | 9 | 12 | 21 | 19 | 10 | 10 |
| D | 3 | 5 | 21 | 26 | 23 | 18 | 18 |
| **Promedio** | | | | | **15,25** | **8,75** | **8,75** |

### 4.4 SJF no expropiativo

Al llegar el primer proceso solo está A, así que corre completo. Después se elige la ráfaga más corta entre los que ya llegaron: B (4), D (5), C (9).

Orden: A (0-8), B (8-12), D (12-17), C (17-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 8 | 8 | 0 | 0 |
| B | 1 | 4 | 8 | 12 | 11 | 7 | 7 |
| D | 3 | 5 | 12 | 17 | 14 | 9 | 9 |
| C | 2 | 9 | 17 | 26 | 24 | 15 | 15 |
| **Promedio** | | | | | **14,25** | **7,75** | **7,75** |

### 4.5 SRTF (SJF expropiativo)

A corre hasta que llega B (ráfaga 4 menor que lo que le queda a A, 7): B lo expropia. Al terminar B, lo que queda de A (7) es mayor que D (5), así que corre D. Después A y por último C.

Orden: A (0-1), B (1-5), D (5-10), A (10-17), C (17-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 17 | 17 | 9 | 0 |
| B | 1 | 4 | 1 | 5 | 4 | 0 | 0 |
| C | 2 | 9 | 17 | 26 | 24 | 15 | 15 |
| D | 3 | 5 | 5 | 10 | 7 | 2 | 2 |
| **Promedio** | | | | | **13,00** | **6,50** | **4,25** |

### 4.6 Round Robin con quantum 2

Cola respetando llegadas: A corre 0-2; al terminar su quantum se encola detrás de B y C (que llegaron mientras corría). D llega en 3 y se suma detrás. La secuencia completa es:

A (0-2), B (2-4), C (4-6), A (6-8), D (8-10), B (10-12), C (12-14), A (14-16), D (16-18), C (18-20), A (20-22), D (22-23), C (23-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 22 | 22 | 14 | 0 |
| B | 1 | 4 | 2 | 12 | 11 | 7 | 1 |
| C | 2 | 9 | 4 | 26 | 24 | 15 | 2 |
| D | 3 | 5 | 8 | 23 | 20 | 15 | 5 |
| **Promedio** | | | | | **19,25** | **12,75** | **2,00** |

### 4.7 Prioridad expropiativa (menor número = mayor prioridad)

A corre 0-1; llega B (prioridad 1) y lo expropia. B termina en 5. Quedan A (prioridad 2, le restan 7) y D (prioridad 2, 5). Empate: se atiende a A por haber llegado antes. Luego D y C.

Orden: B (1-5), A (5-12), D (12-17), C (17-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 12 | 12 | 4 | 0 |
| B | 1 | 4 | 1 | 5 | 4 | 0 | 0 |
| C | 2 | 9 | 17 | 26 | 24 | 15 | 15 |
| D | 3 | 5 | 12 | 17 | 14 | 9 | 9 |
| **Promedio** | | | | | **13,50** | **7,00** | **6,00** |

### 4.8 Prioridad no expropiativa

A ya está corriendo cuando llega B, y no se lo interrumpe aunque B tenga más prioridad. Al terminar A, se elige por prioridad entre B (1), D (2) y C (3), que coincide con el orden de SJF para estos datos.

Orden: A (0-8), B (8-12), D (12-17), C (17-26).

| Proceso | Llegada | Ráfaga | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 8 | 0 | 8 | 8 | 0 | 0 |
| B | 1 | 4 | 8 | 12 | 11 | 7 | 7 |
| D | 3 | 5 | 12 | 17 | 14 | 9 | 9 |
| C | 2 | 9 | 17 | 26 | 24 | 15 | 15 |
| **Promedio** | | | | | **14,25** | **7,75** | **7,75** |

### 4.9 Comparación y conclusión (consigna 21)

| Algoritmo | Espera promedio | Respuesta promedio | Retorno promedio |
|---|---|---|---|
| FCFS | 8,75 | 8,75 | 15,25 |
| SJF (no expropiativo) | 7,75 | 7,75 | 14,25 |
| SRTF | **6,50** | 4,25 | **13,00** |
| Round Robin (Q = 2) | 12,75 | **2,00** | 19,25 |
| Prioridad expropiativa | 7,00 | 6,00 | 13,50 |
| Prioridad no expropiativa | 7,75 | 7,75 | 14,25 |

Con los datos del enunciado:

- **Mejor tiempo de espera y de retorno promedio: SRTF.** La entrega concluía SJF porque había ignorado las llegadas y sus promedios eran otros.
- **Mejor tiempo de respuesta promedio: Round Robin con quantum 2**, porque todos reciben CPU enseguida; el costo es la espera y el retorno más altos por el cambio de contexto.
- **FCFS** sufre el efecto convoy: los procesos cortos esperan detrás de los largos.
- **SJF y SRTF pueden producir inanición** de los procesos largos si no dejan de llegar procesos cortos. La respuesta de la entrega atribuía la inanición solo a prioridad: también aplica a SJF y SRTF, y a prioridad cuando hay procesos de baja prioridad que nunca se eligen.
- **Prioridad** puede ser expropiativa o no; en este ejercicio la versión no expropiativa coincide con SJF por casualidad de los datos, no por definición.
- La conclusión correcta no es "un algoritmo es mejor": depende de la métrica. SRTF minimiza espera; RR minimiza respuesta; prioridad permite expresar importancia del negocio.

**Aplicación al proyecto.** El planificador del SO no es la aplicación: el backend no decide qué pedido corre primero. Pero las mismas ideas aparecen en el pool de PHP-FPM (varios pedidos listos, workers que los despachan), en el orden de la cola del servidor y en la decisión de usar sondeo cada 5 s (más tráfico, mejor respuesta percibida) en lugar de SSE en desarrollo (menos tráfico, pero bloquea el único hilo).

---

## 5. Observaciones a la entrega del TP

1. **Consigna 1:** agregar *write-back* y el chequeo de interrupciones; el ciclo no termina en "execute" si el resultado debe persistir.
2. **Consigna 11:** distinguir PCB (uno por proceso) de tabla de procesos (la colección) y de las tablas del dominio (`pacientes`, `citas`).
3. **Consigna 18:** no aparece en la entrega; conviene numerarla o dejar constancia de que se omitió.
4. **Consigna 20:** rehacer las tablas respetando las llegadas (sección 4); los totales deben coincidir con los intervalos del diagrama de Gantt.
5. **Consigna 21:** corregir la conclusión: SRTF gana en espera y retorno; RR en respuesta; la inanición no es exclusiva de prioridad.
6. **Métricas:** definir tiempo de respuesta como "llegada a primera ejecución", distinto de espera y de retorno. La entrega usaba "espera" y "respuesta" como sinónimos en varios lugares.
7. **Consigna 3:** el diagrama BIU/EU se entiende mejor si se muestran la cola de instrucciones y los registros de segmento.
8. **Redacción:** "la de Prioridad" conviene reemplazarlo por "la planificación por prioridad".

---

## 6. Por qué el proyecto no tiene problemas con estos conceptos

| Concepto | Cómo está cubierto | Evidencia |
|---|---|---|
| Ciclo y capas | front controller, router, controlador, servicio, repositorio | `index.php`, `routes.php`, `controllers/`, `services/`, `persistence/` |
| Concurrencia y doble reserva | validación en la aplicación + restricción `UNIQUE` en la base | `db.php:407`, `services/CitaService.php` |
| Bloqueos y espera | WAL para lecturas concurrentes, `busy_timeout` de 5 s, transacciones cortas | `db.php:154`, `db.php:161`, `migrar_mysql_a_sqlite.php:201` |
| Memoria y rendimiento | índices, paginación, consultas acotadas, caché de la PWA | `db.php:221-526`, `POR_PAGINA` en Mobile |
| Modelos de ejecución | scripts batch separados de la API; sondeo en desarrollo y SSE con FPM en producción | `useEventosRealtime.js:33-36`, `verificar_tiempo_real.mjs` |
| Llamadas al sistema | uso de archivos, sockets y tiempo a través de PHP/Node | `storage/`, `RealtimeController.php:364` |
| Métricas y timeout | límite de 15 s en Web y Mobile, sondeo cada 5 s, logs con correlación | `client.js:46`, `config.js`, `X-Correlation-Id` |
| Seguridad | autenticación y roles en la aplicación; permisos del SO sobre `storage/` | `AuthMiddleware`, `AuthService`, `.htaccess` |

**Lo que conviene decir en la defensa:**

- El proyecto no implementa un SO: lo usa. La comparación con procesos, PCB y planificación es conceptual y sirve para explicar el comportamiento de la API.
- La limitación de `php -S` (un solo hilo) está detectada y resuelta en desarrollo con sondeo, y en producción con PHP-FPM; no es un error oculto.
- La doble reserva se resuelve donde corresponde: en la base, con una restricción, no con una bandera en memoria.
- Los errores del TP están en los cálculos, no en el proyecto; corregirlos antes de entregar demuestra que se entiende el tema.

---

## 7. Bibliografía

Se usa como referencia conceptual; no se copia su contenido.

- Tanenbaum, A. **Sistemas Operativos Modernos**. Procesos, planificación, memoria y concurrencia.
- Stallings, W. **Sistemas Operativos**. Fundamentos de procesos, CPU y administración de recursos.
- Silberschatz, A. **Fundamentos de Sistemas Operativos**, capítulo 2. Estructuras del SO y llamadas al sistema.
- Vista previa de Reverté (ISBN 9788429194180). Material introductorio de sistemas operativos.
- El enlace a `iestpcontamana.edu.pe` que figuraba en la entrega devuelve **404**; conviene reemplazarlo por una fuente disponible.
