<?php
// ============================================================
// core/Response.php - Respuestas JSON consistentes
// ============================================================
// Módulo: "CRUD API con MySQL, PDO y Repository"
// ------------------------------------------------------------
// Todas las respuestas de la API siguen la MISMA estructura.
// Tanto éxitos como errores pasan por esta clase, lo que
// garantiza que cualquier frontend o herramienta de pruebas
// pueda interpretar la respuesta de forma predecible.
//
//   Éxito: { ok: true,  mensaje: "...", data: {...} }
//   Error: { ok: false, mensaje: "...", errores: {...} }
// ============================================================

class Response
{
    /**
     * Respuesta exitosa (200/201 OK)
     * @param mixed $data     Payload de datos (array, objeto, null)
     * @param string $mensaje Descripción legible del resultado
     * @param int $status     Código HTTP (200, 201, ...)
     */
    public static function ok($data = null, string $mensaje = 'OK', int $status = 200): void
    {
        self::json([
            // 'ok' => true: indica que la operación fue exitosa
            'ok' => true,
            // 'mensaje': descripción legible del resultado
            'mensaje' => $mensaje,
            // 'data': el payload (datos) que el frontend necesita
            'data' => $data,
        ], $status);
    }

    /**
     * Respuesta de error
     * @param string $mensaje Descripción del error
     * @param int $status     Código HTTP (400, 401, 404, 422, 500, ...)
     * @param array|null $errores Detalle por campo (para validaciones)
     * @param string|null $code   Código interno de error (opcional)
     */
    public static function error(string $mensaje, int $status = 400, array $errores = [], ?string $code = null): void
    {
        // Estructura base de la respuesta de error (siempre consistente)
        $cuerpo = [
            // 'ok' => false: indica que la operación falló
            'ok' => false,
            // 'mensaje': describe el error de forma legible
            'mensaje' => $mensaje,
            // 'errores': detalle por campo (útil para validaciones de formularios)
            'errores' => $errores,
        ];

        // Seguridad: los errores 5xx jamás exponen detalles internos.
        // Se agrega un requestId para correlacionar con logs internos
        // sin filtrar stack traces, rutas ni credenciales (módulo
        // "Seguridad Básica para APIs - Manejo Seguro de Errores").
        if ($status >= 500) {
            // Oculta el detalle real del error al cliente (solo deja un mensaje genérico)
            $cuerpo['mensaje'] = 'Error interno del servidor';
            // Genera un identificador aleatorio (bin2hex de 8 bytes) para correlacionar con logs
            $cuerpo['requestId'] = bin2hex(random_bytes(8));
            // Código interno: usa el recibido o el genérico 'ERR_INTERNAL' (operador ??)
            $cuerpo['code'] = $code ?? 'ERR_INTERNAL';
        }

        // Envía el cuerpo ya armado con el código HTTP correspondiente y termina la ejecución
        self::json($cuerpo, $status);
    }

    /**
     * Serializa el cuerpo a JSON y envía la respuesta HTTP
     */
    private static function json(array $cuerpo, int $status): void
    {
        // Establece el código de estado HTTP de la respuesta (200, 400, 401, ...)
        http_response_code($status);
        // Declara que el contenido de la respuesta es JSON (lo espera el frontend)
        header('Content-Type: application/json');
        // Serializa el arreglo a un string JSON; JSON_UNESCAPED_UNICODE NO escapa los tildes (UTF-8)
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE);
        // Detiene la ejecución del script (no se debe seguir procesando después de responder)
        exit;
    }
}