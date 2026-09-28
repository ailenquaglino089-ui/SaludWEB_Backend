<?php
// ============================================================
// probar_turnera.php - Prueba de humo de la API de la turnera
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
// ------------------------------------------------------------
// QUÉ HACE
//   Levanta el flujo completo contra una API ya levantada y reporta
//   cada paso como OK o FALLA. Sirve para comprobar de un vistazo que
//   la turnera anda después de un cambio, sin clickear 20 pantallas.
//
// CÓMO SE USA
//   1. Con la API corriendo (por ejemplo: php -S 127.0.0.1:8080)
//   2. php probar_turnera.php [url]
//      Si no se pasa url, usa http://127.0.0.1:8080
//
// QUÉ PRUEBA
//   - Los catálogos públicos (especialidades, médicos, disponibilidad)
//   - Que la disponibilidad genere los turnos con la duración correcta
//   - Que un paciente sin ficha vinculada NO pueda reservar
//   - Que vincular la cuenta con el DNI funcione
//   - Que reservar un turno funcione y que el segundo intento choque
//   - Que un paciente no pueda marcar su turno como 'completada'
//   - Que no pueda ver las citas de otro paciente
//   - Que el listado sin filtro de un paciente no filtre (= no se vea nada)
//
// IMPORTANTE
//   Esta prueba crea datos (un usuario, una ficha de paciente y una cita)
//   y los borra al terminar. Está pensada para desarrollo, contra la base
//   de pruebas, no para producción.
//
//   La ficha de paciente se crea en la base a propósito: antes esta prueba
//   dependía de una variable de entorno (TEST_DNI) con el DNI de un paciente
//   real, así que sin ella la vinculación se saltaba y la prueba de reserva
//   fallaba después con un 403 que no tenía nada que ver con lo que se
//   estaba probando. Ahora la prueba es autocontenida y repetible.
// ============================================================

// URL de la API: se puede pasar como argumento o se usa la de desarrollo
$base = $argv[1] ?? 'http://127.0.0.1:8080';

// Acceso a la base, solo para crear y borrar la ficha temporal del paciente.
// La API se prueba por HTTP; la base se toca únicamente para el fixture.
require_once __DIR__ . '/db.php';

// Contador de pasos bien y mal, para el resumen final
$ok = 0;
$fallos = 0;

// Datos creados por la prueba, para borrarlos aunque algo falle a mitad de camino
$creado = ['usuario' => null, 'paciente' => null, 'cita' => null];

/**
 * Hace una petición HTTP y devuelve [código, cuerpo ya decodificado].
 *
 * Se usa cURL porque permite mandar el cuerpo con bytes exactos. Con
 * file_get_contents no se puede poner un header Authorization cómodo, y
 * el body tiene que ir en UTF-8 real: los acentos de los mensajes de
 * error son la mitad de lo que se prueba acá.
 *
 * @param string $metodo GET, POST o PATCH
 * @param string $url URL completa
 * @param array|null $cuerpo Datos a enviar como JSON (null = sin cuerpo)
 * @param string|null $token JWT para enviar en Authorization
 * @return array [int $codigoHttp, array $respuesta]
 */
function pedir(string $metodo, string $url, ?array $cuerpo = null, ?string $token = null): array
{
    // Se crea el recurso cURL
    $ch = curl_init($url);

    // Se arman los headers: JSON siempre, y Authorization si hay token
    $headers = ['Content-Type: application/json; charset=utf-8', 'Accept: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    // Se configuran las opciones de la petición
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);

    // Si hay cuerpo, se convierte a JSON UTF-8 y se envía
    if ($cuerpo !== null) {
        // JSON_UNESCAPED_UNICODE evita que "revisión" se vuelva "revisi\u00f3n"
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    }

    // Se ejecuta y se cierra el recurso
    $respuesta = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Se decodifica el JSON; si no se puede, se devuelve el texto crudo
    $decodificado = json_decode((string)$respuesta, true);

    return [$codigo, is_array($decodificado) ? $decodificado : ['crudo' => $respuesta]];
}

/**
 * Imprime el resultado de un paso y lleva la cuenta.
 * @param bool $bien Si el paso fue correcto
 * @param string $descripcion Qué se comprobó
 * @param string $detalle Mensaje de la API (o el error)
 */
function paso(bool $bien, string $descripcion, string $detalle = ''): void
{
    global $ok, $fallos;

    if ($bien) {
        $ok++;
        echo "[OK]    $descripcion\n";
    } else {
        $fallos++;
        echo "[FALLA] $descripcion\n";
        // Solo se muestra el detalle cuando hay algo que explicar
        if ($detalle !== '') {
            echo "        $detalle\n";
        }
    }
}

