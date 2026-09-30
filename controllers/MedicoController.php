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
     * GET /api/medicos - Listar médicos paginado
     * Query params opcionales: pagina (por defecto 1), por_pagina (por defecto 10),
     * q (búsqueda por nombre/matrícula/especialidad) y especialidad (filtro EXACTO).
     *
     * Ojo con la diferencia entre los dos filtros, porque se usan juntos en el
     * catálogo de la web: 'q' es una búsqueda de texto parcial (LIKE, tipo "buscar
     * García" y aparece todo lo que se parezca) y 'especialidad' es un filtro
     * exacto (=, tipo "los médicos de Cardiología" y solo esos).
     *
     * @param string $busqueda Texto de búsqueda (opcional)
     * @param string $especialidad Filtro exacto de especialidad (opcional)
     * @param int|null $activo 1 = solo activos, 0 = solo inactivos, null = todos
     */
    // Método que atiende la petición GET /api/medicos
    public function index(): void
    {
        // Lee los parámetros de paginación del query string (?pagina=2&por_pagina=10&q=...)
        $pagina = (int)($_GET['pagina'] ?? 1);          // Página a mostrar
        $porPagina = (int)($_GET['por_pagina'] ?? 10);  // Cantidad de items por página
        $busqueda = trim((string)($_GET['q'] ?? ''));   // Texto de búsqueda (opcional)
        $especialidad = trim((string)($_GET['especialidad'] ?? '')); // Filtro exacto (opcional)

        // El estado se parsea con un filtro estrito y NO con un cast directo.
        // La diferencia importa: con (int)($_GET['activo'] ?? null), un valor
        // cualquiera (o la cadena vacía) se convertiría en 0 y devolvería SOLO
        // los médicos dados de baja. Comparando contra '0' y '1' se acepta
        // únicamente lo válido, y todo lo demás queda en null = sin filtro.
        $activo = in_array($_GET['activo'] ?? null, ['0', '1'], true)
            ? (int) $_GET['activo']
            : null;

        // Delega en el servicio (que valida/acota los parámetros) y responde 200 OK
        Response::ok($this->service->obtenerPaginado($pagina, $porPagina, $busqueda, $especialidad, $activo));
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
            // Lee el cuerpo JSON de la petición; CuerpoJson rechaza un cuerpo
            // vacío o mal formado con un 400
            $data = CuerpoJson::leer();
            // Delega en el servicio la validación y creación
            $medico = $this->service->crear($data);
            // Responde 201 Created con JSON consistente y el médico recién creado
            Response::ok($medico, 'Médico creado correctamente', 201);
        } catch (\RuntimeException $e) {
            // 400: el cuerpo no es un objeto JSON válido
            Response::error($e->getMessage(), $e->getCode() ?: 400);
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
            $data = CuerpoJson::leer();
            // Delega en el servicio la actualización del médico con los datos enviados
            $medico = $this->service->actualizar($id, $data);
            // Responde 200 OK con el médico actualizado
            Response::ok($medico, 'Médico actualizado correctamente');
        } catch (\RuntimeException $e) {
            // 400 si el cuerpo está mal formado; 404 si el médico no existe
            Response::error($e->getMessage(), $e->getCode() ?: 404);
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