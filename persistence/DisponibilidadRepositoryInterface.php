<?php
// ============================================================
// persistence/DisponibilidadRepositoryInterface.php - Contrato
// ============================================================
// Módulo: "Acceso profesional a datos en PHP"
// Contrato de la agenda horaria del profesional (bloques de atención
// semanales recurrentes). Solo firmas, sin SQL.
interface DisponibilidadRepositoryInterface
{
    // Devuelve todos los bloques de un médico (array).
    // $soloActivos filtra por activo = 1 (lo que usa el paciente al reservar).
    public function obtenerPorMedico(int $idMedico, bool $soloActivos = true): array;
    // Devuelve los bloques de un médico para un día de la semana concreto
    // (1 = lunes ... 7 = domingo). Es la consulta que usa el cálculo de
    // horarios disponibles de una fecha puntual.
    public function obtenerPorMedicoYDia(int $idMedico, int $diaSemana): array;
    // Devuelve un bloque por id: array si existe, null si no
    public function obtenerPorId(int $id): ?array;
    // Crea un bloque de atención: devuelve el ID autogenerado
    public function crear(array $data): int;
    // Actualiza un bloque existente con los campos que vienen en $datos.
    // Solo se escriben las columnas presentes en el array, de modo que un
    // campo omitido conserva su valor. Devuelve false si no había nada que
    // actualizar. Existe separada de crear() porque reutilizar crear() para
    // editar creaba un bloque nuevo en lugar de modificar el existente.
    public function actualizar(int $id, array $datos): bool;
    // Elimina un bloque de atención: true si se eliminó
    // (las citas ya tomadas se conservan; hay que revisar si conviene
    //  desactivar en lugar de borrar cuando el bloque tiene historial)
    public function eliminar(int $id): bool;
    // Activa o desactiva un bloque sin borrarlo: true si se actualizó.
    // Desactivar es la opción preferida porque no rompe el histórico.
    public function cambiarActivo(int $id, bool $activo): bool;
    // Verifica si un bloque pertenece a determinado médico: bool.
    // Se usa para autorizar (un médico no puede borrar el bloque de otro).
    public function esDeMedico(int $id, int $idMedico): bool;
}
