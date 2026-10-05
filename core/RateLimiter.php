<?php
// ============================================================
// core/RateLimiter.php - Limitación de intentos (rate limiting)
// ============================================================
// Módulo: "Seguridad Básica para APIs - Fuerza bruta"
// ------------------------------------------------------------
// Mitiga fuerza bruta y credential stuffing limitando los
// intentos fallidos (ej: en POST /api/auth/login) por IP.
//
// Funcionamiento:
//   • registrar() suma un intento a la ventana de tiempo
//   • permitir() devuelve false si se superó el máximo o hay bloqueo
//   • limpiar() borra los intentos (ej: tras un login exitoso)
//
// Persistencia: archivos JSON en storage/rate/ (fuera del webroot).
// En producción se recomienda Redis o la base de datos.
// ============================================================

class RateLimiter
{
    // A partir de cuántos intentos acumulados se avisa por log. Un login
    // fallido aislado es DEBUG; lo que indica un ataque (o un límite mal
    // configurado) es la repetición. Con este número, el log dice "van 5"
    // una sola vez y no cinco veces.
    private const AVISO_CADA_INTENTOS = 5;

    // Directorio donde se guardan los archivos JSON con los contadores por clave/IP
    private string $dir;

    /**
     * @param string $dir Directorio donde se persisten los contadores
     */
    public function __construct(string $dir)
    {
        // Limpia la barra (/) final o la doble barra invertida (\\) de la ruta recibida
        $this->dir = rtrim($dir, '/\\');
        // Si el directorio no existe aún...
        if (!is_dir($this->dir)) {
            // Lo crea con permisos 0775 y de forma recursiva
            mkdir($this->dir, 0775, true);
        }
    }

    /**
     * ¿Se permite ejecutar la acción protegida?
     * Devuelve false si el cliente está bloqueado o superó el máximo.
     *
     * @param string $clave     Identificador (ej: "login:192.168.1.10")
     * @param int    $maxIntentos Máximo de aciertos permitidos en la ventana
     * @param int    $ventanaSegs  Duración de la ventana (en segundos)
     */
    public function permitir(string $clave, int $maxIntentos, int $ventanaSegs): bool
    {
        // Lee el estado actual (intentos y bloqueo) de la clave desde su archivo
        $datos = $this->leer($clave);
        // Timestamp actual en segundos, usado como referencia de la ventana de tiempo
        $ahora = time();

        // ¿Está bloqueado activamente?
        // (?? 0): si no existe la clave 'bloqueado_hasta' asume 0 (nunca bloqueado)
        if (($datos['bloqueado_hasta'] ?? 0) > $ahora) {
            // Aún está bloqueado: se deniega la acción.
            // WARN, no ERROR: el sistema está haciendo exactamente lo que
            // debe. El registro sirve para ver ataques de fuerza bruta en
            // curso (muchos WARN de la misma clave) y para detectar una
            // clave que se bloquea de forma legítima y que el usuario
            // reporta como "no me deja entrar".
            Logger::warn('petición bloqueada por rate limit (bloqueo activo)', $this->contexto($clave, $maxIntentos, $ventanaSegs));

            // Se deniega la acción
            return false;
        }

        // Quita intentos que ya salieron de la ventana
        // array_filter(): conserva solo los timestamps dentro de la ventana; array_values() reindexa
        $datos['intentos'] = array_values(array_filter(
            // El arreglo de timestamps de intentos previos
            $datos['intentos'],
            // Closure (función anónima) que decide qué intentos conservar
            function (int $t) use ($ahora, $ventanaSegs) {
                // Conserva solo si el intento ocurrió hace menos de $ventanaSegs segundos
                return $t > ($ahora - $ventanaSegs);
            }
        ));

        // Si ya alcanzó el máximo → bloquea por una ventana completa
        // count(): cuenta cuántos intentos quedaron dentro de la ventana
        if (count($datos['intentos']) >= $maxIntentos) {
            // Fija el fin del bloqueo: una ventana completa a partir de ahora
            $datos['bloqueado_hasta'] = $ahora + $ventanaSegs;
            // Persiste el bloqueo en el archivo de la clave
            $this->guardar($clave, $datos);

            // Se superó el máximo: se deniega la acción y se deja el
            // registro de que el bloqueo se acaba de disparar, que es el
            // dato útil para ver si el límite elegido es razonable.
            Logger::warn('petición bloqueada por rate limit (máximo alcanzado)', $this->contexto($clave, $maxIntentos, $ventanaSegs));

            return false;
        }

        // No superó el máximo: se permite ejecutar la acción
        return true;
    }

