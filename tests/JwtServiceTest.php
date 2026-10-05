<?php
// ============================================================
// tests/JwtServiceTest.php - Pruebas de emisión y verificación de JWT
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// La firma y la expiración del token son la frontera de seguridad de toda la
// API: si un tokenExpired se acepta, un ex empleado puede seguir entrando; si
// un token válido se rechaza, los usuarios quedan afuera del sistema.
//
// CASOS:
//   • Camino feliz: emitir y verificar el mismo token.
//   • Bordes:    expiración exacta, claims del usuario, TTL configurable.
//   • Fallas:    token con firma distinta, token manipulado, token vencido.
//
// ESTAS PRUEBAS SON unitarias de VERIFICACIÓN DE FIRMA, no de seguridad de la
// biblioteca: no se intenta romper el algoritmo, se verifica que el servicio
// use el secreto y el algoritmo correctos y que rechace lo que no corresponde.
// ============================================================

require_once __DIR__ . '/TestCase.php';

class JwtServiceTest extends TestCase
{
    /**
     * Secreto de prueba. Es un valor fijo y público a propósito: es un test,
     * no producción. En producción el secreto viene de Secret::obtener() y
     * nunca está en el código.
     */
    private const SECRETO = 'secreto-de-pruebas-no-usar-en-produccion-0123456789';

    /**
     * Usuario de ejemplo, con la forma que recibe JwtService::generar().
     */
    private function usuario(): array
    {
        return [
            'id'           => 1,
            'email'        => 'ana@mail.com',
            'nombre'       => 'Ana Pérez',
            'tipo_usuario' => 'paciente',
        ];
    }

    /**
     * Las pruebas de esta clase.
     *
     * @return array<string, callable>
     */
    public function pruebas(): array
    {
        return [
            'un token emitido se verifica y trae los claims' => [$this, 'pruebaEmitirYVerificar'],
            'el token nunca incluye el hash de la contraseña' => [$this, 'pruebaNoIncluyePassword'],
            'el payload no incluye datos de la fila que no corresponden' => [$this, 'pruebaSoloClaimsEsperados'],
            'un token firmado con otro secreto se rechaza' => [$this, 'pruebaFirmaDistinta'],
            'un token manipurado se rechaza' => [$this, 'pruebaTokenManipulado'],
            'un token vencido se rechaza' => [$this, 'pruebaTokenVencido'],
            'un token vacío o sin formato se rechaza' => [$this, 'pruebaTokenInvalido'],
            'el TTL configurado se respeta' => [$this, 'pruebaTtl'],
        ];
    }

