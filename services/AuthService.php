<?php
// ============================================================
// services/AuthService.php - Servicio de Autenticación
// ============================================================
// Maneja lógica de login, registro y validación de credenciales

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
            $data['id_paciente'] ?? null,
            $data['id_medico'] ?? null
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
}