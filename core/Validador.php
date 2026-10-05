<?php
// ============================================================
// core/Validador.php - Validación y saneamiento de entradas
// ============================================================
// Módulo: "Calidad Profesional del Software - Clean Code (DRY)"
// ------------------------------------------------------------
// ESTE ARCHIVO EXISTE PORQUE HABÍA CÓDIGO DUPLICADO.
//
// Antes de este archivo, las mismas reglas estaban escritas en distintos
// servicios, cada una en su propia línea:
//
//   AuthService      -> strtolower(trim($data['email'])) + FILTER_VALIDATE_EMAIL   x3
//   MedicoService    -> strip_tags(trim(...)) + strlen(...) > 150 / 50 / 100
//   PacienteService  -> strip_tags(trim(...)) + strlen(...) > 150 / 50
//   PrescripcionSvc  -> strip_tags(trim(...)) + strlen(...) > 1000
//   CitaService      -> mb_substr(strip_tags(trim(...)), 0, 255/500)
//
// Veinte veces la misma idea, escrita de veinte maneras. Ese es el patrón
// que Clean llama duplicación, y el problema no es que se vea feo: es que
// cuando la regla cambia (por ejemplo, si mañana el DNI pasa a 8 dígitos
// obligatorios), hay que acordarse de cambiarla en veinte lugares. Basta
// con olvidar uno para que el sistema se comporte de dos formas según por
// dónde entre la petición. Esa es la raíz de muchos bugs.
//
// Con este archivo, la regla vive UNA vez y todos los servicios la usan.
//
// RESPONSABILIDAD ÚNICA: validar y sanear valores que llegan de afuera.
// No toca base de datos, no sabe de HTTP, no lanza respuestas: lanza
// InvalidArgumentException con el código HTTP como código de excepción, que
// es lo que los controladores ya traducen a una respuesta JSON. Esa
// independencia es lo que permite probarla sin base de datos ni servidor
// (ver tests/ValidadorTest.php).
//
// SOBRE EL ORDEN DE LAS OPERACIONES (importante):
// primero se sanea (strip_tags + trim) y DESPUÉS se valida. Si se valida
// antes, un nombre formado solo por etiquetas HTML ("<b></b>") pasa el
// control de "vacío" porque tiene caracteres, y recién después queda vacío.
// Ese orden invertido existía en MedicoService y se corrigió acá.
// ============================================================

class Validador
{
    /** Longitud máxima de un email (límite de la columna en la BD) */
    private const EMAIL_MAXIMO = 255;

    /** Longitud mínima y máxima de una contraseña (bcrypt procesa 72 bytes) */
    private const PASSWORD_MINIMO = 6;
    private const PASSWORD_MAXIMO = 72;

    /**
     * Normaliza un email y lo valida.
     *
     * Normalizar es parte de validar: "  Juan@Mail.COM " y "juan@mail.com"
     * son la misma persona, y si se guardaran distintos el login fallaría
     * contra un usuario que ya existe. Por eso el resultado devuelto es el
     * email normalizado, no el que llegó.
     *
     * @param mixed  $email    Email recibido (cualquier tipo)
     * @param string $mensaje  Mensaje de la excepción si es inválido
     * @param int    $estado   Código HTTP con el que se lanza la excepción
     * @return string Email normalizado y válido
     * @throws \InvalidArgumentException Si el email no es válido
     */
    public static function email(mixed $email, string $mensaje = 'Email inválido', int $estado = 422): string
    {
        // trim() quita espacios de los extremos; strtolower() pasa a
        // minúsculas. El cast a string y el "??" cubren los casos en que el
        // cliente manda null, un número o directamente nada.
        $normalizado = strtolower(trim((string) ($email ?? '')));

        // Las tres condiciones en una sola línea: vacío, formato inválido o
        // más largo de lo que la columna admite.
        // FILTER_VALIDATE_EMAIL devuelve el email o false si no cumple el
        // formato; por eso se usa con el signo de negación.
        if ($normalizado === '' || !filter_var($normalizado, FILTER_VALIDATE_EMAIL) || strlen($normalizado) > self::EMAIL_MAXIMO) {
            throw new \InvalidArgumentException($mensaje, $estado);
        }

        return $normalizado;
    }

    /**
     * Valida una contraseña.
     *
     * El máximo de 72 no es arbitrario: es el tamaño máximo que bcrypt
     * procesa. Una contraseña más larga NO es truncada por bcrypt, se
     * acepta y se considera solo la parte que el algoritmo alcanza a
     * ver, lo que en la práctica es aceptar menos caracteres de los que el
     * usuario cree. Rechazarla es más honesto que aceptarla a medias.
     *
     * @param mixed  $password Contraseña recibida
     * @param string $mensaje  Mensaje de la excepción
     * @param int    $estado   Código HTTP de la excepción
     * @return string La contraseña, sin modificar
     * @throws \InvalidArgumentException Si no cumple el rango
     */
    public static function password(
        mixed $password,
        string $mensaje = 'La contraseña debe tener entre 6 y 72 caracteres',
        int $estado = 422
    ): string {
        $valor = (string) ($password ?? '');
        if (strlen($valor) < self::PASSWORD_MINIMO || strlen($valor) > self::PASSWORD_MAXIMO) {
            throw new \InvalidArgumentException($mensaje, $estado);
        }
        return $valor;
    }

