<?php
// ============================================================
// services/AuthService.php - Servicio de Autenticación
// ============================================================
// Maneja lógica de login, registro y validación de credenciales.
// También valida el login SSO (Google / Microsoft): verifica el
// id_token contra las claves públicas del proveedor y emite el JWT
// propio de SaludWEB (misma firma que el login por contraseña).
// Librería firebase/php-jwt: firma/verifica JWT y convierte JWKS.
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\JWK;

// Clase del servicio de autenticación: contiene la lógica de negocio del módulo de accesos
class AuthService
{
    // Conexión PDO a la base de datos (inyectada por constructor)
    private $pdo;
    // Servicio de generación de tokens JWT (inyectado por constructor)
    private JwtService $jwt;

    /**
     * Constructor
     * @param PDO $pdo Conexión a la base de datos
     * @param JwtService $jwt Servicio de tokens JWT (inyectado)
     */
    // Constructor con inyección de dependencias
    public function __construct(PDO $pdo, JwtService $jwt)
    {
        // Guarda la conexión a la base de datos en la propiedad
        $this->pdo = $pdo;
        // Guarda el servicio de JWT en la propiedad
        $this->jwt = $jwt;
    }

    /**
     * Registra un nuevo usuario
     * VALIDA que el email no exista
     * VALIDA que la contraseña sea segura
     * CODIFICA la contraseña con bcrypt
     * @param array $data Datos del usuario (email, password, nombre, tipo_usuario)
     * @return array Datos del usuario creado (sin contraseña)
     * @throws InvalidArgumentException Si hay error de validación
     */
    public function registro(array $data): array
    {
        // Validar email obligatorio, formato y longitud máxima
        // Normaliza el email: pasa a minúsculas y elimina espacios exteriores (?? '' evita error si falta la clave)
        $email = strtolower(trim($data['email'] ?? ''));
        // Valida que el email no esté vacío y tenga formato válido (FILTER_VALIDATE_EMAIL)
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Lanza excepción de validación con código HTTP 422 (Unprocessable Entity)
            throw new \InvalidArgumentException('Email inválido', 422);
        }
        // Valida la longitud máxima del email para no superar la columna de la tabla
        if (strlen($email) > 255) {
            // Lanza excepción de validación con código HTTP 422
            throw new \InvalidArgumentException('Email inválido', 422);
        }

        // Validar nombre obligatorio + sanitizar (XSS) y limitar longitud
        // strip_tags() elimina etiquetas HTML/JS del nombre (mitiga XSS); trim() limpia espacios extremos
        $nombre = strip_tags(trim($data['nombre'] ?? ''));
        // Valida que el nombre no esté vacío y no supere los 100 caracteres
        if (empty($nombre) || strlen($nombre) > 100) {
            // Lanza excepción de validación con código HTTP 422
            throw new \InvalidArgumentException('El nombre es obligatorio (máx. 100 caracteres)', 422);
        }

        // Validar contraseña (mín. 6, máx. 72 por límite de bcrypt)
        // Lee la contraseña enviada, o cadena vacía si el campo no viene
        $password = $data['password'] ?? '';
        // Valida el largo mínimo (6) y máximo (72, límite de bytes procesados por bcrypt)
        if (strlen($password) < 6 || strlen($password) > 72) {
            // Lanza excepción de validación con código HTTP 422
            throw new \InvalidArgumentException('La contraseña debe tener entre 6 y 72 caracteres', 422);
        }

        // Verificar que el email no exista
        // Prepara una consulta parametrizada (protege contra inyección SQL)
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        // Ejecuta la consulta pasando el email como parámetro
        $stmt->execute([$email]);
        // Si fetch() devuelve una fila, el email ya está registrado
        if ($stmt->fetch()) {
            // Impide duplicar cuentas con el mismo email: HTTP 422
            throw new \InvalidArgumentException('El email ya está registrado', 422);
        }

        // Codificar la contraseña con bcrypt (algoritmo seguro)
        // password_hash() crea un hash irreversible de la contraseña
        // Genera el hash bcrypt: la contraseña nunca se guarda en texto plano
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Tipo de usuario (por defecto "paciente")
        // Lee el tipo de usuario enviado; si no viene, el valor por defecto es "paciente"
        $tipoUsuario = $data['tipo_usuario'] ?? 'paciente';
        // Lista blanca (whitelist): solo se admiten estos roles
        if (!in_array($tipoUsuario, ['paciente', 'medico', 'admin'])) {
            // Si el rol no es válido, se fuerza uno seguro por defecto
            $tipoUsuario = 'paciente';
        }

        // --------------------------------------------------
        // Vínculo con la ficha: SIEMPRE nulo en el registro
        // --------------------------------------------------
        // Antes este endpoint aceptaba id_paciente e id_medico del body, y
        // los guardaba sin validar. Eso era un agujero: cualquiera que se
        // registrara podía vincular su cuenta con la ficha de otro paciente y
        // ver o cancelar sus turnos.
        //
        // Ahora el vínculo se hace en un paso aparte y verificado
        // (POST /api/auth/vincular, con DNI o matrícula). Es más seguro y
        // además tiene sentido de negocio: la cuenta se crea al instante,
        // pero el vínculo con la ficha lo confirma quien la cargó.
        $idPaciente = null;
        $idMedico = null;

