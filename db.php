<?php
// Conexión, esquema y datos iniciales.
// ============================================================
// db.php - Conexión a la base de datos SQLite y creación de tablas
// ============================================================
// Este archivo se encarga de:
// 1. Conectar a SQLite usando PDO (PHP Data Objects)
// 2. Aplicar los PRAGMA que necesita la aplicación
// 3. Crear todas las tablas necesarias si no existen
// 4. Insertar datos de ejemplo si la base está vacía
// ============================================================
//
// POR QUÉ SQLITE Y NO MYSQL
// ------------------------------------------------------------
// Antes esto hablaba con MySQL/MariaDB, que traía tres problemas:
//
//   1. Había que tener un servidor instalado y funcionando. Si el
//      servicio no estaba levantado, nada de la aplicación andaba,
//      ni siquiera para mirar una pantalla.
//   2. Ese servidor tenía su propio archivo de datos, su propia
//      configuración y su propio juego de permisos: era otra cosa
//      que podía romperse sola y que había que diagnosticar aparte.
//   3. Los datos vivían fuera del proyecto, así que "correr el
//      proyecto" no era una instrucción completa: faltaba "y además
//      instalar y configurar el motor de base de datos".
//
// SQLite resuelve las tres: es una biblioteca que viene con PHP (no
// hay servidor ni servicio que levantar), los datos son UN ARCHIVO
// dentro del proyecto, y no hay usuarios ni contraseñas que
// configurar. Clonar el repositorio y correr la API alcanza.
//
// LO QUE SE PIERDE, DICHO SIN DORARLO
// SQLite serializa las escrituras: un escritor por vez (los lectores
// sí son concurrentes, con WAL). Para este sistema —una API con una
// base chica y de uso local— no es una limitación real. Para un
// despliegue con mucha escritura simultánea, el motor a usar es otro.
// ============================================================

// ============================================================
// CARGA DE VARIABLES DE ENTORNO
// ------------------------------------------------------------
// Todas las variables de más abajo se leen con getenv(). Por eso el
// archivo .env se carga ANTES de la primera lectura, o de lo contrario
// .env no tiene ningún efecto y todo queda atado a los defaults de acá.
//
// Este include es doble con core/bootstrap.php, no redundante:
//   - Por HTTP: entra por routes.php -> bootstrap.php (que ya cargó Env).
//   - Por CLI:  scripts como sembrar_datos_demo.php o probar_*.php hacen
//               "require db.php" directo, sin pasar por bootstrap.
// Env::cargar() es idempotente, así que en el primer caso no repite nada.
// ============================================================
require_once __DIR__ . '/core/Env.php';
Env::cargar();

// ============================================================
// ZONA HORARIA DE LA APLICACIÓN
// ------------------------------------------------------------
// Se fija acá, y no en php.ini, por un problema concreto y visible:
// muchas instalaciones traen date.timezone = Europe/Berlin, que no es la
// zona ni del reloj del sistema (que está en -03:00) ni la de la base.
// El resultado era que date('c') en una respuesta decía "+02:00" mientras
// el creado_at de la base decía la hora de -03:00: dos relojes distintos
// en el mismo JSON, y "hoy" calculado distinto en PHP que en la base (lo
// que rompe comparaciones como "¿el turno es de hoy?").
//
// Fijarla en código (no en php.ini) hace que valga en cualquier máquina,
// y la vuelve configurable con APP_TIMEZONE para un despliegue en otra
// zona. El default es la zona del proyecto.
//
// Este archivo (db.php) es el punto por donde pasan TODOS los caminos:
// las peticiones HTTP (vía core/bootstrap.php) y los scripts de línea de
// comandos, que lo incluyen directo. Por eso se fija acá y no se repite.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Argentina/Buenos_Aires');

