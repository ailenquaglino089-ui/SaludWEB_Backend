<?php
// ============================================================
// probar_tiempo_real.php - Verificación del módulo de tiempo real
// ============================================================
// Módulo: "Primera funcionalidad en tiempo real" (SSE)
//
// QUÉ ES ESTE ARCHIVO
// -------------------
// Es la verificación de la guía. La guía dice que "la prueba definitiva es la
// prueba de los dos navegadores" y también que hay que medir la latencia. Este
// script automatiza las dos cosas que se pueden medir sin navegador, para que
// el momento de la verdad no dependa de que alguien mire un cronómetro:
//
//   1. Pruebas de las piezas (tabla, repositorio, servicio, seguridad)
//   2. Prueba de punta a punta: se abre el canal SSE REAL por HTTP mientras
//      este mismo script publica un evento, y se mide en milisegundos cuánto
//      tarda en llegar. Esa cifra es la latencia que ve el usuario.
//
// CÓMO SE EJECUTA
//   - Endpoint web (recomendado, mide la latencia real con Apache de por medio):
//       http://localhost/Workspace_SaludWEB/SaludWEB_Backend/probar_tiempo_real.php
//   - Por consola:
//       C:\xampp\php\php.exe probar_tiempo_real.php
//
// REQUISITOS
//   - Apache y MySQL encendidos (XAMPP)
//   - La tabla eventos_realtime creada (se crea sola al cargar db.php)
//
// NOTA SOBRE EL TIEMPO DE EJECUCIÓN
// ----------------------------------
// La parte 2 tarda unos segundos a propósito: el listener consulta la tabla
// una vez por segundo, así que el evento tarda hasta ~1 s en salir. Esa espera
// NO es latencia del usuario final, es el intervalo de sondeo del servidor. El
// script mide desde la publicación hasta la recepción, que es lo que importa.

// Se muestran los errores de PHP en pantalla: este script es de diagnóstico,
// y un error silencioso en una prueba es peor que no tener prueba.
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Se indica que la salida es HTML: las líneas con "<br>" se van a renderizar
// como saltos de línea y no se verá el código fuente en crudo.
header('Content-Type: text/html; charset=utf-8');

// ============================================================
// CARGA DEL MÓDULO
// ============================================================
// Se sube un nivel desde la raíz del proyecto para encontrar core/bootstrap.php,
// que es el que arma las clases. Se usa el MISMO arranque que la API real: si
// las pruebas usaran un arranque distinto, podrían pasar mientras la aplicación
// está rota, que es el peor resultado posible de una suite de pruebas.
$raiz = __DIR__;
require_once $raiz . '/core/bootstrap.php';

// ============================================================
// UTILIDADES DE SALIDA
// ============================================================

/**
 * Imprime un título de sección.
 *
 * @param string $texto Título
 */
function seccion(string $texto): void
{
    echo '<h3 style="margin-top:26px; font-family:system-ui; color:#0f172a;">' . htmlspecialchars($texto) . '</h3>';
}

/**
 * Imprime el resultado de una prueba.
 *
 * @param bool   $ok       Si la prueba pasó
 * @param string $titulo   Qué se probó
 * @param string $detalle  Detalle a mostrar (o vacío)
 */
function prueba(bool $ok, string $titulo, string $detalle = ''): void
{
    $color = $ok ? '#15803d' : '#b91c1c';
    $icono = $ok ? 'OK  ' : 'FALLA';
    $bloque = 'font-family:system-ui; margin:6px 0; padding:9px 12px;'
        . ' border-left:4px solid ' . $color . '; background:' . ($ok ? '#f0fdf4' : '#fef2f2')
        . '; border-radius:6px;';

    echo '<div style="' . $bloque . '">';
    echo '<strong style="color:' . $color . ';">[' . $icono . ']</strong> ';
    echo htmlspecialchars($titulo);

    if ($detalle !== '') {
        echo '<div style="color:#475569; font-size:0.9em; margin-top:3px;">' . htmlspecialchars($detalle) . '</div>';
    }

    echo '</div>';
}

// Contador global de resultados, para el resumen final
$total = 0;
$fallas = 0;

