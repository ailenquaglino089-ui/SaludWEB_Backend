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
    // Obtiene todos los médicos: devuelve un array (lista de médicos).
    // $filtros acepta 'activo' (1/0) y 'id' (un profesional puntual). Vacío
    // = todos. Lo usan las estadísticas para no contar como agenda publicada
    // la de un profesional que está dado de baja.
    public function obtenerTodos(array $filtros = []): array;
    // Obtiene una página de médicos (paginado): recibe offset, cantidad por página,
    // texto de búsqueda opcional, especialidad exacta opcional y filtro de estado
    // opcional; devuelve el array de la página actual
    public function obtenerPaginado(int $offset, int $porPagina, string $busqueda = '', string $especialidad = '', ?int $activo = null): array;
    // Cuenta el total de médicos (respetando la búsqueda, la especialidad y el estado):
    // devuelve un entero
    public function contar(string $busqueda = '', string $especialidad = '', ?int $activo = null): int;
    // Obtiene un médico por su id: devuelve array o null si no existe (devolución nullable)
    public function obtenerPorId(int $id): ?array;
    // Obtiene un médico por su número de matrícula profesional.
    // Módulo "Sistema de gestión de citas online": la matrícula es el
    // identificador con el que un profesional demuestra quién es al vincular
    // su cuenta de usuario con su ficha, así que necesita una búsqueda directa.
    // Devuelve null si no hay coincidencia (puede haber varias en teoría, pero
    // en la práctica la matrícula es única: se toma la primera).
    public function obtenerPorMatricula(string $matricula): ?array;
    // Crea un médico con los datos recibidos: devuelve el ID del nuevo registro
    public function crear(array $data): int;
    // Actualiza un médico existente: devuelve true si se actualizó, false si no
    public function actualizar(int $id, array $data): bool;
    // Desvincula las prescripciones asociadas al médico (no devuelve nada)
    public function desvincularPrescripciones(int $id): void;
    // Elimina un médico: devuelve true si se eliminó, false si no existía
    public function eliminar(int $id): bool;
}