// Configuración de errores según entorno (módulo "Seguridad Básica"):
// En desarrollo mostrá los errores en pantalla para depurar.
// En producción JAMÁS se muestran (display_errors = Off) para no
// filtrar rutas, stack traces ni credenciales a los clientes.
// Lee APP_ENV del entorno; si no existe usa 'development' (?: = operador de coalescencia alternativa)
$appEnv = getenv('APP_ENV') ?: 'development';
// Si el entorno es development muestra errores ('1'); en producción los oculta ('0')
$showErrors = ($appEnv === 'development') ? '1' : '0';
// Aplica la configuración de errores en pantalla para el script actual
ini_set('display_errors', $showErrors);
// Aplica la misma configuración a los errores de arranque de PHP
ini_set('display_startup_errors', $showErrors);
// Reporta todos los niveles de error para no silenciar problemas durante el desarrollo
error_reporting(E_ALL);

// ============================================================
// UBICACIÓN DEL ARCHIVO DE BASE DE DATOS
// ------------------------------------------------------------
// DB_DATABASE es la ruta del archivo .sqlite. Si es relativa, se
// resuelve contra la raíz del backend (este archivo), así que el
// default apunta a storage/saludweb.sqlite y viaja con el proyecto.
//
// storage/ está dentro de .gitignore: la base no se versiona. Es
// correcto: los datos locales no son parte del código, y si alguien
// clona el repositorio, la base se crea sola en el primer request.
// ============================================================
$rutaBase = getenv('DB_DATABASE') ?: 'storage/saludweb.sqlite';
// Ruta absoluta (empieza con '/' o con 'C:\'): se usa tal cual.
// En cualquier otro caso se interpreta relativa a la raíz del backend.
$esAbsoluta = ($rutaBase !== '' && ($rutaBase[0] === '/' || $rutaBase[0] === '\\' || preg_match('#^[A-Za-z]:[\\\\/]#', $rutaBase) === 1));
$rutaDb = $esAbsoluta ? $rutaBase : __DIR__ . '/' . $rutaBase;

// El directorio tiene que existir antes de conectar: PDO no lo crea,
// y el error que da si falta es "unable to open database file", que
// no dice nada sobre cuál era la carpeta que faltaba.
$directorio = dirname($rutaDb);
if (!is_dir($directorio)) {
    // 0777 en el modo es lo habitual para carpetas creadas por el
    // servidor; el valor real lo termina ajustando el umask del sistema.
    @mkdir($directorio, 0777, true);
}

