<?php
// ============================================================
// tests/run.php - Ejecutor de las pruebas unitarias
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// CÓMO SE USA
//   php tests/run.php              Corre todas las pruebas
//   php tests/run.php Logger       Corre solo las clases cuyo nombre
//                                  contiene "Logger" (útil mientras se
//                                  está tocando un archivo)
//   php tests/run.php --lista      Solo muestra qué pruebas existen
//
// POR QUÉ ES UN SCRIPT Y NO UN COMANDO DE FRAMEWORK
//
// Para que las pruebas sirvan de algo tienen que ser incómodas de correr:
// si verificarlas cuesta un effort, se dejan de correr. Este script
// devuelve un CÓDIGO DE SALIDA (0 = todo pasó, 1 = hubo fallos), que es lo
// que permite engancharlo a un pipeline sin escribir nada más: si el
// comando falla, el build falla.
//
// QUÉ CUBRE Y QUÉ NO
//   Cubre: la lógica pura y crítica del backend (enmascarado de datos
//   sensibles, formato de los logs, validación de entradas, correlación,
//   rate limiting, firma y expiración de JWT).
//   No cubre: los controladores ni los repositorios, que necesitan base de
//   datos y HTTP. Esos tienen su propia verificación en el proyecto
//   (probar_roles_http.php, probar_turnera.php, probar_tiempo_real.php y
//   verificar_api.mjs del frontend).
//
// La cobertura por número de líneas no se reporta: es una métrica que se
// puede subir sin esfuerzo y sin probar nada importante. Lo que importa es
// que los comportamientos críticos del negocio estén verificados con casos
// representantes: camino feliz, bordes y fallas.
// ============================================================

// Zona horaria explícita: los tests de JWT comparan Instantes y una zona
// horaria distinta entre desarrollo y producción haría fallar el mismo
// código por motivos que no tienen que ver con el código.
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Los errores de PHP se muestran en pantalla: si una prueba rompe algo por
// un error tipográfico, hay que verlo en la salida y no esconderlo.
ini_set('display_errors', '1');
error_reporting(E_ALL);

$raiz = __DIR__ . '/..';

// ---------------------------------------------------------------
// 1. Cargar el código que se va a probar
// ---------------------------------------------------------------
// No se incluye bootstrap.php a propósito: ese archivo abre la conexión a la
// base de datos y arma toda la aplicación. Las pruebas unitarias tienen que
// poder correr sin MySQL, sin Apache y sin datos sembrados; si dependieran
// de eso, cada prueba sería en realidad una prueba de integración.
require_once $raiz . '/core/Config.php';
require_once $raiz . '/core/Logger.php';
require_once $raiz . '/core/CorrelationId.php';
require_once $raiz . '/core/Validador.php';
require_once $raiz . '/core/RateLimiter.php';
require_once $raiz . '/core/Peticion.php';

// Para las pruebas de JWT hace falta la librería de Composer y la clase del
// servicio. Se cargan acá y no en la prueba para que el fallo sea visible
// en un solo lugar si falta el vendor.
require_once $raiz . '/vendor/autoload.php';
require_once $raiz . '/core/JwtService.php';

// El arnés de pruebas (clase base con las aserciones).
require_once __DIR__ . '/TestCase.php';

// ---------------------------------------------------------------
// 2. Descubrir las clases de prueba
// ---------------------------------------------------------------
// Se listan los archivos del directorio y se toman los que terminan en
// "Test.php". Es descubrimiento automático y no una lista escrita a mano:
// agregar una prueba nueva es crear el archivo, sin tocar el runner (eso
// es lo que evita que la suite se quede vieja).
$archivos = glob(__DIR__ . '/*Test.php') ?: [];
sort($archivos);

$clases = [];
foreach ($archivos as $archivo) {
    // Nombre de la clase a partir del nombre del archivo, sin extensión.
    $clases[] = basename($archivo, '.php');
}

// ---------------------------------------------------------------
// 3. Filtrar por argumento (para correr un subconjunto)
// ---------------------------------------------------------------
$filtro = '';
$listar = false;
foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--lista') {
        $listar = true;
    } elseif (!str_starts_with($argumento, '--')) {
        $filtro = $argumento;
    }
}

if ($filtro !== '') {
    $clases = array_values(array_filter(
        $clases,
        fn(string $clase): bool => stripos($clase, $filtro) !== false
    ));
}

if ($clases === []) {
    echo 'No se encontraron pruebas para "' . $filtro . '".' . PHP_EOL;
    exit(1);
}

// Cada archivo declara su clase; se incluyen recién ahora (después de
// conocer la lista) para que --lista no cargue código innecesario.
foreach ($clases as $clase) {
    require_once __DIR__ . '/' . $clase . '.php';
}

if ($listar) {
    echo 'Pruebas disponibles:' . PHP_EOL;
    foreach ($clases as $clase) {
        echo '  - ' . $clase . PHP_EOL;
    }
    exit(0);
}

// ---------------------------------------------------------------
// 4. Correr y acumular el resultado
// ---------------------------------------------------------------
echo '============================================================' . PHP_EOL;
echo 'Pruebas unitarias - SaludWEB Backend' . PHP_EOL;
echo '============================================================' . PHP_EOL;

// El resumen cuenta clases con pruebas fallidas, no pruebas individuales.
// Contar pruebas exigiría que el arnés exponiera un total, y ese detalle no
// aporta nada al que lee la salida: lo útil es saber cuántas clases están
// verdes para decidir si se puede publicar.
$fallos = 0;
$total = 0;

foreach ($clases as $clase) {
    /** @var TestCase $caso */
    $caso = new $clase();
    $total++;

    // El detalle de cada fallo ya se imprimió arriba, dentro de correr().
    if ($caso->correr() !== 0) {
        $fallos++;
    }
}

// ---------------------------------------------------------------
// 5. Resumen final y código de salida
// ---------------------------------------------------------------
echo PHP_EOL . str_repeat('=', 60) . PHP_EOL;
if ($fallos === 0) {
    echo 'RESULTADO: TODO OK (' . $total . ' clases de prueba)' . PHP_EOL;
    // 0 = todo pasó. Es lo que un pipeline lee como "se puede publicar".
    exit(0);
}

echo 'RESULTADO: HUBO FALLOS (' . $fallos . ' en ' . $total . ' clases de prueba)' . PHP_EOL;
// Distinto de 0 = el build falla. Ver la nota sobre código de salida arriba.
exit(1);
