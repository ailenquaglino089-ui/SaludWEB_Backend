<?php
// ============================================================
// migrar_mysql_a_sqlite.php - Pasa los datos de MySQL a SQLite
// ============================================================
// PARA QUÉ SIRVE
// --------------
// La aplicación pasó a usar SQLite (ver db.php). Este script copia los
// datos que ya existían en la base MySQL/MariaDB hacia el archivo SQLite,
// tabla por tabla, para no arrancar de cero y no perder, sobre todo, las
// CUENTAS DE USUARIO (que no se recrean solas: db.php siembra obras
// sociales, médicos, medicamentos y pacientes de ejemplo, pero NUNCA
// usuarios).
//
// CÓMO SE USA
// -----------
//   php migrar_mysql_a_sqlite.php                 # importación limpia (reemplaza)
//   php migrar_mysql_a_sqlite.php --append        # agrega sin borrar lo existente
//   php migrar_mysql_a_sqlite.php --dry-run       # solo informa, no escribe nada
//   php migrar_mysql_a_sqlite.php --tablas=citas,usuarios
//
// CONFIGURACIÓN DEL ORIGEN (MySQL)
// --------------------------------
// Se leen de variables de entorno, con los valores de XAMPP por defecto:
//   MYSQL_HOST (localhost), MYSQL_PORT (3306), MYSQL_DATABASE (saludweb),
//   MYSQL_USER (root), MYSQL_PASSWORD (vacío)
// Si MYSQL_DATABASE no está definida, se usa DB_NAME del .env (el nombre que
// la app usaba cuando el motor era MySQL); si tampoco está, 'saludweb'.
// Se usan nombres con prefijo MYSQL_ a propósito: las DB_* viejas ya no
// existen (ahora DB_DATABASE apunta al archivo SQLite) y no conviene
// reutilizar nombres que podrían chocar.
//
// DESTINO (SQLite) = lo mismo que usa la aplicación
//   DB_DATABASE (default: storage/saludweb.sqlite)
//
// ES IDEMPOTENTE
// --------------
// Correrlo dos veces deja el mismo resultado. En modo limpio borra el
// contenido de cada tabla antes de copiarla; el esquema (CREATE TABLE) lo
// sigue creando db.php, que este script incluye sin duplicar nada.
// ============================================================
declare(strict_types=1);

// db.php deja lista la conexión SQLite ($pdo) y crea el esquema si falta.
// Además carga el .env (vía core/Env.php), así que DB_DATABASE se respeta.
require __DIR__ . '/db.php';

// ------------------------------------------------------------
// Argumentos de línea de comandos
// ------------------------------------------------------------
$modoAppend = in_array('--append', $argv, true);
$modoDryRun = in_array('--dry-run', $argv, true);

// --tablas=a,b,c limita la copia a esas tablas (útil para reimportar solo
// una parte sin tocar el resto).
$soloTablas = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--tablas=') === 0) {
        $soloTablas = array_filter(array_map('trim', explode(',', substr($arg, 9))));
    }
}

// ------------------------------------------------------------
// Orden de copia: los padres antes que los hijos.
// ------------------------------------------------------------
// Con "PRAGMA foreign_keys = OFF" durante la importación el orden no es
// obligatorio, pero mantenerlo hace que el script sea legible y que, si
// alguna vez se corre con las claves activas, siga funcionando.
$tablas = [
    'obras_sociales',
    'medicos',
    'medicamentos',
    'pacientes',
    'usuarios',
    'especialidades',
    'disponibilidades',
    'prescripciones',
    'citas',
    'notificaciones',
    'triages',
    'auditoria',
    'eventos_realtime',
];

if ($soloTablas !== null) {
    $tablas = array_values(array_intersect($tablas, $soloTablas));
    if (empty($tablas)) {
        fwrite(STDERR, "No hay tablas válidas en --tablas. Opciones: obras_sociales, medicos, medicamentos, pacientes, usuarios, especialidades, disponibilidades, prescripciones, citas, notificaciones, triages, auditoria, eventos_realtime\n");
        exit(1);
    }
}

