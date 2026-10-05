<?php
// ============================================================
// tests/LoggerTest.php - Pruebas del log estructurado
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// Qué se prueba acá y por qué:
//
//   • Que un secreto NUNCA llegue al archivo de log. Esta es la prueba más
//     importante del archivo: si el enmascarado falla, hay una contraseña
//     escrita en disco y eso es un incidente, no un bug.
//   • Que la línea sea JSON con los campos esperados. Si mañana el log deja
//     de ser parseable, las herramientas de ELK dejan de indexar y el
//     diagnóstico se vuelve imposible: esta prueba avisa antes.
//   • Que el filtro por nivel funcione. Un log que escribe DEBUG en
//     producción llena el disco y esconde los errores reales.
//
// CASOS: camino feliz (una línea bien formada), bordes (contexto vacío,
// nivel desconocido, valores gigantic) y fallas (el secreto se filtra).
//
// AAA: cada prueba tiene las tres fases separadas y comentadas.
// ============================================================

require_once __DIR__ . '/TestCase.php';

class LoggerTest extends TestCase
{
    /**
     * Carpeta temporal donde se escriben los logs de esta prueba.
     * Se limpia al terminar, así la prueba es independiente de las demás y
     * repetible (principio FIRST: Repeatable).
     */
    private string $dirTemporal;

    /**
     * Preparación: se ejecuta una sola vez antes de todas las pruebas.
     * Separar esto de cada prueba es lo que hace que las pruebas sean
     * independientes entre sí: no comparten archivos ni contadores.
     */
    public function __construct()
    {
        // sys_get_temp_dir() da la carpeta temporal del sistema.
        $this->dirTemporal = sys_get_temp_dir() . '/saludweb-test-logs-' . getmypid();
    }

    /**
     * Las pruebas de esta clase.
     *
     * @return array<string, callable>
     */
    public function pruebas(): array
    {
        return [
            'la línea de log es JSON con los campos obligatorios' => [$this, 'pruebaLineaConCampos'],
            'un nivel por debajo del mínimo no se escribe' => [$this, 'pruebaFiltroDeNivel'],
            'un nivel desconocido no se escribe' => [$this, 'pruebaNivelDesconocido'],
            'una contraseña en el contexto queda redactada' => [$this, 'pruebaPasswordRedactada'],
            'un token en el contexto queda redactado' => [$this, 'pruebaTokenRedactado'],
            'los datos personales quedan enmascarados parcialmente' => [$this, 'pruebaPiiEnmascarada'],
            'el enmascarado también funciona en datos anidados' => [$this, 'pruebaAnidado'],
            'un valor gigante se trunca en vez de llenar el log' => [$this, 'pruebaTruncado'],
            'el archivo del día se escribe y se puede releer' => [$this, 'pruebaEscrituraEnDisco'],
        ];
    }

