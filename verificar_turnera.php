<?php
/**
 * verificar_turnera.php - Comprobación de la base de datos de la turnera
 * ============================================================
 * Módulo: "Sistema de gestión de citas online" (Turnera)
 * ------------------------------------------------------------
 * Es una herramienta de DESARROLLO, no parte de la API.
 *
 * Para qué existe: la turnera agrega cuatro tablas nuevas y es fácil que
 * una quede creada a medias o que el seed no haya corrido. Este script
 * informa el estado real en una línea por tabla y devuelve código 1 si
 * algo falta, para poder chequearlo rápido desde la terminal.
 *
 * Cómo se ejecuta:
 *   php verificar_turnera.php
 *
 * No modifica nada: solo consulta.
 */

// Se carga db.php, que crea la variable $pdo y ejecuta los CREATE TABLE
require_once __DIR__ . '/db.php';

// Variable para contar los problemas encontrados
$problemas = 0;

// Tablas que la turnera necesita
$tablasTurnera = ['especialidades', 'disponibilidades', 'citas', 'notificaciones'];

echo "=== Verificación de la turnera ===\n";
echo "Base de datos: pacientes\n\n";

// Se listan todas las tablas que existen
$tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasTurnera as $tabla) {
    // Se comprueba una por una si la tabla está creada
    if (in_array($tabla, $tablas, true)) {
        // Dentro del if se cuenta cuántas filas tiene, para ver si el seed corrió
        $conteo = (int) $pdo->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        // Se informa con el conteo entre paréntesis
        echo "[OK]   $tabla ($conteo filas)\n";
    } else {
        // Si falta, se avisa y se suma un problema
        echo "[FALTA] $tabla\n";
        $problemas++;
    }
}

echo "\n--- Datos de apoyo ---\n";

// Se comprueba que haya profesionales activos, porque sin ellos la agenda
// no tiene nada que ofrecer
$medicosActivos = (int) $pdo->query("SELECT COUNT(*) FROM medicos WHERE activo = 1")->fetchColumn();
echo "Médicos activos: $medicosActivos\n";
if ($medicosActivos === 0) {
    // Sin médicos no se puede reservar nada: es un problema real
    echo "  [AVISO] Sin médicos activos no hay horarios para reservar\n";
}

// Se cuentan los bloques de atención publicados
$bloques = (int) $pdo->query("SELECT COUNT(*) FROM disponibilidades WHERE activo = 1")->fetchColumn();
echo "Bloques de atención publicados: $bloques\n";
if ($bloques === 0) {
    echo "  [AVISO] No hay agenda publicada: la disponibilidad devolverá cero horarios\n";
}

// Se cuentan los pacientes, que son quienes reservan
$pacientes = (int) $pdo->query("SELECT COUNT(*) FROM pacientes WHERE activo = 1")->fetchColumn();
echo "Pacientes activos: $pacientes\n";

// Se muestran los índices únicos de citas, que son los que impiden que dos
// pacientes reserven el mismo horario a la vez
echo "\n--- Índices de la tabla citas ---\n";
$indices = $pdo->query("SHOW INDEX FROM citas")->fetchAll(PDO::FETCH_ASSOC);
foreach ($indices as $indice) {
    // Non_unique = 0 significa que el índice es único
    $tipo = ((int) $indice['Non_unique'] === 0) ? 'ÚNICO' : 'normal';
    echo "  {$indice['Key_name']} (columna: {$indice['Column_name']}) - $tipo\n";
}

// Se cierra con el resultado y el código de salida para scripting
echo "\n";
if ($problemas > 0) {
    echo "RESULTADO: Faltan $problemas tabla(s). Revisá db.php.\n";
    // Código 1: indica que algo está mal, útil para pipelines o scripts
    exit(1);
}

echo "RESULTADO: La turnera está lista.\n";
// Código 0: todo bien
exit(0);
