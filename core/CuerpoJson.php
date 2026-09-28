<?php
// ============================================================
// core/CuerpoJson.php - Lectura y validación del cuerpo de las peticiones
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Este archivo resuelve un problema que se repite en TODOS los endpoints
// que reciben datos: leer el body de una petición en PHP y entender si es
// válido.
//
// EL PROBLEMA QUE RESUELVE
// ------------------------
// El código clásico de un controlador es:
//
//     $data = json_decode(file_get_contents('php://input'), true) ?? [];
//
// Ese patrón CONFLATA dos errores muy distintos:
//
//   1. "No vino nada"    → un body vacío
//   2. "Vino algo roto"  → body que no es JSON, JSON truncado, JSON con coma
//
// En los dos casos `?? []` devuelve un arreglo vacío, así que el usuario
// recibe un error de validación sobre un campo que él sí mandó. El mensaje
// dice algo como "Debe indicar la fecha" cuando el problema real es que el
// navegador, un proxy o un cliente móvil cortaron la petición a la mitad.
// Ese es el tipo de error que hace perder una hora de debugging buscando un
// bug que no está en el código del endpoint.
//
// LA SOLUCIÓN
// -----------
// Este helper separa los casos y reporta cada uno con su propio código:
//
//   - Body vacío                 → 400 con "no se recibieron datos"
//   - JSON mal formado           → 400 con el motivo que reporta PHP
//   - JSON que no es objeto      → 400 (un endpoint espera campos, no listas)
//   - Objeto JSON válido ({} []) → se acepta y devuelve []
//
// El error se lanza siempre como RuntimeException con el código HTTP en el
// segundo argumento. Así los controladores solo necesitan catch de dos tipos
// (InvalidArgumentException para validaciones y RuntimeException para todo lo
// demás) y no tienen que saber nada de json_decode.
class CuerpoJson
{
    /**
     * Lee y valida el cuerpo JSON de la petición.
     *
     * @param bool $permitirVacio Si es true, un body vacío devuelve [] sin error
     * @return array El cuerpo ya decodificado como arreglo asociativo
     * @throws RuntimeException Si el body falta, es inválido o no es un objeto
     */
    public static function leer(bool $permitirVacio = false): array
    {
        // Se lee el cuerpo crudo de la petición.
        // 'php://input' es el flujo especial que PHP expone con el body sin
        // procesar, a diferencia de $_POST que solo funciona con formularios.
        $crudo = file_get_contents('php://input');

        // Si no se pudo leer nada (puede pasar con ciertos métodos) o vino
        // vacío, se trata igual
        if ($crudo === false || trim((string)$crudo) === '') {
            // Se decide si un body vacío es válido para este endpoint
            if ($permitirVacio) {
                return [];
            }

            throw new \RuntimeException(
                'No se recibieron datos. Se esperaba un cuerpo JSON.',
                400
            );
        }

        // Se intenta decodificar el JSON.
        // Se pasa true como segundo parámetro para que además se lance una
        // excepción si el JSON está mal formado, en lugar de devolver null
        // (que es lo que hace json_decode por defecto y oculta el error).
        //
        // El catch es indispensable: JSON_THROW_ON_ERROR lanza JsonException,
        // que NO es RuntimeException. Sin traducción, el controlador la
        // recibiría como una excepción desconocida y respondería 500
        // "error del servidor" por un error que en realidad es del cliente.
        // Normalizar el tipo en el helper mantiene los controladores limpios:
        // todos manejan RuntimeException, sin conocer el detalle interno.
        try {
            $datos = json_decode($crudo, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // 400 Bad Request: la petición está mal, el servidor está bien.
            // Se incluye el motivo que reporta PHP porque describe algo que
            // ayuda a quien desarrolla (sintaxis incorrecta, carácter de
            // control, etc.) y no revela nada del servidor.
            throw new \RuntimeException(
                'El cuerpo de la petición no es un JSON válido: ' . $e->getMessage(),
                400
            );
        }

        // Si el JSON es válido pero NO es un objeto (por ejemplo "5" o "[1,2]"),
        // no se puede trabajar con él: un endpoint espera algo tipo
        // {"id_medico": 4, "fecha": "..."}
        if (!is_array($datos)) {
            throw new \RuntimeException(
                'El cuerpo de la petición debe ser un objeto JSON con los datos del turno.',
                400
            );
        }

        // Si llegó una lista (por ejemplo "[1,2,3]"), se rechaza: el endpoint
        // espera campos con nombre, no un índice numérico.
        //
        // OJO con la condición: la consulta al array se hace SOLO si tiene
        // elementos. Un objeto JSON vacío ("{}") se decodifica a un arreglo
        // vacío, que es indistinguible de la lista vacía "[]", y la función
        // que detecta listas devuelve true para un arreglo vacío. Sin esta
        // guarda, mandar "{}" a un endpoint de actualización (que es algo
        // perfectamente válido: "no quiero cambiar nada") se rechazaba con un
        // confuso "debe ser un objeto, no una lista".
        if (count($datos) > 0 && self::esLista($datos)) {
            throw new \RuntimeException(
                'El cuerpo de la petición debe ser un objeto JSON con campos, no una lista.',
                400
            );
        }

        return $datos;
    }

    /**
     * Indica si un arreglo tiene claves numéricas secuenciales (0, 1, 2...).
     *
     * Existe como método propio y no como llamada directa a la función nativa
     * porque esa función es de PHP 8.1 y el proyecto se declara compatible con
     * PHP 8.0. En 8.0 la función no existe y, sin esta alternativa, el helper
     * rompería con un error fatal en lugar de una excepción manejable.
     *
     * @param array $arreglo Arreglo a inspeccionar
     * @return bool True si es una lista secuencial
     */
    private static function esLista(array $arreglo): bool
    {
        // En PHP 8.1 o superior se usa la función nativa
        if (function_exists('array_is_list')) {
            return array_is_list($arreglo);
        }

        // Alternativa para PHP 8.0: si las claves son exactamente 0..n-1 y en
        // ese orden, el arreglo es una lista
        return array_keys($arreglo) === range(0, count($arreglo) - 1);
    }
}
