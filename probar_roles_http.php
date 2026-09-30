<?php
// Prueba de los endpoints HTTP del módulo de roles a través de la API real.
// Complementa probar_roles.php: aquel verifica las reglas de negocio con un
// doble de repositorio, y este verifica que las rutas estén declaradas
// bien, que el 401/403 funcione y que el controlador traduzca las
// excepciones a los códigos HTTP correctos.
//
// Necesita credenciales de admin. Si no están en .env, avisa y sale.
require __DIR__ . '/core/bootstrap.php';

$base = 'http://localhost/Workspace_SaludWEB/SaludWEB_Backend';

$emailAdmin = getenv('ROL_TEST_ADMIN_EMAIL') ?: 'admin@prueba.com';
$claveAdmin = getenv('ROL_TEST_ADMIN_PASS') ?: 'admin123';

$emailMedico = getenv('ROL_TEST_MEDICO_EMAIL') ?: 'medico@prueba.com';
$claveMedico = getenv('ROL_TEST_MEDICO_PASS') ?: 'medico123';

$fallos = 0;

/**
 * Hace una petición HTTP contra la API usando cURL.
 *
 * @param string $url    URL completa
 * @param string $method Verbo HTTP
 * @param array|null $body Cuerpo JSON, o null
 * @param string|null $token Token de autorización
 * @return array{status:int, body:string, json:array}
 */
