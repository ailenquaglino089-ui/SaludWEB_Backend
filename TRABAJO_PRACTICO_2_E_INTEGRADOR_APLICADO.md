# Trabajos prácticos 2 e Integrador aplicados a SaludWEB

## Carátula

| Dato | Información |
|---|---|
| Universidad/Facultad | UTN Facultad Regional Paraná |
| Carrera | Tecnicatura en Programación |
| Cátedra | Arquitectura y Sistemas Operativos |
| Docente | Gabriela Haberkorn |
| Estudiante | Ailen Adriana Quaglino Fail |
| Lugar | Edificio NIDO Dr. Adolfo Goldenberg, Crespo, Entre Ríos |
| TP2 | Jueves 29/05/2025, 9:00 |
| TP Integrador | Miércoles 02/07/2025, 12:00 |

**TP2.** Criterios: aplicación correcta del algoritmo, respuestas correctas, orden y prolijidad, entrega en tiempo y forma, carátula al inicio y PDF con el nombre `Apellido y Nombre - TP2_AySO` (por ejemplo, `Quaglino Fail Ailen - TP2_AySO.pdf`).

**TP Integrador.** 19 consignas sobre arquitectura, sistemas operativos, memoria, archivos, interrupciones y redes.

Este documento aplica ambos trabajos al proyecto SaludWEB. No reemplaza la entrega: la complementa y deja a la vista la relación entre la teoría y el sistema real.

> **Dos avisos antes de empezar.**
>
> 1. La tabla de procesos del TP2 llegó con los valores desordenados en el texto. Abajo se transcribe tal como llegó y se propone una lectura; los cálculos usan esa lectura y están marcados para confirmar. Si la tabla original dice otra cosa, los resultados cambian.
> 2. La tabla del Algoritmo del Banquero llegó incompleta y mezclada, y no permite reconstruir las matrices con seguridad. Por eso se explica el método completo y se resuelve un ejemplo propio, claramente identificado, sin inventar los datos del TP.

---

## Parte A. TP2

### A.1 Consigna 1: planificación de procesos

#### Datos recibidos y lectura propuesta

La transcripción recibida es:

```
Proceso / Tiempo de llegada / T. CPU
A  0  Prioridad  7
B  2  3  4
C  4  1  1
D  5  4  4
2  2.
```

Lectura propuesta, que es la que se usa en las tablas:

| Proceso | Llegada | CPU | Prioridad |
|---|---|---|---|
| A | 0 | 7 | 2 |
| B | 2 | 3 | 4 |
| C | 4 | 1 | 1 |
| D | 5 | 4 | 4 |

Criterios: menor número = mayor prioridad; en empate se atiende al que llegó antes; Round Robin con quantum 2; tiempos en unidades enteras.

#### Métricas

- **Retorno (TAT)**: desde la llegada hasta la finalización. `TAT = fin - llegada`.
- **Espera**: todo el tiempo en cola. `espera = TAT - CPU`.
- **Respuesta**: desde la llegada hasta la primera ejecución. `respuesta = primer inicio - llegada`.

En algoritmos no expropiativos, espera y respuesta coinciden porque el proceso corre de una sola vez.

#### FCFS (no expropiativo)

Orden: A (0-7), B (7-10), C (10-11), D (11-15).

| Proceso | Llegada | CPU | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 7 | 0 | 7 | 7 | 0 | 0 |
| B | 2 | 3 | 7 | 10 | 8 | 5 | 5 |
| C | 4 | 1 | 10 | 11 | 7 | 6 | 6 |
| D | 5 | 4 | 11 | 15 | 10 | 6 | 6 |
| **Promedio** | | | | | **8,00** | **4,25** | **4,25** |

#### SJF (no expropiativo)

Al inicio solo está A, así que corre completo. Después se elige la ráfaga más corta entre los que ya llegaron: C (1), B (3), D (4).

Orden: A (0-7), C (7-8), B (8-11), D (11-15).

| Proceso | Llegada | CPU | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 7 | 0 | 7 | 7 | 0 | 0 |
| B | 2 | 3 | 8 | 11 | 9 | 6 | 6 |
| C | 4 | 1 | 7 | 8 | 4 | 3 | 3 |
| D | 5 | 4 | 11 | 15 | 10 | 6 | 6 |
| **Promedio** | | | | | **7,50** | **3,75** | **3,75** |

