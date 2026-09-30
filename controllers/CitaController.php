<?php
// ============================================================
// controllers/CitaController.php - Capa HTTP (Turnera / Citas)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// El controlador solo traduce HTTP ↔ servicio. NO contiene reglas de negocio:
// todo lo interesante (validaciones, solapamientos, permisos por rol)
// vive en CitaService. Este archivo se limita a:
//
//   1. Leer el body JSON o el query string
//   2. Armar el contexto del usuario desde el token
//   3. Delegar en el servicio
//   4. Traducir las excepciones a códigos HTTP
class CitaController
{
    // Servicio de citas (capa de negocio), inyectado por constructor
    private CitaService $service;

    // Servicio de autenticación, usado para resolver el contexto del usuario
    // (rol + vínculos con paciente y médico) en un solo lugar compartido
    private AuthService $authService;

    /**
     * Constructor: recibe las dependencias por inyección
     * @param CitaService $service
     * @param AuthService $authService
     */
    public function __construct(CitaService $service, AuthService $authService)
    {
        // Asigna el servicio a la propiedad del controlador
        $this->service = $service;
        // Asigna el servicio de autenticación
        $this->authService = $authService;
    }

    /**
     * GET /api/citas/disponibilidad - Horarios libres de un profesional
     *
     * PÚBLICA a propósito. El paciente tiene que poder mirar la agenda
     * ANTES de iniciar sesión: si pedir turno exigiera cuenta, la mitad de
     * los que consultan el sitio ni siquiera llegan a reservar.
     *
     * Solo se devuelve si/no está disponible el horario. NUNCA el nombre del
     * paciente ni dato clínico: la disponibilidad es pública, los turnos
     * tomados no.
     *
     * Query: id_medico (obligatorio), fecha (obligatorio, YYYY-MM-DD)
     */
    public function disponibilidad(): void
    {
        try {
            // El médico es obligatorio: sin él no hay agenda que mirar
            $idMedico = (int)($_GET['id_medico'] ?? 0);
            if ($idMedico <= 0) {
                Response::error('Debe indicar el profesional', 422);
            }
            // La fecha es obligatoria
            $fecha = trim((string)($_GET['fecha'] ?? ''));
            if ($fecha === '') {
                Response::error('Debe indicar la fecha', 422);
            }

            // Delega en el servicio y responde 200 OK
            Response::ok($this->service->obtenerDisponibilidad($idMedico, $fecha));
        } catch (\InvalidArgumentException $e) {
            // Errores de validación: 422 Unprocessable Entity
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/citas/agenda - Agenda unificada de un profesional
     * Query: id_medico (obligatorio), desde y hasta (por defecto: la semana actual)
     *
     * PROTEGIDA. La agenda trae los datos completos de cada turno (nombre del
     * paciente, DNI y motivo), así que solo la ven el propio profesional y el
     * administrador. Para el resto está /api/citas/disponibilidad, que muestra
     * qué horarios están libres sin decir de quién son los tomados.
     */
    public function agenda(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();
            $rol = $contexto['rol'];

            // El paciente no tiene por qué consultar la agenda completa de un
            // profesional: su propio turno lo ve en /api/citas?mias=1
            if ($rol === 'paciente') {
                Response::error(
                    'La agenda de un profesional no está disponible para pacientes. '
                    . 'Consultá tus propios turnos en /api/citas?mias=1',
                    403
                );
            }

            $idMedico = (int)($_GET['id_medico'] ?? 0);
            if ($idMedico <= 0) {
                Response::error('Debe indicar el profesional', 422);
            }

            // El médico solo puede ver SU agenda. Se compara contra el id del
            // token, no contra un parámetro: un profesional que conozca el id
            // de otro no puede listar sus turnos con un ?id_medico= distinto.
            if ($rol === 'medico') {
                $idMedicoPropio = (int)($contexto['id_medico'] ?? 0);
                if ($idMedicoPropio <= 0) {
                    Response::error(
                        'Tu usuario no está asociado a un profesional del catálogo',
                        403
                    );
                }
                if ($idMedico !== $idMedicoPropio) {
                    Response::error('Solo podés consultar tu propia agenda', 403);
                }
            }

            // Si no se manda rango, se usa la semana actual (lunes a domingo).
            // Es la vista más útil por defecto: es cómo se consulta una agenda.
            $desde = trim((string)($_GET['desde'] ?? ''));
            $hasta = trim((string)($_GET['hasta'] ?? ''));
            if ($desde === '') {
                // date('N') devuelve 1 = lunes; se retroceden (N-1) días para llegar al lunes
                $desde = date('Y-m-d', strtotime('-' . ((int)date('N') - 1) . ' days'));
            }
            if ($hasta === '') {
                // El domingo de la misma semana es 6 días después del lunes
                $hasta = date('Y-m-d', strtotime($desde . ' +6 days'));
            }

            Response::ok($this->service->obtenerAgendaMedico($idMedico, $desde, $hasta));
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/citas - Listar citas paginadas (PROTEGIDA)
     *
     * Cada rol ve algo distinto, y eso se resuelve acá leyendo el contexto
     * del token y NO aceptando un id de paciente cualquiera:
     *
     *   paciente → solo sus propias citas (el filtro se impone, no se sugiere)
     *   médico    → por defecto las de su agenda; puede filtrar por otro
     *              médico si es admin
     *   admin     → todas, con filtros libres
     *
     * Que el paciente NO pueda pedir "las citas del paciente 7" es lo que
     * separa una API con autenticación de una que solo la simula.
     */
    public function index(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            // Se leen los filtros del query string
            $pagina = (int)($_GET['pagina'] ?? 1);
            $porPagina = (int)($_GET['por_pagina'] ?? 10);
            $estado = trim((string)($_GET['estado'] ?? ''));
            $fecha = trim((string)($_GET['fecha'] ?? ''));
            $desde = trim((string)($_GET['desde'] ?? ''));
            $hasta = trim((string)($_GET['hasta'] ?? ''));
            $busqueda = trim((string)($_GET['q'] ?? ''));

            // id_medico e id_paciente solo se aceptan si NO viene el ?mias=1
            $idMedico = (int)($_GET['id_medico'] ?? 0);
            $idPaciente = (int)($_GET['id_paciente'] ?? 0);

            // El flag "mias" pide explícitamente los turnos propios
            $soloMias = isset($_GET['mias']) && $_GET['mias'] !== '0';

            // --------------------------------------------------
            // Aplicación de la visibilidad según el rol
            // --------------------------------------------------
            $filtros = [
                'estado' => $estado,
                'fecha' => $fecha,
                'desde' => $desde,
                'hasta' => $hasta,
                'busqueda' => $busqueda,
            ];

            if ($soloMias) {
                // El paciente siempre pide los suyos: se usa su id del token,
                // ignorando por completo cualquier id que venga en la URL
                $filtros['id_paciente'] = (int)($contexto['id_paciente'] ?? 0);
            } elseif ($contexto['rol'] === 'paciente') {
                // Aunque no mande ?mias=1, un paciente sin filtro solo ve lo suyo.
                // Es una decisión de seguridad: el listado no es un endpoint abierto.
                $filtros['id_paciente'] = (int)($contexto['id_paciente'] ?? 0);

                // Un paciente cuya cuenta todavía no está vinculada a una ficha
                // no tiene turnos que ver. Se dice explícitamente en vez de
                // devolver una lista vacía, que el usuario no iba a entender
                // ("no tengo turnos" vs "tu cuenta no está vinculada").
                if ($filtros['id_paciente'] <= 0) {
                    Response::error(
                        'Tu usuario no está vinculado a una ficha de paciente. '
                        . 'Usá POST /api/auth/vincular con tu DNI para poder ver tus turnos.',
                        403
                    );
                }
            } elseif ($contexto['rol'] === 'medico') {
                // --------------------------------------------------
                // EL MÉDICO QUEDA ATRAPADO EN SU PROPIA AGENDA
                // --------------------------------------------------
                // Sin esto, un profesional que entra a /api/citas sin filtros
                // recibía la lista completa del consultorio: los turnos de
                // todos los médicos y de todos los pacientes. Con solo
                // ?id_medico=N para la agenda de otro.
                //
                // Su id sale del token, nunca de la URL. Si el profesional
                // pide la agenda de otro, se ignora el parámetro y se le
                // devuelve la suya: es más claro que un 403, y no depende de
                // que el cliente se acuerde de mandar el filtro.
                $idMedicoPropio = (int)($contexto['id_medico'] ?? 0);

                if ($idMedicoPropio <= 0) {
                    // Situación real: usuario con rol 'medico' sin id_medico
                    // asociado. Sin el vínculo no se sabe de qué agenda trata.
                    Response::error(
                        'Tu usuario no está asociado a un profesional del catálogo',
                        403
                    );
                }

                $filtros['id_medico'] = $idMedicoPropio;
            } else {
                // Solo el administrador puede cruzar filtros: la agenda de
                // cualquier profesional y los turnos de cualquier paciente
                if ($idMedico > 0) {
                    $filtros['id_medico'] = $idMedico;
                }
                if ($idPaciente > 0) {
                    $filtros['id_paciente'] = $idPaciente;
                }
            }

            // El paciente no puede buscar en los turnos de otros
            if ($contexto['rol'] === 'paciente' && $busqueda !== '') {
                // Se avisa en vez de fallar: la búsqueda sigue funcionando
                // sobre su propio historial, que es lo único que le corresponde
                $filtros['busqueda'] = $busqueda;
            }

            // Si solo quiere sus turnos vigentes, se agrega el filtro de activas
            if (isset($_GET['vigentes']) && $_GET['vigentes'] === '1') {
                $filtros['solo_activas'] = true;
            }

            Response::ok($this->service->obtenerPaginadas($pagina, $porPagina, $filtros));
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * GET /api/citas/{id} - Ver una cita (PROTEGIDA)
     */
    public function show(int $id): void
    {
        try {
            // El contexto del usuario se necesita para autorizar el acceso
            $contexto = $this->contextoDesdeToken();

            // Se obtiene la cita (lanza 404 si no existe)
            $cita = $this->service->obtenerPorId($id);

            // Se verifica que este usuario pueda verla.
            // Admin: todas. Médico: las de su agenda. Paciente: solo la suya.
            if ($contexto['rol'] === 'paciente' && (int)$cita['id_paciente'] !== (int)($contexto['id_paciente'] ?? 0)) {
                // Se responde 404 y no 403 a propósito: confirmar que el turno
                // existe pero es de otro ya revela información clínica.
                // El 404 no dice nada sobre lo que hay o no hay.
                Response::error('Cita no encontrada', 404);
            }
            if ($contexto['rol'] === 'medico' && (int)$cita['id_medico'] !== (int)($contexto['id_medico'] ?? 0)) {
                Response::error('Cita no encontrada', 404);
            }

            Response::ok($cita);
        } catch (\RuntimeException $e) {
            // 404 Not Found
            Response::error($e->getMessage(), 404);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/citas - Reservar un turno (PROTEGIDA)
     *
     * Body: id_paciente, id_medico, fecha, hora, motivo, notas
     *
     * El paciente NO necesita mandar su propio id_paciente: se toma del
     * token. Si lo mandara, el endpoint sería trivial de abusar.
     */
    public function store(): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            // Se lee el cuerpo JSON de la petición.
            // CuerpoJson::leer() además avisa con un 400 claro si el JSON vino
            // roto, en lugar de devolver un arreglo vacío y después reportar
            // un campo faltante que en realidad el cliente sí mandó.
            $data = CuerpoJson::leer();

            // Si quien reserva es un paciente, su id se impone desde el token,
            // ignorando el que haya mandado en el body
            if ($contexto['rol'] === 'paciente') {
                $data['id_paciente'] = (int)($contexto['id_paciente'] ?? 0);
            }

            // Se delega la creación (que valida todo) y se responde 201 Created
            $cita = $this->service->crear($data, $contexto);
            Response::ok($cita, 'Turno reservado correctamente', 201);
        } catch (\InvalidArgumentException $e) {
            // Validaciones: 422
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // 400 (JSON mal formado), 404 (no existe) o 409 (horario tomado)
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * PATCH /api/citas/{id}/estado - Cambiar el estado de una cita (PROTEGIDA)
     * Body: {"estado": "confirmada"}
     *
     * Cada rol tiene sus estados permitidos y eso lo valida el servicio.
     */
    public function cambiarEstado(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $data = CuerpoJson::leer();

            // El estado es obligatorio
            if (empty($data['estado'])) {
                Response::error('Debe indicar el nuevo estado', 422);
            }
            $cita = $this->service->cambiarEstado($id, (string)$data['estado'], $contexto);
            Response::ok($cita, 'Estado de la cita actualizado correctamente');
        } catch (\RuntimeException $e) {
            // 404, 403 (sin permiso) o 409 (transición inválida)
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * POST /api/citas/{id}/cancelar - Cancelar un turno (PROTEGIDA)
     *
     * Tiene endpoint propio (y no solo PATCH estado) porque cancelar es la
     * acción más frecuente de la autogestión: liberar el horario desde el
     * celular tiene que ser un botón claro, no un <select> de estados.
     */
    public function cancelar(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $cita = $this->service->cancelar($id, $contexto);
            Response::ok($cita, 'Turno cancelado. El horario quedó disponible para otro paciente.');
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 409);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * DELETE /api/citas/{id} - Eliminar una cita (PROTEGIDA, SOLO ADMIN)
     */
    public function destroy(int $id): void
    {
        try {
            $contexto = $this->contextoDesdeToken();

            $this->service->eliminar($id, $contexto);
            Response::ok(null, 'Cita eliminada correctamente');
        } catch (\RuntimeException $e) {
            // 404 (no existe) o 403 (no es admin)
            Response::error($e->getMessage(), $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Response::error('Error interno del servidor', 500);
        }
    }

    /**
     * Arma el contexto del usuario autenticado a partir del token.
     *
     * El token trae 'sub' (id del usuario), 'rol' y 'email', pero NO trae
     * id_paciente ni id_medico: esos vínculos están en la tabla usuarios.
     * Para autorizar sobre un dato clínico hay que resolverlos, y eso lo
     * hace AuthService en un solo lugar compartido por todos los módulos.
     *
     * @return array ['rol' =>, 'id_usuario' =>, 'id_paciente' =>, 'id_medico' =>]
     */
    private function contextoDesdeToken(): array
    {
        try {
            // El middleware ya verificó el token antes de llegar acá
            return $this->authService->contextoDePeticion();
        } catch (\RuntimeException $e) {
            // No se perderó el código HTTP original (401 / 404)
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        }
    }
}
