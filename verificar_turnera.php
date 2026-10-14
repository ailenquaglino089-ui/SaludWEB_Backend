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
echo "Motor: SQLite\n\n";

// Se listan todas las tablas que existen.
// SHOW TABLES es de MySQL; en SQLite el catálogo de tablas está en la
// tabla interna sqlite_master, donde cada fila es un objeto del esquema
// (tablas, índices, triggers, vistas). Filtrando type = 'table' queda
// exactamente la misma lista que devolvía SHOW TABLES.
$tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

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
// pacientes reserven el mismo horario a la vez.
// SHOW INDEX FROM citas es de MySQL; en SQLite el equivalente es el PRAGMA
// index_list, que además de los índices propios lista el que crea el motor
// solo para sostener la restricción UNIQUE (los "sqlite_autoindex_..."),
// que es justamente el que interesa verificar acá.
echo "\n--- Índices de la tabla citas ---\n";
$indices = $pdo->query("PRAGMA index_list(citas)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($indices as $indice) {
    // unique = 1 significa que el índice es único
    $tipo = ((int) $indice['unique'] === 1) ? 'ÚNICO' : 'normal';
    // PRAGMA index_info devuelve las columnas del índice en orden. El
    // nombre del índice no puede ir como marcador (los PRAGMA no aceptan
    // parámetros), así que se interpola; como el nombre viene del propio
    // motor y solo lleva letras, números y guiones bajos, se escapan las
    // comillas por las dudas y el valor queda seguro.
    $nombreIndice = str_replace("'", "''", $indice['name']);
    $columnas = $pdo->query("PRAGMA index_info('$nombreIndice')")->fetchAll(PDO::FETCH_COLUMN, 2);
    echo "  {$indice['name']} (columnas: " . implode(', ', $columnas) . ") - $tipo\n";
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
