<?php
// ============================================================
// sembrar_datos_demo.php - Datos de demostración limpios
// ============================================================
// Deja la tabla de usuarios con nombres que se leen de una persona a otra y
// con una sola cuenta de administrador. Además crea la FICHA de cada cuenta
// de la demo (médico o paciente) y une una con la otra, que es lo que hace
// que la cuenta pueda operar: sin ficha, un médico no publica agenda ni
// receta, y un paciente no reserva turnos.
//
// POR QUÉ EXISTE
// --------------
// Los datos de arranque estaban acumulados a lo largo de muchas pruebas y
// quedó una mezcla de cuentas reales, cuentas de prueba y cuentas de
// una corrida anterior. Eso se veía en la pantalla de Usuarios: una sola
// administradora junto a tres cuentas de prueba que también eran admin,
// nombres como "Ailen Prog IV" o "Paciente JWT", y
// emails con el timestamp de una corrida ("prueba.turnera.1790550465@...").
//
// Para una demo eso se lee como que el sistema no tiene datos, cuando el
// problema es que tiene datos de basura.
//
// QUÉ HACE Y QUÉ NO HACE
// ----------------------
// Cambia NOMBRES, EMAILS y ROLES de las cuentas de demostración, y además:
//   1. Asegura que cada médico de la demo tenga ficha en la tabla medicos
//      (nombre, matrícula y especialidad) y le asigna esa ficha al usuario.
//   2. Asegura que cada paciente de la demo tenga ficha en la tabla pacientes
//      (nombre, DNI y obra social) y le asigna esa ficha al usuario.
//   3. Corrige vínculos que quedaron mal de pruebas viejas (un médico de la
//      demo apuntando a la ficha de otro médico, un paciente apuntando a una
//      ficha ajena) y apaga las fichas "fantasma" que copian el nombre de un
//      rol del otro (p.ej. un paciente llamado "Dr. Alonso Tramposo").
//
// NO toca ninguna contraseña. Ni una. Este script no lee ni escribe la
// columna password, y eso es deliberado: la contraseña de la administradora
// es de ella y no hay razón para que un script de datos la toque. Para dejar
// entrar con una clave conocida hay otra herramienta, que dice la clave en
// pantalla.
//
// Las fichas se identifican igual que el módulo de vinculación
// (sin repositorio: acá se escribe directo):
//   - médico   → matrícula (medicos.matricula)
//   - paciente → DNI (pacientes.dni, que además es UNIQUE)
// Si la ficha no existe se crea; si existe se adopta y se actualiza su
// nombre/datos de demo. Es IDEMPOTENTE: correrlo dos veces deja lo mismo.
//
// La cuenta administradora queda sin ficha a propósito: un admin opera con
// su rol y no necesita ficha clínica para administrar.
// ------------------------------------------------------------
declare(strict_types=1);

require __DIR__ . '/db.php';

/**
 * El casting de la demo.
 *
 * 'rol' con guion bajo en vez de la tilde de la vida real porque la columna
 * guarda el código del ENUM, no el texto que se muestra.
 *
 * Cada email tiene que ser ÚNICO. Si dos cuentas terminaran con el mismo
 * email, la segunda actualización pisa la primera y el resultado depende del
 * orden de MySQL, que es exactamente el tipo de bug que hace que un script
 * de datos parezca magia.
 *
 * @var array<int, array{email:string, nombre:string, rol:string}>
 */