function pedir(string $url, string $method = 'GET', ?array $body = null, ?string $token = null): array
{
    $ch = curl_init($url);
    $cabeceras = ['Content-Type: application/json', 'Accept: application/json'];

    if ($token !== null) {
        $cabeceras[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $respuesta = curl_exec($ch);
    $estado = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false) {
        return ['status' => 0, 'body' => $error, 'json' => []];
    }

    return [
        'status' => $estado,
        'body'   => $respuesta,
        'json'   => json_decode($respuesta, true) ?? [],
    ];
}

function comprobar(string $titulo, int $esperado, array $respuesta, ?callable $extra = null): void
{
    global $fallos;

    $ok = $respuesta['status'] === $esperado;
    $detalle = '';
    $mensaje = $respuesta['json']['mensaje'] ?? ($respuesta['json']['error'] ?? '');

    if ($ok && $extra !== null) {
        $falloExtra = $extra($respuesta);
        if ($falloExtra !== null) {
            $ok = false;
            $detalle = $falloExtra;
        }
    }

    if ($ok) {
        echo "  OK    {$titulo}  (HTTP {$esperado})\n";
    } else {
        $fallos++;
        echo "  FALLA {$titulo}\n";
        echo "        esperado HTTP {$esperado}, recibido HTTP {$respuesta['status']}\n";
        if ($mensaje !== '') {
            echo "        mensaje: {$mensaje}\n";
        }
        if ($detalle !== '') {
            echo "        -> {$detalle}\n";
        }
        if ($mensaje === '' && $respuesta['body'] !== '') {
            echo "        cuerpo: " . substr($respuesta['body'], 0, 200) . "\n";
        }
    }
}

// ============================================================
// 0. SESIONES
// ============================================================
// Las cuentas se CREAN y se BORRAN dentro de este script.
//
// Antes estas pruebas secebían iniciar sesión con una cuenta fija
// (admin@prueba.com / admin123) que estaba documentada en el README. Eso
// ataba el archivo a un dato externo: en cuanto la cuenta se renombró, cambió
// su rol o se la borró, la prueba dejó de poder ejecutarse. Y peor: para
// probar la escritura real degradaba a un administrador de verdad y confiaba
// en acordarse de restaurarlo.
//
// Con cuentas descartables no hay ninguna de las dos cosas. El script no
// depende de que exista ninguna cuenta en particular, no toca datos de nadie
// y, aunque se corte a la mitad, lo único que puede quedar es un usuario de
// prueba sobrando.
//
// Si algún día hace falta correrlas contra una cuenta real, se puede con
// ROL_TEST_ADMIN_EMAIL y ROL_TEST_ADMIN_PASS. Tiene que ser una cuenta ADMIN,
// porque sin rol admin las pruebas válidas por diseño.
echo "=== 0. SESIONES DE PRUEBA ===\n";

$sello = time();
$emailAdmin = 'descartable.http.admin.' . $sello . '@prueba.local';
$claveAdmin = 'clave-de-prueba-' . bin2hex(random_bytes(8));
$emailMedico = 'descartable.http.medico.' . $sello . '@prueba.local';
$claveMedico = 'clave-de-prueba-' . bin2hex(random_bytes(8));

$usuariosParaBorrar = [];

/**
 * Crea una cuenta descartable y la registra para borrarla al final.
 *
 * @param PDO $pdo       Conexión
 * @param string $email  Email de la cuenta
 * @param string $clave  Contraseña en texto plano (se hashea al guardar)
 * @param string $rol    Rol de la cuenta
 * @return int Id de la cuenta creada
 */
function crearCuentaDescartable(PDO $pdo, string $email, string $clave, string $rol): int
{
    $s = $pdo->prepare(
        'INSERT INTO usuarios (email, password, nombre, tipo_usuario, activo)
         VALUES (?, ?, ?, ?, 1)'
    );
    $s->execute([$email, password_hash($clave, PASSWORD_DEFAULT), 'Cuenta Descartable', $rol]);
    return (int)$pdo->lastInsertId();
}

/**
 * Borra las cuentas descartables que se crearon al principio.
 *
 * Va en el shutdown para que también funcione si el script muere por una
 * excepción. Registrar el callback es lo que garantiza la limpieza en el caso
 * que antes dejaba la base sucia.
 *
 * @param PDO $pdo Conexión
 * @return void
 */
function borrarCuentasDescartables(PDO $pdo): void
{
    $s = $pdo->prepare("DELETE FROM usuarios WHERE email LIKE 'descartable.%'");
    $s->execute();
}

require __DIR__ . '/db.php';

$idAdmin = crearCuentaDescartable($pdo, $emailAdmin, $claveAdmin, 'admin');
$idMedico = crearCuentaDescartable($pdo, $emailMedico, $claveMedico, 'medico');

// La limpieza se ata al cierre del script, no al final del archivo. Si una
// comprobación lanza una excepción o se corta con Ctrl+C, igual se borran.
register_shutdown_function(static function () use ($pdo): void {
    borrarCuentasDescartables($pdo);
    echo "\n  (cuentas descartables borradas)\n";
});

echo "  admin: {$emailAdmin} (id {$idAdmin})\n";
echo "  medico: {$emailMedico} (id {$idMedico})\n";

// Se hace login igual que lo haría una persona, pasando por la API. Es
// justamente esa parte la que se quiere probar: que el token que emite
// /api/auth/login sea el que despuésValide requireRol.
$rAdmin = pedir($base . '/api/auth/login', 'POST', [
    'email' => $emailAdmin,
    'password' => $claveAdmin,
]);

if ($rAdmin['status'] !== 200) {
    echo "  No se pudo iniciar sesión como admin: HTTP {$rAdmin['status']}\n";
    echo "  " . ($rAdmin['json']['mensaje'] ?? $rAdmin['body']) . "\n";
    exit(2);
}

// La respuesta de login es PLANA: los datos del usuario vienen en el mismo
// nivel que el token, no anidados en un objeto "usuario".
//
//   { ok, mensaje, data: { id, email, nombre, tipo_usuario, token, expires_at } }
//
// Ojo con el nombre del campo del rol: acá es `tipo_usuario` (el nombre de la
// columna), mientras que el endpoint de usuarios lo devuelve como `rol` (para
// que se lea igual que el claim del JWT). La diferencia es deliberada y está
// documentada en UsuarioRepository.
$tokenAdmin = $rAdmin['json']['data']['token'] ?? '';
$idAdminSesion = (int)($rAdmin['json']['data']['id'] ?? 0);

$tokenMedico = '';
$rMedico = pedir($base . '/api/auth/login', 'POST', [
    'email' => $emailMedico,
    'password' => $claveMedico,
]);
if ($rMedico['status'] === 200) {
    $tokenMedico = $rMedico['json']['data']['token'] ?? '';
} else {
    echo "  medico: no se pudo iniciar sesión (se omite la prueba de 403)\n";
}

// ============================================================
// 1. SIN TOKEN -> 401
// ============================================================
echo "\n=== 1. SIN TOKEN DEBE RESPONDER 401 ===\n";

comprobar('GET /api/usuarios sin token', 401, pedir($base . '/api/usuarios'));
comprobar('GET /api/usuarios/roles sin token', 401, pedir($base . '/api/usuarios/roles'));
comprobar('PATCH /api/usuarios/1/rol sin token', 401,
    pedir($base . '/api/usuarios/1/rol', 'PATCH', ['rol' => 'medico']));

// ============================================================
// 2. ROL INSUFICIENTE -> 403
// ============================================================
echo "\n=== 2. UN MÉDICO DEBE RECIBIR 403 ===\n";

if ($tokenMedico !== '') {
    comprobar('GET /api/usuarios como medico', 403, pedir($base . '/api/usuarios', 'GET', null, $tokenMedico));
    comprobar('GET /api/usuarios/roles como medico', 403,
        pedir($base . '/api/usuarios/roles', 'GET', null, $tokenMedico));
    comprobar('PATCH /api/usuarios/1/rol como medico', 403,
        pedir($base . '/api/usuarios/1/rol', 'PATCH', ['rol' => 'admin'], $tokenMedico));
} else {
    echo "  (omitido: no hay sesión de médico)\n";
}

// ============================================================
// 3. ADMIN -> 200
// ============================================================
echo "\n=== 3. EL ADMINISTRADOR PUEDE ===\n";

comprobar('GET /api/usuarios/roles', 200,
    pedir($base . '/api/usuarios/roles', 'GET', null, $tokenAdmin),
    function ($r) {
        $roles = $r['json']['data'] ?? null;
        if (!is_array($roles)) {
            return 'la respuesta no trae la lista de roles';
        }
        foreach (['paciente', 'medico', 'admin'] as $esperado) {
            if (!in_array($esperado, $roles, true)) {
                return "falta el rol '{$esperado}' en la lista";
            }
        }
        return null;
    });

comprobar('GET /api/usuarios paginado', 200,
    pedir($base . '/api/usuarios?pagina=1&por_pagina=5', 'GET', null, $tokenAdmin),
    function ($r) {
        $d = $r['json']['data'] ?? [];
        foreach (['items', 'total', 'pagina', 'por_pagina', 'total_paginas'] as $clave) {
            if (!array_key_exists($clave, $d)) {
                return "a la envoltura le falta la clave '{$clave}'";
            }
        }
        if (count($d['items']) > 5) {
            return 'la página tiene más de los 5 registros pedidos';
        }
        // Ninguna fila puede traer el hash de la contraseña.
        foreach ($d['items'] as $fila) {
            if (isset($fila['password'])) {
                return 'un usuario vino con la columna password expuesta';
            }
        }
        return null;
    });

comprobar('GET /api/usuarios con filtro por rol inválido -> 422', 422,
    pedir($base . '/api/usuarios?rol=superusuario', 'GET', null, $tokenAdmin));

comprobar('GET /api/usuarios con búsqueda', 200,
    pedir($base . '/api/usuarios?q=admin', 'GET', null, $tokenAdmin));

comprobar('GET /api/usuarios con por_pagina enorme se acota', 200,
    pedir($base . '/api/usuarios?por_pagina=999999', 'GET', null, $tokenAdmin),
    function ($r) {
        $d = $r['json']['data'] ?? [];
        $porPagina = (int)($d['por_pagina'] ?? 0);
        if ($porPagina > 100) {
            return "por_pagina quedó en {$porPagina}, el máximo es 100";
        }
        return null;
    });

// ============================================================
// 4. REGLAS DE NEGOCIO POR HTTP
// ============================================================
echo "\n=== 4. LAS REGLAS DE SEGURIDAD POR HTTP ===\n";

comprobar('cambiar el rol a uno inexistente -> 404', 404,
    pedir($base . '/api/usuarios/999999/rol', 'PATCH', ['rol' => 'medico'], $tokenAdmin));

comprobar('cambiar a un rol inválido -> 422', 422,
    pedir($base . '/api/usuarios/' . $idAdmin . '/rol', 'PATCH', ['rol' => 'superusuario'], $tokenAdmin));

comprobar('cambiar el propio rol -> 409', 409,
    pedir($base . '/api/usuarios/' . $idAdmin . '/rol', 'PATCH', ['rol' => 'paciente'], $tokenAdmin),
    function ($r) {
        $m = $r['json']['mensaje'] ?? '';
        return stripos($m, 'vos mismo') === false
            ? "el mensaje no explica que es el propio usuario: '{$m}'"
            : null;
    });

comprobar('cambiar un rol sin mandar el campo -> 422', 422,
    pedir($base . '/api/usuarios/' . $idAdmin . '/rol', 'PATCH', [], $tokenAdmin));

comprobar('PATCH sin cuerpo -> 400', 400,
    pedir($base . '/api/usuarios/' . $idAdmin . '/rol', 'PATCH', null, $tokenAdmin));

// ============================================================
// 5. CAMBIO REAL Y RESTAURACIÓN
// ============================================================
echo "\n=== 5. CAMBIO REAL (se restaura al final) ===\n";

// El objetivo del cambio real es OTRA cuenta descartable, no un paciente real.
//
// La versión anterior elegía un paciente de la base, lo promovía a médico y
// después lo rebajaba. Parecía reversible, y lo era... salvo por el día que
// faltó una línea y el paciente quedó como médico. Ahora el objetivo se crea
// acá y se borra con las demás cuentas descartables, así que la prueba no
// escribe sobre datos de nadie.
$emailObjetivo = 'descartable.http.objetivo.' . $sello . '@prueba.local';
$idObjetivo = crearCuentaDescartable($pdo, $emailObjetivo, 'clave-de-prueba', 'paciente');
echo "  objetivo del cambio real: {$emailObjetivo} (id {$idObjetivo})\n";

comprobar('promover a medico -> 200', 200,
    pedir($base . "/api/usuarios/{$idObjetivo}/rol", 'PATCH', ['rol' => 'medico'], $tokenAdmin),
    function ($r) {
        $rol = $r['json']['data']['rol'] ?? null;
        return $rol === 'medico' ? null : "la respuesta volvió con rol '{$rol}'";
    });

comprobar('el cambio se ve en un listado posterior', 200,
    pedir($base . "/api/usuarios?q=" . urlencode($emailObjetivo), 'GET', null, $tokenAdmin),
    function ($r) use ($idObjetivo) {
        foreach ($r['json']['data']['items'] ?? [] as $u) {
            if ((int)$u['id'] === $idObjetivo) {
                return $u['rol'] === 'medico'
                    ? null
                    : "el listado todavía muestra el rol '{$u['rol']}'";
            }
        }
        return 'el usuario recién promovido no aparece en el listado';
    });

comprobar('revertir a paciente -> 200', 200,
    pedir($base . "/api/usuarios/{$idObjetivo}/rol", 'PATCH', ['rol' => 'paciente'], $tokenAdmin),
    function ($r) {
        $rol = $r['json']['data']['rol'] ?? null;
        return $rol === 'paciente' ? null : "la respuesta volvió con rol '{$rol}'";
    });

comprobar('dejar el rol como estaba ya no dispara error', 200,
    pedir($base . "/api/usuarios/{$idObjetivo}/rol", 'PATCH', ['rol' => 'paciente'], $tokenAdmin));

// La cuenta con la que se hizo login no se puede degradar a sí misma. Se
// comprueba con la sesión REAL del admin, no con el id que generó el script.
comprobar('la cuenta con la que se inició sesión no se puede autodegradar', 409,
    pedir($base . "/api/usuarios/{$idAdminSesion}/rol", 'PATCH', ['rol' => 'paciente'], $tokenAdmin));

echo "\n" . str_repeat('-', 60) . "\n";
echo $fallos === 0
    ? "TODAS LAS COMPROBACIONES PASARON\n"
    : "COMPROBACIONES FALLIDAS: {$fallos}\n";

exit($fallos === 0 ? 0 : 1);
