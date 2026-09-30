<?php
/**
 * probar_vinculacion.php - Prueba de la regla de vinculación de fichas
 * ============================================================
 * Módulo: "Sistema de gestión de citas online" (Turnera)
 * ------------------------------------------------------------
 * Para qué existe:
 *   Vincular una cuenta con una ficha de paciente se valida comparando el
 *   nombre escrito en la cuenta contra el nombre de la ficha. Esa
 *   comparación es la única barrera real del endpoint (el DNI solo se
 *   comprueba contra la base, no contra un documento del paciente), así que
 *   una regla permisiva permitiría que cualquiera se apropie de la ficha de
 *   otro. Este script congela el comportamiento esperado con casosGood y
 *   casosBad, para que un cambio futuro en la comparación no introduzca
 *   una regresión silenciosa.
 *
 * Cómo se ejecuta:
 *   php probar_vinculacion.php
 *
 * Devuelve código 0 si todos los casos se comportan como se espera.
 * No modifica la base: solo lee la conexión porque el servicio la necesita
 * para construirse.
 */

// Se cargan las dependencias necesarias para armar el AuthService
require_once __DIR__ . '/core/Config.php';
require_once __DIR__ . '/core/JwtService.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/AuthService.php';

// El secreto es irrelevante para esta prueba: no se genera ningún token
$jwt = new JwtService('prueba-unitaria');

// Se instancia el servicio con la conexión real
$servicio = new AuthService($pdo, $jwt);

// coincidenNombres() es privado a propósito (nadie lo llama desde afuera),
// así que se accede por reflexión para poder probarlo directamente.
$metodo = new ReflectionMethod(AuthService::class, 'coincidenNombres');
$metodo->setAccessible(true);

/**
 * Casos que TIENEN que aceptarse.
 * Cada caso: [nombre de la cuenta, nombre de la ficha, por qué es válido]
 */
$casosBuenos = [
    ['laura sanchez', 'Sanchez, Laura', 'La ficha usa "Apellido, Nombre"'],
    ['laura sanchez gonzalez', 'Sanchez, Laura', 'La cuenta tiene un segundo apellido de más'],
    ['laura gonzalez sanchez', 'Sanchez, Laura', 'Los apellidos están en otro orden'],
    ['laura sanchez', 'Laura Sanchez', 'La ficha ya está en orden natural'],
    ['laura sanchez', 'Sanchez, Laura Ana', 'La ficha tiene un segundo apellido de más'],
    ['laura sanchez', 'Sanchez Laura', 'La ficha viene sin coma, que no define el orden'],
    ['laura maria sanchez', 'Sanchez, Laura', 'La cuenta tiene dos nombres de pila'],
    ['laura sanchez', 'Maria Laura Sanchez Ruiz', 'La ficha tiene dos nombres y dos apellidos'],
    ['Sanchez, Laura', 'Sanchez, Laura', 'Los dos lados traen el mismo formato'],
];

/**
 * Casos que TIENEN que rechazarse.
 * Cada caso: [nombre de la cuenta, nombre de la ficha, por qué se rechaza]
 */
$casosMalos = [
    ['laura sanchez', 'Ruiz, Laura', 'Mismo nombre de pila pero apellido distinto'],
    ['laura sanchez', 'Sosa, Laura', 'Mismo nombre de pila pero otro apellido'],
    ['juan perez', 'Sanchez, Laura', 'Nombre de pila completamente distinto'],
    ['otro paciente', 'Sanchez, Laura', 'No comparten ninguna palabra'],
    ['otro.paciente@salud.com', 'Sanchez, Laura', 'No es un nombre, es el email'],
    ['laura', 'Sanchez, Laura', 'La cuenta solo tiene un nombre: no alcanza para dos palabras comunes'],
];

$fallos = 0;

echo "=== Prueba de vinculación de fichas ===\n\n";

echo "-- Casos que deben aceptarse --\n";
foreach ($casosBuenos as [$cuenta, $ficha, $motivo]) {
    $resultado = $metodo->invoke($servicio, $cuenta, $ficha);
    if ($resultado === true) {
        echo "[OK]    cuenta='$cuenta' vs ficha='$ficha' ($motivo)\n";
    } else {
        $fallos++;
        echo "[FALLA] cuenta='$cuenta' vs ficha='$ficha' se rechazó pero debía aceptarse ($motivo)\n";
    }
}

echo "\n-- Casos que deben rechazarse --\n";
foreach ($casosMalos as [$cuenta, $ficha, $motivo]) {
    $resultado = $metodo->invoke($servicio, $cuenta, $ficha);
    if ($resultado === false) {
        echo "[OK]    cuenta='$cuenta' vs ficha='$ficha' rechazado ($motivo)\n";
    } else {
        $fallos++;
        echo "[FALLA] cuenta='$cuenta' vs ficha='$ficha' se aceptó pero debía rechazarse ($motivo)\n";
    }
}

// Resumen
$total = count($casosBuenos) + count($casosMalos);
echo "\n=== Resultado ===\n";
echo "Casos probados: $total\n";
echo "Fallos:         $fallos\n";

if ($fallos > 0) {
    echo "\nLa regla de vinculación tiene $fallos fallo(s).\n";
    exit(1);
}

echo "\nLa vinculación se comporta como se espera en todos los casos.\n";
exit(0);
