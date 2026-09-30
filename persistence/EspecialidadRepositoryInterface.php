<?php
// ============================================================
// persistence/EspecialidadRepositoryInterface.php - Contrato
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// La interfaz declara el CONTRATO que debe cumplir cualquier repositorio
// de especialidades: solo las firmas de los métodos, sin detalles de
// implementación (la clase concreta decide el SQL).
interface EspecialidadRepositoryInterface
{
    // Devuelve todas las especialidades (array). Si $soloActivas es true
    // filtra por activo = 1, que es lo que necesitan los formularios.
    public function obtenerTodas(bool $soloActivas = true): array;
    // Devuelve una especialidad por id: array si existe, null si no
    public function obtenerPorId(int $id): ?array;
    // Busca una especialidad por nombre exacto (para no crear duplicados)
    public function obtenerPorNombre(string $nombre): ?array;
    // Crea una especialidad: devuelve el ID autogenerado
    public function crear(string $nombre): int;
    // Actualiza el nombre de una especialidad: true si se actualizó
    public function actualizar(int $id, string $nombre): bool;
    // Elimina una especialidad: true si se eliminó
    public function eliminar(int $id): bool;
    // Cuenta cuántos médicos activos tienen esta especialidad: entero
    // Se usa en el catálogo para mostrar "N profesionales" sin traerlos todos
    public function contarMedicos(int $id): int;
}
