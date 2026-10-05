<?php
// ============================================================
// tests/CorrelationIdTest.php - Pruebas de la correlación de peticiones
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// Qué se prueba y por qué:
//
//   • Que el identificador del cliente se adopte cuando es válido: es lo que
//     permite unir los logs del frontend con los del backend.
//   • Que un identificador manipulado se RECHAZCE y se genere uno propio.
//     Esta es la prueba de seguridad del módulo: sin ella, un cliente podría
//     escribir saltos de línea y objetos JSON en el archivo de log, es decir,
//     registrar entradas falsas.
//   • Que el identificador generado tenga siempre el formato esperado.
//
// CASOS: camino feliz (id válido del cliente), bordes (id muy largo, id con
// espacios, id con salto de línea) y fallas (array, número, vacío).
// ============================================================

require_once __DIR__ . '/TestCase.php';

class CorrelationIdTest extends TestCase
{
    /**
     * Las pruebas de esta clase.
     *
     * @return array<string, callable>
     */
    public function pruebas(): array
    {
        return [
            'sin encabezado se genera un identificador propio' => [$this, 'pruebaGeneraSiNoViene'],
            'un identificador válido del cliente se adopta' => [$this, 'pruebaAdoptaValido'],
            'un identificador con salto de línea se rechaza' => [$this, 'pruebaRechazaSaltoDeLinea'],
            'un identificador con espacios se rechaza' => [$this, 'pruebaRechazaEspacios'],
            'un identificador con angle brackets se rechaza' => [$this, 'pruebaRechazaAngle'],
            'un identificador demasiado largo se rechaza' => [$this, 'pruebaRechazaLargo'],
            'un valor que no es string se rechaza' => [$this, 'pruebaRechazaNoString'],
            'el identificador generado tiene el formato del servidor' => [$this, 'pruebaFormatoGenerado'],
            'actual() siempre devuelve algo, aunque nadie arrancó' => [$this, 'pruebaActualSinIniciar'],
            'olvidar() reinicia el estado (independencia entre pruebas)' => [$this, 'pruebaOlvidar'],
        ];
    }

    /**
     * Camino de arranque: sin encabezado, el servidor genera el suyo.
     */
    public function pruebaGeneraSiNoViene(TestCase $t): void
    {
        $t->ejecutar(
            // Arrange: se simula una petición que no trae el encabezado.
            function (): void {
                CorrelationId::olvidar();
                unset($_SERVER['HTTP_X_CORRELATION_ID'], $_SERVER['REDIRECT_HTTP_X_CORRELATION_ID']);
            },
            // Act: se arranca la correlación.
            fn() => CorrelationId::iniciar(),
            function (string $id) use ($t): void {
                $t->afirmarCoincide($id, '/^cid_[a-f0-9]{16}$/', 'identificador generado');
            }
        );
    }

