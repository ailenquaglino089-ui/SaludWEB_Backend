<?php
// ============================================================
// persistence/DisponibilidadRepository.php - Capa de persistencia (Agenda)
// ============================================================
// Repository Pattern para acceso a datos de la agenda horaria del profesional.
class DisponibilidadRepository implements DisponibilidadRepositoryInterface
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
     * Obtiene todos los bloques de atención de un médico
     * @param int $idMedico ID del médico
     * @param bool $soloActivos Si filtra por activo = 1
     * @return array Arreglo de bloques
     */
    public function obtenerPorMedico(int $idMedico, bool $soloActivos = true): array
    {
        // Se prepara la consulta con el id del médico como parámetro
        $stmt = $this->pdo->prepare(
            "SELECT * FROM disponibilidades
             WHERE id_medico = ?"
            . ($soloActivos ? " AND activo = 1" : "")
            . " ORDER BY dia_semana ASC, hora_inicio ASC"
        );
        $stmt->execute([$idMedico]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los bloques de un médico para un día de la semana concreto
     * @param int $idMedico ID del médico
     * @param int $diaSemana 1 = lunes ... 7 = domingo (convención de MySQL WEEKDAY)
     * @return array Arreglo de bloques de ese día
     */
    public function obtenerPorMedicoYDia(int $idMedico, int $diaSemana): array
    {
        // Se filtran los dos criterios: el médico y el día de la semana.
        // El índice idx_disponibilidad_medico_dia (id_medico, dia_semana) hace
        // que esta consulta sea rápida aunque la tabla tenga miles de filas.
        $stmt = $this->pdo->prepare(
            "SELECT * FROM disponibilidades
             WHERE id_medico = ? AND dia_semana = ? AND activo = 1
             ORDER BY hora_inicio ASC"
        );
        $stmt->execute([$idMedico, $diaSemana]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un bloque de atención por su ID
     * @param int $id ID del bloque
     * @return array|null Datos del bloque o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM disponibilidades WHERE id = ?");
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila : null;
    }

    /**
     * Crea un nuevo bloque de atención
     * @param array $data Datos del bloque
     * @return int ID del bloque creado
     */
    public function crear(array $data): int
    {
        // INSERT con 6 marcadores posicionales: todos los valores viajan
        // como parámetros separados de la estructura SQL
        $stmt = $this->pdo->prepare(
            "INSERT INTO disponibilidades
             (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            // Obligatorio: el profesional al que pertenece la agenda
            (int)$data['id_medico'],
            // Obligatorio: 1 = lunes ... 7 = domingo
            (int)$data['dia_semana'],
            // Hora de inicio de la atención (formato TIME de MySQL: HH:MM:SS)
            $data['hora_inicio'],
            // Hora de fin de la atención
            $data['hora_fin'],
            // Largo de cada turno dentro del bloque (30 por defecto)
            (int)($data['duracion_minutos'] ?? 30),
            // Si el bloque nace activo o desactivado
            (int)($data['activo'] ?? 1),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un bloque de atención existente
     *
     * Se construye el SET de forma dinámica con la lista de columnas
     * permitidas, en lugar de escribir todos los campos siempre. Así el
     * servicio puede mandar solo lo que cambió y, sobre todo, se evita que
     * un dato que no viene en la petición se pise con un valor vacío: si el
     * cuerpo no trae 'hora_inicio', la columna no aparece en el UPDATE y
     * conserva su valor anterior.
     *
     * @param int $id ID del bloque a actualizar
     * @param array $datos Columnas a modificar (ya validadas por el servicio)
     * @return bool true si se actualizó
     */
    public function actualizar(int $id, array $datos): bool
    {
        // Lista blanca: solo se permiten estas columnas en el SET.
        // Es la protección contra mass assignment: si el servicio mandara
        // 'id_medico' o 'activo' por error, se ignoran.
        $permitidas = ['dia_semana', 'hora_inicio', 'hora_fin', 'duracion_minutos'];

        $set = [];
        $parametros = [];

        foreach ($permitidas as $columna) {
            // Se ignora lo que no venga en el array
            if (!array_key_exists($columna, $datos)) {
                continue;
            }
            $set[] = "$columna = ?";
            $parametros[] = $datos[$columna];
        }

        // Si no quedó ninguna columna, no hay nada que actualizar
        if (count($set) === 0) {
            return false;
        }

        // El id va al final porque es el único parámetro posicional del WHERE
        $parametros[] = $id;

        $stmt = $this->pdo->prepare(
            'UPDATE disponibilidades SET ' . implode(', ', $set) . ' WHERE id = ?'
        );

        return $stmt->execute($parametros);
    }

    /**
     * Elimina un bloque de atención
     * @param int $id ID del bloque
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM disponibilidades WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Activa o desactiva un bloque sin borrarlo
     * Desactivar es preferible a borrar cuando el bloque ya tiene citas:
     // se conserva el histórico y simplemente deja de ofrecerse al paciente.
     * @param int $id ID del bloque
     * @param bool $activo true para activar, false para desactivar
     * @return bool true si se actualizó
     */
    public function cambiarActivo(int $id, bool $activo): bool
    {
        // El booleano se convierte a 1/0 con el operador ?:, que MySQL
        // entiende como TINYINT(1) en la columna activo
        $stmt = $this->pdo->prepare("UPDATE disponibilidades SET activo = ? WHERE id = ?");
        return $stmt->execute([$activo ? 1 : 0, $id]);
    }

    /**
     * Verifica si un bloque pertenece a determinado médico
     * Se usa para autorizar: un médico no puede tocar el bloque de otro.
     * @param int $id ID del bloque
     * @param int $idMedico ID del médico que dice ser el dueño
     * @return bool true si el bloque es de ese médico
     */
    public function esDeMedico(int $id, int $idMedico): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM disponibilidades WHERE id = ? AND id_medico = ?"
        );
        $stmt->execute([$id, $idMedico]);
        // Si el conteo es mayor que 0, existe el bloque con ese par (id, id_medico)
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