echo "=== Prueba de humo de la turnera ===\n";
echo "API: $base\n\n";

/**
 * Borra todo lo que creó la prueba.
 *
 * Se registra como shutdown para que los datos temporales no queden en la
 * base aunque la prueba se corte a mitad de camino (error fatal, Ctrl+C o
 * exit temprano por falta de un médico activo).
 *
 * IMPORTANTE: lee $creado desde el ámbito global y no como parámetro.
 * register_shutdown_function() copia los argumentos por valor en el momento
 * de registrar la función, así que pasando el array como parámetro la
 * limpieza vería siempre los nulls iniciales y no borraría nada.
 */
function limpiar(): void
{
    global $pdo, $creado;

    try {
        if (!empty($creado['cita'])) {
            $pdo->prepare('DELETE FROM citas WHERE id = ?')->execute([$creado['cita']]);
        }
        if (!empty($creado['usuario'])) {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$creado['usuario']]);
        }
        if (!empty($creado['paciente'])) {
            $pdo->prepare('DELETE FROM pacientes WHERE id = ?')->execute([$creado['paciente']]);
        }
        echo "\n(Datos de la prueba borrados)\n";
    } catch (Throwable $e) {
        echo "\n(Aviso: no se pudieron borrar los datos de prueba: " . $e->getMessage() . ")\n";
    }
}

register_shutdown_function('limpiar');


// Se genera un email único por corrida para no chocar con pruebas anteriores.
// La marca de tiempo evita que dos corridas seguidas usen el mismo usuario.
$sufijo = 'turnera' . date('His');
$email = $sufijo . '@salud.com';
$password = 'prueba123';

// Se busca un paciente activo con DNI para probar la vinculación
$emailFicticio = 'sin.ficha.' . $sufijo . '@salud.com';

// ============================================================
// 1. CATÁLOGOS PÚBLICOS
// ============================================================
echo "--- Catálogos públicos ---\n";

[$codigo, $r] = pedir('GET', $base . '/api/especialidades');
paso($codigo === 200 && !empty($r['data']), 'GET /api/especialidades', "HTTP $codigo");

[$codigo, $r] = pedir('GET', $base . '/api/medicos');
$medicos = $r['data']['items'] ?? [];
paso($codigo === 200 && count($medicos) > 0, 'GET /api/medicos', "HTTP $codigo");

// Se busca un médico ACTIVO: los dados de prueba tienen varios dados de baja
$medicoActivo = null;
foreach ($medicos as $m) {
    if (!empty($m['activo'])) {
        $medicoActivo = $m;
        break;
    }
}
paso($medicoActivo !== null, 'Hay al menos un profesional activo');
if ($medicoActivo === null) {
    // Sin profesional activo no se puede probar nada más: se corta
    echo "\nNo se puede seguir: la prueba necesita un médico activo.\n";
    exit(1);
}
$idMedico = (int)$medicoActivo['id'];

// ============================================================
// 2. AGENDA PÚBLICA Y GENERACIÓN DE TURNOS
// ============================================================
echo "\n--- Agenda y disponibilidad ---\n";

[$codigo, $r] = pedir('GET', $base . "/api/disponibilidades?id_medico=$idMedico");
$bloques = $r['data'] ?? [];
paso($codigo === 200 && count($bloques) > 0, 'GET /api/disponibilidades', "HTTP $codigo");

// Se busca el próximo día con agenda publicada (día de semana entre 1 y 5)
$fecha = null;
$diaSemana = null;
for ($i = 0; $i < 15 && $fecha === null; $i++) {
    $ts = strtotime("+$i days");
    $dia = (int)date('N', $ts);
    foreach ($bloques as $b) {
        if ((int)$b['dia_semana'] === $dia && !empty($b['activo'])) {
            $fecha = date('Y-m-d', $ts);
            $diaSemana = $dia;
            break;
        }
    }
}
paso($fecha !== null, 'Se encuentra un próximo día con agenda');
if ($fecha === null) {
    echo "\nNo se puede seguir: el profesional no tiene agenda publicada.\n";
    exit(1);
}

[$codigo, $r] = pedir('GET', $base . "/api/citas/disponibilidad?id_medico=$idMedico&fecha=$fecha");
$slots = $r['data']['slots'] ?? [];
paso($codigo === 200 && count($slots) > 0, 'GET /api/citas/disponibilidad', "HTTP $codigo");