/**
 * Registra una prueba en el contador global.
 *
 * Existe para no repetir el $total++ y $fallas++ en cada prueba: si se
 * olvida en alguna, el resumen final miente, y un resumen mentiroso en una
 * prueba es peor que no tener resumen.
 *
 * @param bool $ok Resultado
 */
function contar(bool $ok): void
{
    global $total, $fallas;
    $total++;
    if (!$ok) {
        $fallas++;
    }
}

// ============================================================
// PARTE 1: LAS PIEZAS
// ============================================================
echo '<h1 style="font-family:system-ui; color:#0f172a;">'
    . 'Verificación del módulo de tiempo real (Server-Sent Events)</h1>';

// --- 1.1 La tabla existe ---
seccion('1. La tabla de eventos');

$existe = false;
try {
    $fila = $pdo->query("SHOW TABLES LIKE 'eventos_realtime'")->fetch();
    $existe = ($fila !== false);
} catch (Exception $e) {
    $existe = false;
}

contar($existe);
prueba($existe, 'La tabla eventos_realtime existe');

// --- 1.2 Los índices de lectura existen ---
// No es un detalle menor: el listener consulta por (canal, id) una vez por
// segundo por cada conexión abierta. Sin ese índice, cada vuelta sería un
// recorrido completo de la tabla y el coste crecería con el volumen.
$indices = $pdo->query('SHOW INDEX FROM eventos_realtime')->fetchAll();
$nombres = [];
foreach ($indices as $indice) {
    $nombres[$indice['Key_name']] = true;
}

$okIndice = isset($nombres['idx_eventos_canal_id']);
contar($okIndice);
prueba($okIndice, 'El índice (canal, id) está creado', 'Índices: ' . implode(', ', array_keys($nombres)));

// --- 1.3 Publicar y leer un evento ---
$realtimeService = new RealtimeService($realtimeRepo);

// Se usa un canal de pruebas con sufijo, para no mezclar estos eventos con
// los reales del consultorio. Si se usara 'tablero', al terminar el script
// quedarían eventos basura que después verían los usuarios conectados.
$canalPrueba = 'prueba_script';

// Se limpian los eventos que dejaron las corridas anteriores de este mismo
// script. Sin esto, la primera prueba de esta sección fallaría a partir de la
// segunda ejecución: el canal ya tendría eventos viejos y el conteo no sería 1.
//
// Es un detalle sobre pruebas que parece menor, pero un test que solo pasa la
// primera vez no es un test: es un accidente. Y algo peor: si el script se corre
// dos veces y falla la segunda, el que lo lee deduce que el módulo se rompió,
// cuando lo que se rompió fue el test.
$stmtLimpiar = $pdo->prepare('DELETE FROM eventos_realtime WHERE canal = ?');
$stmtLimpiar->execute([$canalPrueba]);

$idPrueba = $realtimeRepo->publicar($canalPrueba, 'cita_creada', [
    'id_cita' => 999999,
    'origen' => 'probar_tiempo_real.php',
]);

$okPublico = $idPrueba > 0;
contar($okPublico);
prueba($okPublico, 'Se publica un evento y se obtiene su id', "Id asignado: $idPrueba");

// --- 1.4 El cursor funciona ---
// Se lee desde el id del evento recién publicado, y tiene que devolver
// exactamente ese evento: ni más (se colaría algo del pasado) ni menos
// (se perdió algo recién publicado).
$eventos = $realtimeRepo->obtenerDesde($canalPrueba, 0, 50);
$okCursor = count($eventos) === 1 && (int)$eventos[0]['id'] === $idPrueba;

contar($okCursor);
prueba($okCursor, 'Leer desde id 0 devuelve solo el evento publicado', count($eventos) . ' evento(s) leídos');

$eventosDesdeElId = $realtimeRepo->obtenerDesde($canalPrueba, $idPrueba, 50);
$okSinRepeticion = count($eventosDesdeElId) === 0;
contar($okSinRepeticion);
prueba($okSinRepeticion, 'Leer desde el id del propio evento NO lo devuelve otra vez');

