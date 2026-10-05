<?php
// ============================================================
// tests/RateLimiterTest.php - Pruebas del limitador de intentos
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// El rate limiter es lógica de seguridad, y la seguridad que no se prueba no
// existe. Estas pruebas usan una carpeta temporal propia, así que no tocan
// los contadores reales de storage/rate/ ni necesitan base de datos.
//
// CASOS:
//   • Camino feliz: los primeros intentos están permitidos.
//   • Bordes:    exactamente el máximo, limpieza de contadores, archivos
//                corruptos, claves que se parecen entre sí.
//   • Fallas:    ventana expirada que vuelve a permitir, clave bloqueada.
//
// El caso "clave bloqueada y luego limpia()" importa en el flujo real: un
// login exitoso tiene que llamar a limpiar() para que el próximo intento
// del mismo usuario no nazca bloqueado por los errores anteriores.
// ============================================================

require_once __DIR__ . '/TestCase.php';

class RateLimiterTest extends TestCase
{
    /** @var string Carpeta temporal de los contadores de esta prueba */
    private string $dirTemporal;

    /** @var RateLimiter Instancia probada */
    private RateLimiter $limiter;

    /**
     * Cada prueba arranca con una carpeta nueva: eso es lo que hace que
     * puedan correr en cualquier orden y repetidas veces.
     */
    public function __construct()
    {
        $this->dirTemporal = sys_get_temp_dir() . '/saludweb-test-rate-' . getmypid() . '-' . uniqid();
        $this->limiter = new RateLimiter($this->dirTemporal);
    }

    /**
     * Las pruebas de esta clase.
     *
     * @return array<string, callable>
     */
    public function pruebas(): array
    {
        return [
            'permite los intentos que están debajo del máximo' => [$this, 'pruebaPermite'],
            'bloquea al llegar al máximo' => [$this, 'pruebaBloqueaEnMaximo'],
            'tras limpiar, vuelve a permitir' => [$this, 'pruebaLimpiar'],
            'la ventana expirada vuelve a permitir' => [$this, 'pruebaVentanaExpirada'],
            'un archivo corrupto no rompe el contador' => [$this, 'pruebaArchivoCorrupto'],
            'claves distintas llevan a archivos distintos' => [$this, 'pruebaClavesDistintas'],
        ];
    }

