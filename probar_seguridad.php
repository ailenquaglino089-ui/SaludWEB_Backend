<?php
// ============================================================
// probar_seguridad.php - Regresión de los controles de acceso
// ============================================================
// QUÉ COMPRUEBA
//   Dos agujeros que estuvieron abiertos y que deben quedar cerrados:
//
//   INC-001  GET /api/pacientes y GET /api/pacientes/{id} eran PÚBLICAS:
//            cualquiera bajaba la lista de pacientes (nombre + DNI) sin
//            token. Ahora exigen Authorization: Bearer <jwt> (401 si falta).
//
//   INC-004  POST /api/auth/registro aceptaba tipo_usuario=admin: cualquiera
//            podía auto-asignarse el rol con más permisos. Ahora solo admite
//            'paciente' o 'medico' (422 para cualquier otro rol).
//
// CÓMO SE USA
//   1. Con la API corriendo (por ejemplo: composer run dev)
//   2. php probar_seguridad.php [url]
//      Si no se pasa url, usa http://127.0.0.1:8000
//
// Crea un usuario de prueba (paciente) y lo borra al terminar. Los intentos
// con rol admin / inexistente se rechazan antes de escribir, así que no dejan
// datos en la base.
// ============================================================

// URL de la API: se puede pasar como argumento o se usa la de desarrollo
$base = $argv[1] ?? 'http://127.0.0.1:8000';

// Acceso a la base, solo para borrar el usuario de prueba al final.
// La API se prueba por HTTP; la base se toca únicamente para la limpieza.
require_once __DIR__ . '/db.php';

// Contador de pasos bien y mal, para el resumen final
$ok = 0;
$fallos = 0;

// Datos creados por la prueba, para borrarlos aunque algo falle a mitad de camino
$creado = ['usuario' => null];

/**
 * Hace una petición HTTP y devuelve [código, cuerpo ya decodificado].
 *
 * Mismo helper que probar_turnera.php: usa cURL para poder mandar el header
 * Authorization cómodo y el cuerpo en UTF-8 real.
 *
 * @param string $metodo GET, POST, PUT, PATCH o DELETE
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
        if ($detalle !== '') {
            echo "        $detalle\n";
        }
    }
}

/**
 * Borra el usuario que creó la prueba.
 *
 * Se registra como shutdown para que no quede en la base aunque la prueba se
 * corte a mitad de camino (error fatal, Ctrl+C o exit temprano).
 */
function limpiar(): void
{
    global $pdo, $creado;

    try {
        if (!empty($creado['usuario'])) {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$creado['usuario']]);
        }
        echo "\n(Datos de la prueba borrados)\n";
    } catch (Throwable $e) {
        echo "\n(Aviso: no se pudieron borrar los datos de prueba: " . $e->getMessage() . ")\n";
    }
}

register_shutdown_function('limpiar');

// Sufijo único por corrida para no chocar con pruebas anteriores
$sufijo = 'seguridad' . date('His');

echo "=== Prueba de controles de acceso ===\n";
echo "API: $base\n\n";

// ============================================================
// 1. INC-004: el auto-registro NO puede crear un administrador
// ============================================================
echo "--- INC-004: el auto-registro no crea admin ---\n";

[$codigo, $r] = pedir('POST', $base . '/api/auth/registro', [
    'email' => 'intruso.' . $sufijo . '@salud.com',
    'password' => 'prueba123',
    'nombre' => 'Intruso Admin',
    'tipo_usuario' => 'admin',
]);
paso($codigo === 422, 'registro con tipo_usuario=admin se rechaza (422)', "HTTP $codigo");

[$codigo, $r] = pedir('POST', $base . '/api/auth/registro', [
    'email' => 'raro.' . $sufijo . '@salud.com',
    'password' => 'prueba123',
    'nombre' => 'Rol Inexistente',
    'tipo_usuario' => 'superadmin',
]);
paso($codigo === 422, 'registro con un rol inexistente se rechaza (422)', "HTTP $codigo");

// ============================================================
// 2. Alta válida + login (para conseguir un token de verdad)
// ============================================================
echo "\n--- Alta válida y token ---\n";

$email = 'paciente.' . $sufijo . '@salud.com';
[$codigo, $r] = pedir('POST', $base . '/api/auth/registro', [
    'email' => $email,
    'password' => 'prueba123',
    'nombre' => 'Paciente Seguridad',
    'tipo_usuario' => 'paciente',
]);
paso($codigo === 201, 'registro de paciente válido (201)', "HTTP $codigo");
if (!empty($r['data']['id'])) {
    $creado['usuario'] = (int)$r['data']['id'];
}

[$codigo, $r] = pedir('POST', $base . '/api/auth/login', [
    'email' => $email,
    'password' => 'prueba123',
]);
$token = $r['data']['token'] ?? null;
paso($codigo === 200 && $token !== null, 'login del paciente devuelve token', "HTTP $codigo");

// ============================================================
// 3. INC-001: la lista de pacientes exige token
// ============================================================
echo "\n--- INC-001: pacientes exige token ---\n";

[$codigo, $r] = pedir('GET', $base . '/api/pacientes');
paso($codigo === 401, 'GET /api/pacientes sin token da 401', "HTTP $codigo");

[$codigo, $r] = pedir('GET', $base . '/api/pacientes/1');
paso($codigo === 401, 'GET /api/pacientes/1 sin token da 401', "HTTP $codigo");

[$codigo, $r] = pedir('POST', $base . '/api/pacientes', [
    'nombre' => 'No Debe Crearse',
    'dni' => '00000000',
]);
paso($codigo === 401, 'POST /api/pacientes sin token da 401', "HTTP $codigo");

// Con un token válido el acceso sí funciona (no se rompió la funcionalidad)
if ($token !== null) {
    [$codigo, $r] = pedir('GET', $base . '/api/pacientes', null, $token);
    $items = $r['data']['items'] ?? [];
    paso($codigo === 200, 'GET /api/pacientes con token válido da 200', "HTTP $codigo");

    if (!empty($items[0]['id'])) {
        $id = (int)$items[0]['id'];
        [$codigo, $r] = pedir('GET', $base . '/api/pacientes/' . $id, null, $token);
        paso($codigo === 200, 'GET /api/pacientes/{id} con token válido da 200', "HTTP $codigo");
    }
}

// ============================================================
// RESUMEN
// ============================================================
echo "\n=== Resultado ===\n";
echo "Correctos: $ok\n";
echo "Fallidos:  $fallos\n";

if ($fallos > 0) {
    echo "\nHay $fallos control(es) de acceso con fallos.\n";
    exit(1);
}

echo "\nTodos los controles de acceso responden como corresponde.\n";
exit(0);