#### SRTF (SJF expropiativo)

A corre 0-2; llega B con 3 unidades, menor que las 5 que le quedan a A, y lo expropia. En 4 llega C con 1 unidad; B tiene 1 restante, así que no se interrumpe (empate; se desempata a favor del que ya está corriendo) y termina en 5. En 5 están A (5), C (1) y D (4): corre C (5-6), luego D (6-10) y por último A (10-15).

| Proceso | Llegada | CPU | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 7 | 0 | 15 | 15 | 8 | 0 |
| B | 2 | 3 | 2 | 5 | 3 | 0 | 0 |
| C | 4 | 1 | 5 | 6 | 2 | 1 | 1 |
| D | 5 | 4 | 6 | 10 | 5 | 1 | 1 |
| **Promedio** | | | | | **6,25** | **2,50** | **0,50** |

#### Round Robin (Q = 2)

Secuencia: A (0-2), B (2-4), A (4-6), C (6-7), B (7-8), D (8-10), A (10-12), D (12-14), A (14-15).

| Proceso | Llegada | CPU | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 7 | 0 | 15 | 15 | 8 | 0 |
| B | 2 | 3 | 2 | 8 | 6 | 3 | 0 |
| C | 4 | 1 | 6 | 7 | 3 | 2 | 2 |
| D | 5 | 4 | 8 | 14 | 9 | 5 | 3 |
| **Promedio** | | | | | **8,25** | **4,50** | **1,25** |

#### Prioridad expropiativa (menor número = mayor prioridad)

A (prioridad 2) corre 0-2; B (4) no lo expropia. A sigue 2-4; C (1) llega en 4 y lo expropia. C termina en 5. Quedan A (2, le restan 5), B (4, 3) y D (4, 4). Corre A (5-10); luego B (10-13) por haber llegado antes que D; luego D (13-17).

| Proceso | Llegada | CPU | Inicio | Fin | Retorno | Espera | Respuesta |
|---|---|---|---|---|---|---|---|
| A | 0 | 7 | 0 | 10 | 10 | 3 | 0 |
| B | 2 | 3 | 10 | 13 | 11 | 8 | 8 |
| C | 4 | 1 | 4 | 5 | 1 | 0 | 0 |
| D | 5 | 4 | 13 | 17 | 12 | 8 | 8 |
| **Promedio** | | | | | **8,50** | **4,75** | **4,00** |

#### Comparación

| Algoritmo | Espera promedio | Respuesta promedio | Retorno promedio |
|---|---|---|---|
| FCFS | 4,25 | 4,25 | 8,00 |
| SJF | 3,75 | 3,75 | 7,50 |
| SRTF | **2,50** | **0,50** | **6,25** |
| Round Robin (Q=2) | 4,50 | 1,25 | 8,25 |
| Prioridad expropiativa | 4,75 | 4,00 | 8,50 |

Con esta lectura, SRTF gana en las tres métricas porque los procesos son cortos y van llegando escalonados. Round Robin mejora la respuesta frente a FCFS pero empeora la espera por los cambios de contexto. Si la prioridad de D fuera 2 en lugar de 4, la tabla de prioridad cambiaría: A y D empatarían en prioridad y A, por haber llegado antes, correría 5-10; D 10-14; B 14-17, con espera promedio 5,00.

#### Aplicación al proyecto

El backend no es un planificador de CPU: el planificador real es el sistema operativo y, en producción, el pool de PHP-FPM. Pero las ideas se usan para explicar el comportamiento:

- **FCFS** se parece a la cola de conexiones de un servidor que atiende por orden de llegada.
- **SJF/SRTF** se parecen a priorizar consultas cortas (por ejemplo, un listado paginado) frente a un reporte pesado.
- **Round Robin** se parece al sondeo cada 5 s del panel en desarrollo: todos reciben atención, con un costo de tráfico.
- **Prioridad** se parece a dar antesala a las operaciones de la turnera o a los pedidos autenticados como administrador.
- La **inanición** se evita con límites y timeouts: un pedido no puede quedar esperando para siempre (15 s en Web y Mobile).

