<?php
// ============================================================
// persistence/NotificacionRepository.php - Capa de persistencia (Notificaciones)
// ============================================================
// Repository Pattern para la bandeja de comunicaciones (recordatorios).
class NotificacionRepository implements NotificacionRepositoryInterface
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
     * Obtiene una página de notificaciones con los datos de la cita y del paciente.
     *
     * Los JOIN son INNER porque una notificación sin cita no tiene sentido
     * (y la foreign key es ON DELETE CASCADE: si se borra la cita, se borran
     * sus notificaciones, así que nunca habrá una fila huérfana).
     *
     * @param int $offset Desde qué fila empezar
     * @param int $porPagina Cuántas notificaciones trae la página
     * @param array $filtros Ver obtenerPaginadas() en la interfaz
     * @return array Arreglo con las notificaciones de la página
     */
    public function obtenerPaginadas(int $offset, int $porPagina, array $filtros = []): array
    {
        $sql = "SELECT n.*,
                       c.fecha AS cita_fecha,
                       c.hora AS cita_hora,
                       c.estado AS cita_estado,
                       c.id_paciente AS id_paciente,
                       med.nombre AS nombre_medico
                FROM notificaciones n
                INNER JOIN citas c ON n.id_cita = c.id
                LEFT JOIN medicos med ON c.id_medico = med.id";

        $where = [];
        $parametros = [];

        // Filtro por estado de la notificación (pendiente, enviado, fallido)
        if (!empty($filtros['estado'])) {
            $where[] = "n.estado = ?";
            $parametros[] = $filtros['estado'];
        }
        // Filtro por tipo (recordatorio, confirmacion, cancelacion)
        if (!empty($filtros['tipo'])) {
            $where[] = "n.tipo = ?";
            $parametros[] = $filtros['tipo'];
        }
        // Filtro por canal (email, whatsapp, sistema)
        if (!empty($filtros['canal'])) {
            $where[] = "n.canal = ?";
            $parametros[] = $filtros['canal'];
        }
        // Filtro por usuario destinatario
        if (!empty($filtros['id_usuario'])) {
            $where[] = "n.id_usuario = ?";
            $parametros[] = (int)$filtros['id_usuario'];
        }
        // Filtro por cita puntual
        if (!empty($filtros['id_cita'])) {
            $where[] = "n.id_cita = ?";
            $parametros[] = (int)$filtros['id_cita'];
        }

        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Las más recientes primero: en una bandeja de avisos lo último es lo más relevante
        $sql .= " ORDER BY n.creado_at DESC, n.id DESC LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($parametros as $parametro) {
            $stmt->bindValue($i++, $parametro);
        }
        $stmt->bindValue($i++, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de notificaciones que coinciden con los filtros
     * @param array $filtros Mismos filtros que obtenerPaginadas()
     * @return int Total de notificaciones
     */
    public function contar(array $filtros = []): int
    {
        // COUNT no necesita los JOIN con medicos: solo con citas (filtros por usuario)
        $sql = "SELECT COUNT(*) FROM notificaciones n
                INNER JOIN citas c ON n.id_cita = c.id";

        $where = [];
        $parametros = [];

        if (!empty($filtros['estado'])) {
            $where[] = "n.estado = ?";
            $parametros[] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $where[] = "n.tipo = ?";
            $parametros[] = $filtros['tipo'];
        }
        if (!empty($filtros['canal'])) {
            $where[] = "n.canal = ?";
            $parametros[] = $filtros['canal'];
        }
        if (!empty($filtros['id_usuario'])) {
            $where[] = "n.id_usuario = ?";
            $parametros[] = (int)$filtros['id_usuario'];
        }
        if (!empty($filtros['id_cita'])) {
            $where[] = "n.id_cita = ?";
            $parametros[] = (int)$filtros['id_cita'];
        }

        if ($where !== []) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $stmt = empty($parametros) ? $this->pdo->query($sql) : $this->pdo->prepare($sql);
        if (!empty($parametros)) {
            $stmt->execute($parametros);
        }
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtiene una notificación por su ID
     * @param int $id ID de la notificación
     * @return array|null Datos de la notificación o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT n.*, c.fecha AS cita_fecha, c.hora AS cita_hora,
                    c.estado AS cita_estado, c.id_paciente AS id_paciente,
                    med.nombre AS nombre_medico
             FROM notificaciones n
             INNER JOIN citas c ON n.id_cita = c.id
             LEFT JOIN medicos med ON c.id_medico = med.id
             WHERE n.id = ?"
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila : null;
    }

    /**
     * Busca una notificación por su token de confirmación/cancelación
     * Es la ruta que permite actuar sobre una cita desde el link del
     * recordatorio, sin que el paciente tenga que iniciar sesión.
     * @param string $token Token único recibido
     * @return array|null Datos de la notificación o null si no existe
     */
    public function obtenerPorToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT n.*, c.fecha AS cita_fecha, c.hora AS cita_hora,
                    c.estado AS cita_estado, c.id_paciente AS id_paciente,
                    c.id_medico AS id_medico, med.nombre AS nombre_medico
             FROM notificaciones n
             INNER JOIN citas c ON n.id_cita = c.id
             LEFT JOIN medicos med ON c.id_medico = med.id
             WHERE n.token_cancelacion = ?"
        );
        $stmt->execute([$token]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ? $fila : null;
    }

    /**
     * Crea el registro de una notificación (nace en estado 'pendiente')
     * Nace 'pendiente' y no 'enviado' a propósito: el registro se escribe al
     * GENERAR el recordatorio, y el envío real ocurre después. Si el proceso de
     * envío falla, queda registrado el fallo y se puede reintentar, en lugar de
     * perder el aviso para siempre.
     * @param array $data Datos de la notificación
     * @return int ID de la notificación creada
     */
    public function crear(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notificaciones
             (id_cita, id_usuario, tipo, canal, destino, estado, token_cancelacion, motivo_error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            // Obligatorio: la cita sobre la que se avisa
            (int)$data['id_cita'],
            // Destinatario autenticado (opcional: puede no haber usuario asociado)
            isset($data['id_usuario']) ? (int)$data['id_usuario'] : null,
            // Tipo de aviso: recordatorio, confirmacion o cancelacion
            $data['tipo'] ?? 'recordatorio',
            // Canal de envío: email, whatsapp o sistema
            $data['canal'] ?? 'sistema',
            // Dirección o teléfono de destino (opcional)
            $data['destino'] ?? null,
            // Estado inicial: pendiente de envío
            $data['estado'] ?? 'pendiente',
            // Token único para actuar desde el link del mensaje
            $data['token_cancelacion'] ?? null,
            // Motivo del fallo, solo se usa si la notificación nace 'fallido'
            $data['motivo_error'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Crea el aviso solo si no hay uno equivalente, y todo en una sola operación.
     *
     * Equivale a "no cree dos avisos iguales para la misma cita, el mismo
     * tipo y el mismo canal", garantizado por el índice único
     * uniq_cita_tipo_canal. Devuelve el id del aviso si lo creó, o null si
     * ya existía.
     *
     * POR QUÉ NO BASTA CON PREGUNTAR ANTES
     * ------------------------------------
     * La forma obvia de evitar duplicados es consultar y, si no hay nada,
     * insertar. Pero entre la consulta y la insertación hay una ventana: si
     * dos procesos llegan al mismo tiempo (un cron y una persona clicking
     * "generar recordatorios", o dos réplicas del backend), los dos leen
     * "no avisada" y los dos insertan. El paciente recibe el mensaje dos
     * veces y el consultorio tiene dos filas que reintentar.
     *
     * Por eso la comprobación tiene que estar en la base, no en el código:
     * el índice único convierte la segunda insertación en un no-op. La
     * clave es ON DUPLICATE KEY, que convierte el choque en un UPDATE que
     * no cambia nada en lugar de propagar el error.
     *
     * @param array $data Mismos campos que crear()
     * @return int|null Id del aviso creado, o null si ya existía
     */
    public function crearSiNoExiste(array $data): ?int
    {
        // id = LAST_INSERT_ID(id) es la parte importante: si el registro ya
        // existe, MySQL no inserta, pero LAST_INSERT_ID() queda apuntando al
        // id que ya estaba, así el resultado sigue siendo interpretable.
        $stmt = $this->pdo->prepare(
            "INSERT INTO notificaciones
             (id_cita, id_usuario, tipo, canal, destino, estado, token_cancelacion, motivo_error)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute([
            (int)$data['id_cita'],
            isset($data['id_usuario']) ? (int)$data['id_usuario'] : null,
            $data['tipo'] ?? 'recordatorio',
            $data['canal'] ?? 'sistema',
            $data['destino'] ?? null,
            $data['estado'] ?? 'pendiente',
            $data['token_cancelacion'] ?? null,
            $data['motivo_error'] ?? null,
        ]);

        // 1 fila afectada = se insertó de verdad.
        // 0 = ya existía y el UPDATE no cambió nada (el id sigue siendo el mismo).
        if ($stmt->rowCount() === 1) {
            return (int) $this->pdo->lastInsertId();
        }

        return null;
    }

    /**
     * Marca la notificación como enviada
     * @param int $id ID de la notificación
     * @return bool true si se actualizó
     */
    public function marcarEnviada(int $id): bool
    {
        // enviado_at = CURRENT_TIMESTAMP guarda el momento real del envío
        $stmt = $this->pdo->prepare(
            "UPDATE notificaciones
             SET estado = 'enviado', enviado_at = CURRENT_TIMESTAMP, motivo_error = NULL
             WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Marca la notificación como fallida y guarda el motivo
     * Guardar el motivo es lo que permite entender por qué no salió el aviso
     * (dirección inválida, proveedor caído, etc.) en lugar de perder el motivo.
     * @param int $id ID de la notificación
     * @param string $motivo Descripción corta del error
     * @return bool true si se actualizó
     */
    public function marcarFallida(int $id, string $motivo): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notificaciones SET estado = 'fallido', motivo_error = ? WHERE id = ?"
        );
        return $stmt->execute([substr($motivo, 0, 255), $id]);
    }

    /**
     * Suma un intento de envío y devuelve el nuevo número de intentos
     * Llevar la cuenta permite aplicar una política de reintentos y detectar
     * avisos que se quedaron atascados.
     * @param int $id ID de la notificación
     * @return int Número de intentos acumulado
     */
    public function registrarIntento(int $id): int
    {
        // intentos = intentos + 1: MySQL lo resuelve en el servidor, sin leer
        // el valor actual desde PHP (evita el clásico problema de leer-modificar
        // -escribir que con peticiones simultáneas pierde actualizaciones)
        $stmt = $this->pdo->prepare("UPDATE notificaciones SET intentos = intentos + 1 WHERE id = ?");
        $stmt->execute([$id]);
        // Luego se lee el valor ya incrementado para devolverlo
        $stmt = $this->pdo->prepare("SELECT intentos FROM notificaciones WHERE id = ?");
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve las notificaciones pendientes más antiguas, para procesarlas
     * por lotes. Es el patrón "outbox": se generan los avisos y un proceso
     * aparte los envía, de modo que una caída del proveedor de email no
     * revierte la generación de los recordatorios.
     * @param int $limite Máximo de notificaciones a devolver
     * @return array Arreglo de notificaciones pendientes
     */
    public function obtenerPendientes(int $limite = 50): array
    {
        // Se limita el tamaño del lote para no cargar de memoria en un sistema real
        $limite = max(1, min(200, (int)$limite));
        $stmt = $this->pdo->prepare(
            "SELECT n.*, c.fecha AS cita_fecha, c.hora AS cita_hora,
                    c.estado AS cita_estado, c.id_paciente AS id_paciente,
                    med.nombre AS nombre_medico
             FROM notificaciones n
             INNER JOIN citas c ON n.id_cita = c.id
             LEFT JOIN medicos med ON c.id_medico = med.id
             WHERE n.estado = 'pendiente'
             ORDER BY n.creado_at ASC
             LIMIT ?"
        );
        // LIMIT con parámetro: en MySQL nativo requiere tipo entero explícito
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una notificación
     * @param int $id ID de la notificación
     * @return bool true si se eliminó
     */
    public function eliminar(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notificaciones WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