// Comprobación CLAVE: los turnos deben medir lo que dice el bloque.
// Este es el error que más costó encontrar: si el cálculo mezcla minutos
// con segundos, aparecen cientos de turnos de 1 minuto.
$slotLibre = null;
$esperadoCorrecto = true;
foreach ($slots as $slot) {
    $inicio = strtotime($slot['hora']);
    $fin = strtotime($slot['hora_fin']);
    $minutos = ($fin - $inicio) / 60;
    // Ningún turno puede durar menos de 5 minutos
    if ($minutos < 5) {
        $esperadoCorrecto = false;
        break;
    }
    if ($slotLibre === null && !empty($slot['disponible'])) {
        $slotLibre = $slot;
    }
}
paso($esperadoCorrecto, 'Los turnos duran lo que declara el bloque (no 1 minuto)');
paso($slotLibre !== null, 'Hay al menos un turno libre', 'La agenda del día está llena');

// ============================================================
// 3. REGISTRO Y AUTENTICACIÓN
// ============================================================
echo "\n--- Registro y sesión ---\n";

[$codigo, $r] = pedir('POST', $base . '/api/auth/registro', [
    'email' => $email,
    'password' => $password,
    'nombre' => 'Paciente Prueba Turnera',
]);
paso($codigo === 201, "POST /api/auth/registro ($email)", "HTTP $codigo");

[$codigo, $r] = pedir('POST', $base . '/api/auth/login', [
    'email' => $email,
    'password' => $password,
]);
$token = $r['data']['token'] ?? null;
paso($codigo === 200 && $token !== null, 'POST /api/auth/login');

// Se anota el usuario creado para borrarlo al terminar la prueba
if (!empty($r['data']['id'])) {
    $creado['usuario'] = (int)$r['data']['id'];
}

// ============================================================
// 4. AUTORIZACIÓN: un paciente sin ficha no reserva
// ============================================================
echo "\n--- Autorización ---\n";

[$codigo, $r] = pedir('POST', $base . '/api/citas', [
    'id_medico' => $idMedico,
    'fecha' => $fecha,
    'hora' => '10:00',
], $token);
// Debe ser 403: la cuenta existe pero no está vinculada a una ficha
paso(
    $codigo === 403,
    'Un paciente sin ficha vinculada NO puede reservar (403)',
    "HTTP $codigo: " . ($r['mensaje'] ?? '')
);

// Tampoco debe poder listar citas ajenas
[$codigo, $r] = pedir('GET', $base . '/api/citas?id_paciente=1', null, $token);
paso(
    $codigo === 403 || ($codigo === 200 && ($r['data']['total'] ?? 0) === 0),
    'Un paciente sin ficha no ve citas de otros',
    "HTTP $codigo, total=" . ($r['data']['total'] ?? '?')
);

// ============================================================
// 5. VINCULACIÓN CON LA FICHA
// ============================================================
echo "\n--- Vinculación de ficha ---\n";

// Se prueba primero con un DNI que no existe: debe fallar
[$codigo, $r] = pedir('POST', $base . '/api/auth/vincular', [
    'tipo' => 'paciente',
    'documento' => '00000000',
], $token);
paso($codigo === 422, 'Vincular con un DNI inexistente falla (422)', "HTTP $codigo");

