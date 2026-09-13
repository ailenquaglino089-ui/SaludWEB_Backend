<?php
// Conexión, esquema y datos iniciales de la variante backend.
// ============================================================
// db.php - Conexión a la base de datos MySQL y creación de tablas
// ============================================================
// Este archivo se encarga de:
// 1. Conectar a MySQL usando PDO (PHP Data Objects)
// 2. Crear la base de datos si no existe
// 3. Crear todas las tablas necesarias si no existen
// 4. Insertar datos de ejemplo si la base está vacía
// ============================================================

// Configuración de errores según entorno (módulo "Seguridad Básica"):
// En desarrollo (XAMPP) mostrá los errores en pantalla para depurar.
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

// Variables de conexión usando getenv() para leer variables de entorno
// getenv() busca variables del sistema operativo o del servidor
// Si no existen, usa valores por defecto (localhost, root, sin contraseña)
// El operador ?: significa "si lo anterior es falso/null, usa esto otro"
$host = getenv('DB_HOST') ?: 'localhost';  // Servidor de base de datos
$dbName = getenv('DB_NAME') ?: 'pacientes'; // Nombre de la base de datos
$user = getenv('DB_USER') ?: 'root';        // Usuario de MySQL
$pass = getenv('DB_PASS') ?: '';             // Contraseña de MySQL (vacía por defecto)

try {
    // Se crea la conexión PDO sin especificar base de datos (para poder crearla)
    // PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION: los errores se lanzan como excepciones
    // PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC: los resultados se devuelven como arrays asociativos
    // Ej: en vez de [0 => 'Juan', 'nombre' => 'Juan'] devuelve solo ['nombre' => 'Juan']
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        // Los errores de MySQL se lanzan como excepciones (se pueden atrapar con try/catch)
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // Los resultados se devuelven como arreglos asociativos (nombre de columna => valor)
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Desactiva la emulación de prepared statements: PHP envía
        // consultas preparadas REALES al motor MySQL (el método
        // envía estructura y datos por separado). Mayor seguridad
        // contra SQL Injection (patrón del módulo "Acceso profesional").
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Crea la base de datos si no existe
    // CREATE DATABASE IF NOT EXISTS: evita error si ya fue creada antes
    // CHARACTER SET utf8mb4: soporta caracteres especiales (tildes, emojis, ñ)
    // COLLATE utf8mb4_unicode_ci: reglas de comparación que respetan el español
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Selecciona la base de datos para usarla
    $pdo->exec("USE `$dbName`");

    // ============================================================
    // CREACIÓN DE TABLAS
    // ============================================================
    // ENGINE=InnoDB: motor que soporta claves foráneas (foreign keys)
    // DEFAULT CHARSET=utf8mb4: codificación de caracteres

    // Tabla: obras_sociales
    // id: identificador único auto-incremental
    // nombre_obra: nombre de la obra social (Ej: OSDE, PAMI)
    $pdo->exec("CREATE TABLE IF NOT EXISTS obras_sociales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_obra VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: pacientes
    // UNIQUE KEY uniq_dni (dni): el DNI no puede repetirse entre pacientes
    // INDEX idx_nombre (nombre): crea un índice para búsquedas rápidas por nombre
    $pdo->exec("CREATE TABLE IF NOT EXISTS pacientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dni VARCHAR(50) NULL,
        nombre VARCHAR(255) NOT NULL,
        id_obra_social INT NOT NULL DEFAULT 1,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_dni (dni),
        INDEX idx_nombre (nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: triages (clasificación de urgencia de pacientes)
    // FOREIGN KEY (...) REFERENCES pacientes(id) ON DELETE CASCADE:
    // si se borra un paciente, se borran sus triages automáticamente
    $pdo->exec("CREATE TABLE IF NOT EXISTS triages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        nivel_gravedad TINYINT(3) NOT NULL,
        observaciones TEXT NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: medicos
    $pdo->exec("CREATE TABLE IF NOT EXISTS medicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        matricula VARCHAR(100) NULL,
        especialidad VARCHAR(255) NULL,
        activo TINYINT(1) DEFAULT 1,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: medicamentos
    $pdo->exec("CREATE TABLE IF NOT EXISTS medicamentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: prescripciones (recetas médicas)
    // medicamentos TEXT: se guarda como JSON con la lista de medicamentos y dosis
    // qr_code: código QR asociado a la receta
    // firma_digital: firma digital del médico
    // ON DELETE SET NULL: si se borra el médico, la receta queda sin médico asignado
    $pdo->exec("CREATE TABLE IF NOT EXISTS prescripciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_medico INT NULL,
        medicamentos TEXT NOT NULL,
        indicaciones TEXT NULL,
        fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_vencimiento DATE NULL,
        estado VARCHAR(50) DEFAULT 'activa',
        qr_code VARCHAR(255) NULL,
        firma_digital VARCHAR(255) NULL,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id) ON DELETE CASCADE,
        FOREIGN KEY (id_medico) REFERENCES medicos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Corrige la columna id_medico y su foreign key para permitir borrar médicos
    // (ON DELETE SET NULL: si se borra un médico, las recetas quedan sin médico)
    try {
        $pdo->exec("ALTER TABLE prescripciones MODIFY id_medico INT NULL");
    } catch (Exception $e) { /* ignorar */ }
    try {
        $pdo->exec("ALTER TABLE prescripciones DROP FOREIGN KEY prescripciones_ibfk_2");
    } catch (Exception $e) { /* ignorar */ }
    try {
        $pdo->exec("ALTER TABLE prescripciones ADD CONSTRAINT prescripciones_ibfk_2 FOREIGN KEY (id_medico) REFERENCES medicos(id) ON DELETE SET NULL");
    } catch (Exception $e) { /* ignorar */ }

    // Tabla: auditoria (registro de acciones realizadas en el sistema)
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        accion TEXT NOT NULL,
        fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: usuarios (para autenticación)
    // Almacena credenciales de acceso (médicos, pacientes, admins pueden tener acceso)
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        nombre VARCHAR(255) NOT NULL,
        tipo_usuario ENUM('paciente', 'medico', 'admin') DEFAULT 'paciente',
        id_paciente INT NULL,
        id_medico INT NULL,
        activo TINYINT(1) DEFAULT 1,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id) ON DELETE SET NULL,
        FOREIGN KEY (id_medico) REFERENCES medicos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

    // Si algo sale mal (no se puede conectar a MySQL), se atrapa la excepción
} catch (PDOException $e) {
    // PDOException: excepción específica de errores de PDO/base de datos
    // http_response_code(500): envía código HTTP 500 (Internal Server Error)
    http_response_code(500);
    // Muestra un mensaje de error formateado con HTML
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error de DB</title></head><body style="font-family:sans-serif; padding:30px; background:#f8f8f8;">';
    echo '<div style="max-width:820px; margin:auto; background:#fff; padding:24px; border-radius:16px; box-shadow:0 20px 60px rgba(0,0,0,0.08);">';
    // Título del error en color rojo, claro y directo
    echo '<h1 style="color:#b91c1c;">Error al conectar con la base de datos</h1>';
    echo '<p style="font-size:1rem; color:#333;">Detalle técnico: ' . htmlspecialchars($e->getMessage()) . '</p>';
    // htmlspecialchars(): evita XSS convirtiendo caracteres como < > & " en entidades HTML
    echo '<p>Revisa los parámetros en <code>db.php</code> y la configuración de MySQL.</p>';
    echo '</div></body></html>';
    exit;  // Detiene la ejecución del script
}