$CASTING = [
    // --- La administradora. ÚNICA cuenta con rol admin. ---
    // Nombre y email reales. No se tocan en ninguna corrida de este script.
    ['email' => 'admin@salud.com', 'nombre' => 'Ailen Quaglino', 'rol' => 'admin'],

    // --- Médicos ---
    ['email' => 'medico@prueba.com', 'nombre' => 'Dra. Milagros Cifuentes', 'rol' => 'medico'],
    ['email' => 'sofia.ondina@saludweb.com', 'nombre' => 'Dra. Sofía Ondina', 'rol' => 'medico'],
    ['email' => 'alonso.tramposo@saludweb.com', 'nombre' => 'Dr. Alonso Tramposo', 'rol' => 'medico'],
    ['email' => 'bruno.cosmico@saludweb.com', 'nombre' => 'Dr. Bruno Cósmico', 'rol' => 'medico'],

    // --- Pacientes ---
    ['email' => 'paciente@prueba.com', 'nombre' => 'Capitán Ñoño Novoa', 'rol' => 'paciente'],
    ['email' => 'senora.gato.atomico@saludweb.com', 'nombre' => 'Doña del Gato Atómico', 'rol' => 'paciente'],
    ['email' => 'ada.byte@saludweb.com', 'nombre' => 'Profe Ada Byte', 'rol' => 'paciente'],
    ['email' => 'don.pedrito@saludweb.com', 'nombre' => 'Don Pedrito Confiado', 'rol' => 'paciente'],
    ['email' => 'prudencia.mentirosa@saludweb.com', 'nombre' => 'Doña Prudencia Mentirosa', 'rol' => 'paciente'],
];

// ------------------------------------------------------------
// CUENTAS QUE SE BORRAN
// ------------------------------------------------------------
// Estas dos se eliminan, no se renombran.
//
// La primera (prog4@correo.com) era una cuenta vieja de la administradora que
// repetía su nombre en la tabla de usuarios, y con el nombre real de ella al
// lado parecía un error de datos.
//
// La segunda es una cuenta que dejó una corrida vieja de pruebas, con un
// timestamp en el email. Es basura de pruebas, no un dato.
//
// Por qué borrar y no desactivar: antes las cuentas sobrantes se desactivaban
// para no romper nada que las apuntara. Se verificó que ninguna tabla las
// referencia (ni por Foreign Key ni por una columna suelta), así que el
// borrado no deja datos huérfanos y evita que sigan apareciendo en consultas
// que filtran por activo.
//
// Si algún día una de estas cuentas llegara a tener historial clínico, hay
// que cambiar el borrado por un desactivado antes de correr el script.
$BORRAR = [
    'prog4@correo.com',
    'prueba.turnera.%',   // el % es un comodín de SQL, no un nombre literal
];

// ------------------------------------------------------------
// FICHAS DE LA DEMO
// ------------------------------------------------------------
// Cada cuenta de la demo necesita su ficha para poder operar:
//
//   - Un médico sin ficha no puede publicar agenda ni prescribir.
//   - Un paciente sin ficha no puede reservar turnos.
//
// Estos dos arreglos son la ficha como la vería la pantalla: para el médico,
// matrícula y especialidad; para el paciente, DNI y obra social. El nombre de
// la ficha es el MISMO que el de la cuenta de usuario, porque el módulo de
// vinculación compara ambos (normalizados) además del documento: una ficha de
// "Dra. Milagros Cifuentes" con la cuenta "Dra. Milagros Cifuentes" vincula
// sola cuando el usuario entra en /configuracion.
//
// Los DNI y matrículas son inventados y del rango 100.xxx a propósito, para
// que nunca choquen con una ficha real que un día se cargue (las reales
// empiezan con 7.350.xxx para arriba o con otra forma).
//
// @var array<int, array{email:string, nombre:string, matricula:string, especialidad:string}>
$DEMO_MEDICOS = [
    [
        'email'        => 'medico@prueba.com',
        'nombre'       => 'Dra. Milagros Cifuentes',
        'matricula'    => 'MP-1001',
        'especialidad' => 'Medicina General',
    ],
    [
        'email'        => 'sofia.ondina@saludweb.com',
        'nombre'       => 'Dra. Sofía Ondina',
        'matricula'    => 'MP-1002',
        'especialidad' => 'Pediatría',
    ],
    [
        'email'        => 'alonso.tramposo@saludweb.com',
        'nombre'       => 'Dr. Alonso Tramposo',
        'matricula'    => 'MP-1003',
        'especialidad' => 'Cardiología',
    ],
    [
        'email'        => 'bruno.cosmico@saludweb.com',
        'nombre'       => 'Dr. Bruno Cósmico',
        'matricula'    => 'MP-1004',
        'especialidad' => 'Neurología',
    ],
];

