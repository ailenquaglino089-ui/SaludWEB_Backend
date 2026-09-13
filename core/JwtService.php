<?php
// ============================================================
// core/JwtService.php - Servicio de tokens JWT
// ============================================================
// Módulo: "Login API con JWT - Autenticación en Aplicaciones Modernas"
// ------------------------------------------------------------
// Usa firebase/php-jwt para firmar (HS256) y verificar tokens.
// Aplica claims estándar (RFC 7519):
//   iss = emisor, iat = emitido en, exp = expiración, sub = sujeto (usuario)
// Y claims privados: rol, email, nombre (sin contraseñas ni datos sensibles).
// ============================================================

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    // Secreto de firma/verificación (HS256), provisto por Secret::obtener()
    private string $secret;
    // Emisor declarado en el claim "iss" (quién emitió el token)
    private string $issuer;
    // Vigencia del token en segundos (define el claim "exp")
    private int $ttl;

    /**
     * @param string $secret  Secreto de firma (HS256)
     * @param string $issuer  Emisor declarado en el claim "iss"
     * @param int    $ttl     Vigencia del token en segundos (exp)
     */
    public function __construct(
        string $secret,               // Secreto de firma (obligatorio)
        string $issuer = 'saludweb-api',  // Emisor por defecto: identifica a esta API
        int $ttl = 3600                   // Vigencia por defecto: 1 hora (3600 segundos)
    ) {
        // Almacena cada parámetro en su propiedad correspondiente de la clase
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->ttl = $ttl;
    }

    /**
     * Genera un token JWT firmado con los datos del usuario.
     * NUNCA se incluyen datos sensibles (password, tarjetas).
     *
     * @param array $usuario Fila de la tabla usuarios (id, email, nombre, tipo_usuario)
     * @return array{token: string, expires_at: int}
     */
    public function generar(array $usuario): array
    {
        // Momento actual en segundos: momento de emisión del token (claim "iat")
        $issuedAt = time();
        // Fecha de expiración: momento de emisión + vigencia configurada ("exp")
        $expiresAt = $issuedAt + $this->ttl;

        // Cuerpo del token (payload): claims estándar + claims privados
        $payload = [
            'iss'    => $this->issuer,                            // Emisor
            'iat'    => $issuedAt,                                // Emitido en
            'exp'    => $expiresAt,                               // Expiración
            'sub'    => (int) $usuario['id'],                     // Sujeto (ID)
            'rol'    => $usuario['tipo_usuario'] ?? 'paciente',   // Role claim
            'email'  => $usuario['email'],                        // Claim privado
            'nombre' => $usuario['nombre'] ?? '',                 // Nombre (vacío si falta)
        ];

        // Codifica y firma el payload con el secreto usando el algoritmo HS256
        $token = JWT::encode($payload, $this->secret, 'HS256');

        // Devuelve el token generado junto con su expiración (para el frontend)
        return [
            'token' => $token,           // El JWT propiamente dicho
            'expires_at' => $expiresAt,  // Cuándo expira (timestamp), útil para el cliente
        ];
    }

    /**
     * Verifica firma y expiración del token y devuelve el payload como array.
     *
     * @throws \UnexpectedValueException Token inválido, manipulado o expirado
     */
    public function verificar(string $token): array
    {
        // Verifica la firma (con el secreto y algoritmo HS256) y la expiración; lanza excepción si falla
        $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
        // Convierte el objeto decodificado a arreglo asociativo y lo devuelve
        return (array) $decoded;
    }

    /**
     * Vigencia configurada (en segundos) antes de expirar.
     */
    public function ttl(): int
    {
        // Devuelve la vigencia configurada (en segundos)
        return $this->ttl;
    }
}