    /**
     * Registra un intento (fallido) para la clave.
     */
    public function registrar(string $clave): void
    {
        // Lee el estado actual de la clave
        $datos = $this->leer($clave);
        // Agrega un nuevo intento (fallido) con el timestamp actual al final del arreglo
        $datos['intentos'][] = time();
        // Guarda el estado actualizado en el archivo
        $this->guardar($clave, $datos);

        // Solo se registra cuando se alcanza el límite, no en cada intento:
        // un login fallido aislado es DEBUG, no WARN. Este es el criterio
        // para que el log sea útil: ruido de menos, no de más.
        if (count($datos['intentos']) >= self::AVISO_CADA_INTENTOS) {
            Logger::warn('intentos fallidos acumulados', [
                'clave'   => $this->huella($clave),
                'intentos' => count($datos['intentos']),
            ]);
        }
    }

    /**
     * Limpia los contadores (ej: login exitoso o verificación de captcha).
     */
    public function limpiar(string $clave): void
    {
        // Calcula la ruta del archivo asociado a la clave
        $archivo = $this->ruta($clave);
        // Si el archivo de contadores existe...
        if (is_file($archivo)) {
            // Lo elimina (@ suprime el aviso de error si el archivo ya no existe al momento de borrar)
            @unlink($archivo);
        }
    }

    /**
     * Ruta del archivo seguro para una clave (hash de la clave).
     */
    private function ruta(string $clave): string
    {
        return $this->dir . '/' . hash('sha256', $clave) . '.json';
    }

    /**
     * Huella corta de la clave, para poder escribirla en el log.
     *
     * La clave real suele ser algo como "login:192.168.1.10": contiene la IP
     * del cliente, que es dato personal. Lo que hace falta en el log es poder
     * agrupar ("todos estos bloqueos son de la misma clave"), y para eso
     * alcanza con una huella. Se cortan los primeros 12 caracteres del hash
     * porque el completo ocupa 64 y no aporta nada más.
     *
     * @param string $clave Clave original
     * @return string Huella corta, por ejemplo "a1b2c3d4e5f6"
     */
    private function huella(string $clave): string
    {
        return substr(hash('sha256', $clave), 0, 12);
    }

    /**
     * Contexto de un bloqueo, para el log.
     *
     * @param string $clave        Clave original (no se escribe: se escribe la huella)
     * @param int    $maxIntentos  Límite configurado
     * @param int    $ventanaSegs  Ventana configurada
     * @return array
     */
    private function contexto(string $clave, int $maxIntentos, int $ventanaSegs): array
    {
        return [
            // La huella, no la clave: permite correlacionar sin exponer la IP.
            'clave'       => $this->huella($clave),
            'max_intentos' => $maxIntentos,
            'ventana_s'   => $ventanaSegs,
            // El intento actual se cuenta como uno más: así el log dice
            // "intento 6 de 5" y no obliga a abrir el archivo JSON para
            // entenderlo.
            'intento'     => count($this->leer($clave)['intentos']) + 1,
            'peticion'    => Peticion::atributos(),
        ];
    }

    /**
     * Lee el estado actual de la clave.
     */
    private function leer(string $clave): array
    {
        // Calcula la ruta del archivo de estado de la clave
        $archivo = $this->ruta($clave);
        // Si ya existe un archivo guardado para esta clave...
        if (is_file($archivo)) {
            // Lee el archivo y decodifica su JSON a un arreglo asociativo (true)
            $datos = json_decode((string) file_get_contents($archivo), true);
            // Si el JSON era válido y devolvió un arreglo...
            if (is_array($datos)) {
                // Devuelve el estado decodificado
                return $datos;
            }
        }
        // Estado inicial: sin intentos y sin bloqueo, si no existía archivo o estaba corrupto
        return ['intentos' => [], 'bloqueado_hasta' => 0];
    }

    /**
     * Persiste el estado de la clave.
     */
    private function guardar(string $clave, array $datos): void
    {
        // Escribe el estado como JSON en el archivo de la clave; LOCK_EX evita escrituras simultáneas
        file_put_contents($this->ruta($clave), json_encode($datos), LOCK_EX);
    }
}