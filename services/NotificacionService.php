<?php
// ============================================================
// services/NotificacionService.php - Capa de negocio (Recordatorios)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// La turnera promete "reducción del ausentismo" y su mecanismo es el
// recordatorio. Este servicio es el que cumple esa promesa, y lo hace
// resolviendo el problema clásico de los avisos:
//
//   Exigir inicio de sesión para confirmar mata el recordatorio.
//   Si el paciente tiene que buscar usuario y contraseña para confirmar
//   que va, la mayoría no lo hace: el mensaje se ignora y el turno se pierde.
//   Por eso el recordatorio lleva un token: el paciente confirma o cancela
//   desde el propio mensaje, en un clic, sin autenticarse.
//
// ARQUITECTURA DEL ENVÍO (patrón "outbox")
// ----------------------------------------
//   1. generarRecordatorios()  → crea los registros en estado 'pendiente'
//   2. procesarPendientes()    → los envía a través del proveedor
//
//   Los dos pasos están separados a propósito. Si el proveedor de email
//   está caído, los recordatorios NO se pierden: quedan en 'pendiente' y
//   se reintentan después. Si se enviara todo en un solo paso, una caída
//   del proveedor borraría los avisos de los pacientes.
//
//   Y la tabla notificaciones NUNCA decide el estado de una cita: eso se
//   lee siempre en la tabla citas. Esta tabla registra comunicaciones,
//   no hechos. Esa separación es lo que evita la "fuente de verdad difusa"
//   que describe la guía de datos en tiempo real.
class NotificacionService
{
    // Repositorio de notificaciones (persistencia)
    private NotificacionRepository $repo;

    // Repositorio de citas (para saber a qué turno corresponde cada aviso)
    private CitaRepository $citaRepo;

    // Repositorio de contactos (para resolver el email del paciente)
    private ContactoRepository $contactoRepo;

    // Proveedor de envío (la implementación concreta: stub, mail, whatsapp...)
    private ProveedorNotificacionesInterface $proveedor;

    // Canal de envío por defecto que usa generarRecordatorios()
    private string $canalPorDefecto;

    // Duración de vida del token de confirmación/cancelación, en días.
    // Pasado ese plazo el enlace deja de servir, para que un mensaje
    // vieux no permita tocar un turno que ya cambió.
    private const DIAS_VIGENCIA_TOKEN = 7;

    /**
     * Constructor con inyección de dependencias
     * @param NotificacionRepository $repo
     * @param CitaRepository $citaRepo
     * @param ContactoRepository $contactoRepo
     * @param ProveedorNotificacionesInterface $proveedor
     * @param string $canalPorDefecto Canal a usar ('sistema', 'email', 'whatsapp')
     */
    public function __construct(
        NotificacionRepository $repo,
        CitaRepository $citaRepo,
        ContactoRepository $contactoRepo,
        ProveedorNotificacionesInterface $proveedor,
        string $canalPorDefecto = 'sistema'
    ) {
        $this->repo = $repo;
        $this->citaRepo = $citaRepo;
        $this->contactoRepo = $contactoRepo;
        $this->proveedor = $proveedor;
        $this->canalPorDefecto = $canalPorDefecto;
    }

    // ============================================================
    // GENERACIÓN DE RECORDATORIOS
    // ============================================================