### A.2 Consigna 2: Algoritmo del Banquero

#### Qué resuelve

El Banquero es un algoritmo de **evitación** de deadlock. Antes de conceder recursos, simula la asignación y comprueba si el sistema queda en un **estado seguro**: existe al menos una secuencia en la que todos los procesos pueden terminar. Si la concesión deja el sistema en estado inseguro, el pedido se posterga (se bloquea) aunque haya recursos disponibles.

No es prevención (no rompe las condiciones de Coffman) ni detección (no busca ciclos): evita entrar en un estado del que no se pueda salir.

#### Matrices y algoritmo

- **Allocation**: recursos asignados hoy a cada proceso.
- **Max**: máximo que cada proceso va a pedir.
- **Need** = Max - Allocation.
- **Available**: recursos libres.
- **Work**: copia de Available que se va actualizando.

Algoritmo de seguridad:

1. `Work = Available`; `Finish[i] = false` para todos.
2. Buscar un proceso con `Finish[i] = false` y `Need[i] <= Work`.
3. Si existe, simular que termina: `Work = Work + Allocation[i]`; `Finish[i] = true`; volver al paso 2.
4. Si todos terminan, el estado es seguro y la secuencia encontrada es la **secuencia segura**. Si no, es inseguro.

Para un pedido `Request[i]`:

1. Verificar `Request[i] <= Need[i]`; si no, error (pidió más de lo declarado).
2. Verificar `Request[i] <= Available`; si no, esperar.
3. Simular: `Available -= Request`; `Allocation[i] += Request`; `Need[i] -= Request`.
4. Ejecutar el algoritmo de seguridad. Si queda seguro, conceder; si no, revertir y bloquear.

#### Ejemplo propio (no son los datos del TP)

Recurso único: 4 workers de PHP-FPM. Procesos: tres tipos de pedido.

| Proceso | Max | Allocation | Need |
|---|---|---|---|
| P1 | 3 | 1 | 2 |
| P2 | 2 | 1 | 1 |
| P3 | 3 | 1 | 2 |

Total = 4; Available = 1.

Secuencia segura: con Work = 1, P2 necesita 1 y puede terminar; Work pasa a 2. P1 necesita 2 y termina; Work pasa a 3. P3 necesita 2 y termina. Secuencia segura: **P2, P1, P3**.

Ahora P3 pide 1 worker. Es menor o igual que su Need (2) y que Available (1), así que se simula: Available queda 0, P3 pasa a Allocation 2 y Need 1. En el chequeo de seguridad, P1 necesita 2, P2 necesita 1 y P3 necesita 1, pero no hay workers libres: nadie puede terminar. El estado es **inseguro**, así que el pedido se **bloquea** aunque en ese instante había un worker disponible. Ese es el punto exacto que pide la consigna: se autoriza mientras la simulación deja una secuencia segura; se bloquea en el primer pedido que lleva a un estado inseguro.

Si el que pide 1 es P2, en cambio, se simula: Available 0, P2 Allocation 2 y Need 0. P2 termina y libera 2 (Work = 2); P1 necesita 2 y termina (Work = 3); P3 necesita 2 y termina. El estado sigue siendo seguro y el pedido se autoriza.

#### Aplicación al proyecto

SaludWEB no implementa el Banquero, pero aplica mecanismos más simples con la misma idea de fondo: no conceder recursos si el sistema puede quedar trabado.

- **Workers de PHP-FPM** y **conexiones a SQLite** son recursos finitos.
- Los **timeouts** (15 s en los clientes, 5 s de `busy_timeout` en SQLite) evitan esperas infinitas.
- El **UNIQUE** de turnos evita la doble reserva, que es una carrera por un recurso.
- Las **colas** y los límites de tasa son una forma práctica de admitir trabajo sin prometer más de lo que el sistema puede sostener.
- El proyecto no necesita declarar máximos por proceso como el Banquero porque las operaciones son cortas y la base serializa las escrituras; si se agregaran tareas largas o varios escritores, el Banquero sería una referencia para el control de admisión.

**Sobre los datos del TP:** la tabla recibida mezcla filas, columnas y vectores (`P1..P4`, `R1..R7`, valores sueltos y una letra `B`), y no alcanza para reconstruir Allocation, Max, Need y Available sin adivinar. Para resolver el ejercicio puntual hace falta la tabla en filas claras o una foto. Este documento deja el método y el ejemplo; no inventa el resultado.

