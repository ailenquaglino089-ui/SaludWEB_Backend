<?php
// ============================================================
// persistence/CitaRepository.php - Capa de persistencia (Citas)
// ============================================================
// Repository Pattern para acceso a datos de las citas de la turnera.
// Es el archivo con más SQL del módulo, pero sigue la misma regla que el
// resto del proyecto: NINGÚN valor del cliente entra concatenado al texto
// SQL. Todo se_bindea como parámetro con marcadores de posición (?).

class CitaRepository implements CitaRepositoryInterface
{
    // Conexión PDO guardada para ejecutar todas las consultas SQL del repositorio
    private $pdo;

    /**
     * Estados en los que la cita sigue OCUPANDO el horario del consultorio.
     * Mientras la cita está en uno de estos estados, slot_reservado vale
     * 'reservado' y el índice único impide que otro paciente lo reserve.
     */
    private const ESTADOS_QUE_RESERVAN = ['pendiente', 'confirmada', 'completada'];

    /**
     * Estados en los que la cita LIBERA el horario.
     * Al pasar a alguno de estos, slot_reservado pasa a NULL y el horario
     * vuelve a quedar disponible para otro paciente.
     */
    private const ESTADOS_QUE_LIBERAN = ['cancelada', 'ausente'];

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
     * Obtiene una página de citas, enrichecida con nombres de paciente y médico.
     *
     * Los JOIN son LEFT para no perder citas: si un paciente o un médico
     * fueran eliminados, la cita se borra en cascada por las foreign keys,
     * pero usar LEFT evita que un dato faltante haga desaparecer la fila
     * entera del listado.
     *
     * @param int $offset Desde qué fila empezar ((página - 1) * por_página)
     * @param int $porPagina Cuántas citas trae la página
     * @param array $filtros Ver obtenerPaginadas() en la interfaz para el detalle
     * @return array Arreglo con las citas de la página
     */
    public function obtenerPaginadas(int $offset, int $porPagina, array $filtros = []): array
    {
        // SELECT base: trae la cita completa y los nombres legibles
        $sql = "SELECT c.*,
                       pac.nombre AS nombre_paciente,
                       pac.dni AS dni_paciente,
                       med.nombre AS nombre_medico,
                       med.especialidad AS especialidad
                FROM citas c
                LEFT JOIN pacientes pac ON c.id_paciente = pac.id
                LEFT JOIN medicos med ON c.id_medico = med.id";

        // Arma el WHERE y sus parámetros con el helper común (evita duplicar la lógica)
        list($where, $parametros) = $this->construirFiltros($filtros);

        // Si hay condiciones, se agregan al SQL unidas con AND
        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Orden: primero por fecha, luego por hora. La agenda se lee de izquierda
        // a derecha y de arriba hacia abajo, que es el orden natural de leerla.
        $sql .= " ORDER BY c.fecha ASC, c.hora ASC LIMIT ? OFFSET ?";

        // Consulta preparada: los valores del filtro NUNCA se concatenan al SQL
        $stmt = $this->pdo->prepare($sql);
        // Se bindean primero los filtros en el mismo orden en que se agregaron
        $i = 1;
        foreach ($parametros as $parametro) {
            $stmt->bindValue($i++, $parametro);
        }
        // LIMIT y OFFSET con tipo entero explícito (MySQL nativo lo requiere)
        $stmt->bindValue($i++, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de citas que coinciden con los filtros
     * Se usa junto a obtenerPaginadas() para calcular las páginas del listado.
     * COUNT(*) es barato: trae un número, no las filas.
     * @param array $filtros Mismos filtros que obtenerPaginadas()
     * @return int Total de citas
     */
    public function contar(array $filtros = []): int
    {
        // COUNT no necesita traer datos de pacientes ni médicos: solo la tabla citas
        $sql = "SELECT COUNT(*) FROM citas c
                LEFT JOIN pacientes pac ON c.id_paciente = pac.id
                LEFT JOIN medicos med ON c.id_medico = med.id";

        // Se reutiliza el MISMO helper de filtros: garantiza que contar() y
        // obtenerPaginadas() siempre usan exactamente el mismo criterio
        list($where, $parametros) = $this->construirFiltros($filtros);

        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Si hay filtros se prepara; si no hay ninguno, query() alcanza y es más rápido
        $stmt = empty($parametros) ? $this->pdo->query($sql) : $this->pdo->prepare($sql);
        if (!empty($parametros)) {
            $stmt->execute($parametros);
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * Construye la cláusula WHERE y la lista de parámetros a partir de un
     * arreglo de filtros. Se usa en obtenerPaginadas() y contar() para que
     * ambos compartan exactamente la misma lógica de filtrado.
     *
     * Filtros aceptados:
     *   estado        → estado exacto de la cita
     *   fecha         → una fecha puntual (YYYY-MM-DD)
     *   desde/hasta   → rango de fechas (ambos YYYY-MM-DD)
     *   id_paciente   → citas de un paciente puntual
     *   id_medico     → citas de un médico puntual
     *   busqueda      → texto libre (motivo, notas, nombre del paciente o médico)
     *   solo_activas  → si es true, excluye las citas que ya liberaron el horario
     *
     * @param array $filtros Filtros a aplicar
     * @return array [array $where, array $parametros]
     */
    private function construirFiltros(array $filtros): array
    {
        // Arreglo donde se acumulan las condiciones SQL
        $where = [];
        // Arreglo paralelo con los valores, en el MISMO orden que las condiciones
        $parametros = [];

        // Filtro por estado exacto (ej: "confirmada")
        if (!empty($filtros['estado'])) {
            $where[] = "c.estado = ?";
            $parametros[] = $filtros['estado'];
        }

        // Filtro por una fecha puntual
        if (!empty($filtros['fecha'])) {
            $where[] = "c.fecha = ?";
            $parametros[] = $filtros['fecha'];
        }

        // Filtro por rango: desde (inclusive) y hasta (inclusive)
        if (!empty($filtros['desde'])) {
            $where[] = "c.fecha >= ?";
            $parametros[] = $filtros['desde'];
        }
        if (!empty($filtros['hasta'])) {
            $where[] = "c.fecha <= ?";
            $parametros[] = $filtros['hasta'];
        }

        // Filtro por paciente.
        //
        // OJO con por qué se usa isset() y no empty() acá: empty(0) es TRUE,
        // así que con empty() un id_paciente = 0 se interpretaba como "no
        // filtrar" y devolvía TODAS las citas de la base. Eso convertía a un
        // paciente cuya cuenta todavía no está vinculada a una ficha en
        // alguien con acceso a los turnos de todo el consultorio.
        //
        // isset() distingue "no vine filtro" (null o clave ausente) de "vine
        // con 0", y en el segundo caso filtra por 0, que no devuelve nada.
        // Es la defensa correcta: ante la duda, mostrar cero.
        if (isset($filtros['id_paciente']) && $filtros['id_paciente'] !== '') {
            $where[] = "c.id_paciente = ?";
            $parametros[] = (int)$filtros['id_paciente'];
        }

        // Filtro por médico, con el mismo criterio por id
        if (isset($filtros['id_medico']) && $filtros['id_medico'] !== '') {
            $where[] = "c.id_medico = ?";
            $parametros[] = (int)$filtros['id_medico'];
        }

        // Filtro por origen de la reserva: 1 = la reservó el paciente desde la
        // autogestión online, 0 = la cargó el consultorio (mostrador o teléfono).
        // Es el dato que permite medir si la turnera está cumpliendo su
        // objetivo de sacar filas presenciales y llamados telefónicos.
        if (isset($filtros['creado_por_paciente']) && $filtros['creado_por_paciente'] !== '') {
            $where[] = "c.creado_por_paciente = ?";
            $parametros[] = (int)$filtros['creado_por_paciente'];
        }

        // solo_activas = true excluye los estados que ya liberaron el horario.
        // Es el filtro que usa el paciente para ver "mis turnos vigentes".
        if (!empty($filtros['solo_activas'])) {
            $where[] = "c.estado IN ('pendiente', 'confirmada')";
        }

        // Búsqueda de texto libre sobre motivo, notas y nombres
        if (!empty($filtros['busqueda'])) {
            $where[] = "(c.motivo LIKE ? OR c.notas LIKE ? OR pac.nombre LIKE ? OR med.nombre LIKE ?)";
            // Se declara el patrón LIKE una vez y se reutiliza en los 4 campos:
            // menos repetido y menos chances de que uno quede sin escapar
            $like = "%" . $filtros['busqueda'] . "%";
            $parametros[] = $like;
            $parametros[] = $like;
            $parametros[] = $like;
            $parametros[] = $like;
        }

        return [$where, $parametros];
    }

    /**
     * Obtiene una cita por su ID
     * @param int $id ID de la cita
     * @return array|null Datos de la cita o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    pac.nombre AS nombre_paciente,
                    pac.dni AS dni_paciente,
                    med.nombre AS nombre_medico,
                    med.especialidad AS especialidad
             FROM citas c
             LEFT JOIN pacientes pac ON c.id_paciente = pac.id
             LEFT JOIN medicos med ON c.id_medico = med.id
             WHERE c.id = ?"
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila : null;
    }

    /**
     * Obtiene las citas de un paciente en un rango de fechas
     * @param int $idPaciente ID del paciente
     * @param string $desde Fecha inicial (YYYY-MM-DD)
     * @param string $hasta Fecha final (YYYY-MM-DD)
     * @return array Arreglo de citas ordenadas por fecha y hora
     */
    public function obtenerPorPacienteYRango(int $idPaciente, string $desde, string $hasta): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    med.nombre AS nombre_medico,
                    med.especialidad AS especialidad
             FROM citas c
             LEFT JOIN medicos med ON c.id_medico = med.id
             WHERE c.id_paciente = ? AND c.fecha BETWEEN ? AND ?
             ORDER BY c.fecha ASC, c.hora ASC"
        );
        $stmt->execute([$idPaciente, $desde, $hasta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las citas de un médico en una fecha puntual
     * Es la consulta que alimenta la agenda unificada del profesional:
     * todo lo que atiende ese día, ordenado por hora.
     * @param int $idMedico ID del médico
     * @param string $fecha Fecha (YYYY-MM-DD)
     * @return array Arreglo de citas ordenadas por hora
     */
    public function obtenerPorMedicoYFecha(int $idMedico, string $fecha): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT c.*,
                    pac.nombre AS nombre_paciente,
                    pac.dni AS dni_paciente
             FROM citas c
             LEFT JOIN pacientes pac ON c.id_paciente = pac.id
             WHERE c.id_medico = ? AND c.fecha = ?
             ORDER BY c.hora ASC"
        );
        $stmt->execute([$idMedico, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crea una nueva cita
     *
     * Punto crítico de concurrencia: el índice UNIQUE (id_medico, fecha, hora,
     * slot_reservado) hace que el motor de la base de datos rechace por sí
     * mismo un segundo intento de reservar el mismo horario. Aunque dos
     * pacientes aprieten "reservar" en el mismo milisegundo, solo uno gana.
     * La validación de la aplicación es para dar un mensaje lindo; esta
     * restricción es la que garantiza que no haya superposiciones.
     *
     * @param array $data Datos de la cita
     * @return int ID de la cita creada
     */
    public function crear(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO citas
             (id_paciente, id_medico, fecha, hora, duracion_minutos,
              estado, motivo, notas, recordatorio_enviado,
              creado_por_paciente, slot_reservado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            // Obligatorio: el paciente que reservó
            (int)$data['id_paciente'],
            // Obligatorio: el profesional que atenderá
            (int)$data['id_medico'],
            // Obligatorio: día de la cita (YYYY-MM-DD)
            $data['fecha'],
            // Obligatorio: hora de inicio (HH:MM:SS)
            $data['hora'],
            // Duración del turno (30 por defecto)
            (int)($data['duracion_minutos'] ?? 30),
            // Estado inicial: siempre 'pendiente'. La cita no nace confirmada
            // porque la confirmación la hace el paciente desde el recordatorio,
            // que es justamente el mecanismo que reduce el ausentismo.
            $data['estado'] ?? 'pendiente',
            // Motivo de la consulta (opcional)
            $data['motivo'] ?? null,
            // Notas internas (opcional)
            $data['notas'] ?? null,
            // El recordatorio todavía no se envió
            (int)($data['recordatorio_enviado'] ?? 0),
            // 1 si la reservó el propio paciente, 0 si fue el consultorio
            (int)($data['creado_por_paciente'] ?? 1),
            // 'reservado' marca el horario como ocupado
            $data['slot_reservado'] ?? 'reservado',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza los campos enviados de una cita
     * @param int $id ID de la cita
     * @param array $data Campos a actualizar
     * @return bool true si se actualizó
     */
    public function actualizar(int $id, array $data): bool
    {
        // Se construye el UPDATE dinámicamente según qué campos vengan en $data
        $campos = [];
        $valores = [];

        // Cada campo permitido se agrega al SET con su valor.
        // Los valores NUNCA se escriben en el texto SQL, solo en $valores.
        if (isset($data['id_paciente'])) {
            $campos[] = 'id_paciente = ?';
            $valores[] = (int)$data['id_paciente'];
        }
        if (isset($data['id_medico'])) {
            $campos[] = 'id_medico = ?';
            $valores[] = (int)$data['id_medico'];
        }
        if (isset($data['fecha'])) {
            $campos[] = 'fecha = ?';
            $valores[] = $data['fecha'];
        }
        if (isset($data['hora'])) {
            $campos[] = 'hora = ?';
            $valores[] = $data['hora'];
        }
        if (isset($data['duracion_minutos'])) {
            $campos[] = 'duracion_minutos = ?';
            $valores[] = (int)$data['duracion_minutos'];
        }
        if (isset($data['motivo'])) {
            $campos[] = 'motivo = ?';
            $valores[] = $data['motivo'];
        }
        if (isset($data['notas'])) {
            $campos[] = 'notas = ?';
            $valores[] = $data['notas'];
        }

        // Si no llegó ningún campo actualizable, no se ejecuta nada
        if ($campos === []) {
            return false;
        }

        // El id se agrega al final porque corresponde al ? del WHERE
        $valores[] = $id;
        $sql = "UPDATE citas SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($valores);
    }

    /**
     * Cambia el estado de la cita y ajusta slot_reservado en la misma operación.
     *
     * Este es el punto donde se decide si el horario queda ocupado o se libera.
     * Hacerlo en un solo UPDATE (y no en dos consultas separadas) evita el
     * estado intermedio en el que la cita figura cancelada pero el horario
     * sigue bloqueado.
     *
     * @param int $id ID de la cita
     * @param string $estado Nuevo estado
     * @return bool true si se actualizó
     */
    public function cambiarEstado(int $id, string $estado): bool
    {
        // Si el estado es de los que liberan el horario, slot_reservado pasa a NULL.
        // En un índice UNIQUE de MySQL los NULL no colisionan entre sí, así que
        // pueden quedar muchas citas canceladas en el mismo horario y, a la vez,
        // ninguna puede ocupar un horario que ya tenga una cita viva.
        $slot = in_array($estado, self::ESTADOS_QUE_LIBERAN, true) ? null : 'reservado';

        $stmt = $this->pdo->prepare(
            "UPDATE citas SET estado = ?, slot_reservado = ? WHERE id = ?"
        );
        return $stmt->execute([$estado, $slot, $id]);
    }

    /**
     * Elimina una cita
     * @param int $id ID de la cita
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM citas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Marca la cita como recordatorio ya enviado
     * Evita generar y enviar el mismo recordatorio varias veces.
     * @param int $id ID de la cita
     * @return bool true si se actualizó
     */
    public function marcarRecordatorioEnviado(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE citas SET recordatorio_enviado = 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Devuelve el resumen general de la tabla de citas
     *
     * La tasa de ausentismo se mide sobre las citas que ya deberían haber ocurrido
     * (completadas + ausentes). Si se calculara sobre todas las citas, las
     * citas futuras todavía no se hayan realizado y el porcentaje sería
     * artificialmente bajo, lo que haría la métrica inútil para el fin que se busca.
     *
     * @return array ['total' => int, 'por_estado' => array, 'ausentismo_porcentaje' => float]
     */
    public function obtenerResumen(int $idMedico = 0): array
    {
        // Si se acota a un profesional, el filtro se agrega con un parámetro
        // más. Se usa un placeholder (no concatenar el id) para no abrir la
        // puerta a inyección aunque el valor venga de un parámetro.
        $where = $idMedico > 0 ? ' WHERE id_medico = ?' : '';

        // Conteo por estado: un solo GROUP BY devuelve todas las categorías de una vez
        $porEstado = [];
        $stmt = $this->pdo->prepare(
            "SELECT estado, COUNT(*) AS total FROM citas" . $where . " GROUP BY estado"
        );
        $stmt->execute($idMedico > 0 ? [$idMedico] : []);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            // Se guarda como pares estado => cantidad para que el frontend
            // pueda consultar directamente y no tenga que recorrer el array
            $porEstado[$fila['estado']] = (int)$fila['total'];
        }

        // Total general: se obtiene sumando los conteos por estado,
        // que es más barato que un segundo COUNT(*) sobre la tabla completa
        $total = 0;
        foreach ($porEstado as $cantidad) {
            $total += $cantidad;
        }

        // Se leen de $porEstado los dos estados comparables, con 0 por defecto
        $completadas = $porEstado['completada'] ?? 0;
        $ausentes = $porEstado['ausente'] ?? 0;
        // Si todavía no hay historial, la tasa es 0 y no se divide por cero
        $denominador = $completadas + $ausentes;
        $ausentismo = $denominador > 0 ? round(($ausentes / $denominador) * 100, 1) : 0.0;

        return [
            'total' => $total,
            'por_estado' => $porEstado,
            'ausentismo_porcentaje' => $ausentismo,
        ];
    }

    /**
     * Cuenta las citas agrupadas por día dentro de un rango
     * Alimenta el gráfico de demanda real de la turnera.
     * @param string $desde Fecha inicial (YYYY-MM-DD)
     * @param string $hasta Fecha final (YYYY-MM-DD)
     * @return array ['2026-09-25' => 4, ...]
     */
    public function contarPorDia(string $desde, string $hasta, int $idMedico = 0): array
    {
        // Filtro opcional por profesional: 0 = todo el consultorio
        $filtroMedico = $idMedico > 0 ? ' AND id_medico = ?' : '';

        $stmt = $this->pdo->prepare(
            "SELECT fecha, COUNT(*) AS total FROM citas
             WHERE fecha BETWEEN ? AND ?" . $filtroMedico . "
             GROUP BY fecha
             ORDER BY fecha ASC"
        );

        $parametros = [$desde, $hasta];
        if ($idMedico > 0) {
            $parametros[] = $idMedico;
        }

        $stmt->execute($parametros);
        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $resultado[$fila['fecha']] = (int)$fila['total'];
        }
        return $resultado;
    }

    /**
     * Cuenta las citas agrupadas por especialidad
     * El JOIN es por nombre porque medicos.especialidad es texto libre
     * (decisión heredada del módulo de médicos de este proyecto).
     * @param string $desde Fecha inicial (YYYY-MM-DD)
     * @param string $hasta Fecha final (YYYY-MM-DD)
     * @return array [['especialidad' => ..., 'total' => N], ...]
     */
    public function contarPorEspecialidad(string $desde, string $hasta, int $idMedico = 0): array
    {
        // Filtro opcional por profesional: 0 = todo el consultorio
        $filtroMedico = $idMedico > 0 ? ' AND c.id_medico = ?' : '';

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(NULLIF(m.especialidad, ''), 'Sin especialidad') AS especialidad,
                    COUNT(*) AS total
             FROM citas c
             LEFT JOIN medicos m ON c.id_medico = m.id
             WHERE c.fecha BETWEEN ? AND ?" . $filtroMedico . "
             GROUP BY especialidad
             ORDER BY total DESC"
        );

        $parametros = [$desde, $hasta];
        if ($idMedico > 0) {
            $parametros[] = $idMedico;
        }

        $stmt->execute($parametros);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta las citas agrupadas por médico, con su ausentismo individual
     * Sirve para repartir mejor el personal: muestra quién tiene la agenda
     * más cargada y quién tiene más pacientes que no asisten.
     * @param string $desde Fecha inicial (YYYY-MM-DD)
     * @param string $hasta Fecha final (YYYY-MM-DD)
     * @return array [['id_medico' =>, 'nombre' =>, 'total' =>, 'ausentes' =>], ...]
     */
    public function contarPorMedico(string $desde, string $hasta, int $idMedico = 0): array
    {
        // Filtro opcional por profesional: 0 = todo el consultorio.
        // Cuando un médico pide su informe, el ranking por profesional
        // devuelve una sola fila (la suya), que es lo esperable.
        $filtroMedico = $idMedico > 0 ? ' AND c.id_medico = ?' : '';

        $stmt = $this->pdo->prepare(
            "SELECT c.id_medico,
                    COALESCE(m.nombre, 'Médico eliminado') AS nombre,
                    COUNT(*) AS total,
                    SUM(CASE WHEN c.estado = 'ausente' THEN 1 ELSE 0 END) AS ausentes
             FROM citas c
             LEFT JOIN medicos m ON c.id_medico = m.id
             WHERE c.fecha BETWEEN ? AND ?" . $filtroMedico . "
             GROUP BY c.id_medico, nombre
             ORDER BY total DESC"
        );

        $parametros = [$desde, $hasta];
        if ($idMedico > 0) {
            $parametros[] = $idMedico;
        }

        $stmt->execute($parametros);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // SUM() devuelve DECIMAL en MySQL y por eso llega como string:
        // se castea a int para que el frontend pueda hacer cuentas sin conversiones
        foreach ($filas as $i => $fila) {
            $filas[$i]['total'] = (int)$fila['total'];
            $filas[$i]['ausentes'] = (int)$fila['ausentes'];
        }
        return $filas;
    }
}
