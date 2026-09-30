<?php
// ============================================================
// persistence/NotificacionRepositoryInterface.php - Contrato
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// Contrato de la capa persistida de notificaciones (recordatorios).
//
// Aclaración de diseño: esta tabla registra COMUNICACIONES, no el estado
// de las citas. La fuente de verdad del turno es siempre la tabla citas;
// leer el estado de una cita desde acá sería duplicar información.
interface NotificacionRepositoryInterface
{
    // Devuelve una página de notificaciones paginada, con el nombre del
    // paciente y del médico para poder mostrarlas en una bandeja.
    public function obtenerPaginadas(int $offset, int $porPagina, array $filtros = []): array;
    // Cuenta el total de notificaciones que coinciden con los filtros
    public function contar(array $filtros = []): int;
    // Devuelve una notificación por id: array si existe, null si no
    public function obtenerPorId(int $id): ?array;
    // Busca una notificación por su token de confirmación/cancelación.
    // Es la ruta que permite actuar sobre una cita desde el link del
    // recordatorio, sin necesidad de que el paciente inicie sesión.
    public function obtenerPorToken(string $token): ?array;
    // Crea el registro de una notificación (queda en estado 'pendiente'):
    // devuelve el ID autogenerado
    public function crear(array $data): int;

    /**
     * Crea el aviso solo si no existe uno equivalente para la misma
     * cita, tipo y canal. Devuelve el id creado o null si ya existía.
     *
     * La unicidad la garantiza el índice uniq_cita_tipo_canal, no una
     * consulta previa: ver NotificacionRepository::crearSiNoExiste().
     *
     * @param array $data Datos de la notificación
     * @return int|null Id del aviso creado, o null si ya existía
     */
    public function crearSiNoExiste(array $data): ?int;
    // Marca la notificación como enviada: true si se actualizó
    public function marcarEnviada(int $id): bool;
    // Marca la notificación como fallida y guarda el motivo: true si se actualizó
    public function marcarFallida(int $id, string $motivo): bool;
    // Suma un intento de envío y devuelve el nuevo número de intentos: int
    public function registrarIntento(int $id): int;
    // Devuelve las notificaciones 'pendiente' más antiguas que aún no se
    // enviaron, para que un proceso las procese por lotes (outbox).
    public function obtenerPendientes(int $limite = 50): array;
    // Elimina una notificación: true si se eliminó
    public function eliminar(int $id): bool;
}