    /**
     * Camino feliz del motivo de ser de la clase: si el cliente ya tiene un
     * identificador, se usa el suyo. Es lo que hace posible correlacionar el
     * error que ve el usuario con el log del servidor.
     */
    public function pruebaAdoptaValido(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                CorrelationId::olvidar();
                // Guion y guion bajo están permitidos a propósito: los
                // clientes suelen usar UUID con esos separadores.
                $_SERVER['HTTP_X_CORRELATION_ID'] = 'web-2026_10-05-a1b2';
            },
            fn() => CorrelationId::iniciar(),
            function (string $id) use ($t): void {
                $t->afirmarIgual('web-2026_10-05-a1b2', $id);
            }
        );
    }

    /**
     * Seguridad: un salto de línea en el identificador permitiría inyectar
     * una línea de log falsa. Tiene que rechazarse.
     */
    public function pruebaRechazaSaltoDeLinea(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                CorrelationId::olvidar();
                $_SERVER['HTTP_X_CORRELATION_ID'] = "abc\n{\"level\":\"INFO\",\"message\":\"log falso\"}";
            },
            fn() => CorrelationId::iniciar(),
            function (string $id) use ($t): void {
                // No se adoptó el valor del cliente: es un id del servidor.
                $t->afirmarCoincide($id, '/^cid_[a-f0-9]{16}$/');
                $t->afirmarNoContiene($id, "\n");
            }
        );
    }

    /** Un espacio no pertenece al alfabeto permitido. */
    public function pruebaRechazaEspacios(TestCase $t): void
    {
        $t->afirmarFalso(CorrelationId::esValido('abc def'));
        $t->afirmarFalso(CorrelationId::esValido(' abc'));
    }

    /** "<" y ">" sirven para inyectar etiquetas en un visor de logs HTML. */
    public function pruebaRechazaAngle(TestCase $t): void
    {
        $t->afirmarFalso(CorrelationId::esValido('<script>'));
        $t->afirmarFalso(CorrelationId::esValido('a>b'));
    }

    /** El tope de 64 caracteres está puesto por algo, y esto lo verifica. */
    public function pruebaRechazaLargo(TestCase $t): void
    {
        $t->afirmarFalso(CorrelationId::esValido(str_repeat('a', 65)));
        // Justo en el límite entra.
        $t->afirmarVerdadero(CorrelationId::esValido(str_repeat('a', 64)));
    }

    /** Cualquier tipo que no sea string queda fuera. */
    public function pruebaRechazaNoString(TestCase $t): void
    {
        $t->afirmarFalso(CorrelationId::esValido(null));
        $t->afirmarFalso(CorrelationId::esValido(12345));
        // Un array es el caso interesante: antes de preg_match, porque un
        // array no es string y preg_match con un array lanzaría una
        // advertencia de PHP.
        $t->afirmarFalso(CorrelationId::esValido(['abc']));
    }

    /** El formato del identificador propio, siempre. */
    public function pruebaFormatoGenerado(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            // Act: se generan varios, porque el formato tiene que cumplirse
            // siempre y no solo con el primero.
            fn() => [CorrelationId::generar(), CorrelationId::generar(), CorrelationId::generar()],
            function (array $ids) use ($t): void {
                foreach ($ids as $id) {
                    $t->afirmarCoincide($id, '/^cid_[a-f0-9]{16}$/', 'formato del identificador');
                }

                // Y dos identificadores seguidos no pueden ser iguales: si lo
                // fueran, dos peticiones distintas se correlacionarían mal.
                $t->afirmarQue(count(array_unique($ids)) === 3, 'los identificadores deben ser distintos');
            }
        );
    }

    /**
     * actual() es el acceso que usa el Logger. Si no existiera la garantía de
     * que siempre hay un id, el log podría quedar sin correlación.
     */
    public function pruebaActualSinIniciar(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                CorrelationId::olvidar();
            },
            fn() => CorrelationId::actual(),
            function (string $id) use ($t): void {
                $t->afirmarCoincide($id, '/^cid_[a-f0-9]{16}$/');

                // La segunda llamada devuelve lo mismo: se generó una vez y
                // se reutiliza, no uno nuevo en cada llamada.
                $t->afirmarIgual($id, CorrelationId::actual());
            }
        );
    }

    /**
     * El método que hace posible que las pruebas sean independientes: limpia
     * el estado estático entre una prueba y otra.
     */
    public function pruebaOlvidar(TestCase $t): void
    {
        $t->ejecutar(
            function (): void {
                CorrelationId::olvidar();
                $_SERVER['HTTP_X_CORRELATION_ID'] = 'uno';
                CorrelationId::iniciar();
                CorrelationId::olvidar();
                $_SERVER['HTTP_X_CORRELATION_ID'] = 'dos';
            },
            fn() => CorrelationId::iniciar(),
            function (string $id) use ($t): void {
                $t->afirmarIgual('dos', $id, 'tras olvidar, el estado tiene que reiniciarse');
            }
        );
    }
}