    /**
     * Sanea un texto: quita etiquetas HTML y espacios de los extremos.
     *
     * Esta es la operación que estaba repetida veinte veces. Devuelve SIEMPRE
     * un string, incluso si llegó null: así el resto del código no tiene que
     * estar defendiéndose de tipos.
     *
     * @param mixed $valor Texto de entrada
     * @return string Texto saneado
     */
    public static function textoLimpio(mixed $valor): string
    {
        // (string) y ?? juntos: null se convierte en '' en vez de romper el
        // trim(), que en PHP 8.1 ya no acepta null.
        // strip_tags() elimina <script>, <b> y cualquier otra etiqueta: es
        // defensa contra XSS para el dato que después se muestra en la web.
        return strip_tags(trim((string) ($valor ?? '')));
    }

    /**
     * Exige que un texto no esté vacío.
     *
     * @param mixed  $valor   Texto de entrada
     * @param string $mensaje Mensaje de la excepción
     * @param int    $estado  Código HTTP de la excepción
     * @return string El texto recibido, sin tocar
     * @throws \InvalidArgumentException Si está vacío
     */
    public static function obligatorio(mixed $valor, string $mensaje, int $estado = 422): string
    {
        // Se sanea antes de comprobar: si solo hay etiquetas HTML, el valor
        // saneado queda vacío y la validación hace lo que se espera.
        $limpio = self::textoLimpio($valor);
        if ($limpio === '') {
            throw new \InvalidArgumentException($mensaje, $estado);
        }
        return $limpio;
    }

    /**
     * Exige que un texto no supere cierta cantidad de caracteres.
     *
     * @param string  $valor   Texto ya saneado
     * @param int     $maximo  Cantidad máxima de caracteres
     * @param string  $mensaje Mensaje de la excepción
     * @param int     $estado  Código HTTP de la excepción
     * @return string El mismo texto, si cumple
     * @throws \InvalidArgumentException Si es demasiado largo
     */
    public static function longitudMaxima(string $valor, int $maximo, string $mensaje, int $estado = 422): string
    {
        // strlen() y no mb_strlen(): el límite protege la columna de la base
        // de datos, que cuenta BYTES. Con acentos un nombre de 100 letras
        // puede pesar más de 100 bytes, y es el byte lo que la columna corta.
        if (strlen($valor) > $maximo) {
            throw new \InvalidArgumentException($mensaje, $estado);
        }
        return $valor;
    }

    /**
     * Valida un texto obligatorio y su longitud, con un único mensaje.
     *
     * Es la forma más común ("no puede estar vacío y no puede superar N
     * caracteres"), y existe para que el llamador no tenga que repetir dos
     * llamadas con el mismo mensaje.
     *
     * @param mixed  $valor   Texto de entrada
     * @param int    $maximo  Longitud máxima
     * @param string $mensaje Mensaje para ambos casos
     * @param int    $estado  Código HTTP de la excepción
     * @return string Texto saneado y validado
     * @throws \InvalidArgumentException Si está vacío o es demasiado largo
     */
    public static function textoObligatorio(
        mixed $valor,
        int $maximo,
        string $mensaje,
        int $estado = 422
    ): string {
        $limpio = self::textoLimpio($valor);
        // Las dos reglas, con el mismo mensaje y el mismo código.
        self::obligatorio($limpio, $mensaje, $estado);
        return self::longitudMaxima($limpio, $maximo, $mensaje, $estado);
    }

    /**
     * Valida un texto opcional: si no viene, devuelve null.
     *
     * La diferencia con textoObligatorio es que un campo ausente NO es un
     * error, es "no se envía este registro". Por eso devuelve null en vez
     * de cadena vacía: el repositorio distingue así "dejar el campo como
     * estaba" de "borrar el contenido".
     *
     * @param mixed  $valor   Texto de entrada (puede venir null)
     * @param int    $maximo  Longitud máxima
     * @param string $mensaje Mensaje de la excepción si es demasiado largo
     * @param int    $estado  Código HTTP de la excepción
     * @return string|null Texto saneado, o null si no se envió
     * @throws \InvalidArgumentException Si viene y es demasiado largo
     */
    public static function textoOpcional(
        mixed $valor,
        int $maximo,
        string $mensaje,
        int $estado = 422
    ): ?string {
        // null significa "el campo no vino": no se valida ni se inventa nada.
        if ($valor === null) {
            return null;
        }
        return self::longitudMaxima(self::textoLimpio($valor), $maximo, $mensaje, $estado);
    }

    /**
     * Acota un texto a una cantidad de caracteres (sin error si la excede).
     *
     * A diferencia de longitudMaxima, acá el texto que excede se recorta. Se usa
     * donde el texto es una nota libre y perder el final es preferible a
     * rechazar la operación entera (el motivo de una consulta, por ejemplo).
     * Por eso usa mb_substr: cortar por BYTES a mitad de una palabra acentuada
     * deja el texto con un caracter roto.
     *
     * @param mixed $valor  Texto de entrada
     * @param int   $maximo Cantidad máxima de caracteres
     * @return string Texto saneado y acotado
     */
    public static function acortar(mixed $valor, int $maximo): string
    {
        return mb_substr(self::textoLimpio($valor), 0, $maximo);
    }

    /**
     * Acota un texto opcional a N caracteres (null si no viene).
     *
     * @param mixed $valor  Texto de entrada
     * @param int   $maximo Cantidad máxima de caracteres
     * @return string|null Texto saneado y acotado, o null
     */
    public static function acortarOpcional(mixed $valor, int $maximo): ?string
    {
        if ($valor === null) {
            return null;
        }
        return self::acortar($valor, $maximo);
    }
}