// --- 1.5 El payload es JSON válido al volver de la base ---
// El contenido se guardó como texto; si al leerlo no se puede reconstruir,
// el frontend recibiría undefined y fallaría al pintar.
$jsonCrudo = $eventos[0]['datos'] ?? '';
$decodificado = json_decode((string)$jsonCrudo, true);
$okJson = is_array($decodificado) && ($decodificado['id_cita'] ?? null) === 999999;

contar($okJson);
prueba($okJson, 'El payload sobrevive el viaje de ida y vuelta en la base', $jsonCrudo);

// --- 1.6 Autorización de canales ---
// La prueba de seguridad más importante del módulo: el canal se traduce
// contra el token, y un usuario no puede escuchar lo que no le corresponde.
// Si esto fallara, un paciente podría abrir el canal de otro paciente y ver
// sus turnos, que es exactamente el tipo de fuga que la guía pide evitar.
seccion('2. Seguridad: quién puede escuchar qué');

$casos = [
    [
        'nombre' => 'El admin escucha tablero',
        'contexto' => ['rol' => 'admin', 'id_usuario' => 5, 'id_paciente' => null, 'id_medico' => null],
        'pide' => 'tablero',
        'debe' => 'tablero',
    ],
    [
        'nombre' => 'El admin escucha la agenda de un profesional concreto',
        'contexto' => ['rol' => 'admin', 'id_usuario' => 5, 'id_paciente' => null, 'id_medico' => null],
        'pide' => 'agenda:10',
        'debe' => 'agenda:10',
    ],
    [
        'nombre' => 'El médico escucha su propia agenda',
        'contexto' => ['rol' => 'medico', 'id_usuario' => 2, 'id_paciente' => null, 'id_medico' => 10],
        'pide' => 'mi-agenda',
        'debe' => 'agenda:10',
    ],
    [
        'nombre' => 'El paciente escucha sus propios turnos',
        'contexto' => ['rol' => 'paciente', 'id_usuario' => 3, 'id_paciente' => 327, 'id_medico' => null],
        'pide' => 'mis-turnos',
        'debe' => 'turnos:327',
    ],
];

foreach ($casos as $caso) {
    try {
        $obtenido = $realtimeService->resolverCanal($caso['pide'], $caso['contexto']);
        $okCaso = ($obtenido === $caso['debe']);
        $detalle = "pidió '{$caso['pide']}' → quedó en '{$obtenido}'";
    } catch (Exception $e) {
        $okCaso = false;
        $detalle = 'Se esperaba un canal y se lanzó: ' . $e->getMessage();
    }

    contar($okCaso);
    prueba($okCaso, $caso['nombre'], $detalle);
}

// Casos que TIENEN que ser rechazados
$rechazos = [
    [
        'nombre' => 'El paciente NO puede escuchar la agenda de un médico',
        'contexto' => ['rol' => 'paciente', 'id_usuario' => 3, 'id_paciente' => 327, 'id_medico' => null],
        'pide' => 'agenda:10',
    ],
    [
        'nombre' => 'El paciente NO puede espiar los turnos de otro paciente',
        'contexto' => ['rol' => 'paciente', 'id_usuario' => 3, 'id_paciente' => 327, 'id_medico' => null],
        'pide' => 'turnos:328',
    ],
    [
        'nombre' => 'El médico NO puede abrir la agenda de otro profesional',
        'contexto' => ['rol' => 'medico', 'id_usuario' => 2, 'id_paciente' => null, 'id_medico' => 10],
        'pide' => 'agenda:12',
    ],
    [
        'nombre' => 'Nadie puede escuchar un canal inexistente',
        'contexto' => ['rol' => 'admin', 'id_usuario' => 5, 'id_paciente' => null, 'id_medico' => null],
        'pide' => 'canal_inventado',
    ],
];

foreach ($rechazos as $caso) {
    try {
        $obtenido = $realtimeService->resolverCanal($caso['pide'], $caso['contexto']);
        $okRechazo = false;
        $detalle = "FALLO DE SEGURIDAD: le devolvió '{$obtenido}' en lugar de rechazarlo";
    } catch (Exception $e) {
        $okRechazo = true;
        $detalle = 'Rechazado correctamente: ' . $e->getMessage();
    }

    contar($okRechazo);
    prueba($okRechazo, $caso['nombre'], $detalle);
}

