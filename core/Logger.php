<?php
// ============================================================
// core/Logger.php - Logs estructurados en JSON con enmascarado
// ============================================================
// Módulo: "Calidad Profesional del Software - Logs y Trazabilidad"
// ------------------------------------------------------------
// Qué resuelve este archivo:
//
//   1. NIVELES CORRECTOS. Un sistema sin niveles obliga a mirar todo. Con
//      niveles, un WARN es una situación anómala recuperable y un ERROR es un
//      fallo que necesita atención. Loguear todo como ERROR genera ruido y
//      fatiga de alertas: el operador termina ignorando el canal entero.
//
//   2. ESTRUCTURA. Cada línea es un JSON de UNA línea (JSON Lines). Eso
//      permite indexar, filtrar y buscar con herramientas como ELK, Datadog
//      o CloudWatch, algo imposible con frases sueltas como
//      "error en login del usuario 5". Campos siempre presentes:
//        timestamp   fecha y hora ISO 8601 con zona horaria
//        level       DEBUG | INFO | WARN | ERROR
//        message     qué pasó, en una frase
//        service     qué componente lo escribió (saludweb-api)
//        correlationId con qué petición se relaciona (ver CorrelationId)
//        context     datos extra, ya enmascarados
//
//   3. LO QUE NO SE LOGUEA. contraseñas, tokens, secretos y datos personales
//      sensibles (PII) no van al archivo, por dos motivos: es una mala
//      práctica y además puede implicar violación de GDPR o PCI-DSS. El
//      enmascarado se aplica en la CAPA DE LOGGING (esta clase), no en cada
//      llamador: si depende de que cada llamada se acuerde de limpiar, se
//      olvida una vez y el secreto queda escrito en disco.
//
//   4. QUE EL LOG NUNCA ROMPA LA API. Si el directorio no se puede escribir,
//      la petición tiene que responder igual. Un logger que tira una
//      excepción cuando el disco está lleno convierte un problema de
//      observabilidad en una caída del servicio.
//
// Las funciones de formato (registro(), linea(), enmascarar()) son PURAS: no
// tocan disco ni red, solo transforman datos. Por eso se prueban solas en
// tests/LoggerTest.php sin necesitar base de datos ni servidor.
// ============================================================

class Logger
{
    // Nivel de detalle interno (desarrollo).
    public const DEBUG = 'DEBUG';
    // Evento normal del sistema: un login exitoso, una cita creada.
    public const INFO = 'INFO';
    // Situación anómala pero recuperable: un 401, un rate limit, un 404.
    public const WARN = 'WARN';
    // Fallo que requiere atención: excepción no controlada, error 500.
    public const ERROR = 'ERROR';

    // Orden de severidad. Sirve para comparar niveles sin comparar cadenas:
    // "WARN" > "INFO" no es una comparación de strings que signifique nada.
    private const PESOS = [
        self::DEBUG => 10,
        self::INFO  => 20,
        self::WARN  => 30,
        self::ERROR => 40,
    ];

    // Niveles aceptados, para validar lo que venga de la configuración.
    private const NIVELES = [self::DEBUG, self::INFO, self::WARN, self::ERROR];

    // Claves cuyo valor se reemplaza COMPLETAMENTE por la etiqueta de
    // redacción. Son secretos (nunca se registran, ni parcialmente) y texto
    // clínico del paciente (que es dato personal sensible).
    private const CLAVES_REDACTADAS = [
        'password', 'passwd', 'pwd', 'contrasena', 'contrasenia',
        'token', 'jwt', 'refresh_token', 'authorization', 'secret',
        'api_key', 'apikey', 'cookie',
        'motivo', 'notas', 'indicaciones', 'diagnostico',
    ];

    // Claves cuyo valor se enmascara PARCIALMENTE: se conservan algunos
    // caracteres para poder correlacionar (por ejemplo, dos pacientes con el
    // mismo DNI se reconocen como la misma persona) sin exponer el dato.
    private const CLAVES_PII = [
        'email', 'correo', 'dni', 'documento', 'telefono', 'celular',
        'nombre', 'apellido', 'matricula',
    ];

