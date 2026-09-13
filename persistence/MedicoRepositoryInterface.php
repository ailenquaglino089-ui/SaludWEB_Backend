<?php
// ============================================================
// persistence/MedicoRepositoryInterface.php - Contrato de datos
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// Define las operaciones que cualquier repositorio de médicos
// debe implementar. El controlador/servicio depende de este
// CONTRATO, no de una clase concreta (inversión de dependencias).
// Una interfaz solo declara la FIRMA de los métodos (qué hacen), no su implementación (cómo)
interface MedicoRepositoryInterface
{
    // Obtiene todos los médicos: devuelve un array (lista de médicos)
    public function obtenerTodos(): array;
    // Obtiene un médico por su id: devuelve array o null si no existe (devolución nullable)
    public function obtenerPorId(int $id): ?array;
    // Crea un médico con los datos recibidos: devuelve el ID del nuevo registro
    public function crear(array $data): int;
    // Actualiza un médico existente: devuelve true si se actualizó, false si no
    public function actualizar(int $id, array $data): bool;
    // Desvincula las prescripciones asociadas al médico (no devuelve nada)
    public function desvincularPrescripciones(int $id): void;
    // Elimina un médico: devuelve true si se eliminó, false si no existía
    public function eliminar(int $id): bool;
}