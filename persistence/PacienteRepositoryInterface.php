<?php
// ============================================================
// persistence/PacienteRepositoryInterface.php - Contrato de datos
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// La interfaz define el CONTRATO que debe cumplir cualquier repositorio de pacientes:
// declara las firmas de los métodos (tipos de parámetros y de retorno), sin su implementación.
interface PacienteRepositoryInterface
{
    // Obtiene todos los pacientes: devuelve un array
    public function obtenerTodos(): array;
    // Obtiene una página de pacientes (paginado): recibe offset (desde qué fila),
    // cantidad por página y un texto de búsqueda opcional; devuelve el array de la página
    public function obtenerPaginado(int $offset, int $porPagina, string $busqueda = ''): array;
    // Cuenta el total de pacientes (respetando la búsqueda): devuelve un entero
    public function contar(string $busqueda = ''): int;
    // Obtiene un paciente por su id: array si existe o null si no (retorno nullable)
    public function obtenerPorId(int $id): ?array;
    // Obtiene un paciente por su dni: array si existe o null si no
    public function obtenerPorDni(string $dni): ?array;
    // Crea un paciente con los datos recibidos: devuelve el ID autogenerado
    public function crear(array $data): int;
    // Actualiza un paciente: devuelve true si la actualización se ejecutó
    public function actualizar(int $id, array $data): bool;
    // Elimina un paciente: devuelve true si se ejecutó el borrado
    public function eliminar(int $id): bool;
}