    /**
     * Genera los recordatorios de las citas próximas que todavía no tienen aviso.
     *
     * El criterio "todavía no tienen aviso" se apoya en dos cosas:
     *   - citas.recordatorio_enviado = 0
     *   - que no exista ya una notificación de tipo recordatorio para esa cita
     *
     * La doble condición es a propósito: es idempotente. Si el endpoint se
     * llama dos veces (por ejemplo, porque dos administradores lo presionaron
     * al mismo tiempo) no se generan recordatorios duplicados, que es
     * exactamente lo que evita molestar al paciente con dos avisos iguales.
     *
     * @param int $diasAnticipacion Ventana de anticipación a cubrir (por defecto 2)
     * @param string $canal Canal de envío (vacío = el configurado por defecto)
     * @param array $contexto Contexto de quien dispara la generación. El
     *                        administrador cubre todo el consultorio; un
     *                        médico, solo su agenda. Sin esto, un profesional
     *                        disparaba los avisos de los pacientes de los demás
     *                        simplemente llamando al endpoint.
     * @return array ['generados' => int, 'omitidos' => int, 'detalle' => array]
     */
    public function generarRecordatorios(int $diasAnticipacion = 2, string $canal = '', array $contexto = []): array
    {
        // Se acota la ventana a un rango razonable
        $dias = max(1, min(30, (int)$diasAnticipacion));
        $canalUsado = $canal !== '' ? $canal : $this->canalPorDefecto;

        // Fecha de hoy
        $hoy = date('Y-m-d');
        // Fecha límite: hoy + ventana de anticipación
        $hasta = date('Y-m-d', strtotime('+' . $dias . ' days'));

        // Se buscan las citas vivas de la ventana temporal
        $filtros = [
            'desde' => $hoy,
            'hasta' => $hasta,
            'solo_activas' => true,
        ];

        // ------------------------------------------------------
        // ALCANCE: toda la agenda o solo la del profesional que llama
        // ------------------------------------------------------
        // El id sale del token, no de la URL, así que no se puede falsear.
        $rol = (string)($contexto['rol'] ?? '');
        if ($rol === 'medico') {
            $idMedicoPropio = (int)($contexto['id_medico'] ?? 0);
            if ($idMedicoPropio <= 0) {
                throw new \RuntimeException(
                    'Tu usuario no está asociado a un profesional del catálogo',
                    403
                );
            }
            $filtros['id_medico'] = $idMedicoPropio;
        } elseif ($rol !== 'admin') {
            // Cualquier otro rol no genera recordatorios: es una tarea del consultorio
            throw new \RuntimeException('Solo el consultorio puede generar recordatorios', 403);
        }

        $citas = $this->citaRepo->obtenerPaginadas(0, 200, $filtros);

        $generados = 0;
        $omitidos = 0;
        $detalle = [];

        // Recorre cada cita de la ventana
        foreach ($citas as $cita) {
            // Atajo del repositorio: si la cita ya fue marcada con recordatorio
            // enviado, no se vuelve a avisar. Es la primera barrera y es la más barata.
            if (!empty($cita['recordatorio_enviado'])) {
                $omitidos++;
                continue;
            }

            // Segunda barrera: se consulta si ya existe un recordatorio para
            // esta cita. Cubre el caso en que la cita se marcó mal, y sobre
            // todo cubre la carrera entre dos llamadas simultáneas.
            $yaAvisado = $this->repo->obtenerPaginadas(0, 1, [
                'id_cita' => (int)$cita['id'],
                'tipo' => 'recordatorio',
            ]);
            if (!empty($yaAvisado)) {
                $omitidos++;
                continue;
            }

            // Se resuelve a dónde enviar. Sin destino NO se crea un registro
            // 'pendiente': es preferible un registro 'fallido' con el motivo
            // explícito a un pendiente que después nadie va a poder enviar.
            $contacto = $this->contactoRepo->obtenerContactoDePaciente((int)$cita['id_paciente']);
            if (!$contacto['tiene_cuenta']) {
                // Da igual si el aviso se crea ahora o ya existía: en los
                // dos casos la cita queda sin avisar y se cuenta como omitida.
                $this->repo->crearSiNoExiste([
                    'id_cita' => (int)$cita['id'],
                    'id_usuario' => null,
                    'tipo' => 'recordatorio',
                    'canal' => $canalUsado,
                    'destino' => null,
                    'estado' => 'fallido',
                    'token_cancelacion' => $this->generarToken(),
                    // Guardar el motivo evita el clásico "no llegó el
                    // recordatorio" sin explicación posible
                    'motivo_error' => 'El paciente no tiene una cuenta con email registrado',
                ]);
                $omitidos++;
                continue;
            }

            // Se genera un token único para que el paciente confirme o cancele
            // desde el mensaje, sin iniciar sesión
            $token = $this->generarToken();

            // Se crea el registro del recordatorio, en estado 'pendiente'.
            // Todavía NO se envió: la generación y el envío son pasos separados.
            //
            // crearSiNoExiste() y no crear() a propósito: entre el SELECT de
            // arriba y este INSERT hay una ventana en la que otro proceso
            // podría insertar el mismo aviso. El índice único
            // uniq_cita_tipo_canal convierte esa carrera en un no-op en
            // lugar de un recordatorio duplicado.
            $idNotificacion = $this->repo->crearSiNoExiste([
                'id_cita' => (int)$cita['id'],
                // Se guarda a qué cuenta pertenece el aviso. Sin esto, el
                // paciente no vería sus propios recordatorios en la app,
                // porque el listado se filtra por id_usuario del token.
                'id_usuario' => $contacto['id_usuario'] ?? null,
                'tipo' => 'recordatorio',
                'canal' => $canalUsado,
                'destino' => $contacto['email'],
                'estado' => 'pendiente',
                'token_cancelacion' => $token,
            ]);

            // null = otro proceso se adelantó y ya había creado el aviso
            if ($idNotificacion === null) {
                $omitidos++;
                continue;
            }

            $generados++;
            $detalle[] = [
                'id_notificacion' => $idNotificacion,
                'id_cita' => (int)$cita['id'],
                'destino' => $contacto['email'],
            ];
        }

        return [
            'generados' => $generados,
            'omitidos' => $omitidos,
            'canal' => $canalUsado,
            'ventana_dias' => $dias,
            'detalle' => $detalle,
        ];
    }