        // Insertar el nuevo usuario
        // Prepara el INSERT parametrizado (anti inyección SQL); "activo" se fija en 1
        $stmt = $this->pdo->prepare(
            "INSERT INTO usuarios (email, password, nombre, tipo_usuario, id_paciente, id_medico, activo)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        // Ejecuta el INSERT con los valores ya validados y sanitizados
        $stmt->execute([
            $email,
            $passwordHash,
            $nombre,
            $tipoUsuario,
            $idPaciente,
            $idMedico
        ]);

        // Obtiene el ID autogenerado por MySQL para el registro recién insertado
        $userId = (int) $this->pdo->lastInsertId();

        // Devolver el usuario creado (sin contraseña)
        // Devuelve los datos del usuario recién creado (sin exponer el hash de la contraseña)
        return $this->obtenerPorId($userId);
    }

    /**
     * Autentica un usuario (login)
     * VALIDA el email y contraseña
     * GENERA un JWT firmado con firebase/php-jwt
     * @param string $email Email del usuario
     * @param string $password Contraseña en texto plano
     * @return array Datos del usuario + token JWT
     * @throws InvalidArgumentException Si credenciales son inválidas
     */
    public function login(string $email, string $password): array
    {
        // Validar que el email sea válido (y no exceder longitud razonable)
        // Normaliza el email (minúsculas y sin espacios extremos)
        $email = strtolower(trim($email));
        // Valida formato, presencia y longitud máxima del email en una sola condición
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            // Mensaje genérico (no revela si el email existe) con código 401 Unauthorized
            throw new \InvalidArgumentException('Email o contraseña inválidos', 401);
        }

        // Buscar el usuario por email
        // Consulta parametrizada que además solo trae usuarios con activo = 1
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        // Ejecuta la consulta con el email como parámetro
        $stmt->execute([$email]);
        // Obtiene la fila como array asociativo (o false si no existe)
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si el usuario no existe, se lanza el mismo error genérico (evita enumerar usuarios válidos)
        if (!$usuario) {
            // HTTP 401: credenciales inválidas
            throw new \InvalidArgumentException('Email o contraseña inválidos', 401);
        }

        // Verificar la contraseña usando password_verify()
        // Compara el hash almacenado con la contraseña ingresada
        // password_verify() compara el hash bcrypt guardado contra la contraseña en texto plano
        if (!password_verify($password, $usuario['password'])) {
            // HTTP 401: la contraseña no coincide con el hash almacenado
            throw new \InvalidArgumentException('Email o contraseña inválidos', 401);
        }

        // Deja al usuario autenticado en sesión (para clientes que usan cookies)
        // Almacena el ID del usuario en la sesión PHP (autenticación por cookies)
        $_SESSION['usuario_id'] = $usuario['id'];