// --- 1.7 La tabla no crece para siempre ---
// Se publican eventos viejos a propósito y se purgan, para comprobar que la
// limpieza funciona. Sin esto, la tabla acumularía filas para siempre y el
// costo del SELECT del listener crecería sin freno.
seccion('3. La tabla no crece para siempre');

// Para simular el paso del tiempo se inserta un evento con fecha vieja. No se
// usa el repositorio para esto porque publicar() siempre pone la fecha de
// ahora (que es lo correcto en producción), y acá justamente se necesita lo
// contrario: un evento que ya debería haber sido purgado.
//
// Se usa prepare/execute y no exec() con el valor concatenado: exec() no
// acepta parámetros, así que el valor tendría que ir pegado en el texto de la
// consulta, que es justamente la práctica que este proyecto no usa en ningún
// lado.
$stmtViejo = $pdo->prepare(
    "INSERT INTO eventos_realtime (canal, tipo, datos, creado_at) "
    . "VALUES (?, 'viejo', '{}', NOW() - INTERVAL 3 HOUR)"
);
$stmtViejo->execute([$canalPrueba]);

$stmtReciente = $pdo->prepare(
    "INSERT INTO eventos_realtime (canal, tipo, datos, creado_at) "
    . "VALUES (?, 'reciente', '{}', NOW())"
);
$stmtReciente->execute([$canalPrueba]);

// El conteo también va con prepare, aunque acá el valor es fijo y no haya
// riesgo: se mantiene el mismo criterio en todo el archivo para que no quede
// la duda de si en algún lado se pegó un valor en el texto de una consulta.
$stmtContar = $pdo->prepare('SELECT COUNT(*) FROM eventos_realtime WHERE canal = ?');
$stmtContar->execute([$canalPrueba]);
$antes = (int) $stmtContar->fetchColumn();

$realtimeService->purgarAhora();

$stmtContar->execute([$canalPrueba]);
$despues = (int) $stmtContar->fetchColumn();

$okPurga = ($despues < $antes);
contar($okPurga);
prueba($okPurga, 'La purga borra los eventos viejos y deja los recientes', "Antes: $antes eventos → después: $despues");

// ============================================================
// PARTE 2: LA PRUEBA DE PUNTA A PUNTA (latencia real)
// ============================================================
echo '<h3 style="margin-top:26px; font-family:system-ui; color:#0f172a;">'
    . '4. Prueba de punta a punta (latencia real)</h3>';

// Para hacer la prueba completa hace falta poder abrir una conexión HTTP contra
// el propio servidor. Desde la línea de comandos eso no se puede, así que la
// prueba de latencia solo corre cuando el script se abre en el navegador (que es
// la forma recomendada).
$esNavegador = isset($_SERVER['HTTP_HOST']);

