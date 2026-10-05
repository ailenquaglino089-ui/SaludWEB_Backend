<?php
// ============================================================
// core/Peticion.php - Ciclo de vida de la petición y su trazabilidad
// ============================================================
// Módulo: "Calidad Profesional del Software - Logs y Trazabilidad"
// ------------------------------------------------------------
// UNA responsabilidad: glue entre la petición HTTP y los logs.
//
// Hace cuatro cosas, y solo cuatro:
//   1. Abre la correlación (delegando en CorrelationId).
//   2. Devuelve el identificador al cliente en un encabezado.
//   3. Registra los errores que PHP nunca ve (avisos, excepciones,
//      errores fatales), que si no se pierden en el output.
//   4. Al terminar, escribe UNA línea con el resumen de la petición.
//
// Por qué una línea de resumen por petición: responde las preguntas que
// hace un operador cuando algo falla. "¿Qué pasó? ¿Cuándo? ¿Quién? ¿Con
// qué impacto?" quedan respondidas en la misma línea y, sobre todo, con el
// mismo correlationId que el resto de los logs de esa petición.
//
// El nivel del resumen NO es fijo: se elige según cómo terminó. Un 404 es
// un WARN (anómalo pero recuperable), un 500 es un ERROR (alguien tiene que
// enterarse). Marcar todo como INFO esconde los fallos; marcarlo todo como
// ERROR genera fatiga de alertas. El nivel se deduce de cómo terminó, y
// siempre queda justificado por el código HTTP de la respuesta.
//
// Sobre el canal SSE (/api/eventos): esa petición no "termina" hasta que el
// cliente se desconecta, así que su línea de resumen aparece con una duración
// grande. Es correcto y útil: dice cuándo se cayó el canal.
// ============================================================

class Peticion
{
    // Momento de arranque de la petición, en milisegundos.
    // hrtime() mide tiempo monotónico: no lo altera un ajuste del reloj del
    // sistema, así que la duración de una petición siempre es real.
    private static float $inicioMs = 0.0;

    // Evita registrar dos veces los mismos handlers si iniciar() se llamara
    // dos veces por un error de_include.
    private static bool $iniciada = false;

    /**
     * Arranca la observabilidad de la petición. Se llama una sola vez, al
     * principio del arranque de la API.
     */
    public static function iniciar(): void
    {
        // Si ya se inició, no se hace nada: registrar los handlers dos veces
        // duplicaría cada log.
        if (self::$iniciada) {
            return;
        }
        self::$iniciada = true;

        // Marca de tiempo de arranque. Se usa hrtime(true) porque devuelve
        // float en segundos y no depende del reloj del sistema: si el reloj
        // se ajusta (NTP) durante la petición, la duración sigue siendo real.
        self::$inicioMs = hrtime(true) / 1_000_000;

        // Decide el identificador de correlación de esta petición.
        $correlacion = CorrelationId::iniciar();

        // Cuando la API corre por línea de comandos (los scripts
        // probar_*.php, sembrar_datos_demo.php, etc.) NO se instalan los
        // handlers ni el encabezado, y solo queda la correlación.
        //
        // El motivo es concreto: en consola, un error sin capturar tiene que
        // verse en la salida del script para que el que lo corrió entienda
        // qué pasó. Si el handler de excepciones respondiera un JSON 500 en
        // consola, esos scripts dejarían de informar su propio fallo. Y un
        // "resumen de petición" en consola no significa nada: no hay
        // petición ni estado HTTP.
        if (PHP_SAPI === 'cli') {
            return;
        }

        // Se lo devuelve al cliente. Que el cliente lo conozca es lo que
        // permite que un usuario reporte "falló algo" con el id exacto, y no
        // con "ayer en la tarde".
        // Solo si no se envió ninguna salida todavía: PHP lanza una advertencia
        // si se intenta mandar un encabezado después de imprimir algo.
        if (!headers_sent()) {
            header(CorrelationId::HEADER . ': ' . $correlacion);
        }

        // Intercepta los avisos y errores de PHP (E_WARNING, E_NOTICE,
        // E_DEPRECATED...). Sin esto, un problema de código se manifiesta
        // solo en la salida HTTP y desaparece detrás de un 500 genérico.
        set_error_handler([self::class, 'avisarPhp']);

        // Intercepta las excepciones no controladas. Esta es la red de
        // seguridad: si algo explota fuera de un try/catch, igual queda
        // registrado con su tipo, mensaje y archivo.
        set_exception_handler([self::class, 'registrarExcepcion']);

        // register_shutdown_function se ejecuta SIEMPRE al terminar el script,
        // incluso con exit(), con un error fatal o con una excepción sin
        // capturar. Es el único punto fiable para el resumen de la petición.
        register_shutdown_function([self::class, 'finalizar']);

        // Línea de apertura. Con qué ruta y con qué método se entra.
        Logger::info('petición recibida', self::atributos());
    }

