<?php
// ============================================================
// persistence/PacienteRepository.php - Capa de persistencia (Pacientes)
// ============================================================
// Repository Pattern: abstracción del acceso a datos
// Solo contiene consultas SQL, NO lógica de negocio

// La clase implementa PacienteRepositoryInterface: cumple el contrato definido por la interfaz
class PacienteRepository implements PacienteRepositoryInterface
{
    // Atributo que guarda la conexión PDO (PHP Data Objects) a MySQL
    private $pdo;

    /**
     * Constructor: recibe la conexión PDO por inyección de dependencias
     * @param PDO $pdo Conexión activa a la base de datos MySQL
     */
    public function __construct(PDO $pdo)
    {
        // Asigna la conexión que llega "desde afuera" al atributo de la instancia
        $this->pdo = $pdo;
    }

    /**
     * Obtiene TODOS los pacientes
     * Ordenados por activos primero, luego por nombre alfabéticamente
     * @return array Arreglo de pacientes
     */
    public function obtenerTodos(): array
    {
        // query() ejecuta el SELECT directamente (no tiene parámetros, no hay riesgo de inyección SQL)
        $stmt = $this->pdo->query(
            // SELECT: trae todas las columnas de pacientes (alias pac) y el nombre de la obra social
            // LEFT JOIN: une obras_sociales para mostrar el nombre_obra; si el paciente no tiene obra social igual aparece (side izquierda)
            // ORDER BY pac.activo DESC: pacientes activos primero; pac.nombre ASC: orden alfabético
            "SELECT pac.*, os.nombre_obra as obra_social
             FROM pacientes pac
             LEFT JOIN obras_sociales os ON pac.id_obra_social = os.id
             ORDER BY pac.activo DESC, pac.nombre ASC"
        );
        // fetchAll(PDO::FETCH_ASSOC) obtiene todas las filas como arrays asociativos [columna => valor]
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un paciente por su ID
     * @param int $id ID del paciente
     * @return array|null Datos del paciente o null
     */
    public function obtenerPorId(int $id): ?array
    {
        // Consulta preparada: el ? es un marcador de posición que evita inyección SQL
        $stmt = $this->pdo->prepare(
            // Misma consulta con JOIN para traer la obra social, filtrada por id del paciente
            "SELECT pac.*, os.nombre_obra as obra_social
             FROM pacientes pac
             LEFT JOIN obras_sociales os ON pac.id_obra_social = os.id
             WHERE pac.id = ?"
        );
        // execute([$id]) reemplaza el ? con el valor real del id y ejecuta la consulta
        $stmt->execute([$id]);
        // fetch() devuelve UNA fila (la primera) o false si no hay resultados
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Operador ?: si $result es false devuelve null (contrato de la interfaz); si no, devuelve el array
        return $result ?: null;
    }

    /**
     * Obtiene un paciente por su DNI
     * @param string $dni DNI del paciente
     * @return array|null Datos del paciente o null
     */
    public function obtenerPorDni(string $dni): ?array
    {
        // Consulta preparada que filtra por la columna dni (protege contra inyección SQL)
        $stmt = $this->pdo->prepare("SELECT * FROM pacientes WHERE dni = ?");
        // Ejecuta la consulta pasando el dni como parámetro
        $stmt->execute([$dni]);
        // Obtiene la primera fila encontrada (o false)
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Devuelve null si no existe un paciente con ese dni, o el array asociativo si existe
        return $result ?: null;
    }

    /**
     * Crea un nuevo paciente
     * @param array $data Datos del paciente
     * @return int ID del paciente creado
     */
    public function crear(array $data): int
    {
        // INSERT INTO: inserta una fila en la tabla pacientes con los ? como marcadores posicionales
        $stmt = $this->pdo->prepare(
            "INSERT INTO pacientes (dni, nombre, id_obra_social, activo) VALUES (?, ?, ?, ?)"
        );
        // Ejecuta el INSERT con los valores reales; ?? = sí la clave no viene, usa el valor por defecto
        $stmt->execute([
            $data['dni'] ?? null,   // Opcional (puede ser null)
            $data['nombre'],         // Obligatorio
            $data['id_obra_social'] ?? 1,  // Por defecto obra social 1
            $data['activo'] ?? 1     // Por defecto activo = 1 (habilitado)
        ]);
        // lastInsertId() devuelve el id generado automáticamente (AUTO_INCREMENT); (int) lo garantiza como entero
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un paciente
     * @param int $id ID del paciente
     * @param array $data Campos a actualizar
     * @return bool true si se actualizó
     */
    public function actualizar(int $id, array $data): bool
    {
        // Arreglos que se llenan dinámicamente según qué campos vengan en $data
        $campos = [];  // Partes "campo = ?" de la cláusula SET
        $valores = []; // Valores que reemplazarán a los ?

        // Si viene el dni en los datos, se agrega al UPDATE
        if (isset($data['dni'])) {
            $campos[] = 'dni = ?';
            $valores[] = $data['dni'];
        }
        // Si viene el nombre, se agrega al UPDATE
        if (isset($data['nombre'])) {
            $campos[] = 'nombre = ?';
            $valores[] = $data['nombre'];
        }
        // Si viene la obra social, se agrega al UPDATE
        if (isset($data['id_obra_social'])) {
            $campos[] = 'id_obra_social = ?';
            $valores[] = $data['id_obra_social'];
        }
        // Si viene el estado activo, se agrega normalizado a 1 (true) o 0 (false)
        if (isset($data['activo'])) {
            $campos[] = 'activo = ?';
            $valores[] = $data['activo'] ? 1 : 0;
        }

        // Si no se recibió ningún campo, no hay nada que actualizar -> false
        if (empty($campos)) {
            return false;
        }

        // Se agrega el id del paciente al final (es el último ? de la cláusula WHERE)
        $valores[] = $id;
        // implode() une las partes SET con coma: ej "dni = ?, nombre = ?"; se concatena con el WHERE id = ?
        $sql = "UPDATE pacientes SET " . implode(', ', $campos) . " WHERE id = ?";
        // Se prepara la consulta (los valores van por ?? como parámetros, sin riesgo de inyección SQL)
        $stmt = $this->pdo->prepare($sql);
        // execute($valores) asigna los valores en orden; retorna true si la actualización fue exitosa
        return $stmt->execute($valores);
    }

    /**
     * Elimina un paciente
     * @param int $id ID del paciente
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        // Consulta preparada: DELETE borra la fila del paciente con ese id (parámetro ? = protección anti inyección SQL)
        $stmt = $this->pdo->prepare("DELETE FROM pacientes WHERE id = ?");
        // Ejecuta y devuelve true si execute() tuvo éxito (no verifica filas afectadas)
        return $stmt->execute([$id]);
    }
}
