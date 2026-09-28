<?php
// ============================================================
// controllers/NotificacionController.php - Capa HTTP (Avisos y recordatorios)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Endpoints con tres niveles de acceso distintos, y esa diferencia es el
// punto central del archivo:
//
//   1. PÚBLICOS por token (/api/notificaciones/token/...)
//      El paciente hace clic en el link del email/SMS. No tiene sesión
//      iniciada en el navegador, así que no hay JWT. En su lugar viaja un
//      token aleatorio de un solo uso práctico y con vigencia de 7 días.
//
//   2. PROTEGIDOS (listar, reintentar, generar recordatorios)
//      Solo usuarios autenticados; el servicio resuelve si pueden o no.
//
//   3. El envío real (procesarPendientes) NO se expone por HTTP.
//      Es una tarea interna: exponla sería darle a cualquier usuario un
//      endpoint para bombardear a terceros con mensajes.
class NotificacionController
{
    // Servicio de notificaciones (capa de negocio)
    private NotificacionService $service;

    // Servicio de autenticación (resuelve el contexto del usuario)
    private AuthService $authService;

    /**
     * Constructor con inyección de dependencias
     * @param NotificacionService $service
     * @param AuthService $authService
     */
    public function __construct(NotificacionService $service, AuthService $authService)
    {
        $this->service = $service;
        $this->authService = $authService;
    }

    /**
     * GET /api/notificaciones - Ver los avisos del usuario autenticado (PROTEGIDA)
     *
     * El paciente ve los avisos de SUS citas; el profesional, los de las
     * citas de su agenda. El filtro por usuario lo impone el servicio a
     * partir del token, no un parámetro de la URL.
     */
    public function index(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $pagina = (int)($_GET['pagina'] ?? 1);
            $porPagina = (int)($_GET['por_pagina'] ?? 10);
            $estado = trim((string)($_GET['estado'] ?? ''));
            $tipo = trim((string)($_GET['tipo'] ?? ''));

            // Se leen los filtros que SÍ puede elegir el usuario.
            // Los que deciden el alcance (id_usuario) los pone el servicio.
            $filtros = [
                'estado' => $estado,
                'tipo' => $tipo,
            ];

            // El admin puede auditar los avisos de cualquier usuario
            if ($contexto['rol'] === 'admin' && !empty($_GET['id_usuario'])) {
                $filtros['id_usuario'] = (int)$_GET['id_usuario'];
            }

            Response::ok($this->service->obtenerPaginadas($pagina, $porPagina, $filtros, $contexto));
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/notificaciones/recordatorios - Generar los recordatorios pendientes (PROTEGIDA)
     *
     * Equivale a "mandar el recordatorio de mañana". Se expone porque en este
     * proyecto no hay un cronjob disponible en el hosting compartido: el
     * frontend lo llama al abrir la app del consultorio.
     *
     * Es IDEMPOTENTE: si el recordatorio de esa cita ya existe, no se crea
     * otro. Por eso se puede llamar cada vez que se abre el panel sin miedo
     * a enviar el mismo aviso veinte veces.
     */
    public function generarRecordatorios(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            // Solo el consultorio (médico o admin) genera recordatorios.
            // El servicio además acota el alcance: un médico genera los de su
            // agenda, el administrador los de todo el consultorio.
            if (!in_array($contexto['rol'], ['admin', 'medico'], true)) {
                Response::error('Solo el consultorio puede generar recordatorios', 403);
            }

            // Se puede configurar cuántos días antes se avisa (por defecto 2)
            $diasAnticipacion = (int)($_GET['dias'] ?? 2);
            $resultado = $this->service->generarRecordatorios($diasAnticipacion, '', $contexto);

            Response::ok($resultado, 'Recordatorios generados correctamente');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 403 (no es una tarea del consultorio o la cuenta no tiene profesional)
            Response::error($e->getMessage(), $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/notificaciones/procesar - Enviar los avisos pendientes (PROTEGIDA, SOLO ADMIN)
     *
     * A diferencia del anterior, este endpoint SÍ tiene efecto: despacha los
     * avisos. Por eso se limita al administrador.
     */
    public function procesar(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            if ($contexto['rol'] !== 'admin') {
                Response::error('Solo un administrador puede procesar el envío de avisos', 403);
            }

            $limite = (int)($_GET['limite'] ?? 50);
            $resultado = $this->service->procesarPendientes($limite);

            Response::ok($resultado, 'Avisos procesados correctamente');
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/notificaciones/{id}/reintentar - Reintentar un aviso fallido (PROTEGIDA)
     *
     * El servicio valida el estado del aviso Y que le pertenezca a quien
     * reintenta. Estar logueado no alcanza: sin ese control, cualquier
     * usuario autenticado podía reenviar los avisos de otro poniendo su id
     * en la URL, y reintentar dispara un envío real.
     */
    public function reintentar(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $notificacion = $this->service->reintentar($id, $contexto);
            Response::ok($notificacion, 'Aviso puesto en cola otra vez');
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 404 (no existe o no es suyo) o 409 (estado que no admite reintento)
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/notificaciones/token/{token} - Ver el turno detrás de un token (PÚBLICA)
     *
     * Es la pantalla que se abre al hacer clic en "Ver mi turno" del aviso.
     * Solo devuelve lo necesario para identificar el turno: fecha, hora,
     * profesional y estado. NUNCA el motivo de consulta ni las notas, que
     * son datos clínicos y no necesitan viajar por un link.
     */
    public function resolverPorToken(string $token): void
    {
        try {
            Response::ok($this->service->resolverPorToken($token));
        } catch (\RuntimeException $e) {
            // 404 = link inválido, vencido o ya usado para otra acción
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/notificaciones/token/{token}/confirmar - Confirmar asistencia (PÚBLICA)
     *
     * El paciente confirma desde el link sin necesidad de loguearse.
     * Es la acción que más valor tiene: reduce el ausentismo sin pedirle
     * que abra una app ni recuerde una contraseña.
     */
    public function confirmarPorToken(string $token): void
    {
        try {
            $cita = $this->service->confirmarPorToken($token);
            Response::ok($cita, 'Turno confirmado. Te esperamos en el consultorio.');
        } catch (\RuntimeException $e) {
            // 409 = el turno ya no se puede confirmar (cancelado o ya atendido)
            Response::error($e->getMessage(), 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/notificaciones/token/{token}/cancelar - Cancelar desde el link (PÚBLICA)
     */
    public function cancelarPorToken(string $token): void
    {
        try {
            $cita = $this->service->cancelarPorToken($token);
            Response::ok($cita, 'Turno cancelado. El horario quedó libre para otro paciente.');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
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
