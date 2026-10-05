<?php
// ============================================================
// core/CorrelationId.php - Identificador de correlación (trazabilidad)
// ============================================================
// Módulo: "Calidad Profesional del Software - Logs y Trazabilidad"
// ------------------------------------------------------------
// Una sola petición HTTP puede tocar varias capas (ruta, middleware,
// servicio, repositorio, base de datos) y generar avisos en todas. Sin un
// identificador común, un log dice "algo falló" y no dice cuál de las 500
// peticiones del minuto falló. Por eso existe esta clase.
//
// UNA sola responsabilidad: el identificador de la petición.
//   1. Decide qué identificador tiene la petición en curso.
//   2. Adopta el que envía el cliente si es confiable.
//   3. Genera uno propio si no viene o si viene manipulado.
//   4. Se lo entrega a quien lo necesite (Logger, Response, middlewares).
//
// NO escribe archivos, NO sabe de HTTP más allá de leer un encabezado y NO
// toma decisiones de negocio. Esa disciplina es la que permite probarla por
// separado (ver tests/CorrelationIdTest.php).
//
// FORMATO DEL IDENTIFICADOR
//   Solo letras, números, guion y guion bajo, hasta 64 caracteres.
//   Sin comas, sin saltos de línea, sin "<" ni ">".
//   La razón no es estética: el identificador viaja dentro de líneas de
//   texto (JSON) y dentro de encabezados HTTP. Si aceptáramos cualquier
//   cadena, un cliente podría mandar algo como "abc" seguido de un salto de
//   línea y un objeto JSON, y terminaría inyectando entradas falsas en el
//   archivo de log. Restringir el alfabeto hace que la validación sea una
//   lista blanca, no una lista negra.
// ============================================================

class CorrelationId
{
    // Nombre del encabezado HTTP que transporta el identificador.
    // Es el mismo nombre en la API, en el frontend y en la app móvil: si
    // los tres usan el mismo encabezado, sus logs se pueden unir después.
    public const HEADER = 'X-Correlation-Id';

    // Prefijo de los identificadores que genera el servidor.
    // No es decorativo: en el archivo de log permite distinguir de un
    // vistazo un identificador propio ("cid_...") de uno que envió el
    // cliente, y delata al que intenta colar un id con formato de traza
    // propia para hacer pasar sus datos por confiables.
    private const PREFIJO = 'cid_';

    // Longitud máxima aceptada para un identificador que viene del cliente.
    // Acotarlo evita dos cosas: entradas gigantes en el log y abuso del
    // encabezado para esconder payloads dentro del identificador.
    private const LONGITUD_MAXIMA = 64;

    // Alfabeto permitido: letras, dígitos, guion y guion bajo.
    // Las anclas ^ y $ exigen que TODA la cadena cumpla el patrón (no
    // alcanza con que "contenga" caracteres válidos).
    private const PATRON = '/^[A-Za-z0-9_-]{1,64}$/';

    // Identificador de la petición en curso.
    // Es estático (y no una propiedad de instancia) porque la correlación
    // es de ÁMBITO DE PETICIÓN: la comparten todas las capas sin que cada
    // una tenga que recibirla por constructor. En PHP cada petición corre
    // en su propio proceso o hilo, así que un estático no se pisa entre
    // peticiones concurrentes.
    private static ?string $actual = null;

    /**
     * Arranca la correlación de la petición.
     *
     * Debe llamarse una vez, al principio de la petición, ANTES de que
     * nadie escriba un log: si el primer log se escribiera sin
     * identificador, ese log quedaría imposible de correlacionar.
     *
     * @return string El identificador vigente (el del cliente si es válido,
     *                uno generado propio si no)
     */
    public static function iniciar(): string
    {
        // Busca el identificador que envía el cliente en el encabezado.
        // HTTP_X_CORRELATION_ID es la forma en que PHP expone un encabezado
        // personalizado: los guiones se convierten en guion bajo y se
        // antepone HTTP_.
        // El "??" con REDIRECT_ cubre el caso de Apache cuando reescribe la
        // URL y mueve los encabezados a otra variable (comportamiento
        // conocido de mod_rewrite).
        // El "??" del final significa: si ninguno de los dos existe, null.
        $propio = $_SERVER['HTTP_X_CORRELATION_ID']
            ?? $_SERVER['REDIRECT_HTTP_X_CORRELATION_ID']
            ?? null;

        // Regla de oro de la trazabilidad: si lo que llega NO es confiable,
        // se genera uno propio. Nunca se reusa un valor sin validar, porque
        // un identificador sin validar es un vector de inyección en los logs.
        self::$actual = self::esValido($propio) ? (string) $propio : self::generar();

        // Devuelve el identificador para que el llamador pueda, por ejemplo,
        // ponerlo en un encabezado de respuesta.
        return self::$actual;
    }

    /**
     * Identificador vigente de la petición.
     *
     * Si nadie llamó a iniciar(), genera uno igual. Así cualquier
     * consumidor (un log, una respuesta 500) puede consultar el
     * identificador sin defenderse de "todavía no hay ninguno".
     *
     * @return string Identificador de correlación
     */
    public static function actual(): string
    {
        // ??= es "asignar si es null". Si ya hay identificador (porque se
        // llamó a iniciar()), lo devuelve tal cual; si no, genera uno y lo
        // guarda. Evita el if de tres líneas.
        return self::$actual ??= self::generar();
    }

    /**
     * ¿El valor recibido es un identificador de correlación confiable?
     *
     * @param mixed $valor Valor crudo (puede ser null, un array, cualquier cosa)
     * @return bool true solo si cumple el formato
     */
    public static function esValido(mixed $valor): bool
    {
        // Si no es un string, no puede ser un identificador válido.
        // Esto también cubre el caso del array: un array no es string, así
        // que queda rechazado de entrada, sin arriesgar que preg_match
        // lance una advertencia.
        if (!is_string($valor)) {
            return false;
        }

        // Longitud máxima, comprobada aparte (y no solo con el {1,64} de la
        // expresión regular) para que quede explícito en el código.
        if (strlen($valor) > self::LONGITUD_MAXIMA) {
            return false;
        }

        // Validación real: la cadena completa debe estar formada solo por
        // caracteres del alfabeto permitido.
        return (bool) preg_match(self::PATRON, $valor);
    }

    /**
     * Genera un identificador nuevo, propio del servidor.
     *
     * @return string Identificador con el formato "cid_<16 caracteres hex>"
     */
    public static function generar(): string
    {
        // random_bytes() es criptográficamente seguro; por eso NO se usan
        // uniqid() ni rand(): un identificador de traza no tiene que ser
        // secreto, sí tiene que ser irrepetible y no adivinable, porque
        // termina en archivos de log que se comparten con operaciones.
        // bin2hex() convierte esos bytes en texto hexadecimal legible.
        return self::PREFIJO . bin2hex(random_bytes(8));
    }

    /**
     * Olvida el identificador actual.
     *
     * Existe para las pruebas automatizadas (principio FIRST: Independent):
     * cada prueba que verifica el arranque de una correlación necesita
     * partir de un estado conocido. En producción nadie debería llamarlo: si
     * se perdiera el identificador a mitad de una petición, los logs de esa
     * petición quedarían sin correlación.
     */
    public static function olvidar(): void
    {
        self::$actual = null;
    }
}