    // Etiqueta que reemplaza a los valores secretos.
    private const ETIQUETA_REDACTADO = '[oculto]';

    // Tope de longitud por valor: evita que una respuesta enorme (un listado
    // de 1000 registros) termine en el log y reviente el archivo.
    private const LONGITUD_MAXIMA_VALOR = 500;

    // Nivel mínimo que se escribe. Configurable por entorno.
    private static string $nivelMinimo = self::INFO;

    // Carpeta donde se escriben los archivos. null = todavía sin resolver.
    private static ?string $directorio = null;

    // Nombre del componente emisor, para cuando conviven varios servicios
    // escribiendo en el mismo archivo.
    private static string $servicio = 'saludweb-api';

    // Marca de que ya se avisó por stderr que el log no se puede escribir.
    // Evita repetir el mismo aviso 500 veces por petición.
    private static bool $avisoFalloEscritura = false;

    /**
     * Configura el logger. Se llama una vez, al arrancar la aplicación.
     *
     * @param string|null $directorio Carpeta de logs (null = la de por defecto)
     * @param string|null $nivelMinimo Nivel mínimo a escribir (null = según entorno)
     * @param string|null $servicio    Nombre del componente emisor
     */
    public static function configurar(
        ?string $directorio = null,
        ?string $nivelMinimo = null,
        ?string $servicio = null
    ): void {
        // Carpeta de logs. Se resuelve en este orden:
        //
        //   1. el parámetro recibido (lo usan las pruebas, con carpetas
        //      temporales propias);
        //   2. la variable de entorno LOG_DIR, que es lo que se documenta en
        //      .env.example y permite mandar los logs a otra unidad o a un
        //      servicio externo sin tocar el código;
        //   3. la carpeta por defecto del proyecto.
        //
        // La carpeta por defecto vive en storage/logs: está dentro del
        // proyecto, pero storage/ está en .gitignore, así que los logs nunca
        // se suben al repositorio por accidente.
        self::$directorio = self::resolverDirectorio($directorio);

        // El nivel por defecto depende del entorno: en desarrollo interesa
        // ver hasta el DEBUG; en producción, un DEBUG por petición llena el
        // disco y esconde los errores que importan.
        $nivelPorDefecto = Config::esProduccion() ? self::INFO : self::DEBUG;

        // El nivel puede venir por constructor o por entorno (LOG_LEVEL).
        $nivel = ($nivelMinimo !== null && $nivelMinimo !== '')
            ? $nivelMinimo
            : Config::get('LOG_LEVEL');
        $nivel = strtoupper(trim($nivel));

        // Solo se acepta un nivel que exista en la lista. Si alguien escribe
        // LOG_LEVEL=VERBOSE, se cae al nivel por defecto en lugar de
        // desactivar el log entero: un valor mal escrito no puede dejar al
        // sistema sin logs, que es peor que tener un nivel de más.
        self::$nivelMinimo = in_array($nivel, self::NIVELES, true) ? $nivel : $nivelPorDefecto;

        // Nombre del servicio, con el valor de entorno como alternativa.
        self::$servicio = $servicio ?? (Config::get('LOG_SERVICE') ?: 'saludweb-api');
    }

    /**
     * Resuelve contra qué carpeta se van a escribir los archivos de log.
     *
     * Existe como método aparte y no como tres líneas dentro de configurar()
     * porque tiene una decisión escondida: las rutas relativas se anclan a la
     * raíz del backend. Si LOG_DIR dice "logs" y el proceso corre con el
     * directorio actual en otro lado (cron, Apache, una consola), escribir en
     * "logs/" crearía una carpeta nueva donde nadie la va a buscar.
     *
     * @param string|null $directorio Carpeta recibida por parámetro
     * @return string Ruta absoluta (o relativas ancladas a la raíz del proyecto)
     */
    private static function resolverDirectorio(?string $directorio): string
    {
        $raiz = __DIR__ . '/..';
        $porDefecto = $raiz . '/storage/logs';

        // Prioridad 1: lo que recibió el método (pruebas).
        $ruta = trim($directorio ?? '');
        // Prioridad 2: la configuración del entorno.
        if ($ruta === '') {
            $ruta = trim(Config::get('LOG_DIR') ?? '');
        }
        // Prioridad 3: la carpeta del proyecto.
        if ($ruta === '') {
            return $porDefecto;
        }

        // Una ruta absoluta se respeta tal cual, porque quien la escribio
        // decidio que los logs van ahi y esa decision no se discute.
        if (self::esRutaAbsoluta($ruta)) {
            return $ruta;
        }

        // Una ruta relativa se ancla a la raíz del backend.
        return $raiz . '/' . ltrim($ruta, '/\\');
    }