    /** @return string Método HTTP de la petición (GET, POST, ...) */
    public static function metodo(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'DESCONOCIDO'));
    }

    /**
     * Ruta de la petición, sin la base del proyecto ni query string.
     *
     * Es importante NO mandar el query string: puede traer datos personales
     * (búsquedas por DNI, por ejemplo) y además no sirve para diagnóstico.
     *
     * @return string Ruta relativa, por ejemplo "/api/medicos"
     */
    public static function ruta(): string
    {
        // parse_url con PHP_URL_PATH devuelve solo el path, sin "?a=1".
        $ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        // Si viniera vacía, se usa "/" en lugar de "".
        return $ruta !== '' ? $ruta : '/';
    }

    /**
     * IP del cliente.
     *
     * Se registra porque es indispensable para diagnosing un intento de
     * fuerza bruta o un abuso. Aclaración: la IP es un dato personal en
     * varios regímenes legales; acá queda en el log interno del servidor,
     * nunca se expone en las respuestas ni se envía a terceros.
     *
     * @return string IP del cliente, o "desconocida"
     */
    public static function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'desconocida';
    }

    /**
     * Atributos fijos de la petición, para agregar como contexto en cualquier
     * log. Un solo lugar donde se decide qué es "la petición".
     *
     * @return array
     */
    public static function atributos(): array
    {
        return [
            'metodo' => self::metodo(),
            'ruta'   => self::ruta(),
            'ip'     => self::ip(),
        ];
    }

    /**
     * Handler de errores de PHP (avisos, warnings, deprecations).
     *
     * @param int    $codigo  Nivel del error (E_WARNING, E_DEPRECATED, ...)
     * @param string $mensaje Texto del error
     * @param string $archivo Archivo donde ocurrió
     * @param int    $linea   Línea donde ocurrió
     * @return bool true para que PHP no vuelva a imprimirlo
     */
    public static function avisarPhp(int $codigo, string $mensaje, string $archivo, int $linea): bool
    {
        // Los errores silenciados con @ no se registran: el código los
        // silenció a propósito, y si los registrásemos el log estaría
        // mintiendo sobre lo que el código cree que pasó.
        if ((error_reporting() & $codigo) === 0) {
            return true;
        }

        // Los avisos de código obsoleto (E_DEPRECATED) son WARN: no rompen
        // nada hoy, pero avisan que mañana lo van a romper.
        $nivel = in_array($codigo, [E_DEPRECATED, E_USER_DEPRECATED], true)
            ? Logger::WARN
            : Logger::ERROR;

        // basename() deja solo el nombre del archivo: la ruta completa
        // revela la estructura del servidor y no aporta nada al diagnóstico.
        Logger::log($nivel, 'error de PHP: ' . $mensaje, [
            'tipo'     => self::nombreDelTipo($codigo),
            'archivo'  => basename($archivo),
            'linea'    => $linea,
            'peticion' => self::atributos(),
        ]);

        // true = ya lo registramos nosotros, PHP no lo imprime otra vez (ni
        // por stdout, ni mezclado en el JSON de la respuesta).
        return true;
    }

    /**
     * Handler de excepciones no controladas: la red de seguridad final.
     *
     * @param \Throwable $e Excepción que nobody atrapó
     */
    public static function registrarExcepcion(\Throwable $e): void
    {
        // ERROR: es exactamente el caso que el módulo pide marcar como ERROR,
        // un fallo que alguien tiene que mirar.
        Logger::error('excepción no controlada: ' . $e->getMessage(), [
            'tipo'     => get_class($e),
            'archivo'  => basename($e->getFile()),
            'linea'    => $e->getLine(),
            'peticion' => self::atributos(),
            // Contexto de validación: el tipo y el mensaje bastan para
            // diagnosticar. NO se manda el stack trace completo porque suele
            // contener rutas absolutas del servidor; en desarrollo se puede
            // reconstruir con el archivo y la línea.
        ]);

        // Si ya se empezó a responder (por ejemplo, el canal SSE ya escribió
        // eventos), no se puede cambiar el código HTTP. En ese caso solo
        // queda el log: mandar otro encabezado después genera un warning de
        // PHP y deja la respuesta a medias.
        if (headers_sent()) {
            return;
        }

        // Respuesta genérica. El detalle real (tipo, archivo, línea) queda
        // en el log, correlacionado con este mismo identificador: el cliente
        // recibe un requestId y el operador encuentra la causa exacta.
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'        => false,
            'mensaje'   => 'Error interno del servidor',
            'errores'   => [],
            'requestId' => CorrelationId::actual(),
            'code'      => 'ERR_INTERNAL',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resumen de la petición al terminar. Se ejecuta siempre.
     */
    public static function finalizar(): void
    {
        // Duración en milisegundos, redondeada a entero. Redondear evita
        // ver "12,3333333 ms" en un log.
        $duracionMs = (int) round(hrtime(true) / 1_000_000 - self::$inicioMs);

        // Código HTTP con el que terminó la respuesta. http_response_code()
        // sin argumentos devuelve el estado actual y no lo modifica.
        $estado = http_response_code();
        if (!is_int($estado)) {
            $estado = 200;
        }

        // Un error fatal de PHP (memoria agotada, timeout) mata el script
        // sin pasar por el handler de excepciones. error_get_last() lo
        // detecta; si además ya se registró, no se repite.
        $ultimo = error_get_last();
        if ($ultimo !== null && self::esFatal($ultimo['type'] ?? 0)) {
            Logger::error('error fatal de PHP: ' . ($ultimo['message'] ?? 'desconocido'), [
                'tipo'     => self::nombreDelTipo($ultimo['type']),
                'archivo'  => basename((string) ($ultimo['file'] ?? '')),
                'linea'    => (int) ($ultimo['line'] ?? 0),
                'peticion' => self::atributos(),
            ]);
        }

        // El nivel se elige por el resultado, no por costumbre:
        //   5xx -> ERROR (hay que enterarse)
        //   4xx -> WARN (anómalo pero el sistema siguió funcionando)
        //   2xx/3xx -> INFO (evento normal)
        if ($estado >= 500) {
            $nivel = Logger::ERROR;
        } elseif ($estado >= 400) {
            $nivel = Logger::WARN;
        } else {
            $nivel = Logger::INFO;
        }

        // El mensaje dice "qué pasó" en una frase; los detalles van aparte.
        Logger::log($nivel, sprintf('petición finalizada: %d', $estado), [
            'metodo'      => self::metodo(),
            'ruta'        => self::ruta(),
            'estado'      => $estado,
            'duracion_ms' => $duracionMs,
            'ip'          => self::ip(),
        ]);
    }

    /**
     * ¿Es un tipo de error que termina el script?
     *
     * @param int $tipo Constante E_* del error
     * @return bool
     */
    private static function esFatal(int $tipo): bool
    {
        return in_array($tipo, [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
        ], true);
    }

    /**
     * Nombre legible de un tipo de error de PHP.
     *
     * @param int $tipo Constante E_* del error
     * @return string
     */
    private static function nombreDelTipo(int $tipo): string
    {
        // get_defined_constants() trae el catálogo completo; se busca la
        // constante cuyo valor sea el tipo. Se recorre el array una vez y se
        // corta en el primer acierto (este método no se llama en el camino
        // caliente más de una vez por error).
        foreach (get_defined_constants() as $nombre => $valor) {
            if ($valor === $tipo && str_starts_with((string) $nombre, 'E_')) {
                return (string) $nombre;
            }
        }
        // Si no se reconoce, se devuelve el número: mejor un dato inútil
        // que un dato inventado.
        return 'E_DESCONOCIDO(' . $tipo . ')';
    }
}
