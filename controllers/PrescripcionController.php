<?php
// ============================================================
// controllers/PrescripcionController.php - Capa HTTP (Prescripciones)
// ============================================================
// Módulos aplicados: "CRUD con Repository" + "Acceso profesional"
// Clase del controlador de prescripciones: recibe peticiones HTTP y delega en el servicio
class PrescripcionController
{
    // Instancia del servicio de prescripciones (capa de negocio), inyectada por constructor
    private PrescripcionService $service;

    // Constructor: recibe el servicio por inyección de dependencias
    public function __construct(PrescripcionService $service)
    {
        // Asigna el servicio a la propiedad del controlador
        $this->service = $service;
    }

    /**
     * GET /api/prescripciones - Listar prescripciones paginado
     * Query params opcionales: pagina (por defecto 1), por_pagina (por defecto 10),
     * q (búsqueda) y estado (filtro por estado)
     */
    // Método que atiende la petición GET /api/prescripciones
    public function index(): void
    {
        // Lee los parámetros de paginación y filtros del query string
        $pagina = (int)($_GET['pagina'] ?? 1);          // Página a mostrar
        $porPagina = (int)($_GET['por_pagina'] ?? 10);  // Cantidad de items por página
        $busqueda = trim((string)($_GET['q'] ?? ''));   // Texto de búsqueda (opcional)
        $estado = trim((string)($_GET['estado'] ?? ''));// Filtro por estado (opcional)
        // Delega en el servicio (que valida/acota los parámetros) y responde 200 OK
        Response::ok($this->service->obtenerPaginadas($pagina, $porPagina, $busqueda, $estado));
    }

    /**
     * GET /api/prescripciones/paciente/{id_paciente}
     * Prescripciones de un paciente específico
     */
    // Método que atiende GET /api/prescripciones/paciente/{id_paciente}
    public function indexPorPaciente(int $id_paciente): void
    {
        // Delega en el servicio y responde 200 OK con las prescripciones del paciente
        Response::ok($this->service->obtenerPorPaciente($id_paciente));
    }

    /**
     * GET /api/prescripciones/{id} - Obtener una por ID
     */
    // Método que atiende la petición GET /api/prescripciones/{id}
    public function show(int $id): void
    {
        // Bloque try: intenta obtener la prescripción y captura los errores que surjan
        try {
            // Pide la prescripción al servicio y responde 200 OK con sus datos
            Response::ok($this->service->obtenerPorId($id));
        } catch (\RuntimeException $e) {
            // Captura el caso de prescripción inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/prescripciones - Crear una nueva
     * Body: id_paciente, id_medico, medicamentos[], indicaciones, fecha_vencimiento
     */
    // Método que atiende la petición POST /api/prescripciones
    public function store(): void
    {
        // Bloque try del alta de una prescripción
        try {
            // Lee el cuerpo JSON de la petición y lo convierte en array; si falla usa null
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la validación y creación (?? [] evita pasar null)
            $prescripcion = $this->service->crear($data ?? []);
            // Responde 201 Created con JSON consistente y la prescripción recién creada
            Response::ok($prescripcion, 'Prescripción creada correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            // Captura errores de validación de entrada: HTTP 422 Unprocessable Entity
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PUT/PATCH /api/prescripciones/{id} - Actualizar
     */
    // Método que atiende las peticiones PUT/PATCH /api/prescripciones/{id}
    public function update(int $id): void
    {
        // Bloque try de la actualización
        try {
            // Lee el cuerpo JSON de la petición (campos a modificar)
            $data = json_decode(file_get_contents('php://input'), true);
            // Delega en el servicio la actualización de la prescripción con los datos enviados
            $prescripcion = $this->service->actualizar($id, $data ?? []);
            // Responde 200 OK con la prescripción actualizada
            Response::ok($prescripcion, 'Prescripción actualizada correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de prescripción inexistente: HTTP 404 Not Found
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
     * DELETE /api/prescripciones/{id} - Eliminar
     */
    // Método que atiende la petición DELETE /api/prescripciones/{id}
    public function destroy(int $id): void
    {
        // Bloque try de la eliminación
        try {
            // Delega en el servicio la eliminación (previamente valida que exista)
            $this->service->eliminar($id);
            // Responde 200 OK con mensaje de éxito (sin datos adicionales)
            Response::ok(null, 'Prescripción eliminada correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de prescripción inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PATCH /api/prescripciones/{id}/estado - Cambiar estado
     * Body: {"estado": "dispensada"}
     */
    // Método que atiende la petición PATCH /api/prescripciones/{id}/estado
    public function cambiarEstado(int $id): void
    {
        // Bloque try del cambio de estado
        try {
            // Lee el cuerpo JSON de la petición (campo "estado")
            $data = json_decode(file_get_contents('php://input'), true);
            // Valida que el cliente haya enviado el nuevo estado
            if (empty($data['estado'])) {
                // HTTP 422: el estado es un campo obligatorio
                Response::error('El estado es obligatorio', 422);
            }
            // Delega en el servicio el cambio de estado de la prescripción
            $prescripcion = $this->service->cambiarEstado($id, $data['estado']);
            // Responde 200 OK con la prescripción actualizada
            Response::ok($prescripcion, 'Estado actualizado correctamente');
        } catch (\RuntimeException $e) {
            // Captura el caso de prescripción inexistente: HTTP 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\InvalidArgumentException $e) {
            // Captura estados no permitidos: HTTP 422 Unprocessable Entity
            Response::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            Response::error('Error interno del servidor', 500);
        }
    }
}