---

## Parte B. TP Integrador: las 19 consignas

### 1. Ciclo de instrucción

Es el conjunto de pasos que repite la CPU para ejecutar cada instrucción: **fetch** (trae la instrucción desde memoria, usando el PC), **decode** (la unidad de control la interpreta), **execute** (la ALU o la unidad correspondiente la ejecuta) y **write-back** (guarda el resultado en un registro o en memoria). Entre instrucciones se revisan las **interrupciones** pendientes. En el proyecto, el ciclo se refleja en una petición: `.htaccess` → `index.php` → router → controlador → servicio → repositorio → respuesta (`index.php:7`, `index.php:11`).

### 2. Sistema operativo

Es el software que administra los recursos del hardware (CPU, memoria, disco, dispositivos) y ofrece abstracciones (procesos, archivos, sockets, memoria virtual). Su función principal es **gestionar recursos y servir de intermediario** entre las aplicaciones y el hardware, con protección y aislamiento. SaludWEB corre sobre un SO; no lo implementa.

### 3. Arquitectura de Von Neumann

Diseño con **una sola memoria** para instrucciones y datos, una CPU (unidad de control + ALU) y un bus compartido. Su **cuello de botella** es ese bus único: buscar instrucciones y datos compite por el mismo camino. La **arquitectura de Harvard** propone memorias y buses separados para instrucciones y datos, permitiendo accesos simultáneos; los procesadores modernos tienen una separación parcial (cachés L1 de instrucciones y de datos). Es relevante porque es la base del modelo de programa almacenado, explica el rendimiento y también la seguridad: si el código y los datos comparten memoria, un error de programación puede ejecutar datos como código.

### 4. Intel 8086

Microprocesador de 16 bits de 1978, base de la arquitectura x86. Características: registros de 16 bits, bus de direcciones de 20 bits (1 MB), segmentación (CS, DS, SS, ES), unidad BIU (busca instrucciones y datos) separada de la EU (ejecuta), cola de prefetch de 6 bytes y conjunto de instrucciones que dio origen a x86. Su importancia es histórica y práctica: fijó el estándar de la PC y una compatibilidad hacia atrás que llega hasta los procesadores actuales. En el proyecto se parece a mantener un contrato de API estable para dos clientes distintos (Web y Mobile).

### 5. Programa y proceso

No son sinónimos. El **programa** es código estático almacenado (un archivo, un repositorio). El **proceso** es ese programa en ejecución, con estado propio: memoria, registros, archivos abiertos, PCB y ciclo de vida. Un programa puede originar muchos procesos. En SaludWEB, `CitaService.php` es el programa; cada pedido que lo ejecuta es una instancia con datos y estado distintos.

### 6. Deadlock y Algoritmo del Banquero

El deadlock es una situación en la que dos o más procesos se bloquean esperando recursos que el otro retiene, en un ciclo. Requiere cuatro condiciones a la vez: exclusión mutua, retención y espera, no expropiación y espera circular. El **Banquero** se relaciona porque es una técnica de **evitación**: antes de asignar, simula y solo concede si el sistema queda en un estado seguro; si no, posterga el pedido. No detecta ni rompe un deadlock ya producido. En el proyecto, los bloqueos de SQLite se manejan con WAL, `busy_timeout` y transacciones cortas (`db.php:154`, `db.php:161`), y la doble reserva con `UNIQUE` (`db.php:407`).

### 7. Algoritmos de planificación

Un algoritmo de planificación decide **qué proceso listo pasa a ejecutarse** y por cuánto tiempo. Criterios: uso de CPU, rendimiento, tiempo de retorno, espera, respuesta, equidad y ausencia de inanición.

- **FCFS**: no expropiativo; atiende por orden de llegada. Simple, pero sufre el efecto convoy.
- **SJF**: no expropiativo; elige la ráfaga más corta. Minimiza la espera promedio si se conocen las ráfagas, pero puede producir inanición de los procesos largos.
- **SRTF**: expropiativo; si llega un proceso con menor tiempo restante, interrumpe al actual. Es la versión expropiativa de SJF.
- **Round Robin**: expropiativo por quantum; cada proceso recibe un turno y, si no termina, vuelve al final de la cola. Favorece la respuesta y la equidad.
- **Prioridad**: puede ser expropiativa o no; elige el proceso de mayor prioridad (menor número, si así se define). Puede producir inanición de las prioridades bajas; se corrige con envejecimiento.

