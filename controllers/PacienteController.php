<?php
// ============================================================
// controllers/PacienteController.php - Capa HTTP (Pacientes)
// ============================================================
// Módulos aplicados: "CRUD con Repository" + "Acceso profesional"
// Clase del controlador de pacientes: recibe peticiones HTTP y delega en el servicio
class PacienteController
{
    // Instancia del servicio de pacientes (capa de negocio), inyectada por constructor
    private PacienteService $service;

    // Constructor: recibe el servicio por inyección de dependencias
    public function __construct(PacienteService $service)
    {
        // Asigna el servicio a la propiedad del controlador
        $this->service = $service;
    }

    /**
     * GET /api/pacientes - Listar todos los pacientes
     */
    // Método que atiende la petición GET /api/pacientes
    public function index(): void
    {
        // Delega en el servicio y responde 200 OK con JSON consistente
        Response::ok($this->service->obtenerTodos());
    }

    /**
     * GET /api/pacientes/{id} - Obtener un paciente por ID
     */
    // Método que atiende la petición GET /api/pacientes/{id}
    public function show(int $id): void
    {
        // Bloque try: intenta obtener el paciente y captura los errores que surjan
        try {
            // Pide el paciente al servicio y responde 200 OK con sus datos
            Response::ok($this->service->obtenerPorId($id));
        } catch (\RuntimeException $e) {
            // Captura el caso de paciente inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/pacientes - Crear un nuevo paciente
     * Body: JSON con nombre, dni, id_obra_social
     */
    // Método que atiende la petición POST /api/pacientes
    public function store(): void
    {
        // Bloque try del alta de un paciente
        try {
            // Lee el cuerpo JSON de la petición y lo convierte en array; si falla usa null
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la validación y creación (?? [] evita pasar null)
            $paciente = $this->service->crear($data ?? []);
            // Responde 201 Created con JSON consistente y el paciente recién creado
            Response::ok($paciente, 'Paciente creado correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación de entrada: HTTP 422 Unprocessable Entity
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PUT/PATCH /api/pacientes/{id} - Actualizar un paciente
     */
    // Método que atiende las peticiones PUT/PATCH /api/pacientes/{id}
    public function update(int $id): void
    {
        // Bloque try de la actualización
        try {
            // Lee el cuerpo JSON de la petición (campos a modificar)
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la actualización del paciente con los datos enviados
            $paciente = $this->service->actualizar($id, $data ?? []);
            // Responde 200 OK con el paciente actualizado
            Response::ok($paciente, 'Paciente actualizado correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de paciente inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación: HTTP 422 Unprocessable Entity
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * DELETE /api/pacientes/{id} - Eliminar un paciente
     */
    // Método que atiende la petición DELETE /api/pacientes/{id}
    public function destroy(int $id): void
    {
        // Bloque try de la eliminación
        try {
            // Delega en el servicio la eliminación (previamente valida que exista)
            $this->service->eliminar($id);
            // Responde 200 OK con mensaje de éxito (sin datos adicionales)
            Response::ok(null, 'Paciente eliminado correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de paciente inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }
}