    /**
     * Dice si una ruta ya trae su propio punto de partida, sin depender de que
     * el sistema operativo esté en español o en inglés.
     *
     * Se cubre el caso de Windows con "C:\..." y las rutas de red "\\servidor",
     * además de la barra inicial de Unix. Sin esto, un LOG_DIR con "C:/logs"
     * sería tratado como relativo y el archivo aparecería en una subcarpeta
     * llamada "C:" dentro del proyecto.
     *
     * @param string $ruta Ruta a inspeccionar
     * @return bool true si es absoluta
     */
    private static function esRutaAbsoluta(string $ruta): bool
    {
        // Unidad de Windows followed de dos puntos (C:, D:, etc.).
        if (preg_match('#^[a-zA-Z]:[\\\\/]#', $ruta) === 1) {
            return true;
        }

        // Rutas de red de Windows (\\servidor\recurso).
        if (str_starts_with($ruta, '\\\\')) {
            return true;
        }

        // Raíz de Unix o de Samba.
        return str_starts_with($ruta, '/');
    }

    /** @return string Nivel mínimo que se está escribiendo */
    public static function nivelMinimo(): string
    {
        return self::$nivelMinimo;
    }

    /** @return string Carpeta donde se escriben los logs */
    public static function directorio(): string
    {
        if (self::$directorio === null) {
            self::configurar();
        }
        return (string) self::$directorio;
    }

    /**
     * Archivo del día. Un archivo por día y no uno solo porque: se rotan
     * solos, se pueden comprimir y borrar por fecha, y no hay que abrir en
     * modo "append" un archivo de 4 GB.
     *
     * @return string Ruta absoluta del archivo del día
     */
    public static function archivo(): string
    {
        return rtrim(self::directorio(), '/\\') . '/saludweb-' . date('Y-m-d') . '.log';
    }

    /**
     * ¿Este nivel se escribe con la configuración actual?
     *
     * @param string $nivel Nivel del mensaje
     * @return bool
     */
    public static function habilitado(string $nivel): bool
    {
        $nivel = strtoupper($nivel);
        // Nivel desconocido: no se escribe. Escribir basura en el log es
        // peor que perder un mensaje.
        if (!isset(self::PESOS[$nivel])) {
            return false;
        }
        return self::PESOS[$nivel] >= self::PESOS[self::$nivelMinimo];
    }

    /** Log de nivel DEBUG (detalle de desarrollo). */
    public static function debug(string $mensaje, array $contexto = []): void
    {
        self::log(self::DEBUG, $mensaje, $contexto);
    }

    /** Log de nivel INFO (evento normal). */
    public static function info(string $mensaje, array $contexto = []): void
    {
        self::log(self::INFO, $mensaje, $contexto);
    }

    /** Log de nivel WARN (anómalo pero recuperable). */
    public static function warn(string $mensaje, array $contexto = []): void
    {
        self::log(self::WARN, $mensaje, $contexto);
    }

    /** Log de nivel ERROR (fallo que requiere atención). */
    public static function error(string $mensaje, array $contexto = []): void
    {
        self::log(self::ERROR, $mensaje, $contexto);
    }

    /**
     * Escribe un log si el nivel corresponde.
     *
     * @param string $nivel   DEBUG | INFO | WARN | ERROR
     * @param string $mensaje Qué pasó
     * @param array  $contexto Datos extra (se enmascaran antes de escribir)
     */
    public static function log(string $nivel, string $mensaje, array $contexto = []): void
    {
        // Primero el filtro por nivel: si el nivel no corresponde, no se
        // arma ni se serializa nada. En un sistema con mucho tráfico esto
        // es la diferencia entre "encender el log" y "no hacer nada".
        if (!self::habilitado($nivel)) {
            return;
        }
        self::escribir(self::linea($nivel, $mensaje, $contexto));
    }