/**
 * @var array<int, array{email:string, nombre:string, dni:string, obra:int}>
 */
$DEMO_PACIENTES = [
    [
        'email'  => 'paciente@prueba.com',
        'nombre' => 'Capitán Ñoño Novoa',
        'dni'    => '40.100.001',
        'obra'   => 1,
    ],
    [
        'email'  => 'senora.gato.atomico@saludweb.com',
        'nombre' => 'Doña del Gato Atómico',
        'dni'    => '40.100.002',
        'obra'   => 2,
    ],
    [
        'email'  => 'ada.byte@saludweb.com',
        'nombre' => 'Profe Ada Byte',
        'dni'    => '40.100.003',
        'obra'   => 1,
    ],
    [
        'email'  => 'don.pedrito@saludweb.com',
        'nombre' => 'Don Pedrito Confiado',
        'dni'    => '40.100.004',
        'obra'   => 3,
    ],
    [
        'email'  => 'prudencia.mentirosa@saludweb.com',
        'nombre' => 'Doña Prudencia Mentirosa',
        'dni'    => '40.100.005',
        'obra'   => 2,
    ],
];

// Nombres de DEMO de los médicos, para detectar fichas del otro rol que los
// copian. Normalizados: "Dr. Alonso Tramposo" y "dr  alonso  tramposo" son lo
// mismo, porque son los nombres que se comparan en el vincular.
$nombresMedicos = array_column($DEMO_MEDICOS, 'nombre');
$nombresPacientes = array_column($DEMO_PACIENTES, 'nombre');

// ------------------------------------------------------------
// EJECUCIÓN
// ------------------------------------------------------------

// El email viejo y el nuevo se emparejan para poder renombrar sin perder
// cuentas. Vive como una lista aparte y no como una columna más del casting
// porque son dos pasos distintos: primero se cambia el email de las cuentas
// que tienen un email provisional, y después se aplica el casting de nombre y
// rol. Si se hiciera todo junto, el WHERE del UPDATE apuntaría a un email que
// todavía no existe y no se actualizaría nadie.
$renombres = [
    'admin@prueba.com'   => 'sofia.ondina@saludweb.com',
    'jwttest@salud.com'  => 'alonso.tramposo@saludweb.com',
    'pacjwt@salud.com'   => 'ada.byte@saludweb.com',
    'turnera.prueba@salud.com' => 'senora.gato.atomico@saludweb.com',
    'turnera2@salud.com' => 'don.pedrito@saludweb.com',
    'otro.paciente@salud.com' => 'prudencia.mentirosa@saludweb.com',
    'medico.turnera@salud.com' => 'bruno.cosmico@saludweb.com',
    // Nombres de la corrida anterior de este mismo script, por si se corre
    // dos veces: se reencontran y vuelven al casting nuevo.
    'sofia.ramirez@saludweb.com' => 'sofia.ondina@saludweb.com',
    'diego.rojas@saludweb.com'   => 'bruno.cosmico@saludweb.com',
    'martin.gomez@saludweb.com'  => 'alonso.tramposo@saludweb.com',
    'carolina.diaz@saludweb.com' => 'ada.byte@saludweb.com',
    'pedro.sanchez@saludweb.com' => 'senora.gato.atomico@saludweb.com',
    'lucia.fernandez@saludweb.com' => 'don.pedrito@saludweb.com',
    'mateo.suarez@saludweb.com'  => 'prudencia.mentirosa@saludweb.com',
    'emilia.lopez@saludweb.com'  => null,  // se borra al final
];