    /**
     * Camino feliz: una línea con todos los campos que promete el formato.
     */
    public function pruebaLineaConCampos(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: un nivel y un contexto sin datos sensibles.
            fn() => null,
            // Act: se arma la línea (no se escribe en disco: es una función pura).
            fn() => Logger::linea(Logger::INFO, 'cita creada', ['id_cita' => 42]),
            // Assert: es JSON válido y trae los campos del contrato.
            function (string $linea) use ($t): void {
                // Decodificar tiene que funcionar: si la línea no es JSON
                // válido, ninguna herramienta la va a poder leer.
                $registro = json_decode($linea, true);
                $t->afirmarQue(is_array($registro), 'la línea tiene que ser JSON válido');

                // Los campos obligatorios del formato.
                $t->afirmarQue(array_key_exists('timestamp', $registro), 'falta timestamp');
                $t->afirmarQue(array_key_exists('level', $registro), 'falta level');
                $t->afirmarQue(array_key_exists('message', $registro), 'falta message');
                $t->afirmarQue(array_key_exists('service', $registro), 'falta service');
                $t->afirmarQue(array_key_exists('correlationId', $registro), 'falta correlationId');
                $t->afirmarQue(array_key_exists('context', $registro), 'falta context');

                // Y los valores son los que se pidieron.
                $t->afirmarIgual('INFO', $registro['level']);
                $t->afirmarIgual('cita creada', $registro['message']);
                $t->afirmarIgual(42, $registro['context']['id_cita']);

                // La línea termina en salto de línea: es lo que la convierte en
                // un registro JSON Lines y no en un bloque amorfo.
                $t->afirmarQue(str_ends_with($linea, "\n"), 'la línea debe terminar en salto de línea');
            }
        );
    }

    /**
     * El filtro por nivel: con el mínimo en WARN, un INFO no se escribe.
     */
    public function pruebaFiltroDeNivel(TestCase $t): void
    {
        $nivelPrevio = Logger::nivelMinimo();

        $t->ejecutar(
            // Arrange: se sube el mínimo a WARN.
            fn() => Logger::configurar(null, Logger::WARN, null),
            // Act: se pregunta qué niveles pasarían el filtro.
            function (): array {
                return [
                    'debug' => Logger::habilitado(Logger::DEBUG),
                    'info'  => Logger::habilitado(Logger::INFO),
                    'warn'  => Logger::habilitado(Logger::WARN),
                    'error' => Logger::habilitado(Logger::ERROR),
                ];
            },
            // Assert: exactamente los de WARN para arriba.
            function (array $resultado) use ($t): void {
                $t->afirmarFalso($resultado['debug'], 'DEBUG no debe pasar con el mínimo en WARN');
                $t->afirmarFalso($resultado['info'], 'INFO no debe pasar con el mínimo en WARN');
                $t->afirmarVerdadero($resultado['warn'], 'WARN debe pasar con el mínimo en WARN');
                $t->afirmarVerdadero($resultado['error'], 'ERROR debe pasar con el mínimo en WARN');
            }
        );

        // Restaurar el nivel: sin esto, el resto de las pruebas heredaría un
        // estado distinto del que encontró y el orden importaría. Eso rompe
        // el principio Independent.
        Logger::configurar(null, $nivelPrevio, null);
    }

    /**
     * Un nivel que no existe se descarta en lugar de escribirse.
     */
    public function pruebaNivelDesconocido(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Logger::habilitado('VERBOSE'),
            function (bool $habilitado) use ($t): void {
                $t->afirmarFalso($habilitado, 'un nivel desconocido no debe habilitarse');
            }
        );
    }

    /**
     * El caso crítico: una contraseña nunca llega al log.
     */
    public function pruebaPasswordRedactada(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Logger::linea(Logger::ERROR, 'login fallido', [
                'password' => 'MiClaveSecreta123',
                'email'    => 'juan@mail.com',
            ]),
            function (string $linea) use ($t): void {
                // La prueba que importa: el secreto NO puede estar.
                $t->afirmarNoContiene($linea, 'MiClaveSecreta123', 'la contraseña se filtró al log');

                // Y tiene que quedar la marca de que se ocultó algo, para que
                // el que lea el log sepa que ahí había un dato.
                $t->afirmarContiene($linea, '[oculto]');
            }
        );
    }

    /**
     * Un token de autenticación tampoco.
     */
    public function pruebaTokenRedactado(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Logger::linea(Logger::WARN, 'petición rara', [
                'token' => 'eyJhbGciOiJIUzI1NiJ9.payload.firma',
            ]),
            function (string $linea) use ($t): void {
                $t->afirmarNoContiene($linea, 'eyJhbGciOiJIUzI1NiJ9', 'el token se filtró al log');
                $t->afirmarNoContiene($linea, 'payload', 'parte del token se filtró al log');
            }
        );
    }

    /**
     * Los datos personales se enmascaran: se conserva lo justo para poder
     * correlacionar y nada más.
     */
    public function pruebaPiiEnmascarada(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Logger::enmascarar(['email' => 'juan@mail.com', 'dni' => '30111222']),
            function (array $resultado) use ($t): void {
                // El email no puede aparecer completo: es dato personal.
                $t->afirmarNoContiene($resultado['email'], 'mail.com', 'el email se filtró');
                // Pero conserva el primer carácter, para poder correlacionar.
                $t->afirmarCoincide((string) $resultado['email'], '/^j\*+$/');

                // El DNI tampoco aparece entero.
                $t->afirmarNoContiene((string) $resultado['dni'], '30111222');
            }
        );
    }

    /**
     * El enmascarado es recursivo: un secreto anidado también se oculta.
     *
     * Este caso existe porque el caso anterior es el que se escribe primero
     * y el anidado es el que se olvida. Un servicio que arma
     * ['usuario' => ['password' => ...]] y lo pasa entero al log sería
     * exactamente la fuga que el módulo quiere evitar.
     */
    public function pruebaAnidado(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Logger::enmascarar([
                'usuario' => [
                    'id'       => 7,
                    'password' => 'otraClave',
                    'contacto' => ['email' => 'ana@mail.com'],
                ],
            ]),
            function (array $resultado) use ($t): void {
                $t->afirmarNoContiene(json_encode($resultado), 'otraClave', 'secreto anidado filtrado');
                $t->afirmarNoContiene(json_encode($resultado), 'mail.com', 'email anidado filtrado');

                // Los datos que NO son sensibles siguen intactos: si el
                // enmascarado fuera "borrar todo", el log no serviría para nada.
                $t->afirmarIgual(7, $resultado['usuario']['id']);
            }
        );
    }

    /**
     * Un valor gigante se trunca: un listado de 1000 filas en el log no
     * ayuda a diagnosticar y sí a llenar el disco.
     */
    public function pruebaTruncado(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: 2000 caracteres.
            fn() => null,
            fn() => Logger::enmascarar(['descripcion' => str_repeat('a', 2000)]),
            function (array $resultado) use ($t): void {
                $texto = (string) $resultado['descripcion'];
                $t->afirmarQue(
                    strlen($texto) < 600,
                    'el valor debería estar truncado (longitud actual: ' . strlen($texto) . ')'
                );
                $t->afirmarContiene($texto, 'truncado', 'debería avisar que el valor fue truncado');
            }
        );
    }

    /**
     * Integración mínima: la línea se escribe en el archivo y se puede releer.
     *
     * No es una prueba unitaria pura (toca el disco), pero es la que
     * comprueba que elLogger::log() realmente llegue al archivo. Sin ella,
     * el resto de las pruebas pasarían aunque el log no se escribiera nunca,
     * que sería el peor resultado posible: un sistema que "tiene logs" y no
     * tiene ninguno.
     */
    public function pruebaEscrituraEnDisco(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: se apunta el logger a una carpeta temporal propia.
            function (): void {
                Logger::configurar($this->dirTemporal, Logger::DEBUG, 'test');
            },
            // Act: se escriben dos líneas.
            function (): void {
                Logger::info('primera línea', ['n' => 1]);
                Logger::warn('segunda línea', ['n' => 2]);
            },
            function () use ($t): void {
                // Assert: el archivo existe y tiene las dos líneas.
                $t->afirmarQue(is_file(Logger::archivo()), 'el archivo de log debería existir');

                $lineas = Logger::ultimasLineas(10);
                $t->afirmarIgual(2, count($lineas), 'cantidad de líneas escritas');
                $t->afirmarContiene($lineas[0], 'primera línea');
                $t->afirmarContiene($lineas[1], 'segunda línea');

                // Y cada línea se puede parsear por separado: ese es el punto
                // del formato JSON Lines.
                $primera = json_decode($lineas[0], true);
                $t->afirmarQue(is_array($primera), 'cada línea del archivo tiene que ser JSON válido');

                // Limpieza: borrar la carpeta temporal para que la prueba
                // sea repetible y no deje basura. Después se devuelve el
                // logger a su configuración real: si no, las pruebas que
                // siguen (en otro archivo) heredarían una carpeta que ya no
                // existe y sus logs irían a parar a ningún lado.
                foreach (glob($this->dirTemporal . '/*') ?: [] as $basura) {
                    @unlink($basura);
                }
                @rmdir($this->dirTemporal);
                Logger::configurar();
            }
        );
    }
}
