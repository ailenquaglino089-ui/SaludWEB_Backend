<?php
// ============================================================
// core/Env.php - Carga de variables de entorno desde .env
// ============================================================
// Módulo: "Seguridad Básica para APIs - Variables de Entorno"
// ------------------------------------------------------------
// QUÉ HACE
// Lee el archivo .env de la raíz del backend y lo convierte en
// variables de entorno accesibles con getenv(), Config::get(),
// $_ENV y $_SERVER. Sin esto, el archivo .env que hay en el
// repositorio se ignora por completo y todo queda atado a los
// valores por defecto que estén escritos en el código.
//
// POR QUÉ NO USA UNA LIBRERÍA (vlucas/phpdotenv y cía.)
// El proyecto solo depende de firebase/php-jwt. Agregar un
// paquete para leer unas cuantas líneas de texto es una
// dependencia más que hay que instalar, auditar y mantener al
// día, y el formato que hace falta acá es el más simple posible:
// CLAVE=valor. Este archivo es esa lectura, sin dependencias.
//
// JERARQUÍA DE VALORES (quién gana)
//   1. La variable real del sistema operativo o del servidor.
//   2. El archivo .env.
//   3. El valor por defecto escrito en el código.
// Que el sistema gane sobre .env es deliberado: en un servidor
// real las variables se definen en el panel o en el servicio, y
// un .env viejo copiado en el servidor no tiene que poder pisar
// la configuración vigente. También significa que se puede
// probar la API con variables sueltas sin tocar el archivo.
//
// SEGURIDAD
// Este archivo NUNCA imprime los valores que carga: los archivos
// .env contienen contraseñas y secretos. El .env real está en
// .gitignore y no se sube; lo que se versiona es .env.example,
// con valores de ejemplo y sin secretos reales.
// ============================================================

class Env
{
    /**
     * Marca de "ya se intentó cargar".
     *
     * Es lo que hace que el método sea idempotente: bootstrap.php
     * y db.php llaman a cargar() porque cualquiera de los dos puede
     * ser el primer archivo en ejecutarse (una petición HTTP entra
     * por bootstrap; un script de la raíz como sembrar_datos_demo.php
     * entra directo por db.php). Si se llamara dos veces no pasaría
     * nada grave, pero reabrir y reparsear el archivo en cada llamada
     * es trabajo repetido sin sentido.
     */
    private static bool $cargado = false;

    /**
     * Ruta absoluta del último .env que se intentó leer.
     *
     * Se guarda solo para poder diagnosticar desde afuera (por ejemplo
     * desde un endpoint de salud o desde un script) QUÉ archivo se está
     * usando. Nunca se guarda el contenido.
     */
    private static string $rutaUsada = '';

    /**
     * Carga el archivo .env si existe. No falla jamás.
     *
     * El contrato es "mejor esfuerzo": si no hay archivo, si no se puede
     * leer o si una línea está mal escrita, la aplicación sigue andando
     * con lo que haya (defaults del código o variables del sistema).
     * Romper el arranque por una línea malformada en un archivo de
     * configuración sería un costo desproporcionado para un beneficio
     * nulo: el error real se ve después, cuando la app no conecta.
     *
     * @param string|null $ruta Ruta al .env. Si es null se usa la raíz del backend.
     */
    public static function cargar(?string $ruta = null): void
    {
        // Si ya se cargó en esta misma ejecución, no se repite.
        if (self::$cargado) {
            return;
        }
        // Se marca ANTES de leer: así, si el archivo tiene algo raro y la
        // función se vuelve a llamar, no se reintenta en loop.
        self::$cargado = true;

        // Ruta por defecto: este archivo vive en core/, así que subir un
        // nivel con dirname(__DIR__) da la raíz del backend, donde está .env.
        $ruta ??= dirname(__DIR__) . '/.env';
        self::$rutaUsada = $ruta;

        // Si el archivo no existe o no se puede leer, la app igual funciona:
        // quedan las variables del sistema y los defaults del código.
        // is_readable() cubre tanto el caso "no existe" como "sin permisos".
        if (!is_readable($ruta)) {
            return;
        }

        // FILE_IGNORE_NEW_LINES: devuelve cada línea sin el salto final.
        // Sin esto cada string terminaría con "\n" y los valores quedarían
        // con un carácter fantasma al final (en JWT_SECRET eso rompería
        // la firma sin que el error diga nada útil).
        $lineas = file($ruta, FILE_IGNORE_NEW_LINES);

        // file() devuelve false ante un error de lectura (no excepción).
        if ($lineas === false) {
            return;
        }

        // Se recorre línea por línea. La numeración original se conserva
        // porque sirve para referenciar el error: "ver .env, línea 12".
        foreach ($lineas as $numero => $linea) {
            self::procesarLinea($linea, $numero);
        }
    }