// Primero se renombra, después se aplica el casting. Al revés, dos cuentas
// podrían terminar con el mismo email y el UPDATE se pisaría a sí mismo.
// El email viejo se toma tal cual está hoy en la base, y por eso la lista de
// renombres vive acá y no dentro del casting: son dos pasos distintos.
foreach ($renombres as $viejo => $nuevo) {
    if ($nuevo === null) {
        continue;
    }
    $s = $pdo->prepare('UPDATE usuarios SET email = ? WHERE email = ? AND email <> ?');
    $s->execute([$nuevo, $viejo, $nuevo]);
    if ($s->rowCount() > 0) {
        echo "  renombrado: {$viejo} -> {$nuevo}\n";
    }
}

// Se aplica el casting final: nombre y rol de cada cuenta.
$update = $pdo->prepare('UPDATE usuarios SET nombre = ?, tipo_usuario = ? WHERE email = ?');
foreach ($CASTING as $fila) {
    $update->execute([$fila['nombre'], $fila['rol'], $fila['email']]);
    echo "  casting: {$fila['email']} -> {$fila['nombre']} ({$fila['rol']})\n";
}

// Las cuentas indicadas arriba se borran de verdad.
//
// Se hace DESPUÉS del casting y no antes, a propósito: el casting funciona
// con emails, y si se borrara antes, una cuenta que debe renombrarse no
// tendría a dónde ir. Ese orden hace que el script sea idempotente también
// cuando se lo corre dos veces seguidas.
foreach ($BORRAR as $patron) {
    $borrar = $pdo->prepare("DELETE FROM usuarios WHERE email LIKE ?");
    $borrar->execute([$patron]);
    if ($borrar->rowCount() > 0) {
        echo "  borrado: {$borrar->rowCount()} cuenta(s) con email tipo '{$patron}'\n";
    }
}

// Cualquier cuenta que no esté en el casting y que no sea la administradora
// queda fuera de la demo. Se desactivan en lugar de borrarse: borrar es
// irreversible y podría romper una prescripción, una cita o una ficha que la
// apunte por id. Desactivar la deja intacta pero fuera de las pantallas.
//
// La exclusión de 'descartable.%' está porque las pruebas crean cuentas
// temporales con ese prefijo. Si este script las desactivara, la prueba que
// las está usando empezaría a fallar sin motivo aparente.
$emails = array_column($CASTING, 'email');
$placeholders = implode(',', array_fill(0, count($emails), '?'));
$s = $pdo->prepare(
    "UPDATE usuarios SET activo = 0
     WHERE email NOT IN ({$placeholders})
       AND email NOT LIKE 'descartable.%'
       AND email NOT LIKE 'verificacion.%'"
);
$s->execute($emails);
$desactivados = $s->rowCount();
if ($desactivados > 0) {
    echo "  desactivados: {$desactivados} cuentas que no son parte de la demo\n";
}

// ------------------------------------------------------------
// FICHAS: CREAR O ADOPTAR Y VINCULAR
// ------------------------------------------------------------
// Acá se escriben las tablas medicos y pacientes DENTRO del mismo script que
// deja las cuentas, porque una cuenta sin ficha es una cuenta que no puede
// operar. Separar la cuenta de su ficha en dos scripts sería una demo a
// medias: hay que correr siempre los dos, y en cuanto se corra uno solo,
// nadie se entera.
//
// La identificación de una ficha existente es la misma que usa el backend
// para vincular (matrícula para médico, DNI para paciente), así que si la
// ficha ya existe por accidente de una prueba, se adopta en vez de duplicar.

/**
 * Normaliza un nombre igual que lo hace el servicio de vinculación: en
 * minúsculas, sin tildes y con los espacios colapsados. Es la misma
 * comparación que usa AuthService al vincular, copiada acá para no arrastrar
 * esa dependencia a un script de datos.
 */
function normalizarNombre(string $texto): string
{
    $sinAcentos = strtr(mb_strtolower($texto), [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'ñ',
    ]);

    // mb_ereg_replace por expresión regular para colapsar los espacios
    // repetidos ("pedro   perez" -> "pedro perez").
    return trim((string)mb_ereg_replace('\s+', ' ', $sinAcentos));
}

