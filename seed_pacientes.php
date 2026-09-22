<?php
// ============================================================
// seed_pacientes.php - Genera N pacientes aleatorios (prueba de paginado)
// ============================================================
// Uso desde línea de comandos:
//   php seed_pacientes.php 100
// (crea 100 pacientes aleatorios; sin parámetro, crea 10)
//
// Sirve para probar el paginado de los listados: con muchos
// pacientes el backend deja de traer toda la tabla y entrega
// páginas de N registros.
// ============================================================

// CAMBIA AQUI LOS VALORES SI TU CONEXION ES DISTINTA
$host = getenv('DB_HOST') ?: 'localhost';   // Servidor de base de datos
$dbName = getenv('DB_NAME') ?: 'pacientes'; // Nombre de la base de datos
$user = getenv('DB_USER') ?: 'root';        // Usuario de MySQL
$pass = getenv('DB_PASS') ?: '';            // Contraseña de MySQL (vacía por defecto)

// Cantidad de pacientes a crear: viene por parámetro ($argv[1])
$cantidad = isset($argv[1]) ? (int)$argv[1] : 10;
// Valida que la cantidad sea positiva (evita crear 0/negativos sin sentido)
if ($cantidad < 1) {
    // STDERR es la salida de error de la consola (no mezcla con el resultado normal)
    fwrite(STDERR, "La cantidad de pacientes debe ser mayor que 0\n");
    exit(1);  // Código de salida 1 = error
}

try {
    // Conexión PDO a MySQL (mismos atributos que db.php)
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Si falla la conexión se avisa por consola y se corta la ejecución
    fwrite(STDERR, "Error de conexión: " . $e->getMessage() . "\n");
    exit(1);
}

// Obras sociales existentes (se eligen al azar para cada paciente)
$obras = $pdo->query('SELECT id FROM obras_sociales')->fetchAll(PDO::FETCH_COLUMN);
// Si no hay obras sociales registradas, se usa el id 1 por defecto
if (empty($obras)) {
    $obras = [1];
}

// Listas de nombres y apellidos argentinos para generar datos aleatorios
$nombres = [
    'Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Laura', 'Diego', 'Lucía',
    'Martín', 'Carla', 'Jorge', 'Sofía', 'Andrés', 'Valentina', 'Pablo',
    'Camila', 'Roberto', 'Florencia', 'Gustavo', 'Julieta', 'Nicolás',
    'Milagros', 'Fernando', 'Agustina', 'Hernán', 'Romina'
];
$apellidos = [
    'González', 'Rodríguez', 'Fernández', 'López', 'Martínez', 'García',
    'Pérez', 'Sánchez', 'Romero', 'Torres', 'Díaz', 'Álvarez', 'Ruiz',
    'Sosa', 'Ramírez', 'Flores', 'Acosta', 'Medina', 'Castro', 'Benítez',
    'Molina', 'Suárez', 'Rojas', 'Silva', 'Cabrera', 'Dominguez'
];

// DNI ya usados (existentes en la tabla + los generados en esta corrida)
// array_flip convierte cada dni en clave del array: buscar es más rápido que in_array
$usados = array_flip(array_filter(
    // Obtiene todos los dni existentes (algunos pueden ser NULL: se filtran)
    $pdo->query('SELECT dni FROM pacientes')->fetchAll(PDO::FETCH_COLUMN)
));

// Consulta preparada para insertar pacientes (nunca se concatena SQL con datos)
$stmt = $pdo->prepare('INSERT INTO pacientes (dni, nombre, id_obra_social, activo) VALUES (?, ?, ?, ?)');

// Contadores para el reporte final
$creados = 0;
$intentos = 0;
// Límite de intentos: si el azar genera DNIs repetidos muchas veces, se corta para evitar un bucle infinito
$maxIntentos = $cantidad * 50;

// Bucle: crea pacientes hasta completar la cantidad pedida
while ($creados < $cantidad && $intentos < $maxIntentos) {
    $intentos++;
    // DNI aleatorio de 7 u 8 dígitos (random_int es criptográficamente seguro)
    $dni = (string) random_int(1000000, 99999999);
    // Si el DNI ya está en uso, se descarta y se intenta con otro
    if (isset($usados[$dni])) {
        continue;
    }
    $usados[$dni] = true;  // Se marca como usado para no repetirlo

    // Nombre aleatorio: "Apellido, Nombre" (formato habitual de listados)
    $nombre = $apellidos[array_rand($apellidos)] . ', ' . $nombres[array_rand($nombres)];
    // Obra social y estado activo aleatorios (mt_rand es rápido para esto)
    $idObra = $obras[array_rand($obras)];
    $activo = mt_rand(0, 1) ? 1 : 0;

    // Inserta el paciente (los valores van por ?, protegidos contra inyección SQL)
    $stmt->execute([$dni, $nombre, $idObra, $activo]);
    $creados++;
}

// Reporte final en consola
echo "Se crearon $creados pacientes de prueba en la base '$dbName'.\n";
// Si el bucle terminó por el límite de intentos, se avisa (posible congestión de DNIs)
if ($creados < $cantidad) {
    fwrite(STDERR, "No se pudo completar la cantidad pedida: muchos DNIs aleatorios repetidos.\n");
}