        // Generar un JWT real: claims estándar + rol (nunca la contraseña)
        // Delega en el servicio JWT la generación del token firmado
        $emitido = $this->jwt->generar($usuario);

// Devolver usuario sin contraseña + token JWT
    // Construye el array de respuesta: datos del usuario + token y su vencimiento en formato ISO 8601
    return [
        'id' => (int) $usuario['id'],
        'email' => $usuario['email'],
        'nombre' => $usuario['nombre'],
        'tipo_usuario' => $usuario['tipo_usuario'],
        'token' => $emitido['token'],
        'expires_at' => date('c', $emitido['expires_at']),
    ];
    }

    /**
     * Login con SSO (Google / Microsoft)
     * VALIDA la firma del id_token contra las claves públicas del proveedor,
     * BUSCA la cuenta local por email (solo cuentas existentes y activas) y
     * EMITE un JWT de SaludWEB (misma firma que el login por contraseña).
     * @param string $provider 'google' | 'microsoft'
     * @param string $idToken  JWT de identidad emitido por el proveedor
     * @return array Datos del usuario + token JWT
     * @throws InvalidArgumentException Si el token es inválido o no hay cuenta local
     */
    public function loginSso(string $provider, string $idToken): array
    {
        // Estructura de un JWT: header.payload.firma (exactamente dos puntos)
        if ($idToken === '' || substr_count($idToken, '.') !== 2) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Valida firma y claims contra el proveedor; devuelve los claims verificados
        $claims = $this->validarIdToken($provider, $idToken);
        // El email es la llave que une la cuenta externa con la cuenta local de SaludWEB
        $email = strtolower(trim($claims['email'] ?? ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El proveedor no devolvió un email válido', 401);
        }

        // Buscar la cuenta local: SSO habilita SOLO cuentas existentes y activas
        // (regla de negocio: no se auto-crean cuentas admin/medico desde afuera)
        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) {
            throw new \InvalidArgumentException(
                'No existe una cuenta de SaludWEB con ese email. Usá tu email y contraseña.',
                401
            );
        }

        // Deja la sesión PHP abierta (compatibilidad con clientes por cookies)
        $_SESSION['usuario_id'] = $usuario['id'];
        // Emite el JWT de SaludWEB con el MISMO servicio que el login por contraseña
        $emitido = $this->jwt->generar($usuario);

        // Respuesta idéntica a login(): el cliente se autentica con el campo "token"
        return [
            'id' => (int) $usuario['id'],
            'email' => $usuario['email'],
            'nombre' => $usuario['nombre'],
            'tipo_usuario' => $usuario['tipo_usuario'],
            'token' => $emitido['token'],
            'expires_at' => date('c', $emitido['expires_at']),
        ];
    }

    // Delega la validación del token al proveedor correspondiente.
    private function validarIdToken(string $provider, string $idToken): array
    {
        if ($provider === 'google') {
            // Google: certificados X.509 públicos (mapa kid => certificado PEM)
            return $this->validarGoogle($idToken);
        }
        if ($provider === 'microsoft') {
            // Microsoft Entra ID: claves públicas RSA desde el JWKS del tenant
            return $this->validarMicrosoft($idToken);
        }
        throw new \InvalidArgumentException('Proveedor de SSO no soportado', 422);
    }

    // Valida un id_token de Google contra los certificados públicos de Google.
    private function validarGoogle(string $idToken): array
    {
        // Client ID esperado (claim "aud"). Viene de variable de entorno;
        // si está vacío, el SSO de Google está desactivado en el servidor.
        $clientId = Config::get('SSO_GOOGLE_CLIENT_ID');
        if ($clientId === '') {
            throw new \InvalidArgumentException('SSO de Google no está configurado en el servidor', 501);
        }
        // Certificados públicos de Google (mapa kid => certificado X.509 en PEM)
        $certificados = $this->httpGetJson('https://www.googleapis.com/oauth2/v1/certs');
        if (!is_array($certificados) || empty($certificados)) {
            throw new \RuntimeException('No se pudieron obtener las claves públicas de Google', 503);
        }
        // El header del token indica qué kid (clave) lo firmó
        $header = $this->obtenerHeader($idToken);
        $kid = $header['kid'] ?? '';
        if ($kid === '' || !isset($certificados[$kid])) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Convierte el certificado X.509 a la clave pública RSA (formato PEM)
        $publicKey = $this->certificadoPublico($certificados[$kid]);
        if ($publicKey === '') {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Decodifica y VERIFICA la firma, además de exp/nbf (lanza si fue manipulado o vencido)
        $payload = (array) JWT::decode($idToken, new Key($publicKey, 'RS256'));
        // Valida el emisor: solo tokens emitidos por Google se aceptan
        if (!in_array($payload['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Valida la audiencia: el token tiene que haber sido emitido PARA este client_id
        if (($payload['aud'] ?? '') !== $clientId) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Exige email verificado: evita usar cuentas de Google sin validar
        if (($payload['email_verified'] ?? false) !== true) {
            throw new \InvalidArgumentException('El email de la cuenta de Google no está verificado', 401);
        }
        return $payload;
    }

    // Valida un id_token de Microsoft Entra ID contra el JWKS del tenant configurado.
    private function validarMicrosoft(string $idToken): array
    {
        // Client ID esperado (claim "aud"). Vacío = SSO de Microsoft desactivado.
        $clientId = Config::get('SSO_MICROSOFT_CLIENT_ID');
        if ($clientId === '') {
            throw new \InvalidArgumentException('SSO de Microsoft no está configurado en el servidor', 501);
        }
        // Tenant: 'common' permite cuentas personales de Microsoft y corporativas (por defecto)
        $tenant = Config::get('SSO_MICROSOFT_TENANT', 'common');
        $emisorEsperado = "https://login.microsoftonline.com/{$tenant}/v2.0";
        // Descubrimiento OpenID Connect: de ahí se obtiene la URL del JWKS del tenant
        $discovery = $this->httpGetJson(
            "https://login.microsoftonline.com/{$tenant}/v2.0/.well-known/openid-configuration"
        );
        if (!is_array($discovery) || empty($discovery['jwks_uri'])) {
            throw new \RuntimeException('No se pudo obtener la configuración de Microsoft', 503);
        }
        // JWKS: el conjunto de claves públicas RSA del tenant (Microsoft las rota)
        $jwks = $this->httpGetJson($discovery['jwks_uri']);
        if (!is_array($jwks) || empty($jwks['keys'])) {
            throw new \RuntimeException('No se pudieron obtener las claves públicas de Microsoft', 503);
        }
        // firebase/php-jwt convierte el JWKS a un set de claves (elige por "kid" al decodificar)
        $keySet = JWK::parseKeySet($jwks);
        // Decodifica y VERIFICA la firma + expiración automáticamente
        $payload = (array) JWT::decode($idToken, $keySet);
        // Valida el emisor exacto del tenant configurado
        if (($payload['iss'] ?? '') !== $emisorEsperado) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        // Valida que el token fue emitido para ESTE client_id (no para otra app)
        if (($payload['aud'] ?? '') !== $clientId) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        return $payload;
    }

    // Decodifica únicamente el HEADER del token (sin verificar) para conocer el kid.
    private function obtenerHeader(string $idToken): array
    {
        $partes = explode('.', $idToken);
        // El JWT usa base64url: se restaura "+" y "/" antes de decodificar
        $jsonHeader = base64_decode(strtr($partes[0], '-_', '+/'));
        $header = json_decode($jsonHeader, true);
        if (!is_array($header)) {
            throw new \InvalidArgumentException('Token de SSO inválido', 401);
        }
        return $header;
    }

    // Convierte un certificado X.509 (PEM) en su clave pública RSA (PEM).
    private function certificadoPublico(string $certificadoPem): string
    {
        // openssl_x509_read valida el certificado y permite extraer la clave pública
        $cert = openssl_x509_read($certificadoPem);
        if ($cert === false) {
            return ''; // Certificado ilegible: no se puede verificar la firma
        }
        // Extrae la clave pública del certificado
        $clave = openssl_pkey_get_public($cert);
        // Los detalles de la clave incluyen "key": la clave pública en formato PEM
        $detalles = openssl_pkey_get_details($clave);
        // Devuelve la clave pública PEM (o '' si no está disponible)
        return $detalles['key'] ?? '';
    }

    // GET simple hacia una URL que responde JSON (con timeout y User-Agent propio).
    private function httpGetJson(string $url): ?array
    {
        // Inicializa una sesión cURL contra la URL indicada
        $ch = curl_init($url);
        // Opciones: devolver el cuerpo, timeout de 10 s y seguir redirecciones
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, // Devuelve el contenido en vez de imprimirlo
            CURLOPT_TIMEOUT => 10,          // No esperar más de 10 segundos
            CURLOPT_FOLLOWLOCATION => true, // Seguir redirecciones (http -> https)
            CURLOPT_USERAGENT => 'SaludWEB-SSO/1.0', // Identificar este cliente HTTP
        ]);
        $cuerpo = curl_exec($ch);                 // Ejecuta la petición
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); // Código HTTP resultante
        curl_close($ch);                          // Libera los recursos de la sesión cURL
        // Solo se aceptan respuestas 2xx; cualquier otra equivale a "sin claves"
        if ($status < 200 || $status >= 300 || $cuerpo === false) {
            return null;
        }
        // Convierte el JSON de la respuesta a un array asociativo
        return json_decode($cuerpo, true);
    }

    /**
     * Obtiene un usuario por ID
     * @param int $id ID del usuario
     * @return array Datos del usuario (sin contraseña)
     * @throws RuntimeException Si no existe
     */
    public function obtenerPorId(int $id): array
    {
        // Prepara el SELECT parametrizado (nunca se incluye la columna de contraseña)
        $stmt = $this->pdo->prepare(
            "SELECT id, email, nombre, tipo_usuario, id_paciente, id_medico, activo, creado_at
             FROM usuarios WHERE id = ?"
        );
        // Ejecuta la consulta con el ID como parámetro
        $stmt->execute([$id]);
        // Obtiene la fila como array asociativo
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no hay fila, el usuario no existe
        if (!$usuario) {
            // Excepción en tiempo de ejecución con código 404 Not Found
            throw new \RuntimeException('Usuario no encontrado', 404);
        }

        // Devuelve los datos del usuario (sin contraseña) para exponerlos en la API
        return $usuario;
    }

    /**
     * Obtiene un usuario por email
     * @param string $email Email del usuario
     * @return array|null Datos del usuario o null
     */
    public function obtenerPorEmail(string $email): ?array
    {
        // Prepara el SELECT parametrizado (sin exponer la contraseña)
        $stmt = $this->pdo->prepare(
            "SELECT id, email, nombre, tipo_usuario, id_paciente, id_medico, activo, creado_at
             FROM usuarios WHERE email = ?"
        );
        // Ejecuta la consulta con el email como parámetro
        $stmt->execute([$email]);
        // Devuelve el resultado o null si no existe (el operador ?: convierte false en null)
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cambia la contraseña de un usuario
     * @param int $id ID del usuario
     * @param string $passwordActual Contraseña actual
     * @param string $passwordNueva Nueva contraseña
     * @return bool true si se cambió
     * @throws InvalidArgumentException Si hay error
     */
    public function cambiarContrasena(int $id, string $passwordActual, string $passwordNueva): bool
    {
        // Obtener usuario
        // Consulta parametrizada que trae solo el hash de la contraseña del usuario
        $stmt = $this->pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        // Ejecuta la consulta con el ID como parámetro
        $stmt->execute([$id]);
        // Obtiene la fila (o false si el usuario no existe)
        $row = $stmt->fetch();

        // Si no hay usuario con ese ID, se lanza excepción
        if (!$row) {
            // HTTP 404: usuario no encontrado
            throw new \RuntimeException('Usuario no encontrado', 404);
        }

        // Verificar contraseña actual
        // Compara la contraseña actual ingresada contra el hash almacenado
        if (!password_verify($passwordActual, $row['password'])) {
            // HTTP 401: la contraseña actual no coincide con el hash guardado
            throw new \InvalidArgumentException('Contraseña actual incorrecta', 401);
        }

        // Validar nueva contraseña
        // Valida el largo mínimo de la nueva contraseña
        if (strlen($passwordNueva) < 6) {
            // HTTP 422: la nueva contraseña no cumple los requisitos
            throw new \InvalidArgumentException('La nueva contraseña debe tener al menos 6 caracteres', 422);
        }

        // Actualizar contraseña
        // Genera el nuevo hash bcrypt de la contraseña
        $newHash = password_hash($passwordNueva, PASSWORD_BCRYPT);
        // Prepara el UPDATE parametrizado (solo se modifica la contraseña del ID indicado)
        $stmt = $this->pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        // Ejecuta la actualización y retorna true si se afectó al menos una fila
        return $stmt->execute([$newHash, $id]);
    }

    /**
     * Arma el contexto de la petición a partir del token ya verificado.
     *
     * MOTIVO DE EXISTIR (módulo "Sistema de gestión de citas online"):
     * el JWT lleva 'sub' (id del usuario), 'rol' y 'email', pero NO lleva
     * id_paciente ni id_medico. Esos vínculos viven en la tabla usuarios y
     * hacen falta para autorizar sobre datos clínicos ("¿este turno es de
     * este paciente?"). Centralizarlo acá evita que cada controlador haga
     * su propia consulta y, sobre todo, evita que uno se olvide de validar.
     *
     * @return array ['rol', 'id_usuario', 'id_paciente', 'id_medico']
     * @throws RuntimeException Si el token no se verificó o el usuario no existe
     */
    public function contextoDePeticion(): array
    {
        // Se toma el payload que AuthMiddleware::verificarToken() ya validó
        $payload = AuthMiddleware::usuarioActual();

        // Si está vacío, significa que la ruta no pasó por verificarToken():
        // es un error de ruteo, no del usuario. 401 igual, para no filtrar.
        if (empty($payload)) {
            throw new \RuntimeException('Sesión no verificada', 401);
        }

        // Todo el trabajo real está en el método de abajo. Acá solo se le pasa
        // el payload del middleware.
        return $this->contextoDesdePayload($payload);
    }

    /**
     * Arma el contexto a partir de un payload YA verificado, sin depender del
     * middleware.
     *
     * POR QUÉ HACE FALLA ESTE SEGUNDO MÉTODO
     * ---------------------------------------
     * No es una comodidad, es la solución a un problema concreto: el canal de
     * tiempo real (SSE) verifica el token por su cuenta porque EventSource no
     * puede mandar el encabezado Authorization, así que esa ruta nunca pasa por
     * AuthMiddleware::verificarToken() y por lo tanto no deja nada en el
     * contexto estático. Si RealtimeController llamara a contextoDePeticion(),
     * recibiría "Sesión no verificada" con un token perfectamente válido.
     *
     * Hay dos caminos que NO son dumping en esta arquitectura:
     *   • Meter el payload del query dentro de AuthMiddleware (un setter).
     *     Funciona, pero es un canal lateral: cualquier endpoint podría
     *     escribir el contexto y saltarse la verificación. Un middleware existe
     *     justamente para que eso no sea posible.
     *   • Esta forma: quien YA tiene el payload verificado en la mano pide
     *     explícitamente el contexto. La diferencia es que hay que tener el
     *     token delante para poder llamar, y no alcanza con que alguien lo
     *     haya dejado escrito en un lugar compartido.
     *
     * @param array $payload Payload ya validado por JwtService
     * @return array ['rol', 'id_usuario', 'id_paciente', 'id_medico']
     */
    public function contextoDesdePayload(array $payload): array
    {
        // Se resuelve el usuario una sola vez para traer los vínculos
        $usuario = $this->obtenerPorId((int)($payload['sub'] ?? 0));

        return [
            // El rol sale del token firmado: es lo único que no se puede falsear
            'rol' => $payload['rol'] ?? $usuario['tipo_usuario'] ?? 'paciente',
            'id_usuario' => (int)($payload['sub'] ?? 0),
            // Vínculos con la entidad, resueltos desde la base
            'id_paciente' => $usuario['id_paciente'] !== null ? (int)$usuario['id_paciente'] : null,
            'id_medico' => $usuario['id_medico'] !== null ? (int)$usuario['id_medico'] : null,
        ];
    }

    /**
     * Vincula la cuenta del usuario con su ficha de paciente o de médico.
     *
     * POR QUÉ EXISTE (módulo "Sistema de gestión de citas online")
     * Para reservar un turno, el sistema tiene que saber a QUIÉN se le
     * reserva. La tabla usuarios guarda email y contraseña; la tabla
     * pacientes guarda DNI y nombre. Son datos distintos, y una cuenta
     * puede existir sin ficha vinculada.
     *
     * Sin este paso, un paciente que se registra no podría sacar turno
     * ("no estás vinculado a una ficha de paciente"), que es exactamente
     * el tipo de mensaje que hace abandonar el sistema.
     *
     * CÓMO SE VERIFICA QUE ES SU FICHA
     *   paciente → DNI + coincidencia del nombre
     *   médico   → número de matrícula profesional
     * No se acepta un id suelto: si se aceptara, cualquiera podría vincularse
     * a la ficha de otro y ver sus turnos. El DNI solo no alcanza como
     * "contraseña" en un sistema real (en producción debería ir un código
     * por SMS o email); se refuerza comparando también el nombre, y por eso
     * está documentado como limitación consciente.
     *
     * @param int $idUsuario Usuario al que se vincula
     * @param string $tipo 'paciente' o 'medico'
     * @param string $documento DNI (paciente) o matrícula (médico)
     * @return array El usuario actualizado
     * @throws InvalidArgumentException Si el documento no coincide o ya está usado
     * @throws RuntimeException Si el usuario no existe
     */
    public function vincularEntidad(int $idUsuario, string $tipo, string $documento): array
    {
        // Se limpia el documento de espacios exteriores
        $documento = trim($documento);

        // El tipo tiene que ser uno de los dos previstos
        if (!in_array($tipo, ['paciente', 'medico'], true)) {
            throw new \InvalidArgumentException('Tipo de vinculación no válido', 422);
        }

        // Sin documento no hay nada con qué verificar la identidad
        if ($documento === '') {
            throw new \InvalidArgumentException('Debe indicar el documento de verificación', 422);
        }

        // Se verifica que el usuario exista y esté activo
        $usuario = $this->obtenerPorId($idUsuario);
        if (empty($usuario['activo'])) {
            throw new \RuntimeException('La cuenta está desactivada', 403);
        }

        // --------------------------------------------------
        // EL TIPO DE LA CUENTA TIENE QUE COINCIDIR CON LA FICHA
        // --------------------------------------------------
        // Sin esta comprobación, una cuenta de paciente podría vincularse a
        // id_medico y una cuenta de médico a id_paciente, invadiendo un rol
        // que no les corresponde. El rol del token no se recalcula después,
        // así que hoy el daño es indirecto, pero deja la puerta abierta a que
        // un cambio futuro en el manejo de roles convierta esto en una escalada
        // de privilegios. Se cierra acá, que es el único lugar que escribe el
        // vínculo.
        if (($usuario['tipo_usuario'] ?? null) !== $tipo) {
            throw new \InvalidArgumentException(
                'Esta cuenta es de tipo ' . ($usuario['tipo_usuario'] ?? 'desconocido')
                . ' y no puede vincularse a una ficha de ' . $tipo,
                422
            );
        }

        if ($tipo === 'paciente') {
            return $this->vincularPaciente($usuario, $documento);
        }

        return $this->vincularMedico($usuario, $documento);
    }

    /**
     * Vincula un usuario con su ficha de paciente verificando DNI y nombre
     * @param array $usuario Fila del usuario ya obtenida
     * @param string $dni DNI informado
     * @return array El usuario actualizado
     */
    private function vincularPaciente(array $usuario, string $dni): array
    {
        // Se busca al paciente por DNI (el índice uniq_dni hace la búsqueda directa)
        $stmt = $this->pdo->prepare("SELECT * FROM pacientes WHERE dni = ? LIMIT 1");
        $stmt->execute([$dni]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe una ficha con ese DNI, el mensaje es el mismo que si
        // existiera pero fuera de otro: no se confirma la existencia de fichas
        if (!$paciente) {
            throw new \InvalidArgumentException(
                'No encontramos una ficha de paciente con ese DNI y nombre. '
                . 'Verificá los datos o pedí ayuda en recepción.',
                422
            );
        }

        // El paciente tiene que estar dado de alta: vincular una ficha baja
        // serviría solo para generar un error más adelante
        if (empty($paciente['activo'])) {
            throw new \InvalidArgumentException('Esa ficha de paciente está dada de baja', 422);
        }

        // Se comparan los nombres sin tildes, mayúsculas ni espacios, porque
        // "José Pérez" y "jose  perez " tienen que ser la misma persona.
        // La función_quita_acentos() de más abajo hace esa normalización.
        $nombreFicha = $this->normalizarTexto((string)$paciente['nombre']);
        $nombreUsuario = $this->normalizarTexto((string)$usuario['nombre']);

        // Se compara solo el primer apellido/apellido compuesto: el paciente
        // puede haberse registrado con "Juan Pérez" y la ficha tener
        // "Juan Pérez González", y eso no debería bloquearle el acceso.
        $coincide = $this->coincidenNombres($nombreUsuario, $nombreFicha);
        if (!$coincide) {
            throw new \InvalidArgumentException(
                'El nombre de la cuenta no coincide con el de la ficha del DNI',
                422
            );
        }

        // Una ficha no puede quedar ligada a dos cuentas distintas: si lo
        // estuviera, dos personas podrían ver y cancelar los mismos turnos
        if ($paciente['id'] !== null && !empty($usuario['id_paciente']) && (int)$usuario['id_paciente'] !== (int)$paciente['id']) {
            throw new \InvalidArgumentException('Tu cuenta ya está vinculada a otra ficha de paciente', 409);
        }

        // Se comprueba que esa ficha no esté ya en uso por otra cuenta
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE id_paciente = ? AND id <> ? LIMIT 1");
        $stmt->execute([(int)$paciente['id'], (int)$usuario['id']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException('Esa ficha de paciente ya está vinculada a otra cuenta', 409);
        }

        // Se guarda el vínculo
        $stmt = $this->pdo->prepare("UPDATE usuarios SET id_paciente = ? WHERE id = ?");
        $stmt->execute([(int)$paciente['id'], (int)$usuario['id']]);

        return $this->obtenerPorId((int)$usuario['id']);
    }

    /**
     * Vincula un usuario con su ficha de médico verificando la matrícula
     * @param array $usuario Fila del usuario ya obtenida
     * @param string $matricula Matricula informada
     * @return array El usuario actualizado
     */
    private function vincularMedico(array $usuario, string $matricula): array
    {
        // La búsqueda por matrícula no tiene índice propio en el esquema, así
        // que se hace con una consulta directa en vez de agregar un índice nuevo
        // para un caso que solo ocurre al vincular la cuenta, una sola vez
        $stmt = $this->pdo->prepare("SELECT * FROM medicos WHERE matricula = ? LIMIT 1");
        $stmt->execute([$matricula]);
        $medico = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$medico) {
            throw new \InvalidArgumentException('No encontramos un profesional con esa matrícula', 422);
        }

        // Un profesional dado de baja no puede publicar agenda ni atender
        if (empty($medico['activo'])) {
            throw new \InvalidArgumentException('Ese profesional está dado de baja', 422);
        }

        // Se comprueba que la ficha no esté ya en uso por otra cuenta
        $stmt = $this->pdo->prepare("SELECT id FROM usuarios WHERE id_medico = ? AND id <> ? LIMIT 1");
        $stmt->execute([(int)$medico['id'], (int)$usuario['id']]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException('Esa ficha de profesional ya está vinculada a otra cuenta', 409);
        }

        // Se guarda el vínculo
        $stmt = $this->pdo->prepare("UPDATE usuarios SET id_medico = ? WHERE id = ?");
        $stmt->execute([(int)$medico['id'], (int)$usuario['id']]);

        return $this->obtenerPorId((int)$usuario['id']);
    }

    /**
     * Normaliza un texto para comparar nombres: minúsculas y sin tildes
     * @param string $texto Texto a normalizar
     * @return string Texto comparable
     */
    private function normalizarTexto(string $texto): string
    {
        // mb_strtolower resuelve el problema clásico de PHP: strtolower() no
        // distingue bien mayúsculas acentuadas en cadenas UTF-8
        $texto = mb_strtolower(trim($texto), 'UTF-8');

        // Se reemplaza cada vocal acentuada por su versión sin acento. Se
        // hace con una tabla de sustitución porque no hay una función nativa
        // que quite diacríticos en PHP.
        $conAcentos = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'];
        $sinAcentos = ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'];
        $texto = str_replace($conAcentos, $sinAcentos, $texto);

        // Se colapsan los espacios repetidos: "jose  perez" = "jose perez"
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim((string)$texto);
    }

    /**
     * Verifica si dos nombres normalizados pueden referirse a la misma persona.
     *
     * La comparación es deliberadamente tolerante con los segundos apellidos
     * y con el orden de las palabras (la ficha puede traer "Apellido, Nombre",
     * "Nombre Apellido" o más de un apellido), pero EXIGE que coincidan al
     * menos dos palabras y que el nombre más corto esté contenido en el otro.
     * Comparar solo el nombre de pila dejaría abierta la vía de vincular la
     * ficha de otra persona que se llamara igual: no haría falta conocer el
     * DNI ajeno, solo adivinar un nombre de uso común.
     *
     * @param string $nombreCuenta Nombre normalizado de la cuenta
     * @param string $nombreFicha Nombre normalizado de la ficha
     * @return bool True si se consideran el mismo nombre
     */
    private function coincidenNombres(string $nombreCuenta, string $nombreFicha): bool
    {
        // Si son idénticos, listo
        if ($nombreCuenta === $nombreFicha) {
            return true;
        }

        // La base de datos guarda los nombres del paciente en formato
        // "Apellido, Nombre" (ej: "Sánchez, Laura"), que es el orden de un
        // padrón o un documento. Antes de comparar, se invierte ese formato
        // para dejar ambos textos en el mismo orden ("laura sanchez").
        $nombreFicha = $this->invertirApellidoNombre($nombreFicha);

        // Se vuelve a comparar por si coincidían solo al invertir el formato
        if ($nombreCuenta === $nombreFicha) {
            return true;
        }

        // Se separa cada nombre en palabras comparables, en minúsculas y sin
        // acentos, para comparar palabra por palabra en vez de comparar el
        // texto entero. Se reaprovecha normalizarTexto(), que ya resuelve
        // el caso de las mayúsculas acentuadas y de la ñ.
        $palabrasCuenta = $this->palabrasSignificativas($nombreCuenta);
        $palabrasFicha = $this->palabrasSignificativas($nombreFicha);

        // Si alguno quedó vacío, no hay nada con qué comparar
        if (count($palabrasCuenta) === 0 || count($palabrasFicha) === 0) {
            return false;
        }

        // --------------------------------------------------------
        // REGLA: dos palabras en común y el nombre corto contenido en el largo
        // --------------------------------------------------------
        // Comparar solo el nombre de pila ("Laura") abriría una vía clara para
        // vincular la ficha de otra persona: alcanzaría con adivinar un nombre
        // de uso común, sin necesidad de conocer el DNI ajeno. Por eso se
        // exigen DOS palabras coincidentes: en la práctica, nombre y apellido.
        //
        // Se comparan conjuntos y no el texto exacto porque los nombres reales
        // vienen con segundos apellidos de un lado y no del otro ("Sánchez,
        // Laura" en la ficha, "Laura Sánchez González" en la cuenta) y a veces
        // sin coma separando apellido y nombre, que es un orden que no se
        // puede asumir. Al mirar conjuntos el orden deja de importar.
        $comunes = array_intersect($palabrasCuenta, $palabrasFicha);

        // Menos de dos palabras en común significa que no hay nombre + apellido
        if (count($comunes) < 2) {
            return false;
        }

        // Se exige además que el nombre más corto esté contenido en el largo.
        // Esto evita el falso positivo de dos personas distintas que comparten
        // una palabra: "Laura Sánchez" y "Laura Andrea" comparten "laura" y
        // "laura" nada más, y ya se rechazaron; el caso difícil es cuando
        // comparten dos palabras pero difieren en un tercero, como
        // "Sánchez, Laura" contra "Sánchez, Laura Beatriz": aquí el nombre
        // corto está contenido en el largo, que es lo que se busca.
        $corto = count($palabrasCuenta) <= count($palabrasFicha) ? $palabrasCuenta : $palabrasFicha;
        $largo = count($palabrasCuenta) <= count($palabrasFicha) ? $palabrasFicha : $palabrasCuenta;

        return count(array_diff($corto, $largo)) === 0;
    }

    /**
     * Separa un nombre en palabras comparables
     *
     * Deja el texto en minúsculas y sin tildes, descarta las palabras de
     * enlace que no identifican a nadie ("de", "del", "la", "los", "y") y
     * quita las palabras de menos de dos letras, que suelen ser iniciales
     * sueltas y no sirven para comparar.
     *
     * @param string $nombre Nombre ya normalizado
     * @return array Lista de palabras significativas
     */
    private function palabrasSignificativas(string $nombre): array
    {
        // Palabras que no distinguen a una persona de otra
        $conjunciones = ['de', 'del', 'la', 'las', 'los', 'y', 'da', 'das', 'dos'];

        // Se separa por espacios
        $piezas = preg_split('/\s+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY);

        $resultado = [];
        foreach ($piezas as $pieza) {
            // Se normaliza cada palabra por separado: "Sánchez" y "Sanchez"
            // tienen que comparar igual, que es la diferencia entre un
            // paciente que logra vincularse y otro que no por escribir su
            // nombre sin tilde
            $normalizada = $this->normalizarTexto($pieza);

            // Se descartan conjunciones y palabras demasiado cortas
            if (mb_strlen($normalizada, 'UTF-8') < 2 || in_array($normalizada, $conjunciones, true)) {
                continue;
            }

            $resultado[] = $normalizada;
        }

        return $resultado;
    }

    /**
     * Convierte "apellido, nombre" en "nombre apellido"
     *
     * Necesario porque la tabla pacientes guarda los nombres así, mientras
     * que el usuario se registra escribiendo "Nombre Apellido". Sin esta
     * conversión, el paciente que se registra con su nombre natural no
     * podría vincularse nunca con su propia ficha.
     *
     * @param string $nombre Nombre normalizado
     * @return string Nombre en orden "nombre apellido"
     */
    private function invertirApellidoNombre(string $nombre): string
    {
        // Si no hay coma, el nombre ya está en orden natural
        if (strpos($nombre, ',') === false) {
            return $nombre;
        }

        // Se separa en las dos partes de la coma
        $partes = explode(',', $nombre, 2);

        // Se limpian los espacios que quedaron alrededor de la coma
        $apellido = trim($partes[0]);
        $resto = trim($partes[1]);

        // Si alguna parte quedó vacía, se devuelve el nombre original
        if ($apellido === '' || $resto === '') {
            return $nombre;
        }

        // Se devuelve en orden "nombre apellido"
        return $resto . ' ' . $apellido;
    }
}