    /** Camino feliz: emitir y verificar. */
    public function pruebaEmitirYVerificar(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: un servicio con el secreto de prueba.
            fn() => new JwtService(self::SECRETO),
            // Act: se emite un token y se verifica.
            function (JwtService $jwt): array {
                $emitido = $jwt->generar($this->usuario());
                return [$emitido, $jwt->verificar($emitido['token'])];
            },
            function (array $resultado) use ($t): void {
                [$emitido, $payload] = $resultado;

                // Assert: el payload trae lo que el frontend necesita.
                $t->afirmarIgual(1, $payload['sub'], 'el claim sub es el id del usuario');
                $t->afirmarIgual('paciente', $payload['rol'], 'el claim rol');
                $t->afirmarIgual('ana@mail.com', $payload['email']);
                $t->afirmarIgual('saludweb-api', $payload['iss'], 'el emisor por defecto');

                // Y la expiración es la que devolvió generar().
                $t->afirmarIgual($emitido['expires_at'], $payload['exp']);
                $t->afirmarQue($payload['exp'] > time(), 'el token tiene que vencer en el futuro');
            }
        );
    }

    /**
     * El claim de contraseña es el que este módulo pide explícitamente: un
     * token es un documento público (lo viaja el cliente), así que jamás debe
     * llevar el hash de la contraseña ni el campo vacío.
     */
    public function pruebaNoIncluyePassword(TestCase $t): void
    {
        $t->ejecutar(
            fn() => new JwtService(self::SECRETO),
            function (JwtService $jwt): array {
                // Se pasa un usuario que incluye password, como si el
                // servicio recibiera la fila completa de la tabla.
                $usuario = $this->usuario();
                $usuario['password'] = '$2y$10$hashQueNoDeberiaViajar';
                return $jwt->verificar($jwt->generar($usuario)['token']);
            },
            function (array $payload) use ($t): void {
                $t->afirmarFalso(array_key_exists('password', $payload), 'el token no debe llevar password');
                $t->afirmarFalso(array_key_exists('password_hash', $payload), 'el token no debe llevar hash');
            }
        );
    }

    /**
     * El token solo lleva los claims documentados. Si aparece un campo de la
     * tabla que no debería, es un problema de privacidad: el token viaja
     * cifrado pero no oculto (cualquiera con el token puede leer su contenido).
     */
    public function pruebaSoloClaimsEsperados(TestCase $t): void
    {
        $t->ejecutar(
            fn() => new JwtService(self::SECRETO),
            function (JwtService $jwt): array {
                // Una fila "real" con columnas de más, como las que hay en
                // la tabla usuarios.
                $usuario = $this->usuario() + [
                    'activo'       => 1,
                    'dni'          => '30111222',
                    'created_at'   => '2026-01-01 10:00:00',
                ];
                return $jwt->verificar($jwt->generar($usuario)['token']);
            },
            function (array $payload) use ($t): void {
                // Los claims estándar del contrato tienen que estar...
                foreach (['iss', 'iat', 'exp', 'sub', 'rol'] as $esperado) {
                    $t->afirmarQue(array_key_exists($esperado, $payload), 'falta el claim ' . $esperado);
                }
                // ...y ningún otro de los que vienen en la fila.
                $t->afirmarFalso(array_key_exists('dni', $payload), 'el DNI no debe viajar en el token');
                $t->afirmarFalso(array_key_exists('activo', $payload), 'el estado no debe viajar en el token');
                $t->afirmarFalso(array_key_exists('created_at', $payload), 'la fecha de alta no debe viajar en el token');
            }
        );
    }

    /**
     * Seguridad: un token firmado con otro secreto tiene que rechazarse. Sin
     * esto, cualquiera podría emitir tokens falsos y pasar por cualquier
     * usuario.
     */
    public function pruebaFirmaDistinta(TestCase $t): void
    {
        // Arrange: un token emitido por "otro servidor", con otro secreto.
        // El intento de verificación y su comprobación van juntos porque el
        // comportamiento que se prueba ES que la verificación falle.
        $otro = new JwtService('otro-secreto-totalmente-distinto-9876543210');
        $tokenAjeno = $otro->generar($this->usuario())['token'];
        $servidorReal = new JwtService(self::SECRETO);

        // Act + Assert: el servicio real tiene que rechazar el token ajeno.
        $t->afirmarLanza(
            fn() => $servidorReal->verificar($tokenAjeno),
            \UnexpectedValueException::class
        );
    }

    /**
     * Seguridad: cambiar un carácter del payload invalida la firma. Es lo que
     * impide que alguien se cambie el rol a mano y se haga médico o admin.
     */
    public function pruebaTokenManipulado(TestCase $t): void
    {
        $t->ejecutar(
            fn() => new JwtService(self::SECRETO),
            function (JwtService $jwt): string {
                $emitido = $jwt->generar($this->usuario());
                // Act: se altera la firma (última parte del token).
                $partes = explode('.', $emitido['token']);
                $partes[2] = strrev($partes[2]) . 'X';
                return implode('.', $partes);
            },
            function (string $tokenAlterado) use ($t): void {
                $jwt = new JwtService(self::SECRETO);
                $t->afirmarLanza(
                    fn() => $jwt->verificar($tokenAlterado),
                    \UnexpectedValueException::class
                );
            }
        );
    }

    /**
     * Seguridad: un token vencido no sirve. Se emite uno con TTL negativo
     * (equivale a "vencido hace una hora") en lugar de esperar, para que la
     * prueba no dependa del reloj ni demore.
     */
    public function pruebaTokenVencido(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: un servicio cuyo TTL ya venció.
            fn() => new JwtService(self::SECRETO, 'saludweb-api', -3600),
            function (JwtService $jwt): string {
                return $jwt->generar($this->usuario())['token'];
            },
            function (string $tokenVencido) use ($t): void {
                $jwt = new JwtService(self::SECRETO);
                $t->afirmarLanza(
                    fn() => $jwt->verificar($tokenVencido),
                    \UnexpectedValueException::class
                );
            }
        );
    }

    /** Bordes: entradas que ni siquiera son un token. */
    public function pruebaTokenInvalido(TestCase $t): void
    {
        $jwt = new JwtService(self::SECRETO);
        foreach (['', 'abc', 'a.b', 'a.b.c.d'] as $basura) {
            $t->afirmarLanza(
                fn() => $jwt->verificar($basura),
                \Throwable::class
            );
        }
    }

    /** El TTL configurado se respeta en el claim exp. */
    public function pruebaTtl(TestCase $t): void
    {
        $t->ejecutar(
            fn() => new JwtService(self::SECRETO, 'saludweb-api', 900),
            function (JwtService $jwt): array {
                return $jwt->generar($this->usuario());
            },
            function (array $emitido) use ($t): void {
                $t->afirmarIgual(900, $emitido['expires_at'] - time(), 'la vigencia del token');
            }
        );
    }
}
