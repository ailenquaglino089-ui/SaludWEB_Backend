<?php
// ============================================================
// tests/TestCase.php - Micro framework de pruebas unitarias
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// POR QUÉ EXISTE ESTE ARCHIVO Y NO PHPUnit
//
// PHPUnit es la opción obvia, y en un proyecto real también sería lo
// correcto. Acá se decidió escribir un arnés mínimo por dos razones
// concretas, que conviene decir en claro en lugar de disimular:
//
//   1. Este backend no usa Composer como gestor de dependencias reales:
//      composer.json tiene UNA dependencia (firebase/php-jwt) y nada de
//      autoload. Agregar PHPUnit implicaría incorporar un autoloader,
//      el archivo de configuración y ~30 dependencias más.
//
//   2. Las pruebas que importan acá son de lógica pura (enmascarado de
//      logs, validación de entradas, correlación, rate limiting). Para eso
//      no hace falta un framework: hacen falta cuatro funciones.
//
// El tradeoff es explícito: este archivo no es un framework de testing
// general. No tiene mocks, ni @dataProvider, ni cobertura, ni reporters.
// Lo que tiene son los principios que importan:
//
//   • FIRST  → cada prueba es rápida, independiente, repetible,
//               auto-validante y se escribe junto al código.
//   • AAA    → cada prueba ordena su contexto, ejecuta y verifica
//               (ver el método ejecutar(), que fuerza las tres fases).
//   • Self-validating → el resultado es un sí o un no. Una prueba que
//               "pasa" y no dice nada no sirve de nada.
//
// Lo que sí queda por resolver en una etapa siguiente: cobertura de código
// (xdebug/pcov) y ejecución automática en un pipeline. Queda anotado en el
// README en lugar de inventarse un resultado.
// ============================================================

/**
 * Caso de prueba: una clase base con las aserciones y el registro de
 * resultados. Cada subclase define pruebas() y llama a ejecutar() con un
 * nombre y una función.
 */
abstract class TestCase
{
    /** @var int Cantidad de pruebas que pasaron */
    private int $ok = 0;

    /** @var array<int, string> Descripción de las pruebas que fallaron */
    private array $fallos = [];

    /**
     * Declara las pruebas de esta clase. Las subclases la implementan.
     *
     * @return array<string, callable> nombre de la prueba => función
     */
    abstract public function pruebas(): array;

    /**
     * Corre todas las pruebas declaradas.
     *
     * @return int Código de salida: 0 si todo pasó, 1 si hubo algún fallo
     */
    public function correr(): int
    {
        $nombreClase = static::class;

        echo PHP_EOL . $nombreClase . PHP_EOL;
        echo str_repeat('-', strlen($nombreClase)) . PHP_EOL;

        // Cada prueba se ejecuta por separado: si una falla, la siguiente
        // igual corre. Eso es el principio Independent de FIRST: un fallo
        // no puede arrastrar a los demás y tapar el panorama real.
        foreach ($this->pruebas() as $nombre => $funcion) {
            try {
                $funcion($this);
                $this->ok++;
                echo '  [OK]    ' . $nombre . PHP_EOL;
            } catch (\AssertionError $e) {
                // Aserción fallida: la prueba corrió pero el resultado no
                // fue el esperado. Se registra el motivo, que es lo que
                // permite entender el fallo sin volver a correr a ciegas.
                $this->fallos[] = $nombre . ': ' . $e->getMessage();
                echo '  [FALLA] ' . $nombre . PHP_EOL;
                echo '          ' . $e->getMessage() . PHP_EOL;
            } catch (\Throwable $e) {
                // Error inesperado (excepción, error de tipeo en la prueba):
                // es un fallo de la prueba, no del código de producción, y
                // también tiene que verse.
                $this->fallos[] = $nombre . ': ' . get_class($e) . ' ' . $e->getMessage();
                echo '  [ERROR] ' . $nombre . PHP_EOL;
                echo '          ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
            }
        }

        return $this->resumen();
    }