    /**
     * Envía las notificaciones pendientes que todavía no salieron.
     *
     * Se procesan por lotes y se registra el resultado de cada una. Un fallo
     * individual NO detiene el lote: un solo email inválido no puede impedir
     * que los otros pacientes reciban su recordatorio.
     *
     * @param int $limite Máximo de notificaciones a procesar en esta corrida
     * @return array ['procesadas' => int, 'enviadas' => int, 'fallidas' => int, 'detalle' => array]
     */
    public function procesarPendientes(int $limite = 50): array
    {
        $limite = max(1, min(200, (int)$limite));

        // Se toman las pendientes más antiguas (patrón outbox: FIFO)
        $pendientes = $this->repo->obtenerPendientes($limite);

        $enviadas = 0;
        $fallidas = 0;
        $detalle = [];

        // Recorre cada notificación pendiente
        foreach ($pendientes as $notificacion) {
            $id = (int)$notificacion['id'];

            // Se cuenta el intento ANTES de enviar, para tener trazabilidad
            // incluso si el envío rompe la ejecución
            $intentos = $this->repo->registrarIntento($id);

            // ------------------------------------------------------
            // Resultado de ESTA notificación.
            //
            // Se guarda en una variable propia y no se deduce de los
            // contadores acumulados. Si se dedujera al final
            // ($enviadas > $fallidas), en un lote mixto todos los detalles
            //quedarían marcados con el mismo resultado, y el que falló
            // aparecería como enviado: exactamente el error que hace creer
            // que el paciente recibió el aviso cuando no lo recibió.
            // ------------------------------------------------------
            $resultadoDeEsta = 'fallido';
            $idCita = (int)$notificacion['id_cita'];

            // Se recupera la cita para armar el mensaje
            $cita = $this->citaRepo->obtenerPorId($idCita);
            if (!$cita) {
                // La cita ya no existe (fue borrada): el aviso no tiene sentido
                $this->repo->marcarFallida($id, 'La cita asociada ya no existe');
                $fallidas++;

                $detalle[] = [
                    'id_notificacion' => $id,
                    'id_cita' => $idCita,
                    'intentos' => $intentos,
                    'resultado' => $resultadoDeEsta,
                ];
                continue;
            }

            // Se arma el cuerpo del mensaje con los datos de la cita
            $mensaje = $this->armarMensaje($cita, $notificacion);

            // Se intenta el envío a través del proveedor
            try {
                $resultado = $this->proveedor->enviar($mensaje);

                if (!empty($resultado['ok'])) {
                    // Envío confirmado: se marca como enviado con su timestamp
                    $this->repo->marcarEnviada($id);
                    // Y se marca la cita para no volver a generar otro aviso
                    $this->citaRepo->marcarRecordatorioEnviado((int)$cita['id']);
                    $enviadas++;
                    $resultadoDeEsta = 'enviado';
                } else {
                    // El proveedor respondió con error
                    $this->repo->marcarFallida($id, (string)($resultado['detalle'] ?? 'Error desconocido'));
                    $fallidas++;
                }
            } catch (\Exception $e) {
                // El proveedor lanzó una excepción (por ejemplo, error de red).
                // Se captura para que un fallo de infraestructura no corte
                // el lote entero ni devuelva un 500 al usuario.
                $this->repo->marcarFallida($id, 'Excepción al enviar: ' . $e->getMessage());
                $fallidas++;
            }

            $detalle[] = [
                'id_notificacion' => $id,
                'id_cita' => (int)$cita['id'],
                'intentos' => $intentos,
                // El resultado REAL de esta notificación, no el acumulado
                'resultado' => $resultadoDeEsta,
            ];
        }

        return [
            'procesadas' => count($pendientes),
            'enviadas' => $enviadas,
            'fallidas' => $fallidas,
            'detalle' => $detalle,
        ];
    }

