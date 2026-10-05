<?php
// ============================================================
// tests/ValidadorTest.php - Pruebas de la validación de entradas
// ============================================================
// Módulo: "Calidad Profesional del Software - Testing"
// ------------------------------------------------------------
// Estas pruebas son la razón de ser de core/Validador.php.
//
// Antes de extraer el validador, esas reglas estaban escritas dentro de cada
// servicio y no había forma de probarlas sin levantar la API y la base de
// datos. Al ser funciones puras, se prueban en milisegundos y sin servidor:
// ese es el beneficio concreto de Separación de Responsabilidades.
//
// CASOS QUE SE PRUEBAN (los tres que pide el módulo):
//   • Camino feliz: un email y una contraseña válidos pasan y se normalizan.
//   • Bordes:    string vacío, cadena de espacios, valores al límite exacto
//                del máximo, texto con HTML, texto que solo tiene HTML.
//   • Fallas:    formato inválido, contraseña corta y larga, texto largo.
//
// El detalle del "valor en el límite exacto" importa: si el máximo es 150,
// un texto de 150 caracteres tiene que entrar y uno de 151 tiene que salir
// con 422. Es el tipo de borde donde aparecen los errores de ">= y >".
// ============================================================

require_once __DIR__ . '/TestCase.php';

class ValidadorTest extends TestCase
{
    /**
     * Las pruebas de esta clase.
     *
     * @return array<string, callable>
     */
    public function pruebas(): array
    {
        return [
            'un email válido se normaliza a minúsculas' => [$this, 'pruebaEmailNormalizado'],
            'un email con espacios se limpia' => [$this, 'pruebaEmailConEspacios'],
            'un email inválido se rechaza con 422' => [$this, 'pruebaEmailInvalido'],
            'un email vacío se rechaza' => [$this, 'pruebaEmailVacio'],
            'el login usa su propio mensaje y su propio código' => [$this, 'pruebaEmailMensajePropio'],
            'una contraseña de 6 y de 72 caracteres se acepta' => [$this, 'pruebaPasswordEnLosLimites'],
            'una contraseña corta se rechaza' => [$this, 'pruebaPasswordCorta'],
            'una contraseña larga se rechaza' => [$this, 'pruebaPasswordLarga'],
            'textoLimpio quita HTML y espacios' => [$this, 'pruebaTextoLimpio'],
            'textoLimpio de null devuelve cadena vacía' => [$this, 'pruebaTextoLimpioNulo'],
            'un texto obligatorio vacío se rechaza' => [$this, 'pruebaTextoObligatorioVacio'],
            'un texto con solo HTML se rechaza (borde)' => [$this, 'pruebaTextoSoloHtml'],
            'el límite de caracteres se respeta al borde' => [$this, 'pruebaLimiteAlBorde'],
            'textoOpcional devuelve null si no viene' => [$this, 'pruebaOpcionalNulo'],
            'textoOpcional acota si viene' => [$this, 'pruebaOpcionalViene'],
            'acortar recorta sin lanzar excepción' => [$this, 'pruebaAcortar'],
            'acortarOpcional devuelve null si no viene' => [$this, 'pruebaAcortarOpcionalNulo'],
        ];
    }

    // ============================================================
    // Email
    // ============================================================