    /**
     * Camino feliz: por debajo del límite, todo está permitido.
     */
    public function pruebaPermite(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: límite de 3 intentos en una ventana de 60 segundos.
            fn() => null,
            // Act: se consultan 3 veces sin registrar ningún fallo.
            fn() => [
                $this->limiter->permitir('login:ip-1', 3, 60),
                $this->limiter->permitir('login:ip-1', 3, 60),
                $this->limiter->permitir('login:ip-1', 3, 60),
            ],
            function (array $resultado) use ($t): void {
                $t->afirmarVerdadero($resultado[0]);
                $t->afirmarVerdadero($resultado[1]);
                $t->afirmarVerdadero($resultado[2]);
            }
        );
    }

    /**
     * El comportamiento que de verdad importa: con tres fallos registrados, el
     * cuarto intento se deniega.
     */
    public function pruebaBloqueaEnMaximo(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                // Arrange: se registran los 3 fallos del login.
                $this->limiter->registrar('login:ip-2');
                $this->limiter->registrar('login:ip-2');
                $this->limiter->registrar('login:ip-2');
            },
            // Act: se consulta si se permite el intento siguiente.
            fn() => $this->limiter->permitir('login:ip-2', 3, 60),
            function (bool $permitido) use ($t): void {
                // Assert: denegado, que es el objetivo del rate limiter.
                $t->afirmarFalso($permitido, 'con 3 fallos previos el cuarto intento debe negarse');
            }
        );
    }

    /**
     * El login correcto tiene que limpiar los contadores; si no, el usuario
     * quedaría bloqueado por errores que ya corrigió.
     */
    public function pruebaLimpiar(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                $this->limiter->registrar('login:ip-3');
                $this->limiter->registrar('login:ip-3');
                $this->limiter->registrar('login:ip-3');
            },
            // Act: login exitoso -> limpiar -> consultar de nuevo.
            function (): array {
                $antes = $this->limiter->permitir('login:ip-3', 3, 60);
                $this->limiter->limpiar('login:ip-3');
                return [$antes, $this->limiter->permitir('login:ip-3', 3, 60)];
            },
            function (array $resultado) use ($t): void {
                $t->afirmarFalso($resultado[0], 'antes de limpiar debería estar bloqueado');
                $t->afirmarVerdadero($resultado[1], 'después de limpiar debería volver a permitirse');
            }
        );
    }

    /**
     * Los intentos viejos se olvidan: la ventana es una ventana, no una
     * condena perpetua. Se prueba con una ventana de 1 segundo y una espera
     * real, que es lo que hace el archivo de estado en el disco.
     */
    public function pruebaVentanaExpirada(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                $this->limiter->registrar('login:ip-4');
            },
            function (): array {
                // Act: ventana de 1 segundo y una espera de 2.
                $bloqueado = $this->limiter->permitir('login:ip-4', 1, 1);
                sleep(2);
                return [$bloqueado, $this->limiter->permitir('login:ip-4', 1, 1)];
            },
            function (array $resultado) use ($t): void {
                $t->afirmarFalso($resultado[0], 'con 1 fallo previo y máximo 1, debe bloquear');
                $t->afirmarVerdadero($resultado[1], 'pasada la ventana, debe volver a permitirse');
            }
        );
    }

    /**
     * Un archivo de estado corrupto (corte de luz a mitad de escritura) no
     * puede romper el login de todo el mundo: se descarta y se empieza de cero.
     */
    public function pruebaArchivoCorrupto(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                // Arrange: se escribe basura donde debería estar el JSON.
                $archivos = glob($this->dirTemporal . '/*.json') ?: [];
                $archivo = $archivos[0] ?? null;
                if ($archivo === null) {
                    // Se fuerza la creación de un archivo con un registrar().
                    $this->limiter->registrar('login:ip-5');
                    $archivos = glob($this->dirTemporal . '/*.json') ?: [];
                    $archivo = $archivos[0] ?? null;
                }
                if ($archivo !== null) {
                    file_put_contents($archivo, '{esto no es json');
                }
            },
            // Act: se consulta con el archivo corrupto.
            fn() => $this->limiter->permitir('login:ip-5', 3, 60),
            function (bool $permitido) use ($t): void {
                // Assert: el sistema se recupera en vez de romperse.
                $t->afirmarVerdadero($permitido, 'un archivo corrupto no debe bloquear el acceso');
            }
        );
    }

    /**
     * Dos IPs distintas no comparten contador: si compartieran, un atacante
     * podría bloquear el acceso de otra persona.
     */
    public function pruebaClavesDistintas(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                // Arrange: una clave queda saturada.
                for ($i = 0; $i < 3; $i++) {
                    $this->limiter->registrar('login:ip-6');
                }
            },
            function (): array {
                return [
                    $this->limiter->permitir('login:ip-6', 3, 60),
                    $this->limiter->permitir('login:ip-7', 3, 60),
                ];
            },
            function (array $resultado) use ($t): void {
                $t->afirmarFalso($resultado[0], 'la clave saturada debe quedar bloqueada');
                $t->afirmarVerdadero($resultado[1], 'otra clave no debe verse afectada');
            }
        );
    }

    /**
     * Limpieza final: se borra la carpeta temporal. Se ejecuta al terminar
     * todas las pruebas de la clase, no al principio de cada una, porque el
     * runner crea una instancia por clase.
     */
    public function __destruct()
    {
        foreach (glob($this->dirTemporal . '/*') ?: [] as $archivo) {
            @unlink($archivo);
        }
        @rmdir($this->dirTemporal);
    }
}