    /**
     * Genera y envía en un solo paso. Es el atajo que usa el personal del
     * consultorio: "mandar los recordatorios de hoy" en un solo click.
     * @param int $diasAnticipacion Ventana de anticipación
     * @param string $canal Canal de envío
     * @return array Resultado combinado de generación y envío
     */
    public function enviarRecordatorios(int $diasAnticipacion = 2, string $canal = ''): array
    {
        $generacion = $this->generarRecordatorios($diasAnticipacion, $canal);
        $envio = $this->procesarPendientes(200);

        return [
            'generacion' => $generacion,
            'envio' => $envio,
        ];
    }

    // ============================================================
    // CONFIRMACIÓN Y CANCELACIÓN DESDE EL LINK DEL RECORDATORIO
    // ============================================================

    /**
     * Resuelve un token de recordatorio y devuelve la cita asociada.
     *
     * NO cambia nada: solo resuelve. Se usa para que el frontend pueda
     * mostrarle al paciente los datos del turno ANTES de que confirme
     * ("¿Confirmás tu turno con la Dra. López mañana a las 10:00?").
     * Pedir confirmación a ciegas es la peor experiencia posible.
     *
     * @param string $token Token recibido en el enlace
     * @return array Datos de la cita y del recordatorio
     * @throws RuntimeException Si el token no existe o venció (404/410)
     */
    public function resolverPorToken(string $token): array
    {
        // El token tiene que tener forma de token; se valida antes de ir a la base
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new \RuntimeException('El enlace no es válido', 404);
        }

        // Se busca la notificación por su token
        $notificacion = $this->repo->obtenerPorToken($token);
        if (!$notificacion) {
            throw new \RuntimeException('El enlace no es válido o ya no está disponible', 404);
        }

        // Se verifica la vigencia del token.
        // Sin este control, un mensaje viejo podría tocar un turno que ya
        // cambió o que ya se atendió, y el paciente vería información vieja
        // como si fuera actual.
        $creado = strtotime($notificacion['creado_at']);
        if ($creado === false) {
            throw new \RuntimeException('El enlace no es válido', 404);
        }
        $vence = strtotime('+' . self::DIAS_VIGENCIA_TOKEN . ' days', $creado);
        if (time() > $vence) {
            throw new \RuntimeException('El enlace venció. Pedí un nuevo recordatorio.', 410);
        }

        // Se carga la cita asociada
        $cita = $this->citaRepo->obtenerPorId((int)$notificacion['id_cita']);
        if (!$cita) {
            throw new \RuntimeException('La cita ya no está disponible', 404);
        }