    /**
     * Ejecuta una prueba aplicando la estructura AAA de forma explícita.
     *
     * Separar las tres fases dentro del método de prueba es lo que hace que
     * se lea como una especificación del comportamiento. Esta función no
     * aporta lógica: existe para que cada prueba deje las tres fases
     * visibles y con la misma forma, y para que el contexto de la fase
     * Arrange llegue a la fase Act sin variables compartidas.
     *
     * Sobre los argumentos: el resultado de cada fase se pasa a la siguiente.
     * PHP ignora los argumentos sobrantes en las funciones propias, así que
     * una fase que no necesita el dato puede declararse sin parámetros y
     * la lectura sigue siendo clara.
     *
     * @param callable $preparar  Fase Arrange: deja el contexto listo
     * @param callable $actuar    Fase Act: ejecuta el comportamiento
     * @param callable $verificar Fase Assert: comprueba el resultado
     * @return mixed El resultado de la fase Act
     */
    protected function ejecutar(callable $preparar, callable $actuar, callable $verificar): mixed
    {
        // Arrange
        $contexto = $preparar();
        // Act
        $resultado = $actuar($contexto);
        // Assert
        $verificar($resultado);
        return $resultado;
    }

    /** @return int Cantidad de pruebas que pasaron */
    public function cantidadOk(): int
    {
        return $this->ok;
    }

    /** @return array<int, string> Fallos registrados */
    public function fallos(): array
    {
        return $this->fallos;
    }

    // ============================================================
    // Aserciones
    //
    // Todas lanzan AssertionError cuando la condición no se cumple, y
    // devuelven el valor cuando sí se cumple (así se pueden encadenar
    // dentro de una expresión).
    // ============================================================

    /**
     * Afirma que una condición es verdadera.
     *
     * @param bool   $condicion Condición evaluada
     * @param string $mensaje   Qué se esperaba (aparece en el fallo)
     */
    protected function afirmarQue(bool $condicion, string $mensaje): void
    {
        if (!$condicion) {
            throw new \AssertionError($mensaje);
        }
    }

    /**
     * Afirma que un valor es verdadero.
     *
     * @param mixed  $valor   Valor evaluado
     * @param string $mensaje Descripción del valor esperado
     */
    protected function afirmarVerdadero(mixed $valor, string $mensaje = 'se esperaba true'): void
    {
        $this->afirmarQue($valor === true, $mensaje . ' (recibido: ' . $this->describir($valor) . ')');
    }

    /**
     * Afirma que un valor es falso.
     *
     * @param mixed  $valor   Valor evaluado
     * @param string $mensaje Descripción del valor esperado
     */
    protected function afirmarFalso(mixed $valor, string $mensaje = 'se esperaba false'): void
    {
        $this->afirmarQue($valor === false, $mensaje . ' (recibido: ' . $this->describir($valor) . ')');
    }

    /**
     * Afirma que dos valores son idénticos (mismo tipo y mismo valor).
     *
     * Se usa === y no == a propósito: "0" == 0 es true en PHP, y en una
     * prueba eso esconde exactamente el tipo de bug que se quiere cazar.
     *
     * @param mixed  $esperado Valor esperado
     * @param mixed  $real     Valor recibido
     * @param string $mensaje  Contexto del valor
     */
    protected function afirmarIgual(mixed $esperado, mixed $real, string $mensaje = ''): void
    {
        $contexto = $mensaje !== '' ? $mensaje . ': ' : '';
        $this->afirmarQue(
            $esperado === $real,
            $contexto . 'se esperaba ' . $this->describir($esperado) . ' y se recibió ' . $this->describir($real)
        );
    }

    /**
     * Afirma que un string contiene un fragmento.
     *
     * @param string $texto    Texto donde buscar
     * @param string $fragmento Fragmento que debe aparecer
     * @param string $mensaje  Contexto
     */
    protected function afirmarContiene(string $texto, string $fragmento, string $mensaje = ''): void
    {
        $contexto = $mensaje !== '' ? $mensaje . ': ' : '';
        $this->afirmarQue(
            str_contains($texto, $fragmento),
            $contexto . 'se esperaba que "' . $texto . '" contuviera "' . $fragmento . '"'
        );
    }

