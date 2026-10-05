<?php
// ============================================================
// core/AuthMiddleware.php - Middleware de autenticación (JWT)
// ============================================================
// Módulo: "Autenticación en Aplicaciones Modernas"
// ------------------------------------------------------------
// Separa dos conceptos:
//   • Autenticación (¿quién eres?)  → verificarToken()   → 401 si falla
//   • Autorización (¿qué puedes hacer?) → requireRol()    → 403 si no aplica
//
// El token viaja en:  Authorization: Bearer <jwt>
// ============================================================

class AuthMiddleware
{
    // Servicio JWT inyectado: se usa para verificar firmas y expiraciones
    private JwtService $jwt;

    // Payload del último token verificado (contexto de la petición)
    private static array $usuarioActual = [];

    public function __construct(JwtService $jwt)
    {
        // Guarda el servicio JWT recibido para usarlo en toda la clase
        $this->jwt = $jwt;
    }

    /**
     * Verifica el token JWT del header Authorization.
     * Si es válido guarda el payload y lo devuelve.
     * Si falta, es inválido o expiró: responde 401 y detiene la ejecución.
     *
     * @return array Payload decodificado (sub, rol, email, exp, ...)
     */
    public function verificarToken(): array
    {
        // Extrae el token del header "Authorization: Bearer <jwt>"
        $token = $this->extraerToken();

        // Si no venía ningún token en la petición...
        if ($token === null) {
            // WARN: no es un ERROR porque el sistema funcionó correctamente.
            // Es un cliente que llama a un endpoint protegido sin
            // credenciales, y esa información sirve para detectar clientes
            // mal configurados, no para detenerse tres veces por minuto.
            Logger::warn('petición a endpoint protegido sin token', [
                'peticion' => Peticion::atributos(),
                // El motivo del rechazo se registra; el token NUNCA.
            ]);

            // Responde 401 y detiene la ejecución (Response::error() hace exit)
            Response::error('Token no proporcionado', 401);
        }

        // Intenta validar el token (seguridad: firma y expiración)
        try {
            // Verifica el token; si es inválido o expiró, lanza una excepción
            $payload = $this->jwt->verificar($token);
            // Guarda el payload como contexto de la petición actual
            self::$usuarioActual = $payload;
            // Devuelve el payload para que el controlador sepa quién es el usuario
            return $payload;
        } catch (\Exception $e) {
            // Token manipulado, mal formado o expirado.
            // WARN con el motivo técnico de fondo (el mensaje de la
            // excepción dice si fue firma o expiración) y NUNCA con el
            // token: un log con el token es una credencial escrita en disco.
            Logger::warn('token inválido o expirado: ' . $e->getMessage(), [
                'peticion' => Peticion::atributos(),
            ]);

            // El cliente recibe el mismo mensaje de siempre (401), sin
            // detalles: distinguir "expiró" de "firmado mal" en la respuesta
            // ayudaría a un atacante a adivinar.
            Response::error('Token inválido o expirado', 401);
        }
    }

    /**
     * Autorización por rol (RBAC). Se invoca DESPUÉS de verificarToken().
     * Si el rol del usuario no está en la lista, responde 403 Forbidden.
     *
     * @param array $payload Payload devuelto por verificarToken()
     * @param array $roles   Roles permitidos (ej: ['admin'])
     */
    public function requireRol(array $payload, array $roles): void
    {
        // Lee el rol del usuario del payload; si no existe, usa '' (vacío)
        $rol = $payload['rol'] ?? '';
        // Si el rol del usuario no está en la lista de roles permitidos...
        // in_array con true = comparación estricta (mismo tipo y valor)
        if (!in_array($rol, $roles, true)) {
            // WARN: el token era válido, pero el usuario intentó una acción
            // fuera de su permiso. Esto SÍ es una señal valiosa: un paciente
            // que llama a un endpoint de administración puede ser un cliente
            // con un bug o un intento de escalada de privilegios.
            Logger::warn('acceso denegado por rol', [
                'rol_actual'   => $rol,
                'roles_permitidos' => $roles,
                'usuario_id'   => $payload['sub'] ?? null,
                'peticion'     => Peticion::atributos(),
            ]);

            // Responde 403 Forbidden y detiene la ejecución
            Response::error('Forbidden: no tenés permisos para realizar esta acción', 403);
        }
    }

    /**
     * Payload del usuario autenticado en la petición actual.
     */
    public static function usuarioActual(): array
    {
        // Devuelve el payload del usuario autenticado en esta petición
        return self::$usuarioActual;
    }

    /**
     * Extrae el token del header "Authorization: Bearer <token>".
     */
    private function extraerToken(): ?string
    {
        // Intenta leer el header Authorization desde PHP; en Apache a veces llega con prefijo REDIRECT_
        // El operador ?? devuelve el primer valor que exista; si ninguno, queda '' (vacío)
        $cabecera = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        // En Apache/PHP como módulo a veces el header no llega a $_SERVER
        // Entonces se reintenta con getallheaders() (si la función existe)
        if ($cabecera === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            // Busca el header Authorization, probando con distinta capitalización
            $cabecera = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        // Expresión regular que busca "Bearer <token>"; \s+ separa y (.+) captura el token
        if (preg_match('/Bearer\s+(.+)/i', $cabecera, $coincidencias)) {
            // trim(): quita espacios en blanco externos del token capturado
            return trim($coincidencias[1]);
        }

        // No tenía el formato "Bearer ...": devuelve null (token ausente)
        return null;
    }
}