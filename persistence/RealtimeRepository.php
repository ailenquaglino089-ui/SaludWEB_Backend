<?php
// ============================================================
// persistence/RealtimeRepository.php - Capa de persistencia (eventos)
// ============================================================
// Módulo: "Primera funcionalidad en tiempo real" (SSE)
//
// Es el archivo con SQL de este módulo, y aplica la misma regla que el resto
// del proyecto: NINGÚN valor entra concatenado en el texto SQL. Todo se
// envía con marcadores de posición (?), que es además lo que permite que el
// motor no mezcle la estructura de la consulta con los datos.
//
// Los métodos son cortos y sin lógica de negocio: la regla de qué canal
// corresponde a quién y qué datos se publican vive en RealtimeService. Esta
// capa solo sabe guardar y leer filas.

class RealtimeRepository implements RealtimeRepositoryInterface
{
    // Conexión PDO que se usa para las cuatro operaciones
    private $pdo;

    /**
     * @param PDO $pdo Conexión inyectada desde bootstrap
     */
    public function __construct(PDO $pdo)
    {
        // Se guarda la conexión recibida por inyección de dependencias
        $this->pdo = $pdo;
    }

    /**
     * Inserta un evento y devuelve su id.
     *
     * @param string $canal Canal de destino
     * @param string $tipo  Tipo de evento
     * @param array  $datos Payload
     * @return int Id del evento insertado
     */
    public function publicar(string $canal, string $tipo, array $datos = []): int
    {
        // El payload se guarda como texto JSON.
        // JSON_UNESCAPED_UNICODE evita que las tildes de los mensajes se
        // guarden como \u00e1, que funciona igual pero vuelve la tabla
        // ilegible al revisarla a mano.
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);

        $stmt = $this->pdo->prepare(
            "INSERT INTO eventos_realtime (canal, tipo, datos) VALUES (?, ?, ?)"
        );
        // Consulta con marcadores: el canal, el tipo y el JSON viajan como
        // datos separados, nunca armando el texto de la consulta.

        $stmt->execute([$canal, $tipo, $json]);

        // lastInsertId() devuelve el AUTO_INCREMENT de la fila que se acaba de
        // insertar. Ese id es el "cursor" del canal: el cliente lo recuerda y
        // la próxima vuelta pide solo lo que tenga id mayor.
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lee los eventos nuevos de un canal, en orden ascendente.
     *
     * @param string $canal    Canal a leer
     * @param int    $ultimoId Último id ya recibido por el cliente
     * @param int    $limite   Máximo de eventos por vuelta
     * @return array Filas de la tabla eventos_realtime
     */
    public function obtenerDesde(string $canal, int $ultimoId, int $limite = 50): array
    {
        // El LÍMITE no es cosmético: el listener da vueltas cada segundo y, si
        // el canal acumuló eventos mientras el servidor estaba caído, mandaría
        // todos de golpe en una sola respuesta. Con tope, se envían de a
        // tandas y el navegador puede procesarlos sin que se trabe el hilo.

        $stmt = $this->pdo->prepare(
            "SELECT id, canal, tipo, datos, creado_at
             FROM eventos_realtime
             WHERE canal = ? AND id > ?
             ORDER BY id ASC
             LIMIT ?"
        );

        // El límite se bindea como entero nativo (PDO::PARAM_INT) porque el
        // emulador de prepared statements interpretaría el string '50' como
        // texto y MySQL lo rechazaría en la cláusula LIMIT.
        $stmt->bindValue(1, $canal);
        $stmt->bindValue(2, $ultimoId, PDO::PARAM_INT);
        $stmt->bindValue(3, max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Id del evento más reciente del canal (0 si el canal no tiene eventos).
     *
     * @param string $canal Canal a consultar
     * @return int Id máximo del canal
     */
    public function ultimoIdDe(string $canal): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(id), 0) FROM eventos_realtime WHERE canal = ?"
        );
        $stmt->execute([$canal]);
        // COALESCE devuelve 0 en vez de NULL cuando el canal está vacío, para
        // que el llamador pueda usarlo directamente como cursor sin tener que
        // comprobar que sea nulo.
        return (int) $stmt->fetchColumn();
    }

    /**
     * Borra los eventos con antigüedad mayor a la indicada.
     *
     * @param int $minutos Antigüedad mínima, en minutos
     * @return int Filas borradas
     */
    public function purgarAntiguos(int $minutos): int
    {
        // Se calcula el corte con la función SQL NOW() menos un INTERVAL:
        // la cuenta la hace el servidor de base de datos con su propio reloj,
        // y no PHP. Con servidores en zonas horarias distintas, comparar
        // fechas calculadas en dos lugares distintos es una fuente clásica
        // de errores que aparecen de vez en cuando y son difíciles de
        // encontrar. La fecha la pone siempre el mismo reloj: el del servidor.
        $stmt = $this->pdo->prepare(
            "DELETE FROM eventos_realtime WHERE creado_at < (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->bindValue(1, max(1, $minutos), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}