        return [
            // La cita se devuelve FILTRADA, no completa. Este endpoint es
            // público: lo abre cualquiera que tenga el link, sin sesión ni
            // token JWT. Si se devolviera la cita entera, el motivo de
            // consulta y el DNI del paciente viajarían por un enlace que
            // puede acabar en un reenvío de correo.
            //
            // Se expone lo mínimo para que el paciente sepa de qué turno se
            // trata y pueda confirmar: cuándo, con quién y en qué estado.
            'cita' => [
                'id' => (int)$cita['id'],
                'fecha' => $cita['fecha'],
                'hora' => $cita['hora'],
                'duracion_minutos' => (int)$cita['duracion_minutos'],
                'estado' => $cita['estado'],
                'nombre_medico' => $cita['nombre_medico'] ?? null,
                'especialidad' => $cita['especialidad'] ?? null,
            ],
            'notificacion' => [
                'id' => (int)$notificacion['id'],
                'tipo' => $notificacion['tipo'],
                'estado' => $notificacion['estado'],
                'vence' => date('Y-m-d', $vence),
            ],
        ];
    }

    /**
     * Confirma una cita usando el token del recordatorio.
     *
     * Es la operación que REDUCE EL AUSENTISMO: el paciente confirma desde
     * el mensaje, sin buscar su contraseña. El consultorio, a la mañana,
     * sabe a quién llamar.
     *
     * @param string $token Token del enlace
     * @return array La cita confirmada
     * @throws RuntimeException Si el enlace no sirve o la cita ya cambió de estado
     */
    public function confirmarPorToken(string $token): array
    {
        // Se resuelve el token (valida formato, existencia y vigencia)
        $datos = $this->resolverPorToken($token);
        $cita = $datos['cita'];

        // Si la cita ya se atendió, no hay nada que confirmar: el turno ya
        // ocurrió. Sin este control, un enlace viejo de hace una semana
        // podría "revivir" el estado de un turno que el profesional ya
        // marcó como completado.
        if (in_array($cita['estado'], ['completada', 'ausente'], true)) {
            throw new \RuntimeException('Ese turno ya se atendió', 409);
        }

        // Si la cita ya está cancelada, confirmar no tiene sentido.
        // Se responde 409 (conflicto) y no 422: no es un dato inválido,
        // es que el estado del recurso cambió.
        if ($cita['estado'] === 'cancelada') {
            throw new \RuntimeException('Ese turno ya estaba cancelado', 409);
        }

        // Si ya está confirmada, se devuelve tal cual.
        // Es idempotente a propósito: si el paciente abre dos veces el mismo
        // enlace, la segunda no debe generar un error ni un segundo aviso.
        if ($cita['estado'] === 'confirmada') {
            return $cita;
        }

        // Se confirma la cita.
        // OJO: esta escritura va DIRECTO sobre la tabla citas, que es la fuente
        // de verdad. Las notificaciones solo registran que se mandó el aviso.
        $this->citaRepo->cambiarEstado((int)$cita['id'], 'confirmada');

        // Se devuelve la misma vista filtrada que devuelve resolverPorToken, y
        // no la cita completa: este endpoint es público y no debe filtrar
        // el motivo de consulta ni el DNI del paciente
        return $this->resolverPorToken($token)['cita'];
    }

    /**
     * Cancela una cita usando el token del recordatorio.
     * Igual que confirmar, el punto fuerte del enlace es que el paciente
     * puede liberar el horario desde el mensaje: un turno cancelado con
     // tiempo es un turno que otro paciente puede usar.
     * @param string $token Token del enlace
     * @return array La cita cancelada
     */
    public function cancelarPorToken(string $token): array
    {
        // Se resuelve el token
        $datos = $this->resolverPorToken($token);
        $cita = $datos['cita'];

        // Si ya está cancelada, se devuelve tal cual (idempotente)
        if ($cita['estado'] === 'cancelada') {
            return $cita;
        }

        // Si la cita ya se atendió, no se puede cancelar: el turno ocurrió
        if (in_array($cita['estado'], ['completada', 'ausente'], true)) {
            throw new \RuntimeException('Ese turno ya se atendió y no se puede cancelar', 409);
        }

        // Se cancela liberando el horario
        $this->citaRepo->cambiarEstado((int)$cita['id'], 'cancelada');

        // Se devuelve la vista filtrada, por el mismo motivo que en confirmar
        return $this->resolverPorToken($token)['cita'];
    }

    // ============================================================
    // LISTADO Y REINTENTOS
    // ============================================================

    /**
     * Obtiene una página de notificaciones
     * @param int $pagina Número de página
     * @param int $porPagina Cantidad por página
     * @param array $filtros Ver NotificacionRepositoryInterface::obtenerPaginadas()
     * @return array Estructura paginada
     */
    public function obtenerPaginadas(int $pagina = 1, int $porPagina = 10, array $filtros = [], array $contexto = []): array
    {
        $pagina = max(1, (int)$pagina);
        $porPagina = max(1, min(100, (int)$porPagina));

        // El estado y el tipo se validan contra listas blancas
        $estadosValidos = ['pendiente', 'enviado', 'fallido'];
        if (!empty($filtros['estado']) && !in_array($filtros['estado'], $estadosValidos, true)) {
            throw new \InvalidArgumentException('El estado de la notificación no es válido', 422);
        }
        $tiposValidos = ['recordatorio', 'confirmacion', 'cancelacion'];
        if (!empty($filtros['tipo']) && !in_array($filtros['tipo'], $tiposValidos, true)) {
            throw new \InvalidArgumentException('El tipo de notificación no es válido', 422);
        }

        // --------------------------------------------------
        // Acotado por rol
        // --------------------------------------------------
        // El alcance se decide acá y no en el controlador a propósito: si el
        // id de usuario fuera un parámetro de la URL, un paciente podría
        // cambiarlo por otro y leer los avisos de otra persona. Como sale
        // del token firmado, no se puede falsear.
        if ((string)($contexto['rol'] ?? '') !== 'admin') {
            // Cualquier otro rol solo ve lo suyo
            $filtros['id_usuario'] = (int)($contexto['id_usuario'] ?? 0);

            // Sin id válido no hay nada que mostrar: se devuelve vacío en vez
            // de devolver todos los avisos por un filtro mal armado
            if ($filtros['id_usuario'] <= 0) {
                return [
                    'items' => [],
                    'total' => 0,
                    'pagina' => 1,
                    'por_pagina' => $porPagina,
                    'total_paginas' => 0,
                ];
            }
        }
        // El admin audita: puede ver todos los avisos, incluso los que no
        // tienen usuario asignado (los de pacientes sin cuenta)

        $total = $this->repo->contar($filtros);
        $totalPaginas = (int)ceil($total / $porPagina);
        if ($pagina > $totalPaginas && $totalPaginas > 0) {
            $pagina = $totalPaginas;
        }
        $offset = ($pagina - 1) * $porPagina;

        return [
            'items' => $this->repo->obtenerPaginadas($offset, $porPagina, $filtros),
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas,
        ];
    }

    /**
     * Reintenta el envío de una notificación fallida.
     *
     * @param int $id ID de la notificación
     * @param array $contexto Contexto de quien reintenta. Sin este control,
     *                       cualquier usuario autenticado podía reintentar
     *                       los avisos de otro poniendo su id en la URL: el
     *                       endpoint solo exigía "estar logueado", no "ser el
     *                       dueño". Reintentar dispara un envío real, así que
     *                       el alcance importa tanto como el permiso.
     * @return array ['ok' => bool, 'detalle' => string]
     */
    public function reintentar(int $id, array $contexto = []): array
    {
        // Se carga la notificación (lanza 404 si no existe)
        $notificacion = $this->repo->obtenerPorId($id);
        if (!$notificacion) {
            throw new \RuntimeException('La notificación no existe', 404);
        }

        // --------------------------------------------------
        // AUTORIZACIÓN: el aviso tiene que ser de quien reintenta
        // --------------------------------------------------
        // Se responde 404 y no 403 a propósito, igual que en el detalle de
        // citas: un 403 confirmaría que ese id existe, que ya es información
        // sobre los turnos de otra persona.
        $this->verificarPertenencia($notificacion, $contexto);

        // Solo tiene sentido reintentar algo que falló o quedó pendiente
        if ($notificacion['estado'] === 'enviado') {
            throw new \InvalidArgumentException('Esa notificación ya fue enviada', 422);
        }

        // Se cuenta un intento más
        $this->repo->registrarIntento($id);

        // Se recupera la cita para armar el mensaje
        $cita = $this->citaRepo->obtenerPorId((int)$notificacion['id_cita']);
        if (!$cita) {
            $this->repo->marcarFallida($id, 'La cita asociada ya no existe');
            return ['ok' => false, 'detalle' => 'La cita asociada ya no existe'];
        }

        // Se reintenta el envío
        try {
            $resultado = $this->proveedor->enviar($this->armarMensaje($cita, $notificacion));
            if (!empty($resultado['ok'])) {
                $this->repo->marcarEnviada($id);
                $this->citaRepo->marcarRecordatorioEnviado((int)$cita['id']);
                return ['ok' => true, 'detalle' => (string)($resultado['detalle'] ?? 'Enviado')];
            }
            $this->repo->marcarFallida($id, (string)($resultado['detalle'] ?? 'Error desconocido'));
            return ['ok' => false, 'detalle' => (string)($resultado['detalle'] ?? 'Error desconocido')];
        } catch (\Exception $e) {
            $this->repo->marcarFallida($id, 'Excepción al enviar: ' . $e->getMessage());
            return ['ok' => false, 'detalle' => $e->getMessage()];
        }
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Verifica que una notificación pertenezca al usuario del contexto.
     *
     * Se usa en los endpoints que actúan sobre un aviso puntual (reintentar).
     * La regla es la misma del listado: el admin audita todo, y cualquier
     * otro rol solo toca los avisos que llevan su id_usuario.
     *
     * Para un médico, el aviso se considera suyo cuando la cita que lo
     * originó está en su agenda, no solo cuando tiene id_usuario propio: los
     * recordatorios se generan desde el turno, y el profesional tiene que
     * poder reenviar el de un paciente sin cuenta.
     *
     * @param array $notificacion Fila de la notificación
     * @param array $contexto Contexto del usuario
     * @throws RuntimeException Con código 404 si el aviso es de otro (403
     *                          confirmaría que el id existe, y eso ya es un
     *                          dato sobre los turnos de otra persona)
     */
    private function verificarPertenencia(array $notificacion, array $contexto): void
    {
        $rol = (string)($contexto['rol'] ?? '');

        // El administrador audita cualquier aviso
        if ($rol === 'admin') {
            return;
        }

        $idUsuarioContexto = (int)($contexto['id_usuario'] ?? 0);

        // Camino corto: el aviso tiene su id_usuario y coincide con el token
        if (!empty($notificacion['id_usuario'])
            && (int)$notificacion['id_usuario'] === $idUsuarioContexto
            && $idUsuarioContexto > 0) {
            return;
        }

        // Un paciente solo puede tocar avisos que lo tengan asignado: no hay
        // ningún otro vínculo posible para él
        if ($rol === 'paciente') {
            throw new \RuntimeException('La notificación no existe', 404);
        }

        // Un profesional se autoriza por la agenda: se busca la cita del aviso
        if ($rol === 'medico') {
            $idMedico = (int)($contexto['id_medico'] ?? 0);
            if ($idMedico > 0) {
                $cita = $this->citaRepo->obtenerPorId((int)$notificacion['id_cita']);
                if ($cita && (int)$cita['id_medico'] === $idMedico) {
                    return;
                }
            }
        }

        throw new \RuntimeException('La notificación no existe', 404);
    }

    /**
     * Arma el mensaje del recordatorio con los datos de la cita.
     *
     * El texto está escrito para leerse en un celular, en una frase, sin
     * contexto: quien lo recibe puede no recordar para qué reservó, ni
     * tener a mano el paper. Por eso el mensaje dice explícitamente
     * QUIÉN, QUÉ, CUÁNDO y ofrece los dos botones que importan.
     *
     * @param array $cita Datos de la cita
     * @param array $notificacion Datos de la notificación (trae el token)
     * @return array Mensaje listo para el proveedor
     */
    private function armarMensaje(array $cita, array $notificacion): array
    {
        // La fecha se arma en un formato que una persona entienda de una vez.
        // date('d/m/Y') es el formato que se usa en Argentina, no el ISO.
        $fechaLegible = date('d/m/Y', strtotime($cita['fecha']));
        // La hora se recorta a HH:MM (la base guarda HH:MM:SS)
        $horaLegible = substr($cita['hora'], 0, 5);

        // Cuerpo del mensaje en texto plano.
        // Se mantiene corto: los recordatorios largos no se leen.
        $cuerpo = "Hola " . $cita['nombre_paciente'] . ", tenés un turno reservado.\n\n"
            . "Profesional: " . $cita['nombre_medico'] . "\n"
            . "Fecha: " . $fechaLegible . "\n"
            . "Hora: " . $horaLegible . "\n"
            . ($cita['especialidad'] ? "Especialidad: " . $cita['especialidad'] . "\n" : "")
            . "\nConfirmá tu asistencia o liberá el horario desde el enlace que recibiste.\n"
            . "Si no podés asistir, avisanos con tiempo: ese horario lo puede usar otro paciente.";

        return [
            'destino' => $notificacion['destino'] ?? null,
            'canal' => $notificacion['canal'] ?? 'sistema',
            'asunto' => 'Recordatorio de turno - SaludWEB',
            'cuerpo' => $cuerpo,
            'datos_extra' => [
                'id_cita' => (int)$cita['id'],
                'token' => $notificacion['token_cancelacion'] ?? null,
                'fecha' => $cita['fecha'],
                'hora' => substr($cita['hora'], 0, 5),
            ],
        ];
    }

    /**
     * Genera un token aleatorio y criptográficamente seguro.
     *
     * random_bytes() y no rand()/uniqid(): el token da acceso para confirmar
     * o cancelar un turno, así que tiene que ser impredecible.
     * uniqid() se basa en la hora del servidor y sería adivinable.
     *
     * @return string Token de 64 caracteres hexadecimales
     */
    private function generarToken(): string
    {
        // 32 bytes de entropía = 64 caracteres en hexadecimal.
        // La columna es VARCHAR(64) y tiene índice único, así que dos
        // colisiones son prácticamente imposibles.
        return bin2hex(random_bytes(32));
    }
}
