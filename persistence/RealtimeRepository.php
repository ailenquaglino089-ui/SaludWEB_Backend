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
    private $pdo;   // conexión MySQL compartida con el resto de la aplicación

    /**
     * @param PDO $pdo Conexión inyectada desde bootstrap
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;   // se guarda la conexión recibida por inyección
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
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);   // array -> texto JSON

        $stmt = $this->pdo->prepare(
            "INSERT INTO eventos_realtime (canal, tipo, datos) VALUES (?, ?, ?)"
            // Tres marcadores para tres columnas. El INSERT se arma una sola vez
            // al preparar y se reutiliza en cada llamada.
        );
        // Consulta con marcadores: el canal, el tipo y el JSON viajan como
        // datos separados, nunca armando el texto de la consulta.

        $stmt->execute([$canal, $tipo, $json]);   // se envían los tres valores

        // lastInsertId() devuelve el AUTO_INCREMENT de la fila que se acaba de
        // insertar. Ese id es el "cursor" del canal: el cliente lo recuerda y
        // la próxima vuelta pide solo lo que tenga id mayor.
        return (int) $this->pdo->lastInsertId();   // se castea a int: PDO devuelve string
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
            // WHERE canal = ?  -> solo los eventos de ESE canal (usa el índice)
            // id > ?          -> solo lo que el cliente todavía no vio
            // ORDER BY id ASC -> en orden, para que el cursor avance derecho
            // LIMIT ?         -> tope por vuelta, como se explica arriba
        );

        $stmt->bindValue(1, $canal);                    // string: nombre del canal
        $stmt->bindValue(2, $ultimoId, PDO::PARAM_INT); // cursor del cliente
        $stmt->bindValue(3, max(1, $limite), PDO::PARAM_INT);
        // max(1, $limite) evita un LIMIT 0 (que no devolvería nada) o un límite
        // negativo, que MySQL interpreta como error.
        $stmt->execute();   // se manda la consulta ya con los valores puestos

        return $stmt->fetchAll(PDO::FETCH_ASSOC);   // todas las filas como arrays
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
            // MAX(id) sozinho devolvería NULL con el canal vacío, y un NULL
            // convertido a int da 0 pero sin avisar: con COALESCE el 0 es
            // explícito y el código de arriba no depende de MySQL ni de PHP
            // para decidir el valor.
        );
        $stmt->execute([$canal]);   // el canal también va como marcador
        return (int) $stmt->fetchColumn();   // una sola columna, una sola fila
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
            // INTERVAL con marcador es válido en MySQL: el ? se reemplaza por el
            // número y la consulta queda "INTERVAL 30 MINUTE".
        );
        $stmt->bindValue(1, max(1, $minutos), PDO::PARAM_INT);  // el corte, en minutos
        $stmt->execute();   // borra solo lo más viejo: los eventos de 30 min

        return $stmt->rowCount();   // cuántas filas se fueron, para diagnóstico
    }
}