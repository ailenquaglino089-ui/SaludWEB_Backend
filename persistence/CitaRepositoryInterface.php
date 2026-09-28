<?php
// ============================================================
// persistence/CitaRepositoryInterface.php - Contrato
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// Contrato de persistencia de las citas (turnos) de la turnera.
interface CitaRepositoryInterface
{
    // Devuelve una página de citas paginada, ya enrichecida con los nombres
    // del paciente y del médico. Recibe offset, cantidad, filtros y el
    // id del médico restringir (0 = sin restricción).
    // $filtros acepta: estado (string), fecha (YYYY-MM-DD), desde, hasta,
    // id_paciente, id_medico, busqueda, solo_activas, creado_por_paciente.
    public function obtenerPaginadas(int $offset, int $porPagina, array $filtros = []): array;
    // Cuenta el total de citas que coinciden con los filtros (para el paginado)
    public function contar(array $filtros = []): int;
    // Devuelve una cita por id: array si existe, null si no
    public function obtenerPorId(int $id): ?array;
    // Devuelve las citas de un paciente en un rango de fechas (array)
    public function obtenerPorPacienteYRango(int $idPaciente, string $desde, string $hasta): array;
    // Devuelve las citas de un médico en una fecha puntual (array).
    // Es la consulta que alimenta la agenda unificada del profesional.
    public function obtenerPorMedicoYFecha(int $idMedico, string $fecha): array;
    // Crea una cita: devuelve el ID autogenerado.
    // Puede lanzar una PDOException con código 23000 (clave duplicada) si
    // otro paciente reservó ese mismo horario; el servicio la traduce a
    // un error de negocio legible en lugar de un 500.
    public function crear(array $data): int;
    // Actualiza los campos enviados de una cita: true si se actualizó
    public function actualizar(int $id, array $data): bool;
    // Cambia el estado de la cita y actualiza slot_reservado según corresponda:
    // true si se actualizó. Centraliza la regla que libera el horario.
    public function cambiarEstado(int $id, string $estado): bool;
    // Elimina una cita: true si se eliminó (solo para uso administrativo)
    public function eliminar(int $id): bool;
    // Marca la cita como recordatorio ya enviado: true si se actualizó.
    // Evita generar y enviar el mismo recordatorio varias veces.
    public function marcarRecordatorioEnviado(int $id): bool;
    // Devuelve el detalle de la tabla en un associative con contadores.
    // Devuelve un array con las claves: total, por_estado (assoc estado=>conteo)
    // y ausentismo_porcentaje.
    public function obtenerResumen(int $idMedico = 0): array;
    // Cuenta las citas agrupadas por día dentro de un rango: array
    // ['2026-09-25' => 4, ...]. Alimenta el gráfico de demanda real.
    public function contarPorDia(string $desde, string $hasta, int $idMedico = 0): array;
    // Cuenta las citas agrupadas por especialidad: array [['especialidad'=>.., 'total'=>N], ...]
    public function contarPorEspecialidad(string $desde, string $hasta, int $idMedico = 0): array;
    // Cuenta las citas agrupadas por médico: array [['nombre'=>.., 'total'=>N, 'ausentes'=>M], ...]
    public function contarPorMedico(string $desde, string $hasta, int $idMedico = 0): array;
}