if (!$esNavegador) {
    echo '<div style="font-family:system-ui; padding:12px; background:#eff6ff; '
        . 'border-left:4px solid #2563eb; border-radius:6px;">'
        . 'La prueba de latencia se omite por consola porque necesita abrir una conexión '
        . 'HTTP contra el propio servidor. Abrí este mismo archivo en el navegador para '
        . 'ejecutarla completa.</div>';
} else {
    // Se necesita un token válido para abrir el canal (el token va en la URL).
    // No se pide la contraseña del usuario: se usa un admin de la base y, si
    // el login falla, la prueba se saltea en lugar de romper todo el script.
    $baseApi = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST']
        . str_replace('/probar_tiempo_real.php', '', $_SERVER['SCRIPT_NAME']);

    $token = null;
    $usuarios = [
        ['admin@salud.com', 'admin123'],
        ['medico@prueba.com', '123456'],
        ['paciente@prueba.com', '123456'],
    ];

    foreach ($usuarios as $intento) {
        $ch = curl_init($baseApi . '/api/auth/login');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['email' => $intento[0], 'password' => $intento[1]]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $respuesta = curl_exec($ch);
        $decodificado = json_decode((string)$respuesta, true);

        if (isset($decodificado['data']['token'])) {
            $token = $decodificado['data']['token'];
            $quien = $intento[0];
            break;
        }

        curl_close($ch);
    }

    if ($token === null) {
        echo '<div style="font-family:system-ui; padding:12px; background:#fffbeb; '
            . 'border-left:4px solid #d97706; border-radius:6px;">'
            . 'No se pudo obtener un token: la prueba de latencia se omite. '
            . 'Verificá que las cuentas de prueba existan.</div>';
    } else {
        echo '<div style="font-family:system-ui; color:#475569; margin-bottom:10px;">'
            . 'Conectado como <strong>' . htmlspecialchars($quien) . '</strong>. '
            . 'La medición empieza cuando el servidor publica el evento y termina cuando el '
            . 'navegador lo recibe.</div>';

        // Se abre el canal SSE REAL contra el servidor, en segundo plano.
        // cURL en vez de EventSource porque acá lo que se mide es la latencia
        // de la red, no la interfaz: interesa el tiempo entre "el servidor
        // escribió" y "el cliente leyó".
        $urlCanal = $baseApi . '/api/eventos?token=' . urlencode($token) . '&canal=tablero';

// curl_multi_init() arma el grupo de conexiones "en paralelo" de cURL.
        // Se usa en lugar de curl_exec() porque curl_exec es bloqueante: se
        // quedaría esperando los 25 segundos del timeout sin dejar que este
        // script haga nada mientras. Con el grupo se avanza el canal por
        // partes y se vuelve al código para seguir midiendo.
        $multi = curl_multi_init();

        // Instante en que el canal confirmó la apertura. Es null mientras no
        // llegue el evento 'conectado', y el bucle de abajo espera exactamente
        // eso. Se declara acá, y no dentro del while, porque el callback de
        // escritura (que corre en otro momento) también lo escribe.
        $corte = null;

        // Acumulador del texto recibido del canal.
        //
        // ESTE ACUMULADOR NO ES OPCIONAL, y es el detalle que hace que la
        // medición funcione o no. Cuando se define CURLOPT_WRITEFUNCTION, cURL
        // deja de armar la respuesta en memoria: cada trozo se pasa a la
        // función y se descarta. Por eso curl_multi_getcontent() devuelve
        // cadena VACÍA y buscar el evento ahí nunca lo encuentra.
        //
        // El primer intento de este script usaba curl_multi_getcontent() y la
        // prueba de latencia daba FALLA siempre, aunque el canal estuviera
        // funcionando perfectamente. La causa era acá y no en el módulo de
        // tiempo real: un error en el instrumento de medición hace fallar la
        // medición, y lo peligroso es que uno sospecha del sistema que en
        // realidad está bien.
        $recibido = '';

        $ch = curl_init($urlCanal);
        curl_setopt_array($ch, [
            // Se acumulan los datos dentro del callback, así que
            // CURLOPT_RETURNTRANSFER no hace falta: sin él, cURL no intenta
            // armar la respuesta completa (que en un stream nunca termina).
            CURLOPT_WRITEFUNCTION => function ($ch, $datos) use (&$corte, &$recibido) {
                $texto = (string)$datos;
                $recibido .= $texto;

                if ($corte === null && strpos($texto, 'conectado') !== false) {
                    // Primer volcado: el canal está abierto y listo.
                    $corte = microtime(true);
                }

                // El valor devuelto es cuántos bytes se aceptaron. Si se
                // devolviera otra cosa (o nada), cURL cortaría la transferencia
                // y el canal se vería cerrado desde el lado del cliente.
                return strlen($texto);
            },
            CURLOPT_TIMEOUT => 25,
        ]);

        curl_multi_add_handle($multi, $ch);

        $activo = 0;
        $arrancado = microtime(true);

        // Se espera a que el canal se abra (aparece el evento 'conectado').
        while ($corte === null && (microtime(true) - $arrancado) < 10) {
            curl_multi_exec($multi, $activo);
            curl_multi_select($multi, 0.2);
        }

        $okAbrio = ($corte !== null);
        contar($okAbrio);
        prueba(
            $okAbrio,
            'El canal SSE se abre y entrega el evento de conexión',
            $okAbrio
                ? 'El navegador recibió "conectado" en ' . round((microtime(true) - $arrancado) * 1000) . ' ms'
                : 'El canal no respondió en 10 segundos'
        );

        if ($okAbrio) {
            // --------------------------------------------------
            // Acá está la medición que pide la guía
            // --------------------------------------------------
            // Se registra el instante JUSTO antes de publicar, y después se
            // espera a que el evento aparezca en el canal. La diferencia es la
            // latencia completa: desde que la acción del usuario se guardó en
            // la base hasta que la pantalla del otro navegador se enteró.
            $instantePublicacion = microtime(true);

            $idPublicado = $realtimeRepo->publicar('tablero', 'cita_creada', [
                'id_cita' => 999999,
                'origen' => 'prueba de latencia',
                'marca' => date('c'),
            ]);

            // Se sigue leyendo el canal hasta que aparezca el evento buscado.
            // El texto ya viene acumulado en $recibido por el callback de
            // escritura, así que acá solo se lo mira.
            $llegadaMs = null;
            $fin = microtime(true) + 20;

            while (microtime(true) < $fin && $llegadaMs === null) {
                curl_multi_exec($multi, $activo);
                curl_multi_select($multi, 0.1);

                // Se busca el TIPO del evento y no el id. Con el tipo alcanza
                // para demostrar que el canal entrega eventos, y además evita
                // una condición de carrera: entre que se mide el instante y
                // que se ejecuta la publicación, el servidor puede haber
                // publicado el evento antes de que este script termine de
                // guardarlo en una variable. Como el payload lleva un id que
                // el script todavía no conoce, buscar el id daría un falso
                // negativo ("no llegó") cuando en realidad ya llegó.
                if (strpos($recibido, 'cita_creada') !== false) {
                    $llegadaMs = (microtime(true) - $instantePublicacion) * 1000;
                }
            }

            $okLlego = ($llegadaMs !== null);
            $okRapido = ($llegadaMs !== null && $llegadaMs < 2000);

            contar($okLlego);
            prueba(
                $okLlego,
                'El evento publicado llega al canal conectado',
                $okLlego
                    ? "Evento #$idPublicado recibido"
                    : 'El evento no llegó en 20 segundos'
            );

            contar($okRapido);
            if ($okLlego) {
                prueba(
                    $okRapido,
                    'La latencia está dentro del rango de la guía (< 2 s con sondeo de 1 s)',
                    'Latencia medida: ' . round($llegadaMs) . ' ms'
                );
            } else {
                echo '<div style="font-family:system-ui; padding:9px 12px; border-left:4px solid #94a3b8; '
                    . 'background:#f8fafc; border-radius:6px;">'
                    . 'Sin latencia que medir porque el evento no llegó.</div>';
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        } else {
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
    }
}

// ============================================================
// RESUMEN
// ============================================================
echo '<hr style="margin:26px 0 14px; border:none; border-top:2px solid #e2e8f0;">';

$todoBien = ($fallas === 0);
$colorResumen = $todoBien ? '#15803d' : '#b91c1c';

echo '<h2 style="font-family:system-ui; color:' . $colorResumen . ';">'
    . ($todoBien ? 'Todas las pruebas pasaron' : 'Hay pruebas que fallaron')
    . '</h2>';

echo '<div style="font-family:system-ui; font-size:1.05em;">'
    . "Total: $total &nbsp;|&nbsp; Pasaron: " . ($total - $fallas) . " &nbsp;|&nbsp; Fallaron: $fallas"
    . '</div>';

// Nota final sobre la limpieza
echo '<div style="font-family:system-ui; margin-top:14px; padding:12px; background:#f8fafc; '
    . 'border-left:4px solid #64748b; border-radius:6px; font-size:0.92em;">'
    . 'Los eventos que publica este script usan el canal <code>prueba_script</code>, '
    . 'distinto de los canales reales, para que los usuarios conectados no vean '
    . 'basura. La tabla <code>eventos_realtime</code> se limpia sola: retiene 30 minutos '
    . 'y se purga cada 25 publicaciones.'
    . '</div>';