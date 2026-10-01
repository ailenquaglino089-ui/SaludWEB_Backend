<?php
// ============================================================
// persistence/RealtimeRepositoryInterface.php - Contrato de persistencia
// ============================================================
// Módulo: "Primera funcionalidad en tiempo real" (SSE)
//
// Es una INTERFAZ, no una clase. Sirve para que el servicio de tiempo real
// no sepa contra qué está hablando: si mañana la tabla eventos_realtime se
// reemplaza por Redis, Memcached o por el servicio de otro proveedor, el
// servicio no cambia ni una línea. Solo se cambia este archivo y su
// implementación, igual que el resto del proyecto.
//
// El orden de carga en core/bootstrap.php pone este contrato ANTES que la
// implementación: si fuera al revés, PHP no encontraría la interfaz al
// declarar la clase y el arranque moriría con un error fatal, no con una
// excepción manejable.

interface RealtimeRepositoryInterface
{
    /**
     * Publica un evento en un canal.
     *
     * @param string $canal Canal de destino (ver RealtimeService)
     * @param string $tipo  Qué pasó (cita_creada, cita_estado...)
     * @param array  $datos Payload mínimo en snake_case (se serializa a JSON)
     * @return int Id asignado al evento (lo usa el cliente para no releer)
     */
    public function publicar(string $canal, string $tipo, array $datos = []): int;

    /**
     * Devuelve los eventos de un canal posteriores a un id.
     *
     * El criterio "posteriores a un id" es lo que hace que la conexión no
     * pierda eventos y no repita los que ya vio: el cliente remembers el
     * último id recibido (el navegador lo reenvía solo en el encabezado
     * Last-Event-ID cuando reconecta) y cada vuelta pide solo lo nuevo.
     *
     * @param string $canal     Canal a leer
     * @param int    $ultimoId  Último id que el cliente ya recibió
     * @param int    $limite    Máximo de eventos por vuelta
     * @return array Filas { id, canal, tipo, datos, creado_at }
     */
    public function obtenerDesde(string $canal, int $ultimoId, int $limite = 50): array;

    /**
     * Devuelve el id del evento más reciente de un canal.
     *
     * Se usa al abrir la conexión: si el cliente se suscribe desde cero, no
     * quiere el historial (le importa lo que pase de ahí en adelante), así que
     * arranca leyendo desde el último id existente.
     *
     * @param string $canal Canal a consultar
     * @return int Id máximo del canal, o 0 si el canal está vacío
     */
    public function ultimoIdDe(string $canal): int;

    /**
     * Borra los eventos más viejos que una cantidad de minutos.
     *
     * La tabla crece con cada cambio de turno, y un canal que nadie escucha
     * no necesita guardar su historia para siempre. Purgar es lo que evita
     * que la "tabla de eventos" se convierta, con el uso, en un segundo
     * historial ilimitado del consultorio.
     *
     * @param int $minutos Antigüedad mínima para borrar
     * @return int Cantidad de filas borradas
     */
    public function purgarAntiguos(int $minutos): int;
}