    /**
     * Interpreta una sola línea del archivo .env.
     *
     * @param string $linea  Texto crudo de la línea.
     * @param int    $numero Número de línea original (para mensajes de error).
     */
    private static function procesarLinea(string $linea, int $numero): void
    {
        // Se recortan espacios alrededor para poder comparar sin sorpresas.
        $linea = trim($linea);

        // Línea vacía: no aporta nada, se saltea.
        // Línea de comentario (# al principio): se saltea igual.
        // IMPORTANTE: el chequeo es "$linea[0] === '#'" y no str_starts_with,
        // porque con una cadena vacía el índice 0 no existe y en PHP 8 eso
        // lanza un aviso en cada línea en blanco del archivo.
        if ($linea === '' || $linea[0] === '#') {
            return;
        }

        // Formato esperado: CLAVE=valor
        // - Las claves son alfanuméricas con guiones bajos y arrancan con
        //   letra o guion bajo (es lo que aceptan los entornos).
        // - Se acepta el prefijo "export " por si el .env también se usa
        //   en una shell (`source .env`), que es un uso habitual.
        // - El valor se toma TODO el resto de la línea, sin recortar nada
        //   todavía: los espacios pueden ser significativos entre comillas.
        $patron = '/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/';
        if (!preg_match($patron, $linea, $coincidencia)) {
            // No tiene formato de asignación. NO se corta la ejecución:
            // una línea ilegible en un archivo de configuración no debería
            // tumbar el servidor. El síntoma útil es un warning en el log
            // (display_errors) indicando la línea, no una excepción fatal.
            trigger_error(
                "Env: la línea {$numero} del archivo .env no es una asignación CLAVE=valor y se ignoró",
                E_USER_WARNING
            );
            return;
        }

        $clave = $coincidencia[1];
        $valor = self::limpiarValor($coincidencia[2]);

        // Decisión de jerarquía: si la variable YA existe en el entorno real,
        // el archivo .env no la toca. getenv() devuelve false cuando la
        // variable no está definida, así que "distinto de false" significa
        // "alguien (el SO, el panel del servidor, una shell) ya la definió".
        // Un valor vacío DEFINIDO cuenta como definido: si el operador puso
        // DB_PASS= vacío a propósito, ese vacío manda.
        if (getenv($clave) !== false) {
            return;
        }

        // putenv() es lo que hace que getenv() la encuentre después: es la
        // API real de variables de entorno de PHP en proceso.
        putenv("{$clave}={$valor}");
        // $_ENV y $_SERVER se llenan también porque hay código (y librerías)
        // que lee por esas vías en vez de por getenv(). Si solo se hiciera
        // putenv(), las tres vistas quedarían desincronizadas.
        $_ENV[$clave] = $valor;
        $_SERVER[$clave] = $valor;
    }

    /**
     * Deja el valor de una línea de .env en su forma final.
     *
     * Formatos aceptados:
     *   valor                  -> valor
     *   "valor con espacios"   -> valor con espacios
     *   'otro valor'           -> otro valor
     *   valor                  # comentario -> valor
     *
     * @param string $valor Texto tal como vino después del signo igual.
     */
    private static function limpiarValor(string $valor): string
    {
        // Se recortan los espacios de los bordes: CLAVE=   valor  es muy
        // común cuando se edita a mano y los espacios no forman parte del
        // valor en ese formato.
        $valor = trim($valor);

        // Valor vacío (CLAVE=): es un caso válido, no hay nada que desarmar.
        if ($valor === '') {
            return '';
        }

        $primero = $valor[0];

        // Si arranca y termina con la misma comilla, esas comillas son del
        // formato, no del valor: se quitan. Permiten guardar espacios al
        // principio/final y caracteres que de otro modo se confundirían
        // (por ejemplo un # adentro del valor).
        if (($primero === '"' || $primero === "'") && str_ends_with($valor, $primero)) {
            $valor = substr($valor, 1, -1);

            // En comillas dobles se desescapan las secuencias de barra
            // invertida más habituales. En comillas simples no: en los
            // entornos UNIX las comillas simples son literales.
            if ($primero === '"') {
                $valor = str_replace(['\\n', '\\t'], ["\n", "\t"], $valor);
            }

            return $valor;
        }

        // Sin comillas: un "#" precedido por al menos un espacio inicia un
        // comentario al final de la línea (es la regla habitual en los .env).
        // El espacio previo evita cortar adentro de valores tipo "clave#1",
        // donde el # es parte del dato y no un comentario.
        $partes = preg_split('/\s+#/', $valor, 2);

        return trim($partes[0]);
    }

    /**
     * Ruta del archivo .env que se intentó usar en esta ejecución.
     *
     * Devuelve la ruta, nunca el contenido. Existe para poder responder
     * "¿de dónde salió esta configuración?" sin tener que adivinar si el
     * archivo se cargó, si se cargó otro, o si no se cargó ninguno.
     */
    public static function rutaUsada(): string
    {
        return self::$rutaUsada;
    }

    /**
     * ¿Se intentó cargar el .env en esta ejecución?
     *
     * Útil para verificar desde un script que el cargador corrió de verdad
     * y no que se está ejecutando una copia vieja sin él.
     */
    public static function fueCargado(): bool
    {
        return self::$cargado;
    }
}
