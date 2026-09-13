<?php
// ============================================================
// controllers/MedicoController.php - Capa de controlador HTTP
// ============================================================
// Módulos aplicados: "Primera API en PHP" + "CRUD con Repository"
// El Controller interpreta la petición HTTP y delega en el
// Servicio. NO contiene SQL. Todas las respuestas pasan por
// el helper Response (JSON consistente).
//
// Flujo: Controlador -> Servicio -> Repository -> MySQL -> JSON
// ============================================================

// Clase del controlador de médicos: recibe peticiones HTTP y delega en el servicio
class MedicoController
{
    // Instancia del servicio de médicos (capa de negocio), inyectada por constructor
    private MedicoService $service;

    // Constructor: recibe el servicio por inyección de dependencias
    public function __construct(MedicoService $service)
    {
        // Asigna el servicio a la propiedad del controlador
        $this->service = $service;
    }

    /**
     * GET /api/medicos - Listar todos los médicos
     */
    // Método que atiende la petición GET /api/medicos
    public function index(): void
    {
        // Delega en el servicio y responde 200 OK con JSON consistente
        Response::ok($this->service->obtenerTodos());
    }

    /**
     * GET /api/medicos/{id} - Obtener un médico por ID
     */
    // Método que atiende la petición GET /api/medicos/{id}
    public function show(int $id): void
    {
        // Bloque try: intenta obtener el médico y captura los errores que surjan
        try {
            // Pide el médico al servicio y responde 200 OK con sus datos
            Response::ok($this->service->obtenerPorId($id));
        } catch (\RuntimeException $e) {
            // Captura el caso de médico inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/medicos - Crear un nuevo médico (alta)
     */
    // Método que atiende la petición POST /api/medicos
    public function store(): void
    {
        // Bloque try del alta de un médico
        try {
            // Lee el cuerpo JSON de la petición y lo convierte en array; si falla usa null
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la validación y creación (?? [] evita pasar null)
            $medico = $this->service->crear($data ?? []);
            // Responde 201 Created con JSON consistente y el médico recién creado
            Response::ok($medico, 'Médico creado correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación de entrada: HTTP 422 Unprocessable Entity
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PUT/PATCH /api/medicos/{id} - Actualizar un médico
     */
    // Método que atiende las peticiones PUT/PATCH /api/medicos/{id}
    public function update(int $id): void
    {
        // Bloque try de la actualización
        try {
            // Lee el cuerpo JSON de la petición (campos a modificar)
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la actualización del médico con los datos enviados
            $medico = $this->service->actualizar($id, $data ?? []);
            // Responde 200 OK con el médico actualizado
            Response::ok($medico, 'Médico actualizado correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de médico inexistente: HTTP 404 Not Found
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
     * DELETE /api/medicos/{id} - Eliminar un médico
     */
    // Método que atiende la petición DELETE /api/medicos/{id}
    public function destroy(int $id): void
    {
        // Bloque try de la eliminación
        try {
            // Delega en el servicio la eliminación (previamente valida que exista)
            $this->service->eliminar($id);
            // Responde 200 OK con mensaje de éxito (sin datos adicionales)
            Response::ok(null, 'Médico eliminado correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de médico inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }
}