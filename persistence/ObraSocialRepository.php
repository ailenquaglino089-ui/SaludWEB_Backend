<?php
// ============================================================
// persistence/ObraSocialRepository.php - Acceso a datos
// ============================================================
// Módulo: "CRUD API con Repository"
// El Repository es el ÚNICO lugar del proyecto donde se escribe
// SQL. Devuelve datos limpios; no decide HTTP ni lee $_REQUEST.
// A diferencia de los otros repositorios, este NO implementa una interfaz
// (basta con la clase concreta porque solo tiene un método de lectura).
class ObraSocialRepository
{
    // Propiedad tipada: PDO es la conexión a MySQL usada para ejecutar las consultas
    private PDO $pdo;

    // Constructor por inyección de dependencias: recibe la conexión lista para usar
    public function __construct(PDO $pdo)
    {
        // Guarda la conexión en el atributo para usarla en todos los métodos
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todas las obras sociales ordenadas por nombre
     * @return array Arreglo de obras sociales
     */
    public function obtenerTodas(): array
    {
        // query() ejecuta el SELECT directamente (sin parámetros, no hay riesgo de inyección SQL)
        $stmt = $this->pdo->query(
            // SELECT: solo se traen las columnas id y nombre_obra (catálogo para formularios)
            // ORDER BY nombre_obra ASC: orden alfabético ascendente (A-Z)
            "SELECT id, nombre_obra
             FROM obras_sociales
             ORDER BY nombre_obra ASC"
        );
        // fetchAll(PDO::FETCH_ASSOC) devuelve todas las filas como arrays asociativos [columna => valor]
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}