    /**
     * Afirma que un string NO contiene un fragmento.
     *
     * Es la aserción que protege los secretos: si un log contiene una
     * contraseña, esta es la prueba que tiene que romperse.
     *
     * @param string $texto    Texto donde buscar
     * @param string $fragmento Fragmento que NO debe aparecer
     * @param string $mensaje  Contexto
     */
    protected function afirmarNoContiene(string $texto, string $fragmento, string $mensaje = ''): void
    {
        $contexto = $mensaje !== '' ? $mensaje . ': ' : '';
        $this->afirmarQue(
            !str_contains($texto, $fragmento),
            $contexto . 'NO debía aparecer "' . $fragmento . '" en: ' . $texto
        );
    }

    /**
     * Afirma que un string cumple una expresión regular.
     *
     * @param string $texto Texto a evaluar
     * @param string $patron Expresión regular
     * @param string $mensaje Contexto
     */
    protected function afirmarCoincide(string $texto, string $patron, string $mensaje = ''): void
    {
        $contexto = $mensaje !== '' ? $mensaje . ': ' : '';
        $this->afirmarQue(
            (bool) preg_match($patron, $texto),
            $contexto . 'se esperaba que "' . $texto . '" coincidiera con ' . $patron
        );
    }

    /**
     * Afirma que una función lanza una excepción, y devuelve la excepción
     * para poder verificar el mensaje y el código.
     *
     * En este proyecto el código de la excepción ES el código HTTP (422, 401
     *...), así que verificarlo es verificar el contrato con el cliente.
     *
     * @param callable    $funcion    Código que se espera que falle
     * @param string      $clase      Clase de excepción esperada
     * @param int|null    $codigo     Código esperado (null = no se verifica)
     * @param string|null $mensajeEsp Fragmento esperado en el mensaje
     * @return \Throwable La excepción que se acaba de lanzar
     */
    protected function afirmarLanza(callable $funcion, string $clase, ?int $codigo = null, ?string $mensajeEsp = null): \Throwable
    {
        try {
            $funcion();
        } catch (\Throwable $e) {
            // Primero el tipo: si la función falla por otra razón, la
            // prueba tiene que decirlo, no pasar por una validación que no
            // ocurrió.
            $this->afirmarQue(
                $e instanceof $clase,
                'se esperaba ' . $clase . ' y se lanzó ' . get_class($e) . ': ' . $e->getMessage()
            );

            // Después el código (el contrato HTTP).
            if ($codigo !== null) {
                $this->afirmarIgual($codigo, $e->getCode(), 'código de la excepción');
            }

            // Después el mensaje: es lo que ve el usuario final.
            if ($mensajeEsp !== null) {
                $this->afirmarContiene($e->getMessage(), $mensajeEsp, 'mensaje de la excepción');
            }

            return $e;
        }

        throw new \AssertionError('se esperaba una excepción ' . $clase . ' y no se lanzó ninguna');
    }

    /**
     * Imprime el resumen y devuelve el código de salida.
     *
     * @return int 0 si no hubo fallos, 1 si hubo alguno
     */
    private function resumen(): int
    {
        $total = $this->ok + count($this->fallos);
        echo sprintf(
            '  %d/%d pruebas OK%s%s',
            $this->ok,
            $total,
            PHP_EOL,
            count($this->fallos) > 0 ? '  Fallos: ' . count($this->fallos) . PHP_EOL : ''
        );

        return count($this->fallos) > 0 ? 1 : 0;
    }

    /**
     * Convierte cualquier valor en algo legible en un mensaje de fallo.
     *
     * @param mixed $valor Valor a describir
     * @return string
     */
    private function describir(mixed $valor): string
    {
        if (is_string($valor)) {
            return '"' . $valor . '"';
        }
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }
        if ($valor === null) {
            return 'null';
        }
        if (is_array($valor)) {
            return 'array(' . json_encode($valor, JSON_UNESCAPED_UNICODE) . ')';
        }
        if (is_object($valor)) {
            return get_class($valor);
        }
        return (string) $valor;
    }
}
