<?php
// ============================================================
// controllers/EspecialidadController.php - Capa HTTP (Catálogo de especialidades)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Las especialidades son un catálogo público: el paciente tiene que poder
// elegir la especialidad ANTES de ver qué profesionales atienden, para no
// mostrarle 80 médicos y que tenga que buscar entre ellos.
//
// Se escribe así:
//   - GETs: públicos (no necesitan login)
//   - POST/PUT/PATCH/DELETE: solo admin (el catálogo lo mantiene el consultorio)
//
// Esto es coherente con lo que ya existe en MedicoController: los catálogos
// de lectura son públicos, la gestión es administrativa.
//
// El controlador NO toca el repositorio: las reglas del catálogo (nombre
// obligatorio, duplicados ignorando tildes, no borrar con profesionales
// asociados) viven en EspecialidadService. Acá solo se traduce HTTP a
// llamadas del servicio y se convierte una excepción en un código de estado.
class EspecialidadController
{
    // Servicio de especialidades (capa de negocio)
    private EspecialidadService $service;

    // Servicio de autenticación (para saber si el usuario es admin)
    private AuthService $authService;

    /**
     * Constructor con inyección de dependencias
     * @param EspecialidadService $service
     * @param AuthService $authService
     */
    public function __construct(EspecialidadService $service, AuthService $authService)
    {
        $this->service = $service;
        $this->authService = $authService;
    }

    /**
     * GET /api/especialidades - Listado público de especialidades
     * Query: activas=0 para incluir también las dadas de baja (uso interno)
     */
    public function index(): void
    {
        try {
            $soloActivas = isset($_GET['activas']) && $_GET['activas'] !== '0';
            Response::ok($this->service->listar($soloActivas));
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/especialidades/{id} - Ver una especialidad
     */
    public function show(int $id): void
    {
        try {
            Response::ok($this->service->obtenerPorId($id));
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/especialidades - Crear especialidad (SOLO ADMIN)
     * Body: { "nombre": "Clínica Médica" }
     */
    public function store(): void
    {
        try {
            $this->verificarAdmin('crear');

            $data = CuerpoJson::leer();
            $nombre = (string)($data['nombre'] ?? '');

            Response::ok(
                $this->service->crear($nombre),
                'Especialidad creada correctamente',
                201
            );
        } catch (\InvalidArgumentException $e) {
            // 422 (nombre vacío) o 409 (ya existe)
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 401 (sin sesión) o 403 (no es admin)
            Response::error($e->getMessage(), $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PUT/PATCH /api/especialidades/{id} - Actualizar especialidad (SOLO ADMIN)
     */
    public function update(int $id): void
    {
        try {
            $this->verificarAdmin('modificar');

            $data = CuerpoJson::leer();
            $nombre = (string)($data['nombre'] ?? '');

            Response::ok(
                $this->service->actualizar($id, $nombre),
                'Especialidad actualizada correctamente'
            );
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 401, 403, 404 (no existe) o 409 (nombre duplicado)
            Response::error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * DELETE /api/especialidades/{id} - Eliminar especialidad (SOLO ADMIN)
     *
     * El servicio impide el borrado si hay profesionales asociados y explica
     * el motivo con un 409, en lugar de dejar el catálogo inconsistente.
     */
    public function destroy(int $id): void
    {
        try {
            $this->verificarAdmin('eliminar');

            $this->service->eliminar($id);
            Response::ok(null, 'Especialidad eliminada correctamente');
        } catch (\RuntimeException $e) {
            // 401, 403, 404 (no existe) o 409 (tiene médicos asociados)
            Response::error($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * Verifica que quien llama sea administrador
     *
     * @param string $accion Texto para el mensaje de error ("crear", ...)
     * @throws RuntimeException Con código 401 o 403
     */
    private function verificarAdmin(string $accion): void
    {
        $contexto = $this->contextoDesdeToken();
        if ($contexto['rol'] !== 'admin') {
            throw new \RuntimeException(
                'Solo un administrador puede ' . $accion . ' especialidades',
                403
            );
        }
    }

    /**
     * Contexto del usuario autenticado (rol + vínculos)
     */
    private function contextoDesdeToken(): array
    {
        try {
            return $this->authService->contextoDePeticion();
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode() ?: 401);
        }
    }
}
