<?php
// ============================================================
// persistence/PrescripcionRepository.php - Capa de persistencia (Prescripciones)
// ============================================================
// Repository Pattern para acceso a datos de prescripciones

// La clase implementa PrescripcionRepositoryInterface: respeta el contrato de métodos definido en la interfaz
class PrescripcionRepository implements PrescripcionRepositoryInterface
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
     * Obtiene todas las prescripciones
     * Ordenadas por fecha de emisión (más recientes primero)
     * @return array Arreglo de prescripciones
     */
    public function obtenerTodas(): array
    {
        // query() ejecuta el SELECT directo (sin parámetros -> no hay inyección SQL posible)
        $stmt = $this->pdo->query(
            // SELECT p.*: todas las columnas de prescripciones (alias p) más los nombres del paciente y del médico
            // LEFT JOIN: une pacientes y medicos para enriquecer la prescripción con nombres legibles
            // (LEFT = aunque no haya médico o paciente asignado, la prescripción igual aparece)
            // ORDER BY p.fecha_emision DESC: prescripciones más recientes primero
            "SELECT p.*, 
                    pac.nombre as nombre_paciente, 
                    med.nombre as nombre_medico 
             FROM prescripciones p
             LEFT JOIN pacientes pac ON p.id_paciente = pac.id
             LEFT JOIN medicos med ON p.id_medico = med.id
             ORDER BY p.fecha_emision DESC"
        );
        // fetchAll() trae todas las filas como arrays asociativos
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // array_map() aplica formatearFila() a cada fila para decodificar el campo medicamentos (JSON)
        return array_map([$this, 'formatearFila'], $filas);
    }

    /**
     * Obtiene una página de prescripciones (paginado)
     * Evita traer TODAS las prescripciones cuando la tabla es grande:
     * trae solo la página solicitada con LIMIT ... OFFSET ... (performance).
     * Puede filtrar por texto (medicamentos, paciente o médico) y por estado.
     * @param int $offset Desde qué fila empezar ((página - 1) * por_página)
     * @param int $porPagina Cuántas prescripciones trae la página
     * @param string $busqueda Texto de búsqueda opcional
     * @param string $estado Filtro por estado opcional (activa, vencida, etc.)
     * @return array Arreglo con las prescripciones de la página
     */
    public function obtenerPaginadas(int $offset, int $porPagina, string $busqueda = '', string $estado = ''): array
    {
        // SELECT base con JOIN para enriquecer con nombres de paciente y médico
        $sql = "SELECT p.*, 
                       pac.nombre as nombre_paciente, 
                       med.nombre as nombre_medico 
                FROM prescripciones p
                LEFT JOIN pacientes pac ON p.id_paciente = pac.id
                LEFT JOIN medicos med ON p.id_medico = med.id";
        // Condiciones WHERE que se acumulan dinámicamente según los filtros
        $where = [];
        // Parámetros que se bindean después en orden (protección anti inyección SQL)
        $parametros = [];

        // Filtro por texto: LIKE en el JSON de medicamentos, paciente o médico
        if ($busqueda !== '') {
            $where[] = "(pac.nombre LIKE ? OR med.nombre LIKE ? OR p.medicamentos LIKE ?)";
            $parametros[] = "%$busqueda%";
            $parametros[] = "%$busqueda%";
            $parametros[] = "%$busqueda%";
        }
        // Filtro por estado exacto (si viene, ej: "activa")
        if ($estado !== '') {
            $where[] = "p.estado = ?";
            $parametros[] = $estado;
        }

        // Si hay filtros se agregan al SQL unidos con AND
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Orden (igual que obtenerTodas) + LIMIT/OFFSET para recortar la página
        $sql .= " ORDER BY p.fecha_emision DESC LIMIT ? OFFSET ?";

        // Consulta preparada: ningún valor se concatena al SQL, todos van por ?
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        // Se bindean primero los parámetros de búsqueda/estado (como texto)
        foreach ($parametros as $parametro) {
            $stmt->bindValue($i++, $parametro);
        }
        // LIMIT y OFFSET con tipo entero explícito (requerido por MySQL nativo)
        $stmt->bindValue($i++, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        // Devuelve solo las prescripciones de la página, con medicamentos decodificados
        return array_map([$this, 'formatearFila'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Cuenta el total de prescripciones (respetando búsqueda y estado)
     * Se usa junto a obtenerPaginadas() para calcular las páginas del listado.
     * @param string $busqueda Texto de búsqueda opcional
     * @param string $estado Filtro por estado opcional
     * @return int Total de prescripciones
     */
    public function contar(string $busqueda = '', string $estado = ''): int
    {
        // COUNT(*) es barato: devuelve solo un número, no las filas completas
        $sql = "SELECT COUNT(*) FROM prescripciones p
                LEFT JOIN pacientes pac ON p.id_paciente = pac.id
                LEFT JOIN medicos med ON p.id_medico = med.id";
        // Condiciones WHERE y parámetros (iguales a los de obtenerPaginadas)
        $where = [];
        $parametros = [];

        if ($busqueda !== '') {
            $where[] = "(pac.nombre LIKE ? OR med.nombre LIKE ? OR p.medicamentos LIKE ?)";
            $like = "%$busqueda%";  // Patrón LIKE único reutilizado en los tres campos
            $parametros = [$like, $like, $like];
        }
        if ($estado !== '') {
            $where[] = "p.estado = ?";
            $parametros[] = $estado;
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Consulta preparada (o directa si no hay filtros) y devolución del conteo
        $stmt = empty($parametros) ? $this->pdo->query($sql) : $this->pdo->prepare($sql);
        if (!empty($parametros)) {
            $stmt->execute($parametros);
        }
        // fetchColumn() devuelve el número; (int) lo garantiza como entero
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene las prescripciones de un paciente específico
     * @param int $id_paciente ID del paciente
     * @return array Arreglo de prescripciones
     */
    public function obtenerPorPaciente(int $id_paciente): array
    {
        // Consulta preparada: el ? marca dónde se insertará el id del paciente (anti inyección SQL)
        $stmt = $this->pdo->prepare(
            // Misma consulta con JOINs de nombres, pero filtrada por el paciente con WHERE p.id_paciente = ?
            "SELECT p.*, 
                    pac.nombre as nombre_paciente,
                    med.nombre as nombre_medico
             FROM prescripciones p
             LEFT JOIN pacientes pac ON p.id_paciente = pac.id
             LEFT JOIN medicos med ON p.id_medico = med.id
             WHERE p.id_paciente = ?
             ORDER BY p.fecha_emision DESC"
        );
        // Executa el SELECT reemplazando el ? con el id del paciente
        $stmt->execute([$id_paciente]);
        // Obtiene todas las filas resultantes
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Formatea cada fila (decodifica el JSON de medicamentos) antes de devolver
        return array_map([$this, 'formatearFila'], $filas);
    }

    /**
     * Obtiene una prescripción por su ID
     * @param int $id ID de la prescripción
     * @return array|null Datos de la prescripción o null
     */
    public function obtenerPorId(int $id): ?array
    {
        // Consulta preparada que filtra por el id de la prescripción
        $stmt = $this->pdo->prepare(
            // Igual que el listado, pero con WHERE p.id = ? para traer solo una prescripción
            "SELECT p.*,
                    pac.nombre as nombre_paciente,
                    med.nombre as nombre_medico
             FROM prescripciones p
             LEFT JOIN pacientes pac ON p.id_paciente = pac.id
             LEFT JOIN medicos med ON p.id_medico = med.id
             WHERE p.id = ?"
        );
        // Ejecuta con el id como parámetro real
        $stmt->execute([$id]);
        // fetch() devuelve la primera (única) fila o false
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // return $result ? ... : null: si existe la fila la formatea; si no (false) devuelve null (contrato)
        return $result ? $this->formatearFila($result) : null;
    }

    /**
     * Decodifica el campo "medicamentos" (JSON en la base de datos)
     * para que la API devuelva un array limpio, listo para consumirse.
     * @param array $fila Fila de la base de datos
     * @return array Fila con los medicamentos decodificados
     */
    private function formatearFila(array $fila): array
    {
        // Verifica que la fila contenga el campo 'medicamentos'
        if (isset($fila['medicamentos'])) {
            // json_decode() convierte el string JSON a array asociativo (true = array, no objeto)
            $decodificado = json_decode($fila['medicamentos'], true);
            // Si el JSON era válido y era array se usa esa versión; si falló, se devuelve un array vacío
            $fila['medicamentos'] = is_array($decodificado) ? $decodificado : [];
        }
        // Devuelve la fila ya procesada (con medicamentos como array)
        return $fila;
    }

    /**
     * Crea una nueva prescripción
     * @param array $data Datos de la prescripción
     * @return int ID de la prescripción creada
     */
    public function crear(array $data): int
    {
        // INSERT INTO: agrega una prescripción; los ? son marcadores posicionales (1 por cada columna)
        $stmt = $this->pdo->prepare(
            "INSERT INTO prescripciones 
             (id_paciente, id_medico, medicamentos, indicaciones, fecha_vencimiento, estado)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        // Ejecuta el INSERT con los valores; ?? = si la clave no viene, se usa el valor por defecto
        $stmt->execute([
            $data['id_paciente'],    // Obligatorio: paciente al que se prescribió
            $data['id_medico'] ?? null,   // Opcional: médico que prescribe (puede ser null)
            $data['medicamentos'],  // JSON string (lista de medicamentos)
            $data['indicaciones'] ?? null, // Opcional
            $data['fecha_vencimiento'] ?? null, // Opcional: fecha de vencimiento de la receta
            $data['estado'] ?? 'activa'   // Por defecto el estado inicial es 'activa'
        ]);
        // lastInsertId() devuelve el id del registro recién insertado; (int) lo convierte a entero
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza una prescripción
     * @param int $id ID de la prescripción
     * @param array $data Campos a actualizar
     * @return bool true si se actualizó
     */
    public function actualizar(int $id, array $data): bool
    {
        // Se construye el UPDATE dinámicamente según cuáles campos lleguen en $data
        $campos = [];  // Partes "campo = ?" del SET
        $valores = []; // Valores que reemplazarán los ?

        // Si viene medicamentos, se agrega al UPDATE
        if (isset($data['medicamentos'])) {
            $campos[] = 'medicamentos = ?';
            $valores[] = $data['medicamentos'];
        }
        // Si vienen indicaciones, se agrega al UPDATE
        if (isset($data['indicaciones'])) {
            $campos[] = 'indicaciones = ?';
            $valores[] = $data['indicaciones'];
        }
        // Si viene el estado, se agrega al UPDATE
        if (isset($data['estado'])) {
            $campos[] = 'estado = ?';
            $valores[] = $data['estado'];
        }
        // Si viene la fecha de vencimiento, se agrega al UPDATE
        if (isset($data['fecha_vencimiento'])) {
            $campos[] = 'fecha_vencimiento = ?';
            $valores[] = $data['fecha_vencimiento'];
        }

        // Si ningún campo fue enviado, no hay nada que modificar -> false
        if (empty($campos)) {
            return false;
        }

        // El id de la prescripción se agrega al final (corresponde al ? del WHERE)
        $valores[] = $id;
        // implode() une las partes del SET con coma, y se arma el UPDATE ... WHERE id = ?
        $sql = "UPDATE prescripciones SET " . implode(', ', $campos) . " WHERE id = ?";
        // Se prepara la consulta (los valores van por parámetro, nunca concatenados al SQL)
        $stmt = $this->pdo->prepare($sql);
        // execute($valores) bindea los valores en orden y devuelve true si todo salió bien
        return $stmt->execute($valores);
    }

    /**
     * Elimina una prescripción
     * @param int $id ID de la prescripción
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        // Consulta preparada que borra la prescripción con ese id (el ? la protege de inyección SQL)
        $stmt = $this->pdo->prepare("DELETE FROM prescripciones WHERE id = ?");
        // Ejecuta el DELETE y devuelve true si se ejecutó correctamente
        return $stmt->execute([$id]);
    }

    /**
     * Cambia el estado de una prescripción (activa, vencida, dispensada, etc)
     * @param int $id ID de la prescripción
     * @param string $nuevoEstado Nuevo estado
     * @return bool true si se actualizó
     */
    public function cambiarEstado(int $id, string $nuevoEstado): bool
    {
        // UPDATE preparado que modifica solo el campo estado; recibe dos parámetros posicionales (?)
        $stmt = $this->pdo->prepare("UPDATE prescripciones SET estado = ? WHERE id = ?");
        // Ejecuta pasando el nuevo estado y el id; el orden coincide con los ? de la consulta
        return $stmt->execute([$nuevoEstado, $id]);
    }
}