    /**
     * Arma el registro estructurado (función pura: no escribe nada).
     *
     * @param string $nivel   Nivel del mensaje
     * @param string $mensaje Qué pasó
     * @param array  $contexto Datos extra
     * @return array El registro listo para serializar
     */
    public static function registro(string $nivel, string $mensaje, array $contexto = []): array
    {
        return [
            // date('c') = ISO 8601 (ej: 2026-10-05T14:03:21-03:00). Se usa
            // con zona horaria explícita porque un log sin zona no se puede
            // comparar con otro servidor en otra región.
            'timestamp'    => date('c'),
            // El nivel tal cual se escribió, en mayúsculas.
            'level'        => strtoupper($nivel),
            // El mensaje en una frase. Los datos van en 'context', no
            // pegados al texto: es lo que permite filtrar sin parsear.
            'message'      => $mensaje,
            // Qué componente escribió la línea.
            'service'      => self::$servicio,
            // Con qué petición se correlaciona esta línea.
            'correlationId' => CorrelationId::actual(),
            // Los datos extra, SIEMPRE pasar por el enmascarado.
            'context'      => self::enmascarar($contexto),
        ];
    }

    /**
     * Serializa el registro a una línea JSON (función pura).
     *
     * @param string $nivel   Nivel del mensaje
     * @param string $mensaje Qué pasó
     * @param array  $contexto Datos extra
     * @return string Una línea JSON terminada en \n
     */
    public static function linea(string $nivel, string $mensaje, array $contexto = []): string
    {
        // JSON_UNESCAPED_UNICODE: los tildes quedan legibles ("médico" y no
        // "m\u00e9dico"), que en un log que va a leer una persona importa.
        // JSON_UNESCAPED_SLASHES: las URLs quedan como "/api/medicos" y no
        // como "\/api\/medicos".
        $json = json_encode(
            self::registro($nivel, $mensaje, $contexto),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // Si un valor del contexto tenía algo no serializable, json_encode
        // devuelve false. No se pierde el log por eso: se escribe una línea
        // mínima y el problema queda visible en el archivo.
        if ($json === false) {
            $json = json_encode([
                'timestamp'     => date('c'),
                'level'         => strtoupper($nivel),
                'message'       => $mensaje,
                'service'       => self::$servicio,
                'correlationId' => CorrelationId::actual(),
                'context'       => ['_error' => 'contexto no serializable'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // El "\n" final es lo que convierte esto en JSON Lines: una línea por
        // registro. Sin él, dos registros quedarían pegados en la misma línea
        // y una herramienta que lea línea por línea los mezclaría.
        return $json . "\n";
    }

    /**
     * Enmascara datos sensibles antes de escribir (función pura y recursiva).
     *
     * @param mixed $datos Cualquier valor (array, objeto, escalar)
     * @return mixed El mismo valor, con secretos redactados y PII parcial
     */
    public static function enmascarar(mixed $datos): mixed
    {
        // Arreglos: se recorren clave por clave. El enmascarado depende del
        // NOMBRE de la clave (password, email...), no del valor: es el
        // nombre el que dice qué es el dato.
        if (is_array($datos)) {
            $salida = [];
            foreach ($datos as $clave => $valor) {
                // strtolower() porque las claves llegan en cualquier caja:
                // "Password", "PASSWORD" y "password" son el mismo secreto.
                $nombre = is_string($clave) ? strtolower($clave) : '';

                if (in_array($nombre, self::CLAVES_REDACTADAS, true)) {
                    // Se replaces el valor entero: ni un caracter del secreto
                    // llega al disco.
                    $salida[$clave] = self::ETIQUETA_REDACTADO;
                } elseif (in_array($nombre, self::CLAVES_PII, true)) {
                    // PII: se conservan algunos caracteres para poder
                    // correlacionar y se tapa el resto.
                    $salida[$clave] = self::enmascararParcial(is_scalar($valor) ? (string) $valor : '');
                } else {
                    // Cualquier otra clave: se recorre en profundidad, porque
                    // el dato sensible puede estar anidado (por ejemplo
                    // ['usuario' => ['password' => ...]]).
                    $salida[$clave] = self::enmascarar($valor);
                }
            }
            return $salida;
        }

        // Objetos: se registra la clase, no el contenido. Volcar un objeto
        // entero puede arrastrar conexiones o secretos que nadie revisó.
        if (is_object($datos)) {
            return '[objeto ' . get_class($datos) . ']';
        }

        // Strings sueltas (mensajes, valores): se acotan para que un texto
        // gigante no ocupe el archivo. Sin multibyte: es un corte de tamaño,
        // no una operación sobre letras.
        if (is_string($datos)) {
            return strlen($datos) > self::LONGITUD_MAXIMA_VALOR
                ? substr($datos, 0, self::LONGITUD_MAXIMA_VALOR) . '…[truncado]'
                : $datos;
        }

        // null, bool, int y float pasan sin cambios: no tienen nada que
        // ocultar y perderlos convertiría el log en algo inútil.
        return $datos;
    }

    /**
     * Enmascara parcialmente un dato personal.
     *
     * Conserva el primer carácter (para distinguir "juan@" de "jose@" sin
     * exponer la dirección) y oculta el resto. Si el valor es muy corto
     * para enmascarar sin revelarlo entero, se oculta completo.
     *
     * @param string $valor Dato personal
     * @return string
     */
    private static function enmascararParcial(string $valor): string
    {
        $valor = trim($valor);
        // Con 4 caracteres o menos, tapar "lo de menos 1" dejaría el dato
        // casi entero: en ese caso se oculta todo.
        if ($valor === '' || strlen($valor) <= 4) {
            return self::ETIQUETA_REDACTADO;
        }
        return substr($valor, 0, 1) . str_repeat('*', 3);
    }

    /**
     * Escribe una línea ya serializada en el archivo del día.
     *
     * @param string $linea Línea JSON terminada en \n
     */
    private static function escribir(string $linea): void
    {
        $archivo = self::archivo();

        // Se crea la carpeta si falta (con permisos 0775: el usuario del
        // servidor escribe, el grupo puede leer).
        if (!is_dir(self::directorio())) {
            @mkdir(self::directorio(), 0775, true);
        }

        // LOCK_EX: si dos peticiones terminan al mismo tiempo, cada
        // escritura espera a la anterior. Sin el lock, dos procesos pueden
        // intercalar bytes y dejar una línea JSON partida.
        // El "@" silencia el aviso si el archivo no se puede abrir: un log
        // ilegible no puede tumbar la API (ver el bloque de abajo).
        $ok = @file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);

        // Si la escritura falló, se avisa UNA vez por stderr. stderr es el
        // único canal que sigue disponible cuando el problema es el disco,
        // y el aviso va fuera del archivo justamente porque el archivo es
        // lo que falló.
        if ($ok === false && !self::$avisoFalloEscritura) {
            self::$avisoFalloEscritura = true;
            error_log('[SaludWEB] No se puede escribir el log en ' . $archivo);
        }
    }

    /**
     * Últimas líneas del archivo del día, para diagnóstico.
     *
     * @param int $cuantas Cuántas líneas devolver (por defecto 20)
     * @return array<string> Líneas, de la más antigua a la más reciente
     */
    public static function ultimasLineas(int $cuantas = 20): array
    {
        $archivo = self::archivo();
        // Si el archivo no existe todavía no hay nada que devolver: es un
        // estado normal, no un error.
        if (!is_file($archivo)) {
            return [];
        }
        // tail() no existe en PHP; la idiomática es leer el archivo entero y
        // quedarse con las últimas N líneas con array_slice.
        $lineas = @file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lineas === false) {
            return [];
        }
        // Si el archivo tiene menos líneas que las pedidas, el sliceNegative
        // las devuelve todas igual, que es lo que queremos.
        return array_values(array_slice($lineas, -max(1, $cuantas)));
    }
}
