<?php
// ============================================================
// controllers/DisponibilidadController.php - Capa HTTP (Agenda del profesional)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Gestiona los bloques horarios que el profesional publica: qué días
// atiende, entre qué horas y cuánto dura cada turno.
//
// Reglas de negocio que este endpoint respeta (viven en el servicio):
//   - Solo el propio profesional o un admin pueden modificar su agenda
//   - No se pueden superponer bloques del mismo día
//   - No se puede borrar un bloque que ya tiene turnos reservados
class DisponibilidadController
{
    // Servicio de disponibilidad (capa de negocio)
    private DisponibilidadService $service;

    // Servicio de autenticación (resuelve el contexto del usuario)
    private AuthService $authService;

    /**
     * Constructor con inyección de dependencias
     * @param DisponibilidadService $service
     * @param AuthService $authService
     */
    public function __construct(DisponibilidadService $service, AuthService $authService)
    {
        $this->service = $service;
        $this->authService = $authService;
    }

    /**
     * GET /api/disponibilidades - Ver la agenda publicada de un profesional
     * Query: id_medico (obligatorio), dia_semana (opcional, 1 = lunes ... 7 = domingo)
     *
     * Es PÚBLICA a propósito: el paciente tiene que ver los días y horarios
     * en que el profesional atiende antes de comprometerse a reservar.
     */
    public function index(): void
    {
        try {
            // El profesional es obligatorio
            $idMedico = (int)($_GET['id_medico'] ?? 0);
            if ($idMedico <= 0) {
                Response::error('Debe indicar el profesional', 422);
            }

            // El servicio devuelve la semana completa; filtrar por un solo día
            // lo hace el frontend sin necesidad de otro endpoint.
            Response::ok($this->service->obtenerPorMedico($idMedico));
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/disponibilidades - Publicar un bloque de atención (PROTEGIDA)
     * Body: id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos
     */
    public function store(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();
            $data = CuerpoJson::leer();

            $bloque = $this->service->crear($data, $contexto);
            Response::ok($bloque, 'Horario de atención publicado correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PUT/PATCH /api/disponibilidades/{id} - Editar o activar/desactivar un bloque (PROTEGIDA)
     * Body: { "activo": 0 } para ocultarlo, o los campos del bloque para editarlo
     *
     * El servicio diferencia entre las dos cosas: si viene 'activo', cambia
     * la visibilidad; si vienen los campos horarios, los actualiza.
     */
    public function update(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();
            $data = CuerpoJson::leer();

            // Si viene el flag 'activo', la operación es mostrar/ocultar.
            // Es un caso tan usado (pausar la atención en vacaciones) que
            // merece una ruta propia y legible.
            if (array_key_exists('activo', $data)) {
                $bloque = $this->service->cambiarActivo($id, (bool)(int)$data['activo'], $contexto);
                Response::ok($bloque, 'Visibilidad del horario actualizada correctamente');
                return;
            }

            // Edición del bloque. Se llama a actualizar() y no a crear() con
            // el id adentro: crear() no lee el id, así que pasarle 'id' solo
            // creaba un bloque nuevo y dejaba el viejo en la agenda.
            $bloque = $this->service->actualizar($id, $data, $contexto);
            Response::ok($bloque, 'Horario actualizado correctamente');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 404 (no existe), 403 (no es su agenda) o 409 (tiene turnos)
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * DELETE /api/disponibilidades/{id} - Quitar un bloque de la agenda (PROTEGIDA)
     *
     * Se implementa como desactivación (activo = 0) y no como borrado físico,
     * porque las citas ya reservadas necesitan seguir existiendo.
     */
    public function destroy(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $this->service->eliminar($id, $contexto);
            Response::ok(null, 'Horario quitado de la agenda');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * Contexto del usuario autenticado (rol + vínculos)
     * @return array ['rol' =>, 'id_usuario' =>, 'id_paciente' =>, 'id_medico' =>]
     */
    private function contextoDesdeToken(): array
    {
        try {
            return $this->authService->contextoDePeticion();
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        }
    }
}
