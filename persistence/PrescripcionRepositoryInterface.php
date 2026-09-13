<?php
// ============================================================
// persistence/PrescripcionRepositoryInterface.php - Contrato
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// La interfaz declara el CONTRATO que debe cumplir cualquier repositorio de prescripciones:
// solo las firmas de los métodos, sin detalles de implementación (la clase concreta decide el SQL).
interface PrescripcionRepositoryInterface
{
    // Devuelve todas las prescripciones (array)
    public function obtenerTodas(): array;
    // Devuelve las prescripciones de un paciente específico (array; id_paciente entero)
    public function obtenerPorPaciente(int $id_paciente): array;
    // Devuelve una prescripción por id: array si existe o null si no
    public function obtenerPorId(int $id): ?array;
    // Crea una prescripción: devuelve el ID autogenerado
    public function crear(array $data): int;
    // Actualiza una prescripción: true si se ejecutó la actualización
    public function actualizar(int $id, array $data): bool;
    // Elimina una prescripción: true si se ejecutó el borrado
    public function eliminar(int $id): bool;
    // Cambia el estado de una prescripción (activa, vencida, dispensada...): true si se actualizó
    public function cambiarEstado(int $id, string $nuevoEstado): bool;
}