// ------------------------------------------------------------
// Conexión al origen (MySQL)
// ------------------------------------------------------------
$myHost = getenv('MYSQL_HOST') ?: 'localhost';
$myPort = getenv('MYSQL_PORT') ?: '3306';
$myBase = getenv('MYSQL_DATABASE') ?: (getenv('DB_NAME') ?: 'saludweb');
$myUser = getenv('MYSQL_USER') ?: 'root';
$myPass = getenv('MYSQL_PASSWORD');
$myPass = ($myPass === false) ? '' : $myPass;

echo "=== Migración MySQL -> SQLite ===\n";
echo "Origen : mysql:host=$myHost;port=$myPort;dbname=$myBase (usuario $myUser)\n";
echo "Destino: " . (getenv('DB_DATABASE') ?: 'storage/saludweb.sqlite') . "\n";
echo "Modo   : " . ($modoDryRun ? 'DRY-RUN (no escribe)' : ($modoAppend ? 'agregar (conserva lo existente)' : 'importación limpia (reemplaza)')) . "\n\n";

// ------------------------------------------------------------
// Chequeo previo del servidor (evita quedar colgado)
// ------------------------------------------------------------
// Un servidor MySQL puede aceptar la conexión TCP y quedarse sin mandar el
// paquete de saludo (por ejemplo, con el motor InnoDB trabado). En ese caso
// "new PDO(...)" no falla: se queda esperando para siempre, porque el
// timeout de conexión cubre el TCP, no el saludo. Para no dejar el script
// colgado sin explicación, se prueba el saludo a mano, con timeout propio.
$errno = 0;
$errstr = '';
$sock = @stream_socket_client("tcp://$myHost:$myPort", $errno, $errstr, 5);
if ($sock === false) {
    fwrite(STDERR, "No se pudo abrir la conexión TCP con MySQL ($myHost:$myPort): $errstr\n");
    fwrite(STDERR, "Verificá que el servidor MySQL/MariaDB esté levantado.\n");
    exit(1);
}
stream_set_timeout($sock, 5);
// El servidor manda un byte apenas se conecta como parte del saludo. Si en
// 5 segundos no llegó nada, el servidor no está en condiciones de atender.
$saludo = fread($sock, 1);
$estado = stream_get_meta_data($sock);
fclose($sock);
if ($saludo === false || $saludo === '' || !empty($estado['timed_out'])) {
    fwrite(STDERR, "MySQL ($myHost:$myPort) acepta la conexión TCP pero no responde el saludo.\n");
    fwrite(STDERR, "El servidor está trabado (revisá su log de errores). No se copió nada.\n");
    exit(1);
}

try {
    $origen = new PDO(
        "mysql:host=$myHost;port=$myPort;dbname=$myBase;charset=utf8mb4",
        $myUser,
        $myPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // ATTR_TIMEOUT = 5 segundos para el connect. Sin esto, un
            // servidor MySQL que acepta la conexión TCP pero no completa el
            // saludo deja este script colgado esperando para siempre, sin
            // mensaje ni forma de saber qué pasó.
            PDO::ATTR_TIMEOUT => 5,
        ]
    );
} catch (PDOException $e) {
    // Mensaje claro y accionable: sin MySQL corriendo no hay nada que
    // copiar, y el error crudo de PDO no lo deja ver de un vistazo.
    fwrite(STDERR, "No se pudo conectar a MySQL ($myHost:$myPort/$myBase).\n");
    fwrite(STDERR, "Detalle: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Verificá que el servidor MySQL/MariaDB esté levantado y que los datos de MYSQL_* sean correctos.\n");
    exit(1);
}