// --- Médicos ---
// Por cada médico de la demo: se busca la ficha por matrícula (que es como
// la busca el vincular); si no, por nombre; si tampoco, se crea. Después se
// la actualiza con los datos de demo y se la clava al usuario.
$buscarMed = $pdo->prepare('SELECT id FROM medicos WHERE matricula = ? LIMIT 1');
$buscarMedPorNombre = $pdo->prepare('SELECT id FROM medicos WHERE nombre = ? LIMIT 1');
$crearMed = $pdo->prepare('INSERT INTO medicos (nombre, matricula, especialidad, activo) VALUES (?, ?, ?, 1)');
$actualizarMed = $pdo->prepare('UPDATE medicos SET nombre = ?, matricula = ?, especialidad = ?, activo = 1 WHERE id = ?');
$vincularMed = $pdo->prepare('UPDATE usuarios SET id_medico = ? WHERE email = ?');

echo PHP_EOL . 'FICHAS DE MÉDICO' . PHP_EOL;
foreach ($DEMO_MEDICOS as $med) {
    $origen = null;

    $buscarMed->execute([$med['matricula']]);
    $ficha = $buscarMed->fetch(PDO::FETCH_ASSOC);

    if ($ficha) {
        $origen = 'matrícula';
    } else {
        // La matrícula no es única en el esquema, así que si no apareció,
        // todavía puede existir la ficha con el nombre: es la ficha que quedó
        // de una corrida anterior de pruebas de vinculación.
        $buscarMedPorNombre->execute([$med['nombre']]);
        $ficha = $buscarMedPorNombre->fetch(PDO::FETCH_ASSOC);
        if ($ficha) {
            $origen = 'nombre';
        }
    }

    if ($ficha === false) {
        $crearMed->execute([$med['nombre'], $med['matricula'], $med['especialidad']]);
        $idFicha = (int)$pdo->lastInsertId();
        $origen = 'creada';
    } else {
        $idFicha = (int)$ficha['id'];
        // Se re-escribe la ficha adoptada con los datos canónicos de la demo:
        // si una ficha de prueba trajo otra matrícula o estaba dada de baja,
        // acá vuelve a quedar como la demo la espera.
        $actualizarMed->execute([$med['nombre'], $med['matricula'], $med['especialidad'], $idFicha]);
    }

    $vincularMed->execute([$idFicha, $med['email']]);
    echo "  {$med['email']} -> ficha #{$idFicha} ({$origen}) {$med['nombre']}\n";
}

// --- Pacientes ---
// Ídem, pero por DNI (que acá sí es UNIQUE, así que la búsqueda por DNI
// nunca puede devolver dos fichas).
$buscarPac = $pdo->prepare('SELECT id FROM pacientes WHERE dni = ? LIMIT 1');
$buscarPacPorNombre = $pdo->prepare('SELECT id FROM pacientes WHERE nombre = ? LIMIT 1');
$crearPac = $pdo->prepare('INSERT INTO pacientes (dni, nombre, id_obra_social, activo) VALUES (?, ?, ?, 1)');
$actualizarPac = $pdo->prepare('UPDATE pacientes SET dni = ?, nombre = ?, id_obra_social = ?, activo = 1 WHERE id = ?');
$vincularPac = $pdo->prepare('UPDATE usuarios SET id_paciente = ? WHERE email = ?');

