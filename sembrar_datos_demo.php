<?php
// ============================================================
// sembrar_datos_demo.php - Datos de demostración limpios
// ============================================================
// Deja la tabla de usuarios con nombres que se leen de una persona a otra y
// con una sola cuenta de administrador.
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
// Cambia NOMBRES, EMAILS y ROLES de las cuentas de demostración.
//
// NO toca ninguna contraseña. Ni una. Este script no lee ni escribe la
// columna password, y eso es deliberado: la contraseña de la administradora
// es de ella y no hay razón para que un script de datos la toque. Para dejar
// entrar con una clave conocida hay otra herramienta, que dice la clave en
// pantalla.
//
// Es IDEMPOTENTE: correrlo dos veces deja lo mismo que correrlo una. Se puede
// usar para arreglar la base después de otra prueba que la haya dejado sucia.
//
// LA CUENTA ADMINISTRADORA
// ------------------------
// Queda una sola cuenta con rol admin: admin@salud.com, que es la de la
// administradora del proyecto. Su nombre y su email no se tocan. Las demás
// pasan a medico o paciente.
//
// Esto no es decorativo. Con cuatro administradores, la pantalla de Usuarios
// muestra cuatro filas que pueden cambiar roles, y no queda claro cuál es la
// que realmente manda. Con una, se lee de un vistazo.
//
// LOS NOMBRES DE LOS DEMÁS
// -------------------------
// Son ficticios y un poco ingeniosos, a propósito.
//
// La primera versión de la demo usaba nombres de la vida real (Dra. Laura
// Gómez, Pedro Sánchez). Resultaron un problema en los dos sentidos:
//
//   - Eran aburridos. Una tabla de usuarios con nombres así no dice de qué
//     trata la pantalla ni por qué hay Accounts de prueba.
//   - Y uno terminaba siendo el nombre de la administradora repetido, que en
//     una tabla de permisos se lee como un error de datos.
//
// Con nombres inventados, cada fila se reconoce de un vistazo y queda claro
// que el conjunto es de prueba sin que haya que decirlo.
//
// La cuenta admin@salud.com queda fuera de esta decisión a propósito: es una
// persona real y lleva su nombre real.
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
// RESULTADO
// ------------------------------------------------------------
echo PHP_EOL . str_repeat('-', 66) . PHP_EOL;
echo "Cuentas de la demostración" . PHP_EOL . str_repeat('-', 66) . PHP_EOL;

$filas = $pdo->query(
    'SELECT id, email, nombre, tipo_usuario, activo
     FROM usuarios ORDER BY tipo_usuario, nombre'
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($filas as $f) {
    if (!$f['activo']) {
        continue;
    }
    echo str_pad((string)$f['id'], 5)
        . str_pad($f['tipo_usuario'], 10)
        . str_pad($f['email'], 32)
        . $f['nombre'] . PHP_EOL;
}

$admins = $pdo->query("SELECT COUNT(*) AS c FROM usuarios WHERE tipo_usuario = 'admin' AND activo = 1")->fetch();
echo PHP_EOL . 'administradores activos: ' . $admins['c'] . PHP_EOL;
echo 'contraseñas: NO se tocó ninguna' . PHP_EOL;