En el proyecto, el planificador real es el SO y el pool de PHP-FPM; la aplicación no decide el orden de los pedidos, pero usa prioridades de negocio (por ejemplo, la turnera) y timeouts para acotar la espera.

### 8. Estados de un proceso y transiciones

- **Nuevo**: creado, todavía no admitido en la cola de listos.
- **Listo**: en la cola, esperando CPU.
- **En ejecución**: usando la CPU.
- **En espera (bloqueado)**: esperando un evento o E/S.
- **Terminado**: finalizó o fue abortado.

Transiciones: **nuevo → listo** (admisión), **listo → en ejecución** (despacho), **en ejecución → listo** (expropiación o fin de quantum), **en ejecución → en espera** (pide E/S o un evento), **en espera → listo** (ocurre el evento), **en ejecución → terminado** (salida normal o aborto). La transición **listo → terminado** no es parte del modelo clásico; si un proceso se cancela antes de ejecutarse, se modela como una salida excepcional.

En el proyecto: un pedido llega (nuevo), espera un worker (listo), corre (ejecutando), espera a SQLite (en espera), vuelve (listo) y responde (terminado).

### 9. Ubicación en particionamiento dinámico

- **Primer ajuste**: recorre desde el principio y toma el primer bloque libre suficientemente grande.
- **Siguiente ajuste**: continúa desde la última posición asignada y toma el próximo bloque que alcance.
- **Mejor ajuste**: elige el bloque libre más chico que alcance; deja el menor desperdicio, pero genera fragmentos pequeños.
- **Peor ajuste**: elige el bloque más grande; deja un resto grande y reutilizable.
- También existe **quick fit**, que mantiene listas de bloques libres agrupados por tamaño.

Todos sufren **fragmentación externa** (huecos dispersos); se mitiga con compactación o paginación. En el proyecto, la idea aparece en la administración de bloques libres del sistema de archivos y en las páginas libres de SQLite, aunque el programador PHP no elige el algoritmo.

### 10. Memoria virtual y memoria física

El **espacio virtual** es el conjunto de direcciones lógicas que ve cada proceso; se divide en páginas y no necesita estar completo en RAM. El **espacio físico** es la RAM real, dividida en marcos (frames). La MMU traduce direcciones virtuales a físicas y permite aislamiento, compartición y swapping. En el proyecto, cada proceso PHP y Node tiene su espacio virtual; SQLite trabaja con páginas de archivo y el SO las cachea en memoria física.

### 11. De la dirección lógica a la física

La **MMU** traduce en cada acceso: con paginación, separa la dirección en número de página y desplazamiento, busca el marco en la **tabla de páginas** (con el **TLB** como caché de traducciones) y arma la dirección física. Con segmentación, usa la base y el límite del segmento. Si la página no está, se produce un **fallo de página** y el SO la trae del disco. En el proyecto, esta traducción la hace el SO; SQLite, en cambio, maneja números de página de su propio archivo, que son desplazamientos de archivo, no direcciones virtuales.

### 12. Reemplazo de páginas

Se usa cuando ocurre un **fallo de página** y no hay marcos libres: el SO elige una página víctima para desalojarla.

- **Óptimo (OPT)**: desaloja la página que se usará más lejos en el futuro. No se puede implementar en la práctica porque requiere conocer el futuro; sirve como estándar de comparación.
- **FIFO**: desaloja la página más antigua. Es simple, pero puede empeorar al aumentar los marcos (anomalía de Belady).
- **LRU**: desaloja la que no se usó por más tiempo; se aproxima con bits de referencia o con reloj.
- **Reloj (segunda oportunidad)**: recorre circularmente; si el bit de referencia es 1, lo pone en 0 y avanza; si es 0, desaloja.

En el proyecto, el SO aplica estas políticas a la caché de páginas del archivo SQLite; el navegador y la PWA tienen sus propias políticas de caché, y SQLite su propio caché de páginas.