    /** Camino feliz: el email vuelve normalizado, que es lo que se guarda. */
    public function pruebaEmailNormalizado(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::email('  Juan.Perez@Mail.COM  '),
            function (string $resultado) use ($t): void {
                $t->afirmarIgual('juan.perez@mail.com', $resultado);
            }
        );
    }

    /** El mismo caso, aislado: los espacios que se cuelan desde un formulario. */
    public function pruebaEmailConEspacios(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::email("\tana@mail.com\n"),
            function (string $resultado) use ($t): void {
                $t->afirmarIgual('ana@mail.com', $resultado);
            }
        );
    }

    /**
     * Caso de falla: formato inválido.
     *
     * Esta prueba no usa ejecutar() a propósito: si la excepción se lanzara
     * en la fase Act, el runner la reportaría como error. Aquí el intento
     * infructuoso y la comprobación están juntos, que es la forma natural de
     * probar un comportamiento que consiste en fallar.
     */
    public function pruebaEmailInvalido(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::email('no-es-un-email'),
            \InvalidArgumentException::class,
            422,
            'Email inválido'
        );
    }

    /** Borde: cadena vacía y null. */
    public function pruebaEmailVacio(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::email(''),
            \InvalidArgumentException::class,
            422
        );
        $t->afirmarLanza(
            fn() => Validador::email(null),
            \InvalidArgumentException::class,
            422
        );
    }

    /**
     * El módulo exige que los errores no filtren información. El login tiene
     * que responder 401 con un mensaje genérico, aunque el validador haya
     * encontrado un email mal formado: por eso el mensaje y el código son
     * parámetros, y no están fijos dentro del validador.
     */
    public function pruebaEmailMensajePropio(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::email('arroba', 'Email o contraseña inválidos', 401),
            \InvalidArgumentException::class,
            401,
            'Email o contraseña inválidos'
        );
    }

    // ============================================================
    // Contraseña
    // ============================================================

    /** Bordes: exactamente en los dos extremos del rango permitido. */
    public function pruebaPasswordEnLosLimites(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => [
                Validador::password('123456'),      // 6: el mínimo
                Validador::password(str_repeat('a', 72)), // 72: el máximo de bcrypt
            ],
            function (array $resultado) use ($t): void {
                $t->afirmarIgual('123456', $resultado[0]);
                $t->afirmarIgual(72, strlen($resultado[1]));
            }
        );
    }

    /** Falla: un carácter menos que el mínimo. */
    public function pruebaPasswordCorta(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::password('12345'),
            \InvalidArgumentException::class,
            422
        );
    }

    /** Falla: un carácter más que el máximo de bcrypt. */
    public function pruebaPasswordLarga(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::password(str_repeat('a', 73)),
            \InvalidArgumentException::class,
            422
        );
    }

    // ============================================================
    // Textos
    // ============================================================

    /** Camino feliz del saneamiento: HTML y espacios fuera. */
    public function pruebaTextoLimpio(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::textoLimpio('  <b>García</b> Pérez  '),
            function (string $resultado) use ($t): void {
                $t->afirmarIgual('García Pérez', $resultado);
            }
        );
    }

    /**
     * Borde: null. En PHP 8.1+ trim(null) es una deprecación, y el código
     * viejo (trim($data['x'] ?? '')) ya lo cubría, pero muchos otros lugares
     * no. Acá se garantiza que la función nunca recibe null.
     */
    public function pruebaTextoLimpioNulo(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::textoLimpio(null),
            function (string $resultado) use ($t): void {
                $t->afirmarIgual('', $resultado);
            }
        );
    }

    /** Falla: cadena vacía o solo espacios. */
    public function pruebaTextoObligatorioVacio(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::textoObligatorio('   ', 150, 'El nombre es obligatorio'),
            \InvalidArgumentException::class,
            422,
            'El nombre es obligatorio'
        );
    }

    /**
     * Borde interesante, y el que estaba fallando antes del refactor: un texto
     * formado solo por etiquetas HTML. Con el orden viejo (validar antes de
     * sanear) pasaba el control de "vacío" porque tenía caracteres, y se
     * guardaba como cadena vacía. Esta prueba existe para que nadie vuelva a
     * invertir ese orden.
     */
    public function pruebaTextoSoloHtml(TestCase $t): void
    {
        $t->afirmarLanza(
            fn() => Validador::textoObligatorio('<b></b>', 150, 'El nombre es obligatorio'),
            \InvalidArgumentException::class,
            422,
            'El nombre es obligatorio'
        );
    }

    /**
     * El borde del límite: 150 entra, 151 sale.
     * Si alguien cambia un ">" por un ">=", esta prueba se rompe.
     */
    public function pruebaLimiteAlBorde(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            function (): array {
                return [
                    'dentro' => Validador::textoObligatorio(str_repeat('a', 150), 150, 'muy largo'),
                    'error'  => null,
                ];
            },
            function (array $resultado) use ($t): void {
                $t->afirmarIgual(150, strlen($resultado['dentro']), '150 caracteres tienen que entrar');
                $t->afirmarLanza(
                    fn() => Validador::textoObligatorio(str_repeat('a', 151), 150, 'muy largo'),
                    \InvalidArgumentException::class,
                    422,
                    'muy largo'
                );
            }
        );
    }

    /**
     * Un campo opcional ausente NO es un error: significa "no lo toques".
     * Por eso devuelve null y no cadena vacía.
     */
    public function pruebaOpcionalNulo(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::textoOpcional(null, 1000, 'muy largo'),
            function (?string $resultado) use ($t): void {
                $t->afirmarIgual(null, $resultado);
            }
        );
    }

    /** Si viene, se sanea y se acota como cualquier otro texto. */
    public function pruebaOpcionalViene(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::textoOpcional(' <i>paracetamol</i> ', 1000, 'muy largo'),
            function (string $resultado) use ($t): void {
                $t->afirmarIgual('paracetamol', $resultado);
            }
        );
    }

    /**
     * acortar() recorta en vez de rechazar: es la diferencia entre "el motivo
     * de la consulta se guardó truncado" y "el usuario no pudo reservar el
     * turno por escribir de más". Para texto libre, recortar es mejor.
     */
    public function pruebaAcortar(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::acortar(str_repeat('á', 300), 255),
            function (string $resultado) use ($t): void {
                // mb_substr cuenta caracteres, no bytes: con acentos, 255
                // caracteres son más de 255 bytes. Si alguien reemplaza
                // mb_substr por substr, el texto queda cortado a la mitad de
                // una letra y esta prueba se rompe.
                $t->afirmarIgual(255, mb_strlen($resultado, 'UTF-8'));
                $t->afirmarIgual($resultado, mb_strtolower($resultado, 'UTF-8'), 'debería ser solo acentos sin acortar a la mitad');
            }
        );
    }

    /** Igual que acortar, pero respetando el "campo ausente". */
    public function pruebaAcortarOpcionalNulo(TestCase $t): void
    {
        $t->ejecutar(
            fn() => null,
            fn() => Validador::acortarOpcional(null, 255),
            function (?string $resultado) use ($t): void {
                $t->afirmarIgual(null, $resultado);
            }
        );
    }
}
