<?php
/**
 * resetear_password.php - Utilidad de desarrollo para (re)establecer la clave de una cuenta
 * ============================================================
 * Módulo: "Autenticación" (soporte de desarrollo)
 * ------------------------------------------------------------
 * Las contraseñas se guardan como hash bcrypt (password_hash), que es
 * irreversible: no se pueden "leer" ni "recuperar", solo reemplazar. Este
 * script reemplaza el hash de una cuenta por uno nuevo de una clave conocida.
 *
 * Sirve, por ejemplo, para volver a entrar con la cuenta administradora
 * (admin@salud.com) cuando no se recuerda su contraseña en un entorno local.
 *
 * IMPORTANTE: es una herramienta SOLO para desarrollo local. Cualquiera con
 * acceso al archivo de la base puede cambiar claves; en producción, el cambio
 * de contraseña pasa por el endpoint autenticado
 * PATCH /api/usuarios/{id}/password (que además pide la clave actual).
 * Por eso este script se niega a correr desde el navegador: solo funciona por
 * consola (CLI).
 *
 * Uso:
 *   php resetear_password.php <email> <nueva-clave>
 *
 * Ejemplo:
 *   php resetear_password.php admin@salud.com MiClaveSegura123
 */

// --- Solo por consola -------------------------------------------------------
// PHP_SAPI identifica el entorno de ejecución. 'cli' = terminal. Si el archivo
// se pidiera por HTTP, cortamos antes de tocar nada: exponer un cambio de
// contraseña sin autenticación por web sería un agujero de seguridad.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se puede ejecutar por consola (php resetear_password.php ...).\n");
}

// Reutiliza la MISMA conexión y la MISMA ruta de base que usa la API, así el
// cambio queda en el mismo archivo SQLite que lee el backend.
require_once __DIR__ . '/db.php';

// --- Leer argumentos --------------------------------------------------------
// $argv trae los parámetros de la línea de comandos: [0]=script, [1]=email, [2]=clave.
$email  = trim($argv[1] ?? '');
$clave  = $argv[2] ?? '';

// Validación básica de los datos de entrada.
if ($email === '' || $clave === '') {
    fwrite(STDERR, "Uso: php resetear_password.php <email> <nueva-clave>\n");
    exit(1);
}

// filter_var con FILTER_VALIDATE_EMAIL: exige un email con formato válido.
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "El email '{$email}' no tiene un formato válido.\n");
    exit(1);
}

// La misma regla que aplica el alta pública: mínimo 6 caracteres.
if (strlen($clave) < 6) {
    fwrite(STDERR, "La contraseña debe tener al menos 6 caracteres.\n");
    exit(1);
}

// --- Comprobar que la cuenta exista ----------------------------------------
$buscar = $pdo->prepare('SELECT id, tipo_usuario FROM usuarios WHERE email = ?');
$buscar->execute([$email]);
$usuario = $buscar->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    fwrite(STDERR, "No existe ninguna cuenta con el email '{$email}'.\n");
    fwrite(STDERR, "Para crearla, usá el registro público (POST /api/auth/registro).\n");
    exit(1);
}

// --- Reemplazar el hash -----------------------------------------------------
// password_hash($clave, PASSWORD_BCRYPT): genera el hash bcrypt, EXACTAMENTE
// igual que lo hace el registro (services/AuthService.php). Así el login
// (password_verify) lo acepta sin ningún cambio.
$hash = password_hash($clave, PASSWORD_BCRYPT);

$actualizar = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
$actualizar->execute([$hash, $usuario['id']]);

// --- Verificar y avisar -----------------------------------------------------
// Confirmamos contra el hash recién guardado para no dejar una duda abierta.
$ok = false;
$leer = $pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
$leer->execute([$usuario['id']]);
$fila = $leer->fetch(PDO::FETCH_ASSOC);
if ($fila) {
    $ok = password_verify($clave, $fila['password']);
}

if ($ok) {
    echo "OK: contraseña de {$email} (id {$usuario['id']}, rol {$usuario['tipo_usuario']}) actualizada.\n";
    exit(0);
}

fwrite(STDERR, "ERROR: no se pudo confirmar el cambio de contraseña.\n");
exit(1);
