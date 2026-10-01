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
    // MySQL NO tiene índices "parciales" (no se puede indexar solo las filas
    // que cumplen una condición), y en un índice UNIQUE los valores NULL
    // NO se consideran duplicados entre sí. Por eso la cuarta columna,
    // slot_reservado, vale 'reservado' mientras la cita está activa
    // (pendiente/confirmada/completada) y se pone en NULL cuando la cita
    // se cancela o el paciente no asiste.
    //
    // Consecuencia: el par (médico, fecha, hora) solo puede estar ocupado
    // por UNA cita viva, y al cancelar se liberan todas las horas posibles
    // del mismo horario sin chocar con el índice. Esa es la garantía de
    // que no se pueden superponer dos pacientes en el mismo consultorio.
    // ============================================================

    // Tabla: especialidades
    // Catálogo de especialidades médicas (Ej: Medicina General, Pediatría).
    // Existe como catálogo propio para poder agrupar, filtrar y medir
    // demanda por especialidad sin depender de texto libre.
    $pdo->exec("CREATE TABLE IF NOT EXISTS especialidades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_especialidad (nombre)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla: disponibilidades
    // Agenda horaria RECURRENTE del profesional: bloques de atención
    // semanales que se repiten todas las semanas.
    // Ej: id_medico=1, dia_semana=1..5, 08:00 a 12:00, turnos de 30 min.
    //
    // dia_semana usa la convención ISO-8601 de PHP: 1 = lunes ... 7 = domingo.
    // Es la misma que devuelve date('N') y la que usa la mayoría de las
    // librerías de calendario, así que no hay conversión al mostrar la agenda.
    //
    // OJO con un detalle que confunde: la función WEEKDAY() de MySQL NO usa
    // esta numeración (ella devuelve 0 = lunes ... 6 = domingo). Si algún día
    // se hace un cálculo del día de la semana en SQL, hay que sumar 1 o usar
    // DAYOFWEEK() a la inversa, o el resultado va corrido un día.
    //
    // duracion_minutos: largo de cada turno dentro del bloque (30 por defecto).
    // activo: permite desactivar un bloque sin borrarlo, para no perder el
    // histórico de las citas ya tomadas en ese horario.
    $pdo->exec("CREATE TABLE IF NOT EXISTS disponibilidades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_medico INT NOT NULL,
        dia_semana TINYINT(1) NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        duracion_minutos INT NOT NULL DEFAULT 30,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_medico) REFERENCES medicos(id) ON DELETE CASCADE,
        INDEX idx_disponibilidad_medico_dia (id_medico, dia_semana)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS citas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_medico INT NOT NULL,
        fecha DATE NOT NULL,
        hora TIME NOT NULL,
        duracion_minutos INT NOT NULL DEFAULT 30,
        estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        motivo VARCHAR(255) NULL,
        notas VARCHAR(500) NULL,
        recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
        creado_por_paciente TINYINT(1) NOT NULL DEFAULT 1,
        slot_reservado VARCHAR(20) NULL DEFAULT 'reservado',
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES pacientes(id) ON DELETE CASCADE,
        FOREIGN KEY (id_medico) REFERENCES medicos(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_slot (id_medico, fecha, hora, slot_reservado),
        INDEX idx_citas_paciente (id_paciente, fecha),
        INDEX idx_citas_medico_dia (id_medico, fecha),
        INDEX idx_citas_estado_fecha (estado, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
    $pdo->exec("CREATE TABLE IF NOT EXISTS notificaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_cita INT NOT NULL,
        id_usuario INT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'recordatorio',
        canal VARCHAR(20) NOT NULL DEFAULT 'sistema',
        destino VARCHAR(255) NULL,
        estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
        token_cancelacion VARCHAR(64) NULL,
        intentos INT NOT NULL DEFAULT 0,
        motivo_error VARCHAR(255) NULL,
        enviado_at TIMESTAMP NULL DEFAULT NULL,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cita) REFERENCES citas(id) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_token_cancelacion (token_cancelacion),
        UNIQUE KEY uniq_cita_tipo_canal (id_cita, tipo, canal),
        INDEX idx_notificaciones_estado (estado, creado_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // --------------------------------------------------
    // MIGRACIÓN: un aviso por (cita, tipo, canal)
    // --------------------------------------------------
    // El índice uniq_cita_tipo_canal de arriba solo se crea junto con la tabla.
    // En una base que ya tenía la tabla, hace falta agregarlo aparte.
    //
    // POR QUÉ: la generación de recordatorios revisa si la cita ya fue
    // avisada y después inserta. Entre la consulta y la insertación hay una
    // ventana: si dos procesos llaman al endpoint a la vez (un cron y un
    // clic, o dos réplicas del backend), los dos ven "no avisada" y los dos
    // insertan. El paciente recibe el recordatorio dos veces.
    //
    // La barrera real no es el SELECT sino la base: con el índice único, la
    // segunda insertación choca y se descarta. Ver
    // NotificacionRepository::crearSiNoExiste().
    try {
        $indices = [];
        foreach ($pdo->query("SHOW INDEX FROM notificaciones WHERE Key_name = 'uniq_cita_tipo_canal'") as $idx) {
            $indices[] = $idx['Key_name'];
        }
        if (empty($indices)) {
            // Antes de crear el índice hay que resolver los duplicados que
            // pueden haber dejado carreras anteriores. De cada grupo se
            // conserva el más viejo (el que se puede reintentar) y se borran
            // los sobrantes, porque un aviso duplicado no aporta nada.
            $pdo->exec(
                "DELETE n1 FROM notificaciones n1
                 INNER JOIN notificaciones n2
                    ON n1.id_cita = n2.id_cita
                   AND n1.tipo = n2.tipo
                   AND n1.canal = n2.canal
                   AND n1.id > n2.id"
            );
            $pdo->exec("ALTER TABLE notificaciones ADD UNIQUE KEY uniq_cita_tipo_canal (id_cita, tipo, canal)");
        }
    } catch (\PDOException $e) {
        // Si la migración falla no se corta el arranque: la aplicación
        // sigue funcionando, solo pierde la garantía de unicidad.
    }

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
    //   El servidor web (Apache/PHP) no puede "empujar" nada: cada petición
    //   termina cuando el script termina. Quien está esperando datos es el
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
        id INT AUTO_INCREMENT PRIMARY KEY,
        canal VARCHAR(60) NOT NULL,
        tipo VARCHAR(40) NOT NULL,
        datos TEXT NULL,
        creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_eventos_canal_id (canal, id),
        INDEX idx_eventos_creado (creado_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // El índice (canal, id) es el que hace que el endpoint SSE sea barato: la
    // consulta que hace el listener en cada vuelta es
    //   SELECT ... WHERE canal = ? AND id > ? ORDER BY id LIMIT 50
    // y con este índice es un recorrido corto desde el último id visto, no
    // un escaneo de la tabla. Sin él, con la tabla creciendo, cada vuelta
    // costaría más y el listener se iría retrasando.
    //
    // El índice por creado_at existe para la purga: borrar lo que ya no le
    // sirve a nadie con "WHERE creado_at < ?" usa ese índice y no la tabla.

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
    // INSERT IGNORE + SELECT DISTINCT: si la especialidad no existe todavía
    // se inserta; si ya está, IGNORE evita el error de clave duplicada
    // (unico_especialidad) y no interrumpe la creación del esquema.
    $pdo->exec(
        "INSERT IGNORE INTO especialidades (nombre)
         SELECT DISTINCT especialidad FROM medicos
         WHERE especialidad IS NOT NULL AND TRIM(especialidad) <> ''"
    );
    // Si el paso anterior no trajo nada (base ya creada antes de este módulo),
    // se carga un catálogo mínimo para que la autogestión tenga con qué trabajar.
    $especialidadesCount = (int)$pdo->query('SELECT COUNT(*) FROM especialidades')->fetchColumn();
    if ($especialidadesCount === 0) {
        $pdo->exec(
            "INSERT IGNORE INTO especialidades (nombre) VALUES
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
