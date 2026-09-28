<?php
// ============================================================
// controllers/EstadisticaController.php - Capa HTTP (Panel de gestión)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Expone métricas y KPIs para el panel de administración. Los datos
// no son en tiempo real por diseño (ver explicación en EstadisticaService):
// un retraso de 30 segundos no afecta una decisión de gestión.
//
// El panel es información administrativa, así que requiere autenticación.
// El alcance lo define el rol dentro del controlador: el admin ve el
// consultorio entero y el médico solo su agenda.
class EstadisticaController
{
    // Servicio de estadísticas (capa de negocio)
    private EstadisticaService $service;

    // Servicio de autenticación
    private AuthService $authService;

    /**
     * Constructor con inyección de dependencias
     * @param EstadisticaService $service
     * @param AuthService $authService
     */
    public function __construct(EstadisticaService $service, AuthService $authService)
    {
        $this->service = $service;
        $this->authService = $authService;
    }

    /**
     * GET /api/estadisticas - Informe general de turnos
     * Query: desde (YYYY-MM-DD), hasta (YYYY-MM-DD)
     *
     * Alcance según el rol:
     *   admin   → métricas de todo el consultorio
     *   médico  → métricas SOLO de su agenda
     *   otro    → 403
     *
     * El acotado para el médico no es un detalle menor: el ausentismo y la
     * ocupación del consultorio completo son datos de la dirección, y un
     * profesional no tiene por qué ver el rendimiento de sus colegas ni la
     * demanda de las especialidades que no atiende. El id sale del token, así
     * que no se puede pedir el informe de otro con un parámetro.
     *
     * Un paciente NO debe ver el ausentismo de la agenda: está excluido por rol.
     */
    public function index(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            // Restricción por rol: solo quien gestiona la agenda necesita KPIs
            if (!in_array($contexto['rol'], ['admin', 'medico'], true)) {
                Response::error('No tenés permisos para ver las estadísticas del consultorio', 403);
                return;
            }

            // Se resuelve el alcance ANTES de leer los filtros de fecha
            $idMedico = 0;   // 0 = todo el consultorio
            if ($contexto['rol'] === 'medico') {
                $idMedico = (int)($contexto['id_medico'] ?? 0);
                if ($idMedico <= 0) {
                    Response::error(
                        'Tu usuario no está asociado a un profesional del catálogo',
                        403
                    );
                    return;
                }
            }

            $desde = trim((string)($_GET['desde'] ?? ''));
            $hasta = trim((string)($_GET['hasta'] ?? ''));

            Response::ok($this->service->generarInforme($desde, $hasta, $idMedico));
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
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
