<?php
// ============================================================
// persistence/EspecialidadRepository.php - Capa de persistencia (Especialidades)
// ============================================================
// Repository Pattern para acceso a datos del catálogo de especialidades.
// Es el único lugar de la aplicación donde hay SQL de esta tabla.
class EspecialidadRepository implements EspecialidadRepositoryInterface
{
    // Conexión PDO guardada para ejecutar todas las consultas SQL del repositorio
    private $pdo;

    /**
     * Constructor con inyección de dependencias
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        // Recibe la conexión "desde afuera" (inyección de dependencias) y la guarda en el atributo
        $this->pdo = $pdo;
    }

    /**
     * Obtiene todas las especialidades
     * @param bool $soloActivas Si es true (por defecto) filtra por activo = 1
     * @return array Arreglo de especialidades
     */
    public function obtenerTodas(bool $soloActivas = true): array
    {
        // query() ejecuta el SELECT directo: no lleva parámetros, así que no hay
        // inyección SQL posible y se evita el paso extra de prepare() + execute()
        $sql = "SELECT e.*, (
                    SELECT COUNT(*) FROM medicos m
                    WHERE m.especialidad = e.nombre AND m.activo = 1
                 ) AS cantidad_medicos
                 FROM especialidades e";
        // Si se piden solo las activas, se agrega el WHERE por activo = 1
        if ($soloActivas) {
            $sql .= " WHERE e.activo = 1";
        }
        // Orden alfabético: el catálogo se muestra ordenado y el usuario lo espera así
        $sql .= " ORDER BY e.nombre ASC";
        // fetchAll() trae todas las filas como arrays asociativos
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una especialidad por su ID
     * @param int $id ID de la especialidad
     * @return array|null Datos de la especialidad o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        // Consulta preparada: el ? se reemplaza por el parámetro al ejecutar
        $stmt = $this->pdo->prepare("SELECT * FROM especialidades WHERE id = ?");
        // execute() bindea el id en el marcador de posición
        $stmt->execute([$id]);
        // fetch() devuelve la primera (única) fila o false si no hay coincidencia
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        // Si se encontró fila se devuelve el array; si dio false, se devuelve null
        return $fila ? $fila : null;
    }

    /**
     * Busca una especialidad por su nombre exacto
     * Se usa antes de crear para no duplicar especialidades por diferencia de mayúsculas
     * @param string $nombre Nombre exacto de la especialidad
     * @return array|null Datos de la especialidad o null si no existe
     */
    public function obtenerPorNombre(string $nombre): ?array
    {
        // Consulta preparada con el nombre como parámetro
        $stmt = $this->pdo->prepare("SELECT * FROM especialidades WHERE nombre = ?");
        $stmt->execute([$nombre]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila : null;
    }

    /**
     * Crea una nueva especialidad
     * @param string $nombre Nombre de la especialidad
     * @return int ID de la especialidad creada
     */
    public function crear(string $nombre): int
    {
        // INSERT con marcador posicional: el nombre viaja SIEMPRE como parámetro,
        // nunca concatenado al texto SQL (protección contra inyección SQL)
        $stmt = $this->pdo->prepare("INSERT INTO especialidades (nombre) VALUES (?)");
        $stmt->execute([$nombre]);
        // lastInsertId() devuelve el ID del registro recién insertado
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza el nombre de una especialidad
     * @param int $id ID de la especialidad
     * @param string $nombre Nuevo nombre
     * @return bool true si se actualizó
     */
    public function actualizar(int $id, string $nombre): bool
    {
        // UPDATE preparado: SET nombre = ? y WHERE id = ? (el orden de los ? importa)
        $stmt = $this->pdo->prepare("UPDATE especialidades SET nombre = ? WHERE id = ?");
        return $stmt->execute([$nombre, $id]);
    }

    /**
     * Elimina una especialidad
     * @param int $id ID de la especialidad
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        // DELETE preparado que borra la especialidad con ese id
        $stmt = $this->pdo->prepare("DELETE FROM especialidades WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Cuenta los médicos activos de una especialidad
     * @param int $id ID de la especialidad
     * @return int Cantidad de médicos activos con esa especialidad
     */
    public function contarMedicos(int $id): int
    {
        // JOIN por el nombre de la especialidad (no por id) porque medicos.especialidad
        // es texto libre preexistente en el proyecto. COUNT(*) es barato: solo un número.
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM medicos m
             JOIN especialidades e ON m.especialidad = e.nombre
             WHERE e.id = ? AND m.activo = 1"
        );
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
