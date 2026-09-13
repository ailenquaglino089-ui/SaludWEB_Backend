<?php
// ============================================================
// core/Secret.php - Gestión del secreto JWT
// ============================================================
// Módulo: "Login API con JWT - Autenticación en Aplicaciones Modernas"
// ------------------------------------------------------------
// El secreto JWT nunca debe estar en el código fuente.
// Orden de resolución (buenas prácticas):
//   1. Variable de entorno JWT_SECRET (producción)
//   2. Archivo persistente storage/secret.key (desarrollo local)
//   3. Se genera automáticamente la primera vez (con alta entropía)
// ============================================================

class Secret
{
    /**
     * Devuelve el secreto con el que se firman/verifican los JWT.
     * La generación usa random_bytes() con 48 bytes (96 hex chars).
     */
    public static function obtener(): string
    {
        // 1. Prioridad: variable de entorno (obligatoria en producción)
        $env = getenv('JWT_SECRET');
        // Si existe y tiene al menos 48 caracteres, es un secreto válido
        if (is_string($env) && strlen($env) >= 48) {
            // Lo usa directamente (prioridad máxima, caso producción)
            return $env;
        }

        // En producción el JWT_SECRET debe venir del entorno, nunca
        // generarse dinámicamente (invalidaría los tokens al reiniciar).
        if (Config::esProduccion()) {
            // En producción no se puede operar sin un secreto real: corta la ejecución
            http_response_code(500);
            // Devuelve un error JSON sin exponer detalles internos del servidor
            echo json_encode(['ok' => false, 'mensaje' => 'Configuración de servidor incompleta'], JSON_UNESCAPED_UNICODE);
            // Detiene el script: no se puede firmar JWT sin un secreto definido
            exit;
        }

        // 2. Fallback (solo desarrollo): archivo persistente fuera del código
        $archivo = __DIR__ . '/../storage/secret.key';
        // Si ya existe el archivo de secreto persistido...
        if (is_file($archivo)) {
            // Lee su contenido, lo convierte a string y le quita espacios en blanco de los bordes
            $guardado = trim((string) file_get_contents($archivo));
            // El secreto guardado también debe cumplir la longitud mínima de seguridad
            if (strlen($guardado) >= 48) {
                // Usa el secreto del archivo (caso reutilización en desarrollo)
                return $guardado;
            }
        }

        // 3. Generación automática con alta entropía (primera ejecución en dev)
        // bin2hex(random_bytes(48)) produce 96 caracteres hexadecimales aleatorios
        $secreto = bin2hex(random_bytes(48));
        // Obtiene la carpeta contenedora del archivo (ej: storage/)
        $dir = dirname($archivo);
        // Si la carpeta no existe todavía...
        if (!is_dir($dir)) {
            // La crea con permisos 0775 y de forma recursiva (crea varias carpetas de una vez si hace falta)
            mkdir($dir, 0775, true);
        }
        // Guarda el secreto en el archivo; LOCK_EX evita que otro proceso lo escriba a la vez
        file_put_contents($archivo, $secreto, LOCK_EX);

        // Finalmente entrega el secreto recién generado para usarlo al firmar JWT
        return $secreto;
    }
}