### 13. Archivos

Un archivo es una unidad de almacenamiento con nombre que agrupa datos. Es importante porque permite persistir, organizar, compartir y recuperar información con una abstracción simple. La **extensión** es una convención que indica el tipo y sugiere con qué programa abrirlo, pero no garantiza la estructura interna: dos archivos `.txt` pueden tener formatos distintos, y un `.jpg` renombrado sigue siendo otra cosa. La estructura real se detecta por el contenido (firmas o *magic bytes*) o por metadatos. En el proyecto: `.php` es código, `.sqlite` es la base, `.json` es intercambio, `.env` son secretos y `.md` es documentación. Servir `.env` por error sería un problema de seguridad, no de extensión.

### 14. Sistemas de archivos

Es la capa del SO que organiza los datos en un dispositivo: bloques, metadatos, directorios, espacio libre, permisos y, según el caso, journaling. Se aplica a discos, SSD, USB y tarjetas.

- **FAT32**: muy compatible, sin permisos ni journaling; límite de 4 GB por archivo y 2 TB por volumen. Se usa en USB y tarjetas.
- **NTFS**: el sistema de Windows; journaling, permisos ACL, archivos y volúmenes grandes, cifrado y compresión.
- **ext4**: el estándar de Linux; journaling, extents, archivos y volúmenes grandes, buen rendimiento y confiabilidad.

En el proyecto, `storage/saludweb.sqlite` vive en NTFS o ext4 según el equipo; WAL crea archivos `-wal` y `-shm` al lado. Importan los permisos del directorio, las mayúsculas/minúsculas del sistema de archivos y la política de backups.

### 15. Interrupciones

Son señales que interrumpen temporalmente la ejecución para atender un evento (E/S, temporizador, error). El CPU guarda el contexto, consulta el **vector de interrupciones** (tabla de direcciones de los manejadores), ejecuta la **ISR** y restaura el contexto. Pueden ser **enmascarables** (se pueden postergar) o **no enmascarables** (NMI, graves). El vector es la guía que lleva al manejador correcto. En el proyecto, las interrupciones de hardware las maneja el SO; la aplicación ve sus consecuencias como timeouts, cortes de conexión, señales de terminación o eventos de red, no las atiende directamente.

### 16. Topologías de red

La topología es la forma en que se organizan los nodos y los enlaces. La respuesta de la entrega describió **tipos de red por alcance** (LAN, MAN, WAN), que no es lo mismo. Las topologías más usadas son:

- **Bus**: todos comparten un único medio; es económica, pero las colisiones y un corte del bus afectan a todos.
- **Estrella**: todos se conectan a un nodo central (switch o router); es la dominante en LAN, fácil de administrar, y el nodo central es un punto único de falla.
- **Anillo**: los nodos forman un círculo y el dato circula en un sentido; es predecible, pero un corte puede afectar el anillo (según la tecnología).
- También existen **malla** (mucha redundancia, costo alto), **árbol** y **híbridas**.

En el proyecto, la red típica es en estrella: router/switch, PC con la Web, celular con la app y el backend; en producción, el servidor está en la nube y se accede por Internet.

### 17. Modelo OSI y TCP/IP

**OSI** tiene 7 capas: **física** (bits y medio), **enlace** (tramas, MAC, control de errores local), **red** (paquetes, direccionamiento lógico, routing), **transporte** (extremo a extremo, puertos, confiabilidad), **sesión** (diálogo y sincronización), **presentación** (formato, cifrado, compresión) y **aplicación** (servicios de red).

**TCP/IP** es la arquitectura práctica de Internet, con 4 capas: **acceso a la red** (física + enlace), **Internet** (IP, ICMP), **transporte** (TCP, UDP) y **aplicación** (HTTP, DNS, SMTP, FTP, SSH). En el modelo de 5 capas se separan física y enlace.

La respuesta de la entrega mezcló ambos modelos, los enumeró fuera de orden y definió TCP/IP como algo que "identifica lógicamente un dispositivo", que en realidad es la función de IP. La corrección es separar OSI (referencia) de TCP/IP (implementación) y ubicar cada protocolo en su capa.