try {
    // ============================================================
    // CONEXIÓN
    // ============================================================
    // PDO::ERRMODE_EXCEPTION: los errores de SQLite se lanzan como
    // excepciones. Es la diferencia entre enterarse de un problema y
    // que el código siga como si nada hubiera pasado.
    // PDO::ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC: los resultados se
    // devuelven como arrays asociativos (nombre de columna => valor),
    // que es como los consumen todos los repositorios.
    $pdo = new PDO('sqlite:' . $rutaDb, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ============================================================
    // PRAGMA: la configuración de la conexión
    // ------------------------------------------------------------
    // En SQLite muchos comportamientos se activan POR CONEXIÓN, no
    // por base de datos. Por eso van acá y se repiten en cada
    // conexión nueva, incluida la de cualquier script que abra la
    // base por su cuenta.
    // ============================================================

    // foreign_keys = ON: respeta las claves foráneas (ON DELETE CASCADE
    // y ON DELETE SET NULL). SQLite las trae DESACTIVADAS por
    // compatibilidad histórica: sin esta línea, borrar un paciente
    // dejaría sus triages, sus citas y sus recetas huérfanos, en
    // silencio y sin ningún error. MySQL las respetaba siempre, así que
    // activarlo mantiene el comportamiento que ya tenía el sistema.
    $pdo->exec('PRAGMA foreign_keys = ON');

    // journal_mode = WAL ("write-ahead logging"): permite que las
    // lecturas ocurran mientras hay una escritura en curso. Con el modo
    // por defecto, una escritura bloquea a todos los lectores, y en esta
    // aplicación conviven: el canal SSE de /api/eventos lee de forma
    // continua mientras la turnera escribe.
    $pdo->exec('PRAGMA journal_mode = WAL');

    // busy_timeout = 5000: si el archivo está ocupado por otra conexión
    // (dos procesos escribiendo a la vez), espera hasta 5 segundos antes
    // de rendirse. Sin esto, el segundo proceso recibe "database is
    // locked" de inmediato y la petición falla por una condición
    // transitoria que se resolvía sola medio segundo después.
    $pdo->exec('PRAGMA busy_timeout = 5000');

    // synchronous = NORMAL: con WAL, este nivel no arriesga la
    // integridad de la base ante un corte del proceso (solo ante un
    // corte de energía total, donde como máximo se pierde la última
    // transacción). A cambio, evita un fsync por cada escritura.
    $pdo->exec('PRAGMA synchronous = NORMAL');
} catch (PDOException $e) {
    // PDOException: excepción específica de errores de PDO/base de datos
    // http_response_code(500): envía código HTTP 500 (Internal Server Error)
    http_response_code(500);
    // Muestra un mensaje de error formateado con HTML
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error de DB</title></head><body style="font-family:sans-serif; padding:30px; background:#f8f8f8;">';
    echo '<div style="max-width:820px; margin:auto; background:#fff; padding:24px; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.08);">';
    // Título del error en color rojo, claro y directo
    echo '<h1 style="color:#b91c1c;">Error al abrir la base de datos</h1>';
    echo '<p style="font-size:1rem; color:#333;">Archivo: <code>' . htmlspecialchars($rutaDb) . '</code></p>';
    echo '<p style="font-size:1rem; color:#333;">Detalle técnico: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // htmlspecialchars(): evita XSS convirtiendo caracteres como < > & " en entidades HTML
    echo '<p>Verificá que la carpeta <code>' . htmlspecialchars($directorio) . '</code> exista y que el proceso tenga permiso de escritura. La base es un archivo: no hace falta ningún servidor de base de datos.</p>';
    echo '</div></body></html>';
    exit;  // Detiene la ejecución del script
}

// ============================================================
// CREACIÓN DE TABLAS
// ============================================================
// Equivalencias con el esquema MySQL anterior, que conviene tener a
// mano al leer:
//
//   INT AUTO_INCREMENT PRIMARY KEY  ->  INTEGER PRIMARY KEY AUTOINCREMENT
//   VARCHAR(n) / TEXT               ->  TEXT   (SQLite no limita el largo)
//   TINYINT(1)                      ->  INTEGER (0/1)
//   TIMESTAMP / DATE / TIME         ->  TEXT   (ISO: 'AAAA-MM-DD HH:MM:SS')
//   ENUM('a','b')                   ->  TEXT + CHECK (col IN ('a','b'))
//   ENGINE=InnoDB / CHARSET         ->  no aplica (no hay motores ni
//                                       codificación por tabla: siempre UTF-8)
//
// Buscar el error acá es fácil: cada tabla de abajo trae un comentario
// con lo que representa.

$pdo->exec("CREATE TABLE IF NOT EXISTS obras_sociales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_obra TEXT NOT NULL
)");

// Tabla: pacientes
// dni UNIQUE: el DNI no puede repetirse entre pacientes. SQLite trata
// cada NULL como distinto, igual que MySQL, así que se pueden cargar
// varios pacientes sin DNI.
$pdo->exec("CREATE TABLE IF NOT EXISTS pacientes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    dni TEXT NULL UNIQUE,
    nombre TEXT NOT NULL,
    id_obra_social INTEGER NOT NULL DEFAULT 1,
    activo INTEGER NOT NULL DEFAULT 1,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");
// Índice para las búsquedas por nombre (los listados ordenan y filtran
// por este campo).
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_pacientes_nombre ON pacientes (nombre)");

// Tabla: triages (clasificación de urgencia de pacientes)
// REFERENCES ... ON DELETE CASCADE: si se borra un paciente, se borran
// sus triages automáticamente (necesita PRAGMA foreign_keys = ON).
$pdo->exec("CREATE TABLE IF NOT EXISTS triages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE CASCADE,
    nivel_gravedad INTEGER NOT NULL,
    observaciones TEXT NULL,
    fecha TEXT DEFAULT (datetime('now','localtime'))
)");

// Tabla: medicos
$pdo->exec("CREATE TABLE IF NOT EXISTS medicos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    matricula TEXT NULL,
    especialidad TEXT NULL,
    activo INTEGER DEFAULT 1,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");

// Tabla: medicamentos
$pdo->exec("CREATE TABLE IF NOT EXISTS medicamentos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    descripcion TEXT NULL,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");

// Tabla: prescripciones (recetas médicas)
// medicamentos TEXT: se guarda como JSON con la lista de medicamentos
// y dosis (json_encode/json_decode desde PHP, que es portable).
// id_medico se crea NULL y con ON DELETE SET NULL desde el arranque:
// en el esquema MySQL esto se conseguía con tres ALTER TABLE después
// de crear la tabla (MODIFY + DROP FOREIGN KEY + ADD CONSTRAINT).
// SQLite no soporta ALTER COLUMN ni DROP FOREIGN KEY, así que la
// columna se declara directamente como tiene que quedar y esos tres
// pasos de "migración" dejan de existir.
$pdo->exec("CREATE TABLE IF NOT EXISTS prescripciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE CASCADE,
    id_medico INTEGER NULL REFERENCES medicos(id) ON DELETE SET NULL,
    medicamentos TEXT NOT NULL,
    indicaciones TEXT NULL,
    fecha_emision TEXT DEFAULT (datetime('now','localtime')),
    fecha_vencimiento TEXT NULL,
    estado TEXT DEFAULT 'activa',
    qr_code TEXT NULL,
    firma_digital TEXT NULL
)");

// Tabla: auditoria (registro de acciones realizadas en el sistema)
$pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    accion TEXT NOT NULL,
    fecha TEXT DEFAULT (datetime('now','localtime'))
)");

// Tabla: usuarios (para autenticación)
// tipo_usuario era un ENUM: acá se reemplaza por un CHECK, que hace la
// misma validación (rechaza cualquier valor fuera de la lista) sin
// atarse a un tipo de dato que SQLite no tiene.
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    nombre TEXT NOT NULL,
    tipo_usuario TEXT DEFAULT 'paciente'
        CHECK (tipo_usuario IN ('paciente', 'medico', 'admin')),
    id_paciente INTEGER NULL REFERENCES pacientes(id) ON DELETE SET NULL,
    id_medico INTEGER NULL REFERENCES medicos(id) ON DELETE SET NULL,
    activo INTEGER DEFAULT 1,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");

// ============================================================
// MÓDULO TURNERA: GESTIÓN DE CITAS ONLINE
// ------------------------------------------------------------
// Objetivo: permitir que el paciente reserve, modifique y cancele
// consultas desde la web o el celular, sin filas presenciales ni
// llamados telefónicos, y que el profesional vea una agenda unificada
// sin superposiciones.
//
// Las cuatro tablas de abajo usan una técnica de concurrencia
// importante que conviene leer antes de tocar el SQL:
//
//   UNIQUE (id_medico, fecha, hora, slot_reservado)
//
// Ni MySQL ni SQLite tienen índices "parciales" (no se puede indexar
// solo las filas que cumplen una condición), y en un índice UNIQUE los
// valores NULL NO se consideran duplicados entre sí. Por eso la cuarta
// columna, slot_reservado, vale 'reservado' mientras la cita está
// activa (pendiente/confirmada/completada) y se pone en NULL cuando la
// cita se cancela o el paciente no asiste.
//
// Consecuencia: el par (médico, fecha, hora) solo puede estar ocupado
// por UNA cita viva, y al cancelar se liberan todas las horas posibles
// del mismo horario sin chocar con el índice. Esa es la garantía de
// que no se pueden superponer dos pacientes en el mismo consultorio.
//
// Esta regla depende de una semántica de NULL que SQLite implementa
// igual que MySQL, así que el cambio de motor NO la altera.
// ============================================================

// Tabla: especialidades
// Catálogo de especialidades médicas (Ej: Medicina General, Pediatría).
// Existe como catálogo propio para poder agrupar, filtrar y medir
// demanda por especialidad sin depender de texto libre.
$pdo->exec("CREATE TABLE IF NOT EXISTS especialidades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL UNIQUE,
    activo INTEGER NOT NULL DEFAULT 1,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");

// Tabla: disponibilidades
// Agenda horaria RECURRENTE del profesional: bloques de atención
// semanales que se repiten todas las semanas.
// Ej: id_medico=1, dia_semana=1..5, 08:00 a 12:00, turnos de 30 min.
//
// dia_semana usa la convención ISO-8601 de PHP: 1 = lunes ... 7 = domingo.
// Es la misma que devuelve date('N') y la que usa la mayoría de las
// librerías de calendario, así que no hay conversión al mostrar la agenda.
//
// OJO con un detalle que confunde: la numeración de PHP (ISO-8601) se
// parece a la de algunas funciones de fecha de las bases de datos pero
// NO es la misma. La regla del sistema es esta: el día de la semana se
// calcula SIEMPRE en PHP y se guarda como número. Nunca se le pide a la
// base que lo deduzca, justamente para no depender de la convención de
// cada motor.
//
// duracion_minutos: largo de cada turno dentro del bloque (30 por defecto).
// activo: permite desactivar un bloque sin borrarlo, para no perder el
// histórico de las citas ya tomadas en ese horario.
$pdo->exec("CREATE TABLE IF NOT EXISTS disponibilidades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_medico INTEGER NOT NULL REFERENCES medicos(id) ON DELETE CASCADE,
    dia_semana INTEGER NOT NULL,
    hora_inicio TEXT NOT NULL,
    hora_fin TEXT NOT NULL,
    duracion_minutos INTEGER NOT NULL DEFAULT 30,
    activo INTEGER NOT NULL DEFAULT 1,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_disponibilidad_medico_dia ON disponibilidades (id_medico, dia_semana)");

// Tabla: citas
// Cada fila es un turno reservado entre un paciente y un profesional.
//
// estado: el ciclo de vida completo de la cita.
//   pendiente   → recién reservada, todavía sin confirmar
//   confirmada  → el paciente confirmó que va
//   cancelada   → se liberó (el horario vuelve a quedar disponible)
//   completada  → la atención se realizó
//   ausente     → el paciente no asistió (alimenta la tasa de ausentismo)
//
// slot_reservado: ver la explicación del índice único más arriba.
//   'reservado' → la cita ocupa el horario
//   NULL        → la cita está cancelada o el paciente faltó, el horario queda libre
//
// creado_por_paciente: guarda si la reservó el propio paciente (1) o el
// consultorio (0). Permite medir el uso real de la autogestión online.
//
// actualizado_at: en MySQL se mantenía sola con "ON UPDATE
// CURRENT_TIMESTAMP". SQLite no tiene esa cláusula, así que el trabajo
// lo hace el trigger trg_citas_actualizado_at, creado más abajo. El
// efecto para el resto del sistema es idéntico: cada vez que se toca
// una cita, la fecha de modificación queda al día sin que ningún
// repositorio tenga que acordarse de escribirla.
$pdo->exec("CREATE TABLE IF NOT EXISTS citas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_paciente INTEGER NOT NULL REFERENCES pacientes(id) ON DELETE CASCADE,
    id_medico INTEGER NOT NULL REFERENCES medicos(id) ON DELETE CASCADE,
    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    duracion_minutos INTEGER NOT NULL DEFAULT 30,
    estado TEXT NOT NULL DEFAULT 'pendiente',
    motivo TEXT NULL,
    notas TEXT NULL,
    recordatorio_enviado INTEGER NOT NULL DEFAULT 0,
    creado_por_paciente INTEGER NOT NULL DEFAULT 1,
    slot_reservado TEXT NULL DEFAULT 'reservado',
    creado_at TEXT DEFAULT (datetime('now','localtime')),
    actualizado_at TEXT DEFAULT (datetime('now','localtime')),
    UNIQUE (id_medico, fecha, hora, slot_reservado)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_citas_paciente ON citas (id_paciente, fecha)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_citas_medico_dia ON citas (id_medico, fecha)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_citas_estado_fecha ON citas (estado, fecha)");

// Trigger que reemplaza a ON UPDATE CURRENT_TIMESTAMP.
//
// Verificado antes de escribirlo: un trigger AFTER UPDATE que actualiza
// la MISMA tabla no contamina el rowCount() de la sentencia original.
// Eso importa porque el módulo de turnos usa bloqueo optimista
// ("UPDATE ... WHERE id = ? AND estado = ?" y después mira si afectó
// una fila o ninguna); si el trigger sumara al contador, una
// actualización que no cambió nada parecería exitosa y dos usuarios
// podrían pisarse el turno.
//
// La recursión no es un problema: SQLite trae recursive_triggers
// desactivado, así que el UPDATE de adentro no vuelve a disparar
// este mismo trigger.
$pdo->exec("CREATE TRIGGER IF NOT EXISTS trg_citas_actualizado_at
    AFTER UPDATE ON citas
    FOR EACH ROW
    BEGIN
        UPDATE citas SET actualizado_at = datetime('now','localtime') WHERE id = NEW.id;
    END");

// Tabla: notificaciones
// Capa persistida de los recordatorios anti-ausentismo.
//
// Decisión de arquitectura (módulo "Datos en Tiempo Real"):
//   La fuente de verdad es la tabla citas. Esta tabla NO duplica el
//   estado de la cita: guarda únicamente el registro de las
//   comunicaciones enviadas y en qué estado quedó cada una.
//   Nunca se escribe en esta tabla desde el cliente, y jamás se
//   consulta para decidir el estado de una cita: eso se lee siempre
//   en citas. Así se evita el antipatrón de "fuente de verdad difusa"
//   descrito en la guía de datos en tiempo real.
//
// tipo:   recordatorio | confirmacion | cancelacion
// canal:  email | whatsapp | sistema (el que se use al implementar)
// estado: pendiente | enviado | fallido
//
// token_cancelacion: token aleatorio y único que viaja en el enlace
// del recordatorio. Permite que el paciente confirme o cancele desde
// el mensaje sin iniciar sesión, que es justamente lo que reduce el
// ausentismo (si exigir login, muchos pacientes lo abandonan).
//
// UNIQUE (id_cita, tipo, canal): un aviso por combinación. Es la
// barrera que impide mandar el recordatorio dos veces cuando dos
// procesos lo generan al mismo tiempo (ver
// NotificacionRepository::crearSiNoExiste).
$pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    id_cita INTEGER NOT NULL REFERENCES citas(id) ON DELETE CASCADE,
    id_usuario INTEGER NULL REFERENCES usuarios(id) ON DELETE SET NULL,
    tipo TEXT NOT NULL DEFAULT 'recordatorio',
    canal TEXT NOT NULL DEFAULT 'sistema',
    destino TEXT NULL,
    estado TEXT NOT NULL DEFAULT 'pendiente',
    token_cancelacion TEXT NULL UNIQUE,
    intentos INTEGER NOT NULL DEFAULT 0,
    motivo_error TEXT NULL,
    enviado_at TEXT NULL DEFAULT NULL,
    creado_at TEXT DEFAULT (datetime('now','localtime')),
    UNIQUE (id_cita, tipo, canal)
)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_notificaciones_estado ON notificaciones (estado, creado_at)");

// ============================================================
// MÓDULO "TIEMPO REAL" (Server-Sent Events)
// ------------------------------------------------------------
// Tabla: eventos_realtime
//
// ESTA TABLA NO ES UNA SEGUNDA BASE DE DATOS.
// Es el equivalente en servidor del "outbox" de eventos que la guía
// describe, y su función es ÚNICA: avisar "algo cambió en el canal X"
// para que el navegador conectado por SSE sepa que tiene que volver a
// pedir los datos por REST.
//
// Por qué NO se guarda aquí el contenido que se muestra:
//   Si esta tabla guardara el turno completo con nombre del paciente,
//   estado y motivo, existirían DOS copias del mismo dato (esta y
//   `citas`) que se pueden desincronizar. Ese es justamente el
//   antipatrón de "fuente de verdad difusa" que la guía descarta.
//   Acá solo va la SEÑAL (qué pasó, sobre qué id, a qué canal) y los
//   datos los sigue leyendo el cliente del endpoint REST que ya
//   respeta los permisos. Si el dato cambia, cambia en un solo lugar.
//
// Por qué existe igual y no se manda directo desde el PHP que escribe:
//   El servidor web no puede "empujar" nada: cada petición termina
//   cuando el script termina. Quien está esperando datos es el
//   NAVEGADOR, no el servidor. Hace falta un lugar donde dejar el aviso
//   para que el proceso que está escuchando lo encuentre al pasar.
//
// Canal: a quién le interesa el evento (ver RealtimeService::resolverCanal)
//   tablero             → todo cambio de turnos (admin y médicos)
//   agenda:<id_medico>  → cambios de la agenda de un profesional
//   turnos:<id_paciente>→ cambios de los turnos de un paciente
//
// tipo: qué pasó (cita_creada, cita_estado, cita_cancelada, cita_eliminada)
// datos: JSON con los identificadores mínimos para que el cliente sepa
//        qué tiene que volver a pedir. No contiene datos clínicos.
// creado_at: se usa para purgar los eventos viejos (ver RealtimeRepository::purgarAntiguos)
$pdo->exec("CREATE TABLE IF NOT EXISTS eventos_realtime (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    canal TEXT NOT NULL,
    tipo TEXT NOT NULL,
    datos TEXT NULL,
    creado_at TEXT DEFAULT (datetime('now','localtime'))
)");
// El índice (canal, id) es el que hace que el endpoint SSE sea barato: la
// consulta que hace el listener en cada vuelta es
//   SELECT ... WHERE canal = ? AND id > ? ORDER BY id LIMIT 50
// y con este índice es un recorrido corto desde el último id visto, no
// un escaneo de la tabla. Sin él, con la tabla creciendo, cada vuelta
// costaría más y el listener se iría retrasando.
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_eventos_canal_id ON eventos_realtime (canal, id)");
// El índice por creado_at existe para la purga: borrar lo que ya no le
// sirve a nadie usa ese índice y no la tabla.
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_eventos_creado ON eventos_realtime (creado_at)");

// ============================================================
// INSERCIÓN DE DATOS DE EJEMPLO
// ============================================================
// Solo se insertan si las tablas están vacías

// Obras sociales por defecto
$obrasCount = (int)$pdo->query('SELECT COUNT(*) FROM obras_sociales')->fetchColumn();
// fetchColumn() devuelve la primera columna de la primera fila (el COUNT)
// (int) convierte el resultado a número entero
// Si la tabla de obras sociales está vacía (primera ejecución)...
if ($obrasCount === 0) {
    // Inserta las tres obras sociales por defecto en una sola sentencia
    $pdo->exec("INSERT INTO obras_sociales (nombre_obra) VALUES ('Particular'), ('OSDE'), ('PAMI')");
}

// Médicos por defecto
// Cuenta cuántos médicos hay en la tabla (0 si nunca se insertó nada)
$medicosCount = (int)$pdo->query('SELECT COUNT(*) FROM medicos')->fetchColumn();
// Si no hay médicos registrados, se cargan los datos de ejemplo
if ($medicosCount === 0) {
    // Consulta preparada con marcadores (?) para evitar inyección SQL
    $stmt = $pdo->prepare('INSERT INTO medicos (nombre, matricula, especialidad, activo) VALUES (?, ?, ?, 1)');
    // Datos de ejemplo: cada fila es un arreglo (nombre, matrícula, especialidad)
    $default = [
        ['Dr. Juan Pérez', '12345', 'Medicina General'],
        ['Dra. María López', '67890', 'Pediatría'],
        ['Dr. Sebastián Gómez', '11223', 'Cardiología'],
        ['Dra. Lucía Fernández', '44556', 'Dermatología'],
    ];
    // Recorre cada médico de ejemplo...
    foreach ($default as $m) {
        $stmt->execute($m);  // Ejecuta la consulta con cada par de valores
    }
}

// Medicamentos por defecto
// Cuenta cuántos medicamentos existen (para saber si hace falta sembrar datos)
$medicamentosCount = (int)$pdo->query('SELECT COUNT(*) FROM medicamentos')->fetchColumn();
// Si la tabla está vacía, se cargan los medicamentos de ejemplo
if ($medicamentosCount === 0) {
    // Preparación de la consulta con marcador (?) contra inyección SQL
    $stmt = $pdo->prepare('INSERT INTO medicamentos (nombre) VALUES (?)');
    // Recorre la lista de medicamentos por defecto, insertando uno por pasada
    foreach (['Paracetamol', 'Ibuprofeno', 'Amoxicilina', 'Loratadina', 'Omeprazol'] as $nombre) {
        // Ejecuta la consulta preparada pasando el nombre como único dato
        $stmt->execute([$nombre]);
    }
}

// Pacientes por defecto
// Cuenta cuántos pacientes hay (para sembrar datos solo la primera vez)
$pacientesCount = (int)$pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
// Si no existe ningún paciente, se insertan los de ejemplo
if ($pacientesCount === 0) {
    // Consulta preparada: (dni, nombre, id_obra_social) + el campo activo fijo en 1
    $stmt = $pdo->prepare('INSERT INTO pacientes (dni, nombre, id_obra_social, activo) VALUES (?, ?, ?, 1)');
    // Pacientes de ejemplo: cada fila es (dni, nombre, id de obra social)
    $defaults = [
        ['12345678', 'María García', 1],
        ['23456789', 'Carlos López', 2],
        ['34567890', 'Ana Fernández', 3],
    ];
    // Recorre los pacientes de ejemplo...
    foreach ($defaults as $p) {
        // Ejecuta la consulta con cada trío de valores
        $stmt->execute($p);
    }
}

// ============================================================
// SEMILLA DEL MÓDULO TURNERA
// ------------------------------------------------------------
// Solo corre la primera vez (cuando las tablas están vacías).
// ============================================================

// Especialidades: se derivan de las que YA tienen cargadas los médicos,
// para no obligar a cargar dos veces la misma información.
// INSERT OR IGNORE + SELECT DISTINCT: si la especialidad no existe
// todavía se inserta; si ya está, OR IGNORE evita el error de clave
// duplicada (nombre UNIQUE) y no interrumpe la creación del esquema.
// (En MySQL esto se escribía INSERT IGNORE; OR IGNORE es su equivalente
// exacto en SQLite.)
$pdo->exec(
    "INSERT OR IGNORE INTO especialidades (nombre)
     SELECT DISTINCT especialidad FROM medicos
     WHERE especialidad IS NOT NULL AND TRIM(especialidad) <> ''"
);
// Si el paso anterior no trajo nada (base ya creada antes de este módulo),
// se carga un catálogo mínimo para que la autogestión tenga con qué trabajar.
$especialidadesCount = (int)$pdo->query('SELECT COUNT(*) FROM especialidades')->fetchColumn();
if ($especialidadesCount === 0) {
    $pdo->exec(
        "INSERT OR IGNORE INTO especialidades (nombre) VALUES
         ('Medicina General'), ('Pediatría'), ('Cardiología'),
         ('Dermatología'), ('Psicología'), ('Odontología')"
    );
}

// Disponibilidades: agenda semanal de lunes a viernes para todos los médicos
// activos que todavía no tengan bloques cargados.
//
// Se usa un INSERT ... SELECT con NOT EXISTS para que el script sea
// idempotente: si se vuelve a ejecutar, no duplica los bloques.
// Los médicos atienden de 08:00 a 12:00, turnos de 30 minutos.
$pdo->exec(
    "INSERT INTO disponibilidades (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo)
     SELECT m.id, d.dia, '08:00:00', '12:00:00', 30, 1
     FROM medicos m
     CROSS JOIN (SELECT 1 AS dia UNION ALL SELECT 2 UNION ALL SELECT 3
                 UNION ALL SELECT 4 UNION ALL SELECT 5) d
     WHERE m.activo = 1
       AND NOT EXISTS (
           SELECT 1 FROM disponibilidades x
           WHERE x.id_medico = m.id AND x.dia_semana = d.dia
       )"
);
