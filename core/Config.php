<?php
// ============================================================
// core/Config.php - Configuración y validación del entorno
// ============================================================
// Módulo: "Seguridad Básica para APIs - Variables de Entorno"
// ------------------------------------------------------------
// • Centraliza el acceso a variables de entorno (.env / getenv())
// • Define el entorno (development | production) y su comportamiento
// • En PRODUCCIÓN valida que todos los secretos requeridos existan
//   (de lo contrario la app arranca en un estado inseguro)
// ============================================================

class Config
{
    /**
     * Entorno actual de la aplicación.
     * Default: development (comportamiento relajado para XAMPP local).
     */
    public static function appEnv(): string
    {
        // Lee la variable de entorno APP_ENV (puede no existir si no está configurada)
        $env = getenv('APP_ENV');
        // Si es un string no vacío lo devuelve en minúsculas; si no, usa 'development' por defecto
        return is_string($env) && $env !== '' ? strtolower($env) : 'development';
    }

    /**
     * ¿Estamos en producción?
     */
    public static function esProduccion(): bool
    {
        // True solo si el entorno resuelto es exactamente 'production'
        return self::appEnv() === 'production';
    }

    /**
     * Lee una variable de entorno con un valor por defecto.
     */
    public static function get(string $variable, string $default = ''): string
    {
        // Lee la variable de entorno solicitada
        $valor = getenv($variable);
        // Devuelve el valor si existe; caso contrario devuelve el valor por defecto
        return is_string($valor) && $valor !== '' ? $valor : $default;
    }

    /**
     * Valida que TODAS las variables obligatorias estén presentes.
     * En producción lanza una excepción si falta alguna; así la app
     * NUNCA arranca con un secreto vacío o hardcodeado.
     *
     * @throws RuntimeException Si en producción falta una variable requerida
     */
    public static function validar(): void
    {
        // En desarrollo no se exigen variables: se usa lo que haya (o default) sin bloquear
        if (!self::esProduccion()) {
            return;
        }

        // Lista de variables que SÍ o SÍ deben existir en producción
        $requeridas = ['JWT_SECRET', 'APP_ENV'];
        // Arreglo donde se acumularán las variables que falten
        $faltantes = [];

        // Recorre variable por variable para comprobar su presencia
        foreach ($requeridas as $var) {
            $valor = getenv($var);
            // Si no es un string o está vacía, se considera "faltante"
            if (!is_string($valor) || $valor === '') {
                $faltantes[] = $var;
            }
        }

        // Si hubo alguna variable faltante, se lanza una excepción...
        if (!empty($faltantes)) {
            // RuntimeException: error de ejecución que detiene el arranque de la app
            throw new \RuntimeException(
                // El mensaje enumera las variables que faltan (implode las une con comas)
                'Variables de entorno requeridas en producción no definidas: ' . implode(', ', $faltantes)
            );
        }
    }
}