echo PHP_EOL . 'FICHAS DE PACIENTE' . PHP_EOL;
foreach ($DEMO_PACIENTES as $pac) {
    $origen = null;

    $buscarPac->execute([$pac['dni']]);
    $ficha = $buscarPac->fetch(PDO::FETCH_ASSOC);

    if ($ficha) {
        $origen = 'DNI';
    } else {
        $buscarPacPorNombre->execute([$pac['nombre']]);
        $ficha = $buscarPacPorNombre->fetch(PDO::FETCH_ASSOC);
        if ($ficha) {
            $origen = 'nombre';
        }
    }

    if ($ficha === false) {
        $crearPac->execute([$pac['dni'], $pac['nombre'], $pac['obra']]);
        $idFicha = (int)$pdo->lastInsertId();
        $origen = 'creada';
    } else {
        $idFicha = (int)$ficha['id'];
        $actualizarPac->execute([$pac['dni'], $pac['nombre'], $pac['obra'], $idFicha]);
    }

    $vincularPac->execute([$idFicha, $pac['email']]);
    echo "  {$pac['email']} -> ficha #{$idFicha} ({$origen}) {$pac['nombre']}\n";
}

// ------------------------------------------------------------
// FICHAS FANTASMA
// ------------------------------------------------------------
// Durante las pruebas de vinculación se crearon fichas que copian el nombre
// de un rol del otro, el caso más claro es un PACIENTE llamado
// "Dr. Alonso Tramposo". En una tabla de pacientes eso se lee como un error
// de datos (un doctor en la lista de pacientes). Se apagan, no se borran:
// borrar una ficha podría romper citas o prescripciones que la apunten.
echo PHP_EOL . 'FICHAS SIN USO' . PHP_EOL;

$nombresMedicosNorm = array_map('normalizarNombre', $nombresMedicos);
$nombresPacientesNorm = array_map('normalizarNombre', $nombresPacientes);

// Paciente cuyo nombre es el de un médico de la demo.
foreach ($pdo->query('SELECT id, nombre FROM pacientes') as $p) {
    if (in_array(normalizarNombre((string)$p['nombre']), $nombresMedicosNorm, true)) {
        $pdo->prepare('UPDATE pacientes SET activo = 0 WHERE id = ?')->execute([(int)$p['id']]);
        echo "  paciente #{$p['id']} apagada ({$p['nombre']} es nombre de médico)\n";
    }
}

// Médico cuyo nombre es el de un paciente de la demo (por simetría).
foreach ($pdo->query('SELECT id, nombre FROM medicos') as $m) {
    if (in_array(normalizarNombre((string)$m['nombre']), $nombresPacientesNorm, true)) {
        $pdo->prepare('UPDATE medicos SET activo = 0 WHERE id = ?')->execute([(int)$m['id']]);
        echo "  médico #{$m['id']} apagado ({$m['nombre']} es nombre de paciente)\n";
    }
}

// ------------------------------------------------------------
// RESULTADO
// ------------------------------------------------------------
echo PHP_EOL . str_repeat('-', 66) . PHP_EOL;
echo "Cuentas de la demostración" . PHP_EOL . str_repeat('-', 66) . PHP_EOL;

$filas = $pdo->query(
    'SELECT id, email, nombre, tipo_usuario, activo, id_paciente, id_medico
     FROM usuarios ORDER BY tipo_usuario, nombre'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($filas as $f) {
    if (!$f['activo']) {
        continue;
    }
    // La última columna dice si la cuenta ya tiene ficha, que es lo que la
    // vuelve operativa. En la pantalla de Usuarios ese dato sale como
    // "vinculado"; acá se imprime igual para que el estado de la demo se
    // pueda confirmar de un vistazo sin abrir la web.
    $ficha = $f['tipo_usuario'] === 'admin'
        ? '(sin ficha, no la necesita)'
        : (($f['tipo_usuario'] === 'medico' ? 'medico#' : 'paciente#')
            . (int)($f['tipo_usuario'] === 'medico' ? $f['id_medico'] : $f['id_paciente']));
    echo str_pad((string)$f['id'], 5)
        . str_pad($f['tipo_usuario'], 10)
        . str_pad($f['email'], 32)
        . str_pad($ficha, 28)
        . $f['nombre'] . PHP_EOL;
}

$admins = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE tipo_usuario = 'admin' AND activo = 1")->fetch();
echo PHP_EOL . 'administradores activos: ' . $admins['c'] . PHP_EOL;
echo 'contraseñas: NO se tocó ninguna' . PHP_EOL;