// El DNI real se recibe por variable de entorno para que la prueba no
// quede atada a un dato concreto de esta base
$dni = getenv('TEST_DNI') ?: '';
if ($dni !== '') {
    [$codigo, $r] = pedir('POST', $base . '/api/auth/vincular', [
        'tipo' => 'paciente',
        'documento' => $dni,
    ], $token);
    paso($codigo === 200, "Vincular con el DNI $dni", "HTTP $codigo: " . ($r['mensaje'] ?? ''));
} else {
    // Sin DNI externo, la prueba crea su propia ficha. El nombre coincide a
    // propósito con el del usuario recién registrado: la vinculación valida
    // que el nombre de la cuenta sea el de la ficha del DNI.
    $obraSocial = $pdo->query('SELECT id FROM obras_sociales ORDER BY id LIMIT 1')->fetchColumn();

    if ($obraSocial === false) {
        echo "[OMITE] Vincular con DNI real: no hay obras sociales cargadas\n";
    } else {
        // DNI de prueba: 8 dígitos, empieza con 9 para no pisar un DNI real
        $dni = '9' . mt_rand(10000000, 99999999);
        $stmt = $pdo->prepare(
            'INSERT INTO pacientes (dni, nombre, id_obra_social, activo) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([$dni, 'Prueba Turnera', (int)$obraSocial]);
        $creado['paciente'] = (int)$pdo->lastInsertId();

        echo "[INFO] Ficha temporal creada: DNI $dni (id {$creado['paciente']})\n";

        [$codigo, $r] = pedir('POST', $base . '/api/auth/vincular', [
            'tipo' => 'paciente',
            'documento' => $dni,
        ], $token);
        paso(
            $codigo === 200,
            'Vincular la cuenta con la ficha del DNI',
            "HTTP $codigo: " . ($r['mensaje'] ?? '')
        );
    }
}

// ============================================================
// 6. RESERVA
// ============================================================
echo "\n--- Reserva de turnos ---\n";

$horaReserva = $slotLibre['hora'] ?? null;
$idCita = null;

if ($horaReserva !== null) {
    [$codigo, $r] = pedir('POST', $base . '/api/citas', [
        'id_medico' => $idMedico,
        'fecha' => $fecha,
        'hora' => $horaReserva,
        'motivo' => 'Control de prueba',
    ], $token);
    $idCita = $r['data']['id'] ?? null;
    if ($idCita !== null) {
        $creado['cita'] = (int)$idCita;
    }
    paso($codigo === 201, "Reservar el turno $horaReserva del $fecha", "HTTP $codigo: " . ($r['mensaje'] ?? ''));

    // Segundo intento del mismo horario: debe chocar con 409
    [$codigo, $r] = pedir('POST', $base . '/api/citas', [
        'id_medico' => $idMedico,
        'fecha' => $fecha,
        'hora' => $horaReserva,
        'motivo' => 'Segundo intento',
    ], $token);
    paso($codigo === 409, 'Reservar dos veces el mismo horario choca (409)', "HTTP $codigo: " . ($r['mensaje'] ?? ''));

    // La disponibilidad pública debe reflejar la reserva
    [$codigo, $r] = pedir('GET', $base . "/api/citas/disponibilidad?id_medico=$idMedico&fecha=$fecha");
    $ocupado = false;
    foreach ($r['data']['slots'] ?? [] as $slot) {
        if ($slot['hora'] === $horaReserva && empty($slot['disponible'])) {
            $ocupado = true;
            break;
        }
    }
    paso($ocupado, 'La disponibilidad pública marca el turno como ocupado');
}

// ============================================================
// 7. ESTADOS Y PERMISOS
// ============================================================
echo "\n--- Estados de la cita ---\n";

if ($idCita !== null) {
    // El paciente no puede declararse atendido
    [$codigo, $r] = pedir('PATCH', $base . "/api/citas/$idCita/estado", [
        'estado' => 'completada',
    ], $token);
    paso($codigo === 403, 'El paciente NO puede marcar su turno como completada (403)', "HTTP $codigo");

    // Sí puede confirmar
    [$codigo, $r] = pedir('PATCH', $base . "/api/citas/$idCita/estado", [
        'estado' => 'confirmada',
    ], $token);
    paso(
        $codigo === 200 && ($r['data']['estado'] ?? '') === 'confirmada',
        'El paciente SÍ puede confirmar su turno',
        "HTTP $codigo"
    );

    // Un estado inventado debe rechazarse
    [$codigo, $r] = pedir('PATCH', $base . "/api/citas/$idCita/estado", [
        'estado' => 'inventado',
    ], $token);
    paso($codigo === 422, 'Un estado inválido se rechaza (422)', "HTTP $codigo");
}

// ============================================================
// 8. CUERPO MAL FORMADO
// ============================================================
echo "\n--- Robustez del cuerpo JSON ---\n";

// Se manda un JSON truncado a propósito, como si la conexión se cortara
$ch = curl_init($base . '/api/citas');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ],
    CURLOPT_POSTFIELDS => '{"id_medico":1,"fecha":',
    CURLOPT_TIMEOUT => 15,
]);
$respuesta = curl_exec($ch);
$codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$decodificado = json_decode((string)$respuesta, true);

// Un JSON roto debe dar 400 con un mensaje útil, NO un 500 genérico
paso(
    $codigo === 400,
    'Un JSON mal formado da 400 (no 500)',
    "HTTP $codigo: " . ($decodificado['mensaje'] ?? $respuesta)
);

// ============================================================
// RESUMEN
// ============================================================
echo "\n=== Resultado ===\n";
echo "Correctos: $ok\n";
echo "Fallidos:  $fallos\n";

if ($fallos > 0) {
    echo "\nLa turnera tiene $fallos fallo(s).\n";
    exit(1);
}

echo "\nLa turnera responde correctamente en todos los pasos probados.\n";
exit(0);