// ------------------------------------------------------------
// Importación
// ------------------------------------------------------------
// Las claves foráneas se apagan durante la copia: los datos llegan en
// cualquier orden interno y no queremos que un padre todavía ausente haga
// fallar un hijo. Al terminar, se vuelven a encender.
$pdo->exec('PRAGMA foreign_keys = OFF');

$totalFilas = 0;
$resumen = [];

foreach ($tablas as $tabla) {
    // ¿Existe la tabla en MySQL? (SHOW TABLES LIKE es de MySQL, y estamos
    // consultando AL origen, así que acá sí corresponde.)
    $existe = $origen->query("SHOW TABLES LIKE " . $origen->quote($tabla))->fetch() !== false;
    if (!$existe) {
        $resumen[] = sprintf("  %-18s -- (no existe en MySQL, se omite)", $tabla);
        continue;
    }

    // Se copian solo las columnas que existen en LOS DOS lados, por si el
    // esquema viejo tenía alguna columna de más o de menos.
    $colsSQLite = $pdo->query("PRAGMA table_info('" . str_replace("'", "''", $tabla) . "')")
        ->fetchAll(PDO::FETCH_COLUMN, 1);
    // En PRAGMA table_info la columna 1 es 'name'.
    $colsMySQL = $origen->query("SHOW COLUMNS FROM `$tabla`")->fetchAll(PDO::FETCH_COLUMN, 0);
    // En SHOW COLUMNS la columna 0 es 'Field'.
    $comunes = array_values(array_intersect($colsSQLite, $colsMySQL));

    if (empty($comunes)) {
        $resumen[] = sprintf("  %-18s -- (sin columnas en común, se omite)", $tabla);
        continue;
    }

    $filas = $origen->query("SELECT `" . implode('`, `', $comunes) . "` FROM `$tabla`")->fetchAll();

    if ($modoDryRun) {
        $resumen[] = sprintf("  %-18s %d filas (dry-run, no se copiaron)", $tabla, count($filas));
        $totalFilas += count($filas);
        continue;
    }

    $pdo->beginTransaction();
    try {
        if (!$modoAppend) {
            // Importación limpia: se vacía la tabla destino antes de copiar.
            // DELETE (y no DROP) para no perder el esquema ni los triggers.
            $pdo->exec("DELETE FROM `$tabla`");
        }

        // INSERT OR IGNORE + id explícito: en modo --append no pisa lo que ya
        // está; en modo limpio la tabla quedó vacía, así que inserta todo.
        $verbo = $modoAppend ? 'INSERT OR IGNORE' : 'INSERT';
        $marcadores = implode(', ', array_fill(0, count($comunes), '?'));
        $stmt = $pdo->prepare("$verbo INTO `$tabla` (`" . implode('`, `', $comunes) . "`) VALUES ($marcadores)");

        $copiadas = 0;
        foreach ($filas as $fila) {
            // PDO entrega todos los valores de MySQL como string o null; la
            // afinidad de tipos de SQLite se encarga de guardar los números
            // como números en las columnas INTEGER.
            $stmt->execute(array_values($fila));
            $copiadas++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Error copiando '$tabla': " . $e->getMessage() . "\n");
        $pdo->exec('PRAGMA foreign_keys = ON');
        exit(1);
    }

    $resumen[] = sprintf("  %-18s %d filas", $tabla, $copiadas);
    $totalFilas += $copiadas;
}

$pdo->exec('PRAGMA foreign_keys = ON');

// ------------------------------------------------------------
// Reporte
// ------------------------------------------------------------
echo "--- Tablas ---\n";
echo implode("\n", $resumen), "\n";
echo "\nTotal: $totalFilas filas";
echo $modoDryRun ? " (DRY-RUN: no se escribió nada)\n" : " copiadas.\n";

if (!$modoDryRun && $totalFilas > 0) {
    echo "\nListo. La próxima vez que se abra la API, db.php encontrará la base ya\n";
    echo "poblada y no volverá a sembrar los datos de ejemplo.\n";
}
