<?php
// ============================================================
// controllers/AuthController.php - Controlador de Autenticación
// ============================================================
// Módulos aplicados: "Primera API en PHP" + "CRUD con Repository"
// Maneja peticiones HTTP de login, registro y autenticación.
// Clase del controlador de autenticación: recibe peticiones HTTP y delega en el servicio
class AuthController
{
    // Instancia del servicio de autenticación (capa de negocio), inyectada por constructor
    private AuthService $service;
    // Componente de rate limiting para controlar los intentos de login por IP
    private RateLimiter $rateLimiter;

    // Constructor: recibe por inyección de dependencias el servicio y el rate limiter
    public function __construct(AuthService $service, RateLimiter $rateLimiter)
    {
        // Asigna el servicio de autenticación a la propiedad del controlador
        $this->service = $service;
        // Asigna el rate limiter a la propiedad del controlador
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * POST /api/auth/registro - Registra un nuevo usuario
     */
    // Método que atiende la petición POST /api/auth/registro
    public function registro(): void
    {
        // Bloque try: intenta registrar al usuario y captura los errores que puedan surgir
        try {
            // Lee el cuerpo JSON de la petición y lo convierte en array asociativo; si falla o viene vacío usa []
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
            // Delega en el servicio la validación y la creación del nuevo usuario
            $usuario = $this->service->registro($data);
            // Responde con JSON consistente (helper Response) y código 201 Created
            Response::ok($usuario, 'Usuario registrado correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación de entrada (email inválido, duplicado, etc.)
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/auth/login - Autentica un usuario
     * Body: JSON con email y password
     * Response: Datos del usuario + token JWT
     *
     * Seguridad: rate limiting por IP (5 intentos / 15 min) para
     * mitigar fuerza bruta y credential stuffing.
     */
    // Método que atiende la petición POST /api/auth/login
    public function login(): void
    {
        // Obtiene la IP del cliente desde el servidor; si no existe, usa un valor neutro por defecto
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Construye una clave única por IP para identificarla dentro del rate limiter
        $clave = 'login:' . $ip;

        // Bloque try del proceso de login
        try {
            // Anti fuerza bruta: límite de intentos por ventana de tiempo
            // permitir() retorna false si la IP ya superó la cantidad máxima de intentos en la ventana
            if (!$this->rateLimiter->permitir($clave, 5, 900)) {
                // HTTP 429 Too Many Requests: se bloquea la IP por exceder 5 intentos en 900 segundos (15 min)
                Response::error('Demasiados intentos fallidos. Intentá nuevamente en 15 minutos.', 429);
            }

            // Lee el cuerpo JSON (email y password) y lo convierte en array; si falta, usa []
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            // Valida que el cliente haya enviado tanto el email como la contraseña
            if (empty($data['email']) || empty($data['password'])) {
                // HTTP 422: faltan datos obligatorios en el cuerpo de la petición
                Response::error('Email y contraseña son obligatorios', 422);
            }

            // Delega en el servicio la verificación de credenciales y la generación del token JWT
            $usuario = $this->service->login($data['email'], $data['password']);

            // Login exitoso → se limpian los contadores de la IP
            // Elimina el contador de intentos fallidos asociado a esta IP
            $this->rateLimiter->limpiar($clave);
            // Responde 200 OK con los datos del usuario y el token JWT generado
            Response::ok($usuario, 'Login exitoso');
        } catch (\InvalidArgumentException $e) {
            // Credenciales inválidas → se registra un intento fallido
            // Incrementa el contador de la IP: al llegar a 5 se dispara el bloqueo temporal
            $this->rateLimiter->registrar($clave);
            // Responde el error con código por defecto 401 Unauthorized (credenciales incorrectas)
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        } catch (\Exception $e) {
            // Captura errores internos imprevistos
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/auth/cambiar-contrasena
     * Cambia la contraseña de un usuario autenticado
     * Body: passwordActual y passwordNueva
     */
    // Método que atiende la petición POST /api/auth/cambiar-contrasena
    public function cambiarContrasena(): void
    {
        // Bloque try del cambio de contraseña
        try {
            // Lee el cuerpo JSON de la petición (passwordActual y passwordNueva)
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            // El usuario se resuelve por el token Bearer o por sesión
            // Intenta obtener el ID del JWT; si no hay token, lo toma de la sesión PHP
            $usuarioId = $this->obtenerIdDesdeToken() ?? ($_SESSION['usuario_id'] ?? null);
            // Valida que exista un usuario autenticado
            if (!$usuarioId) {
                // HTTP 401: la operación requiere autenticación previa
                Response::error('Usuario no autenticado', 401);
            }

            // Valida que el cliente haya enviado ambas contraseñas
            if (empty($data['passwordActual']) || empty($data['passwordNueva'])) {
                // HTTP 422: faltan campos obligatorios
                Response::error('Ambas contraseñas son obligatorias', 422);
            }

            // Delega en el servicio la verificación de la contraseña actual y la actualización
            $this->service->cambiarContrasena(
                (int)$usuarioId,
                $data['passwordActual'],
                $data['passwordNueva']
            );

            // Responde 200 OK confirmando el cambio de contraseña
            Response::ok(null, 'Contraseña cambiada correctamente');
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación (contraseña actual incorrecta → 401, nueva débil → 422)
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        } catch (\Exception $e) {
            // Captura errores internos imprevistos
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/auth/me - Obtiene los datos del usuario autenticado
     * Soporta autenticación por token Bearer o por sesión.
     */
    // Método que atiende la petición GET /api/auth/me
    public function obtenerUsuarioActual(): void
    {
        // Bloque try del endpoint "usuario actual"
        try {
            // Resuelve el ID del usuario a partir del JWT o, si no hay, desde la sesión PHP
            $usuarioId = $this->obtenerIdDesdeToken() ?? ($_SESSION['usuario_id'] ?? null);
            // Valida que exista un usuario autenticado
            if (!$usuarioId) {
                // HTTP 401: petición sin autenticación
                Response::error('Usuario no autenticado', 401);
            }

            // Pide al servicio los datos del usuario (se convierte el ID a entero)
            $usuario = $this->service->obtenerPorId((int)$usuarioId);
            // Responde 200 OK con los datos del usuario (sin contraseña)
            Response::ok($usuario);
        } catch (\RuntimeException $e) {
            // Captura el caso de usuario inexistente en la base de datos
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            // Captura token inválido o expirado
            Response::error($e->getMessage(), 401);
        } catch (\Exception $e) {
            // Captura errores internos imprevistos
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/auth/logout - Cierra la sesión del usuario
     */
    // Método que atiende la petición POST /api/auth/logout
    public function logout(): void
    {
        // Destruye la sesión PHP completa del usuario
        session_destroy();
        // Responde 200 OK confirmando el cierre de sesión
        Response::ok(null, 'Sesión cerrada correctamente');
    }

    /**
     * Extrae el ID de usuario desde el JWT ya verificado por el middleware.
     * El claim "sub" (subject) contiene el id del usuario autenticado.
     *
     * @return int|null ID del usuario, o null si no hay petición autenticada
     */
    // Método privado de apoyo: extrae el ID del usuario desde el JWT ya verificado
    private function obtenerIdDesdeToken(): ?int
    {
        // Obtiene el payload del JWT decodificado por el middleware (null si no hay token válido)
        $payload = AuthMiddleware::usuarioActual();
        // Lee el claim estándar "sub" (subject) que almacena el ID del usuario; si falta usa null
        $id = $payload['sub'] ?? null;

        // Verifica que el ID sea numérico (seguridad: evita aceptar valores no enteros)
        if (is_numeric($id)) {
            // Lo convierte a entero y lo retorna
            return (int) $id;
        }
        // Si no hay token o el ID no es numérico, devuelve null (usuario no autenticado)
        return null;
    }
}