En el proyecto: la Web y la Mobile hablan HTTP/JSON sobre TCP; en producción conviene HTTPS (TLS); el navegador resuelve el nombre por DNS; CORS es una política de la capa de aplicación; `127.0.0.1` y `10.0.2.2` son direcciones de red.

### 18. IPv4 e IPv6

**IPv4** usa direcciones de 32 bits en notación decimal con puntos (por ejemplo, 192.168.1.1), con unas 4.294.967.296 combinaciones. **IPv6** usa 128 bits en hexadecimal con dos puntos, con aproximadamente 3,4 × 10^38 direcciones. IPv6 surge por el **agotamiento de IPv4**, y además simplifica el encabezado, evita la necesidad de NAT, permite autoconfiguración y mejora la movilidad. En el proyecto: `127.0.0.1` (loopback), `10.0.2.2` (host visto desde el emulador Android), la IP LAN para un celular físico y `::1` para IPv6 local.

### 19. Información de una IP y clases

Una IP identifica una interfaz en una red y tiene **parte de red** y **parte de host**, separadas por la **máscara**. En el direccionamiento classful histórico:

| Clase | Primer octeto | Máscara | Red / Host | Redes | Hosts utilizables |
|---|---|---|---|---|---|
| A | 1-126 | 255.0.0.0 (/8) | 1 / 3 octetos | 126 | 16.777.214 |
| B | 128-191 | 255.255.0.0 (/16) | 2 / 2 octetos | 16.384 | 65.534 |
| C | 192-223 | 255.255.255.0 (/24) | 3 / 1 octetos | 2.097.152 | 254 |
| D | 224-239 | — | multicast | — | — |
| E | 240-255 | — | experimental | — | — |

La entrega tenía un error en el rango de la clase A ("1-26" en lugar de "1-126") y no indicaba los hosts utilizables. También conviene recordar las **direcciones privadas** (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16) y el **loopback** (127.0.0.0/8). Hoy se usa **CIDR** (por ejemplo, /24) y no clases; las clases sirven para entender la historia y los rangos. En el proyecto, una LAN doméstica usa 192.168.0.0/24 o 192.168.1.0/24; el emulador Android usa 10.0.2.2; el loopback es 127.0.0.1.

---

## Parte C. Correcciones para la entrega

| Consigna | Qué corregir |
|---|---|
| TP2, tabla | Confirmar la lectura de los datos; el texto recibido está desordenado. |
| TP2, Banquero | Adjuntar la tabla en filas claras (Allocation, Max, Available) para poder resolver el caso puntual. |
| 6 | Aclarar que el Banquero **evita** el deadlock; no lo detecta ni lo resuelve una vez producido. |
| 8 | "Listo → Terminado" no es una transición clásica; explicarla como cancelación excepcional. |
| 11 | Ampliar con MMU, tabla de páginas, TLB y fallo de página. |
| 12 | Decir que OPT **no es implementable** en la práctica (no "no se puede realizar" sin explicar); agregar la anomalía de Belady en FIFO. |
| 15 | Agregar interrupciones enmascarables y no enmascarables; el vector de interrupciones es una tabla de punteros a las ISR. |
| 16 | La respuesta describió LAN/MAN/WAN (alcance), no topologías; reemplazar por bus, estrella y anillo (y malla). |
| 17 | Separar OSI (7 capas) de TCP/IP (4 capas), ordenar las capas y no definir TCP/IP como direccionamiento. |
| 18 | Correcto; agregar autoconfiguración y ausencia de NAT como ventajas de IPv6. |
| 19 | Corregir el rango de clase A (1-126), agregar hosts utilizables, clases D/E, rangos privados y CIDR. |

---

## Parte D. Bibliografía

- Apuntes de la cátedra, clases 4 a 9.
- Tanenbaum, A. **Sistemas Operativos Modernos**. Procesos, planificación, memoria y archivos.
- Stallings, W. **Sistemas Operativos**. Fundamentos y administración de recursos.
- Silberschatz, A. **Fundamentos de Sistemas Operativos**. Procesos, memoria y sistemas de archivos.
- RFC 791 (IPv4), RFC 8200 (IPv6) y RFC 1918 (direcciones privadas).
- Kurose, J. y Ross, K. **Redes de Computadoras**. Capas, protocolos y direccionamiento.
