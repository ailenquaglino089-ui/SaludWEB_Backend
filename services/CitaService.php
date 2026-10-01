<?php
// ============================================================
// services/CitaService.php - Capa de negocio (Turnera / Citas)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Este servicio es el corazón de la turnera. Concentra las tres reglas
// que hacen que la autogestión funcione de verdad:
//
//   1. Un turno solo se ofrece si el profesional declaró que atiende.
//      (bloques en la tabla disponibilidades)
//
//   2. Un turno no se puede superponer con otro.
//      Se valida en la aplicación para dar un mensaje entendible, y se
//      garantiza en la base con un índice UNIQUE, que es lo único que
//      realmente sirve contra dos pacientes reservando en el mismo
//      milisegundo.
//
//   3. Un paciente no puede tener dos citas vivas al mismo tiempo.
//      Si se turna solo, tiene que esperar a que termine el primer turno.
class CitaService
{
    // Repositorio de citas (persistencia)
    private CitaRepository $repo;

    // Repositorio de la agenda horaria del profesional (persistencia)
    private DisponibilidadRepository $disponibilidadRepo;

    // Repositorio de médicos (para verificar que el profesional existe y está activo)
    private MedicoRepository $medicoRepo;

    // Repositorio de pacientes (para verificar que el paciente existe y está activo)
    private PacienteRepository $pacienteRepo;

    // Servicio de tiempo real, para publicar un aviso cuando cambia un turno.
    //
    // Es NULLABLE a propósito, y esa es la decisión de diseño importante:
    // el módulo de tiempo real es un extra sobre la turnera, no un requisito
    // de la turnera. Con el tipo declarado sin "?" y la propiedad sin valor
    // inicial, PHP exigiría que TODAS las instalaciones loantianaran.
    // Declarándolo opcional:
    //   • se puede probar CitaService con dobles de repositorio sin montar
    //     el canal en vivo,
    //   • una instalación donde la tabla eventos_realtime no llegara a
    //     crearse no rompe las reservas, solo pierde la actualización
    //     instantánea,
    //   • y la comprobación del "if" al publicar deja obvio que es un
    //     extra, no el camino principal.
    private ?RealtimeService $realtime = null;

    // Días de anticipación máxima que se aceptan para reservar.
    // Sin un tope, un paciente podría agendar turnos a un año vista y el
    // profesional quedaría sin visibilidad de su agenda real.
    private const DIAS_MAXIMOS_ANTICIPACION = 60;

    /**
     * Constructor con inyección de dependencias
     * @param CitaRepository $repo
     * @param DisponibilidadRepository $disponibilidadRepo
    * @param MedicoRepository $medicoRepo
     * @param PacienteRepository $pacienteRepo
    * @param RealtimeService|null $realtime Publicador de avisos en vivo (opcional)
     */
    public function __construct(
        CitaRepository $repo,
        DisponibilidadRepository $disponibilidadRepo,
        MedicoRepository $medicoRepo,
        PacienteRepository $pacienteRepo,
        ?RealtimeService $realtime = null
    ) {
        // Inyecta y guarda los repositorios para usarlos en toda la clase
        $this->repo = $repo;
        $this->disponibilidadRepo = $disponibilidadRepo;
        $this->medicoRepo = $medicoRepo;
        $this->pacienteRepo = $pacienteRepo;
        // El quinto parámetro es opcional: el valor por defecto null deja
        // funcionando el servicio igual que antes, sin tocar las pruebas
        // existentes ni ninguna otra forma de construirlo.
        $this->realtime = $realtime;
    }

    // ============================================================
    // DISPONIBILIDAD: los horarios que se ofrecen al paciente
    // ============================================================

    /**
     * Calcula los horarios disponibles de un profesional para una fecha puntual.
     *
     * Es el endpoint que el paciente consulta para elegir día y hora sin
     * necesidad de llamar por teléfono, y el que la web refresca por
     * polling para que los horarios que ve otro paciente no le aparezcan
     * después como ocupados.
     *
     * Cómo funciona, en tres pasos:
     *   1. Se busca el día de la semana de la fecha pedida.
     *   2. Se leen los bloques de atención que el profesional declaró para ese día.
     *   3. Se "expanden" los bloques en turnos concretos (08:00, 08:30, ...)
     *      y a cada uno se le pega el estado: libre u ocupado.
     *
     * @param int $idMedico ID del profesional
     * @param string $fecha Fecha en formato YYYY-MM-DD
     * @return array Estructura con los bloques, los slots y el resumen
     * @throws InvalidArgumentException Si la fecha o el médico no son válidos
     */
    public function obtenerDisponibilidad(int $idMedico, string $fecha): array
    {
        // Valida el formato de la fecha antes de hacer cualquier cálculo
        $this->validarFecha($fecha);

        // Verifica que el médico exista y esté activo.
        // Se usa el mismo mensaje para "no existe" y "está inactivo" a propósito:
        // no conviene confirmarle a un usuario que un id existe pero está dado de baja.
        $medico = $this->medicoRepo->obtenerPorId($idMedico);
        if (!$medico || empty($medico['activo'])) {
            throw new \InvalidArgumentException('El profesional no está disponible', 422);
        }

        // date('N', ...) devuelve el día de la semana en formato ISO: 1 = lunes ... 7 = domingo.
        // Es la misma convención que usa la tabla disponibilidades, por eso no hay conversión.
        $diaSemana = (int)date('N', strtotime($fecha));

        // Se leen los bloques de atención activos del médico para ese día de la semana
        $bloques = $this->disponibilidadRepo->obtenerPorMedicoYDia($idMedico, $diaSemana);

        // Se traen las citas ya reservadas de ese médico en esa fecha,
        // que son las que van a tapar los slots
        $citas = $this->repo->obtenerPorMedicoYFecha($idMedico, $fecha);

        // Se arma un mapa "hora de la cita => estado" para consultar en O(1) cada slot.
        // indexar por hora evita el O(n²) que sería buscar linealmente en cada turno.
        $ocupadas = [];
        foreach ($citas as $cita) {
            // slot_reservado con valor NO NULL significa que la cita sigue
            // ocupando el horario. Las canceladas y los ausentes ya lo liberaron.
            if (!empty($cita['slot_reservado'])) {
                // Se normaliza la hora a HH:MM para que coincida exactamente
                // con las horas de los slots, que también son HH:MM
                $ocupadas[$this->normalizarHora($cita['hora'])] = $cita;
            }
        }

        // Instante actual en segundos, para marcar como vencidos los turnos que ya pasaron
        $ahora = time();
        // Se convierte la fecha pedida a un timestamp a medianoche
        $timestampFecha = strtotime($fecha . ' 00:00:00');

        // Arreglo donde se acumulan los turnos concretos del día
        $slots = [];

        // Recorre cada bloque de atención declarado para ese día
        foreach ($bloques as $bloque) {
            // Duración de cada turno dentro del bloque (30 por defecto)
            $duracion = (int)$bloque['duracion_minutos'];
            // Si la duración fuera cero o negativa el bucle no terminaría nunca:
            // se acota a un mínimo de 5 minutos
            $duracion = max(5, $duracion);

            // CONVERSIÓN CRÍTICA DE UNIDADES: aSegundos() y deSegundos()
            // trabajan en SEGUNDOS, pero la duración viene en MINUTOS.
            // Sin multiplicar por 60 acá, el paso del bucle sería de 30
            // segundos en vez de 30 minutos, y se generarían 240 turnos
            // ficticios de un minuto entre las 08:00 y las 12:00.
            // Todo el cálculo de solapamiento depende de esta línea.
            $duracionSegundos = $duracion * 60;

            // Inicio y fin del bloque convertidos a segundos desde medianoche
            $inicio = $this->aSegundos($bloque['hora_inicio']);
            $fin = $this->aSegundos($bloque['hora_fin']);

            // El bucle genera un turno tras otro mientras el turno COMPLETO
            // (inicio + duración) quepa dentro del bloque. Ese +duración en la
            // condición es lo que impide ofrecer un horario que se cortaría
            // a la mitad.
            for ($cursor = $inicio; $cursor + $duracionSegundos <= $fin; $cursor += $duracionSegundos) {
                // Se convierte el cursor de vuelta a "HH:MM"
                $hora = $this->deSegundos($cursor);
                // Se normaliza a HH:MM:SS que es lo que espera la columna TIME de MySQL
                $horaSql = $hora . ':00';

                // Motivo por el que el turno no estaría disponible (null = disponible)
                $motivoBloqueo = null;

                // ¿Ya hay una cita viva en este horario?
                if (isset($ocupadas[$hora])) {
                    $motivoBloqueo = 'ocupado';
                } elseif ($cursor < $inicio) { // Sanidad: nunca debería cumplirse
                    $motivoBloqueo = 'fuera_de_rango';
                } else {
                    // ¿El turno ya pasó? Solo se descarta si la fecha pedida es
                    // la fecha de hoy: los días futuros nunca tienen turnos pasados.
                    if ($this->yaPaso($timestampFecha, $cursor, $ahora)) {
                        $motivoBloqueo = 'pasado';
                    }
                }

                // Se agrega el turno al listado, con su estado
                $slots[] = [
                    // Hora de inicio del turno, en HH:MM
                    'hora' => $hora,
                    // Hora en que termina el turno, en HH:MM
                    'hora_fin' => $this->deSegundos($cursor + $duracionSegundos),
                    // Si se puede reservar o no
                    'disponible' => $motivoBloqueo === null,
                    // Por qué no se puede (null, 'ocupado' o 'pasado').
                    // Se devuelve la causa y no un simple false para que el
                    // frontend pueda explicar en pantalla qué pasó.
                    'motivo_bloqueo' => $motivoBloqueo,
                    // Si el turno está tomado, se devuelve el id de la cita.
                    // NO se devuelve nombre ni dato clínico del paciente:
                    // la agenda pública no puede filtrar quién atiende a quién.
                    'id_cita' => isset($ocupadas[$hora]) ? (int)$ocupadas[$hora]['id'] : null,
                ];
            }
        }

        // Cuenta cuántos turnos quedan libres, que es el dato que más le importa al paciente
        $disponibles = 0;
        foreach ($slots as $slot) {
            if ($slot['disponible']) {
                $disponibles++;
            }
        }

        return [
            'id_medico' => $idMedico,
            'nombre_medico' => $medico['nombre'],
            'especialidad' => $medico['especialidad'] ?? null,
            'matricula' => $medico['matricula'] ?? null,
            'fecha' => $fecha,
            // 1 = lunes ... 7 = domingo
            'dia_semana' => $diaSemana,
            // Los bloques de atención crudos, por si la interfaz quiere mostrar
            // "Atiende de 08:00 a 12:00" en lugar de la lista de turnos
            'bloques' => array_map(function ($b) {
                return [
                    'hora_inicio' => $this->normalizarHora($b['hora_inicio']),
                    'hora_fin' => $this->normalizarHora($b['hora_fin']),
                    'duracion_minutos' => (int)$b['duracion_minutos'],
                ];
            }, $bloques),
            'slots' => $slots,
            'total_slots' => count($slots),
            'total_disponibles' => $disponibles,
            // Si el médico no declaró atención ese día, se avisa explícitamente
            // en vez de devolver una lista vacía que el usuario no sabría interpretar
            'atiende_ese_dia' => $bloques !== [],
        ];
    }

    /**
     * Devuelve la agenda de un profesional en un rango de fechas.
     * Es la "agenda unificada": todo lo que atiende, agrupado por día.
     * @param int $idMedico ID del profesional
     * @param string $desde Fecha inicial (YYYY-MM-DD)
     * @param string $hasta Fecha final (YYYY-MM-DD)
     * @return array Lista de días, cada uno con su lista de citas
     * @throws InvalidArgumentException Si las fechas no son válidas
     */
    public function obtenerAgendaMedico(int $idMedico, string $desde, string $hasta): array
    {
        // Valida el formato de ambas fechas
        $this->validarFecha($desde);
        $this->validarFecha($hasta);

        // Verifica que el médico exista y esté activo
        $medico = $this->medicoRepo->obtenerPorId($idMedico);
        if (!$medico || empty($medico['activo'])) {
            throw new \InvalidArgumentException('El profesional no está disponible', 422);
        }

        // Convierte las fechas a timestamp y las ordena (el usuario puede
        //mandarlas al revés; acá se normaliza para no devolver una agenda vacía)
        $desdeTs = strtotime($desde . ' 00:00:00');
        $hastaTs = strtotime($hasta . ' 00:00:00');
        if ($desdeTs > $hastaTs) {
            // Intercambia las variables usando la sintaxis de asignación múltiple de PHP
            list($desdeTs, $hastaTs) = [$hastaTs, $desdeTs];
            list($desde, $hasta) = [$hasta, $desde];
        }

        // Se genera la lista de todos los días del rango, incluso los que no
        // tienen citas. Sin esto, los días sin turnos desaparecerían de la agenda
        // y el profesional no vería un día libre, que es información valiosa.
        $dias = [];
        for ($ts = $desdeTs; $ts <= $hastaTs; $ts += 86400) {
            $dias[date('Y-m-d', $ts)] = [];
        }

        // Se traen todas las citas del rango de una sola consulta
        $pagina = $this->repo->obtenerPaginadas(0, 500, [
            'id_medico' => $idMedico,
            'desde' => $desde,
            'hasta' => $hasta,
        ]);

        // Se reparte cada cita en el día que le corresponde
        foreach ($pagina as $cita) {
            $fecha = $cita['fecha'];
            if (!isset($dias[$fecha])) {
                $dias[$fecha] = [];
            }
            $dias[$fecha][] = $cita;
        }

        // Se arma la respuesta final: un objeto por día, con sus citas ordenadas por hora
        $agenda = [];
        foreach ($dias as $fecha => $citas) {
            // ksort ordena por clave (la hora) sin reindexar la clave fecha
            ksort($dias[$fecha]);
            $agenda[] = [
                'fecha' => $fecha,
                // date('w'): 0 = domingo ... 6 = sábado, el formato que entiende el frontend
                'dia_semana' => (int)date('w', strtotime($fecha)),
                'total_citas' => count($citas),
                'citas' => $dias[$fecha],
            ];
        }

        return [
            'id_medico' => $idMedico,
            'nombre_medico' => $medico['nombre'],
            'especialidad' => $medico['especialidad'] ?? null,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias' => $agenda,
            'total_citas' => $this->repo->contar([
                'id_medico' => $idMedico,
                'desde' => $desde,
                'hasta' => $hasta,
            ]),
        ];
    }

    // ============================================================
    // LISTADO Y DETALLE
    // ============================================================

    /**
     * Obtiene una página de citas con los filtros validados.
     * @param int $pagina Número de página (empieza en 1)
     * @param int $porPagina Cantidad de citas por página
     * @param array $filtros Ver CitaRepositoryInterface::obtenerPaginadas()
     * @return array Estructura paginada: { items, total, pagina, por_pagina, total_paginas }
     */
    public function obtenerPaginadas(int $pagina = 1, int $porPagina = 10, array $filtros = []): array
    {
        // Normaliza y acota los parámetros numéricos para evitar valores inválidos
        $pagina = max(1, (int)$pagina);
        $porPagina = max(1, min(100, (int)$porPagina));

        // --------------------------------------------------
        // RED DE SEGURIDAD DEL FILTRO POR PACIENTE
        // --------------------------------------------------
        // El repositorio filtra por id_paciente cuando la clave viene en los
        // filtros. Si un paciente sin ficha vinculada llega con id_paciente
        // ausente o en 0, el filtro no se aplica y la consulta devuelve
        // TODAS las citas de la base, incluyendo las de otros pacientes.
        //
        // Por eso acá se garantiza que, si el filtro dice id_paciente = 0,
        // el resultado sea una lista vacía y no un listado sin restringir.
        // Es la diferencia entre "no veo nada" y "veo la agenda completa".
        if (array_key_exists('id_paciente', $filtros) && (int)$filtros['id_paciente'] <= 0) {
            return [
                'items' => [],
                'total' => 0,
                'pagina' => 1,
                'por_pagina' => $porPagina,
                'total_paginas' => 0,
            ];
        }

        // Sanitiza los filtros de texto: quitar etiquetas HTML y espacios extremos
        if (!empty($filtros['busqueda'])) {
            // strip_tags() elimina <script> y cualquier otra etiqueta (anti-XSS)
            $filtros['busqueda'] = strip_tags(trim((string)$filtros['busqueda']));
            // Se limita la longitud para no abuse de LIKE con textos enormes
            $filtros['busqueda'] = mb_substr($filtros['busqueda'], 0, 100);
        }

        // Valida el estado contra la lista blanca de estados reales.
        // Sin esta validación, un estado inventado devolvería una lista vacía
        // sin avisar al usuario de que el filtro está mal escrito.
        if (!empty($filtros['estado'])) {
            $filtros['estado'] = strtolower(trim((string)$filtros['estado']));
            if (!in_array($filtros['estado'], $this->estadosValidos(), true)) {
                throw new \InvalidArgumentException('El estado de la cita no es válido', 422);
            }
        }

        // Valida los filtros de fecha si vienen
        if (!empty($filtros['fecha'])) {
            $this->validarFecha($filtros['fecha']);
        }
        if (!empty($filtros['desde'])) {
            $this->validarFecha($filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $this->validarFecha($filtros['hasta']);
        }

        // Total de registros que coinciden, necesario para calcular las páginas
        $total = $this->repo->contar($filtros);
        // ceil() redondea hacia arriba: 57 registros con 10 por página = 6 páginas
        $totalPaginas = (int)ceil($total / $porPagina);

        // Si se pidió una página más allá del final, se devuelve la última
        if ($pagina > $totalPaginas && $totalPaginas > 0) {
            $pagina = $totalPaginas;
        }

        // OFFSET = cuántas filas saltar: página 1 -> 0, página 2 -> porPagina
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
     * Obtiene una cita por su ID
     * @param int $id ID de la cita
     * @return array Datos de la cita
     * @throws RuntimeException Si no existe (404)
     */
    public function obtenerPorId(int $id): array
    {
        $cita = $this->repo->obtenerPorId($id);
        if (!$cita) {
            throw new \RuntimeException('Cita no encontrada', 404);
        }
        return $cita;
    }

    // ============================================================
    // ALTA DE CITAS
    // ============================================================

    /**
     * Crea una nueva cita (reserva de turno).
     *
     * @param array $data Datos enviados por el cliente
     * @param array $contexto ['rol' => ..., 'id_paciente' => ..., 'id_medico' => ...]
     *        El contexto del usuario autenticado, para decidir a nombre de quién se reserva
     * @return array La cita creada
     * @throws InvalidArgumentException Si los datos no son válidos o el horario no existe
     * @throws RuntimeException Si el turno ya fue tomado
     */
    public function crear(array $data, array $contexto = []): array
    {
        // --------------------------------------------------
        // PASO 0: Quién puede reservar y para quién
        // --------------------------------------------------
        // Un paciente solo puede pedir turno para sí mismo. Si se aceptara el
        // id_paciente del body sin más, cualquiera podría reservar en nombre
        // de otro (con lo que además se filtraría la disponibilidad ajena).
        // El id se impone desde el token y el del body se descarta.
        $rol = (string)($contexto['rol'] ?? '');
        if ($rol === 'paciente') {
            $idContexto = (int)($contexto['id_paciente'] ?? 0);
            if ($idContexto <= 0) {
                // La cuenta existe pero no está vinculada a una ficha de
                // paciente: sin esa vinculación no se puede agendar
                throw new \RuntimeException(
                    'Tu usuario no está vinculado a una ficha de paciente. Contactá al consultorio.',
                    403
                );
            }
            // Se sobrescribe el id del body con el del token, sin excepción
            $data['id_paciente'] = $idContexto;
        }

        // El origen de la reserva se deduce del rol, no del body: es lo que
        // permite medir después cuánta gente usa la autogestión online.
        $data['creado_por_paciente'] = ($rol === 'paciente') ? 1 : 0;

        // --------------------------------------------------
        // PASO 1: Validaciones de formato
        // --------------------------------------------------

        // El paciente es obligatorio: una cita sin paciente no tiene sentido
        if (empty($data['id_paciente'])) {
            throw new \InvalidArgumentException('Debe indicar el paciente', 422);
        }
        // El profesional es obligatorio
        if (empty($data['id_medico'])) {
            throw new \InvalidArgumentException('Debe indicar el profesional', 422);
        }
        // La fecha es obligatoria
        if (empty($data['fecha'])) {
            throw new \InvalidArgumentException('Debe indicar la fecha del turno', 422);
        }
        // La hora es obligatoria
        if (empty($data['hora'])) {
            throw new \InvalidArgumentException('Debe indicar la hora del turno', 422);
        }

        // Normaliza los identificadores a entero
        $idPaciente = (int)$data['id_paciente'];
        $idMedico = (int)$data['id_medico'];

        // Valida el formato de la fecha (YYYY-MM-DD) y que sea una fecha real
        $this->validarFecha($data['fecha']);
        $fecha = $data['fecha'];

        // Normaliza la hora a HH:MM:SS y valida que sea una hora real
        $hora = $this->validarHora($data['hora']);

        // --------------------------------------------------
        // PASO 2: Reglas de negocio sobre el pasado y el futuro
        // --------------------------------------------------

        // No se puede reservar en el pasado.
        // Se compara a medianoche porque un turno de las 09:00 sigue siendo
        // "hoy" hasta medianoche, no se trata de bloquear todo el día.
        $hoy = date('Y-m-d');
        if ($fecha < $hoy) {
            throw new \InvalidArgumentException('No se pueden reservar turnos en fechas pasadas', 422);
        }

        // No se puede reservar con demasiada anticipación.
        // Sin este tope el profesional no puede dimensionar su agenda real,
        // que es uno de los beneficios que se buscan con la turnera.
        $limite = date('Y-m-d', strtotime('+' . self::DIAS_MAXIMOS_ANTICIPACION . ' days'));
        if ($fecha > $limite) {
            throw new \InvalidArgumentException(
                'Solo se pueden reservar turnos con hasta ' . self::DIAS_MAXIMOS_ANTICIPACION . ' días de anticipación',
                422
            );
        }

        // --------------------------------------------------
        // PASO 3: El paciente y el profesional deben existir y estar activos
        // --------------------------------------------------

        $paciente = $this->pacienteRepo->obtenerPorId($idPaciente);
        if (!$paciente) {
            throw new \InvalidArgumentException('El paciente no existe', 422);
        }
        if (empty($paciente['activo'])) {
            throw new \InvalidArgumentException('El paciente está dado de baja', 422);
        }

        $medico = $this->medicoRepo->obtenerPorId($idMedico);
        if (!$medico) {
            throw new \InvalidArgumentException('El profesional no existe', 422);
        }
        if (empty($medico['activo'])) {
            throw new \InvalidArgumentException('El profesional está dado de baja', 422);
        }

        // --------------------------------------------------
        // PASO 4: El turno tiene que caer dentro de la agenda declarada
        // --------------------------------------------------

        // Se busca si el profesional atiende ese día de la semana
        $diaSemana = (int)date('N', strtotime($fecha));
        $bloques = $this->disponibilidadRepo->obtenerPorMedicoYDia($idMedico, $diaSemana);

        // Si no declaró atención ese día, no hay nada que reservar.
        // El mensaje es explícito porque es el error más común de uso:
        // el paciente eligió un día en el que el profesional no atiende.
        if ($bloques === []) {
            throw new \InvalidArgumentException('El profesional no atiende en esa fecha', 422);
        }

        // Se busca el bloque que contiene la hora pedida
        $bloqueSeleccionado = $this->buscarBloqueQueContiene($bloques, $hora);
        if ($bloqueSeleccionado === null) {
            throw new \InvalidArgumentException(
                'Ese horario está fuera del horario de atención del profesional',
                422
            );
        }

        // El turno hereda la duración del bloque. No se acepta una duración
        // arbitraria del cliente: si el profesional atiende en turnos de 30
        // minutos, no se puede meter uno de 15 ni uno de 120.
        $duracion = (int)$bloqueSeleccionado['duracion_minutos'];

        // --------------------------------------------------
        // PASO 5: El horario no puede estar tomado
        // --------------------------------------------------

        // Se traen las citas de ese médico en esa fecha
        $citasDelDia = $this->repo->obtenerPorMedicoYFecha($idMedico, $fecha);
        $horaNormalizada = $this->normalizarHora($hora);

        foreach ($citasDelDia as $citaExistente) {
            // Solo miran las citas que siguen ocupando el horario
            if (empty($citaExistente['slot_reservado'])) {
                continue;
            }
            $horaExistente = $this->normalizarHora($citaExistente['hora']);
            $inicioExistente = $this->aSegundos($horaExistente);
            $inicioNuevo = $this->aSegundos($horaNormalizada);
            $finExistente = $inicioExistente + (int)$citaExistente['duracion_minutos'];
            $finNuevo = $inicioNuevo + $duracion;

            // Solapamiento real: los intervalos [inicio, fin) se cruzan.
            // La comparación estricta del fin evita marcar como solapado un
            // turno que termina exactamente cuando empieza el siguiente,
            // que es el caso normal de una agenda de turnos seguidos.
            if ($inicioNuevo < $finExistente && $inicioExistente < $finNuevo) {
                throw new \RuntimeException(
                    'Ese horario acaba de ser reservado por otro paciente',
                    409
                );
            }
        }

        // --------------------------------------------------
        // PASO 6: El paciente no puede tener dos turnos al mismo tiempo
        // --------------------------------------------------

        // Se buscan las citas del paciente en esa fecha
        $citasPaciente = $this->repo->obtenerPorPacienteYRango($idPaciente, $fecha, $fecha);
        foreach ($citasPaciente as $citaPaciente) {
            // Solo cuentan las citas vivas (pendiente o confirmada)
            if (!in_array($citaPaciente['estado'], ['pendiente', 'confirmada'], true)) {
                continue;
            }
            $inicioOtro = $this->aSegundos($this->normalizarHora($citaPaciente['hora']));
            $finOtro = $inicioOtro + (int)$citaPaciente['duracion_minutos'];
            $inicioNuevo = $this->aSegundos($horaNormalizada);

            if ($inicioNuevo < $finOtro && $inicioOtro < $inicioNuevo + $duracion) {
                throw new \InvalidArgumentException(
                    'El paciente ya tiene otro turno en ese horario',
                    422
                );
            }
        }

        // --------------------------------------------------
        // PASO 7: Guardado
        // --------------------------------------------------

        // El motivo de la consulta se sanitiza (anti-XSS) y se limita a 255 caracteres
        $motivo = isset($data['motivo'])
            ? mb_substr(strip_tags(trim((string)$data['motivo'])), 0, 255)
            : null;

        // Las notas internas también, con un límite de 500 caracteres
        $notas = isset($data['notas'])
            ? mb_substr(strip_tags(trim((string)$data['notas'])), 0, 500)
            : null;

        // Se guarda un registro de quién pidió la cita. Sirve para medir el uso
        // real de la autogestión online frente a la reserva por teléfono.
        $creadoPorPaciente = ($contexto['rol'] ?? '') === 'paciente' ? 1 : 0;

        try {
            $id = $this->repo->crear([
                'id_paciente' => $idPaciente,
                'id_medico' => $idMedico,
                'fecha' => $fecha,
                'hora' => $hora,
                'duracion_minutos' => $duracion,
                // Toda cita nace 'pendiente'. Confirmar automáticamente
                // haría que el recordatorio dejara de tener sentido, y el
                // recordatorio es justamente el mecanismo anti-ausentismo.
                'estado' => 'pendiente',
                'motivo' => $motivo,
                'notas' => $notas,
                'recordatorio_enviado' => 0,
                'creado_por_paciente' => $creadoPorPaciente,
                'slot_reservado' => 'reservado',
            ]);
        } catch (\PDOException $e) {
            // El índice UNIQUE (id_medico, fecha, hora, slot_reservado) es la
            // garantía real contra superposiciones. Si el motor de la base
            // rechaza el INSERT con código 23000 (violación de clave única),
            // significa que otra petición ganó la carrera por ese horario.
            // Se traduce a un error de negocio legible en vez de un 500.
            if ((string)$e->getCode() === '23000' || $e->getCode() === 23000) {
                throw new \RuntimeException('Ese horario acaba de ser reservado por otro paciente', 409);
            }
            // Cualquier otro error de base de datos se deja subir para que
            // el controlador lo maneje como 500 genérico
            throw $e;
        }

        // --------------------------------------------------
        // PASO 8: Avisar en vivo que hay un turno nuevo
        // --------------------------------------------------
        // Se publica DESPUÉS de guardar, nunca antes: si el INSERT fallara,
        // se estaría anunciando un turno que no existe, y el panel del
        // consultorio mostraría un número más que no corresponde.
        $cita = $this->repo->obtenerPorId($id);

        $this->avisarEnVivo('cita_creada', $cita);

        // Se devuelve la cita recién creada, ya con los nombres resueltos
        return $cita;
    }

    // ============================================================
    // CAMBIO DE ESTADO, CANCELACIÓN Y BORRADO
    // ============================================================

    /**
     * Cambia el estado de una cita.
     *
     * Quién puede pasar a qué estado es una regla de negocio, no una
     * permisos genérica, y por eso vive acá y no en el routing:
     *
     *   El paciente solo puede CONFIRMAR o CANCELAR sus propias citas.
     *   El médico puede marcar COMPLETADA, AUSENTE o CANCELAR.
     *   El administrador puede hacer todo.
     *
     * @param int $id ID de la cita
     * @param string $nuevoEstado Estado destino
     * @param array $contexto Contexto del usuario: rol, id_paciente, id_medico
     * @return array La cita actualizada
     * @throws RuntimeException Si la cita no existe o el cambio no está permitido
     * @throws InvalidArgumentException Si el estado no es válido
     */
    public function cambiarEstado(int $id, string $nuevoEstado, array $contexto = []): array
    {
        // Se carga la cita (lanza 404 si no existe)
        $cita = $this->obtenerPorId($id);

        // El estado se pasa a minúsculas y se recorta por si el cliente mandó
        // "CONFIRMADA" o " Confirmada "
        $nuevoEstado = strtolower(trim($nuevoEstado));

        // Se valida contra la lista blanca de estados reales
        if (!in_array($nuevoEstado, $this->estadosValidos(), true)) {
            throw new \InvalidArgumentException('Estado de cita no válido', 422);
        }

        // Se verifica que el usuario esté autorizado a tocar ESTA cita.
        // La autorización va por dato (esta cita es tuya), no solo por rol.
        $this->verificarAccesoACita($cita, $contexto);

        // Se verifica que la transición de estado tenga sentido y que el rol
        // tenga permiso. Cancelar una cita ya completada sería falsear el
        // histórico, y dejar que un paciente se marque 'completada' también.
        $this->verificarTransicion($cita['estado'], $nuevoEstado, (string)($contexto['rol'] ?? ''));

        // Se delega el cambio; el repositorio actualiza estado y libera
        // o mantiene el horario en una sola operación
        $this->repo->cambiarEstado($id, $nuevoEstado);

        $actualizada = $this->repo->obtenerPorId($id);

        // El tipo de evento distingue cancelar de confirmar, porque para el
        // paciente NO son la misma cosa: una cancelación libera un horario
        // (y le avisa al resto), mientras que una confirmación no cambia la
        // agenda. Mandar un solo tipo obligaría al frontend a adivinar mirando
        // el estado, cuando el backend ya lo sabe con certeza.
        $this->avisarEnVivo(
            $nuevoEstado === 'cancelada' ? 'cita_cancelada' : 'cita_estado',
            $actualizada
        );

        return $actualizada;
    }

    /**
     * Cancela una cita liberando el horario.
     * Es un caso particular de cambiarEstado() con estado 'cancelada',
     * pero tiene su propio endpoint porque es la acción más frecuente de la
     * autogestión y merece un mensaje claro y un atajo.
     * @param int $id ID de la cita
     * @param array $contexto Contexto del usuario
     * @return array La cita cancelada
     */
    public function cancelar(int $id, array $contexto = []): array
    {
        // Se reutiliza cambiarEstado con el estado fijo 'cancelada'
        return $this->cambiarEstado($id, 'cancelada', $contexto);
    }

    /**
     * Elimina una cita (uso administrativo exclusivo).
     *
     * Borrar de verdad es destructivo y por eso queda restringido al admin.
     * En el uso normal lo correcto es cancelar: así queda el registro de
     * que existió el turno, que es justamente el dato que sirve para medir
     * la demanda y el ausentismo.
     *
     * @param int $id ID de la cita
     * @param array $contexto Contexto del usuario
     * @throws RuntimeException Si la cita no existe o el usuario no es admin
     */
    public function eliminar(int $id, array $contexto = []): void
    {
        // Verifica que la cita exista
        $cita = $this->obtenerPorId($id);
        // Solo el administrador puede borrar
        if (($contexto['rol'] ?? '') !== 'admin') {
            throw new \RuntimeException('Solo un administrador puede eliminar citas', 403);
        }
        $this->repo->eliminar($id);

        // El aviso se publica con los datos que la cita tenía ANTES de
        // borrarse. Si se publicara después, ya no habría nada que leer y el
        // evento viajaría sin los identificadores, que son justamente los
        // que el cliente necesita para saber qué tiene que recargar.
        $this->avisarEnVivo('cita_eliminada', $cita);
    }

    // ============================================================
    // AVISO EN VIVO
    // ============================================================

    /**
     * Publica un aviso de cambio de turno en el canal de tiempo real.
     *
     * El método está aislado en un solo lugar, y no disperso en los tres
     * puntos donde cambia una cita, por dos razones concretas:
     *
     *   1. Centraliza el try/catch. Publicar un aviso es un extra: si falla
     *      (tabla que no existe, sin permiso, la base saturada), la reserva
     *      de turno NO puede fallar por eso. Ya se guardó en la base, y
     *      devolver un error haría que el usuario creyera que no se reservó,
     *      cuando en realidad sí: eso hace que reintente y termine con dos
     *      turnos.
     *   2. Centraliza el chequeo de que el módulo esté montado. Si
     *      $this->realtime es null (instalación sin el extra), la llamada es
     *      un no-op silencioso y el resto del módulo sigue igual.
     *
     * @param string $tipo Tipo de evento
     * @param array  $cita Datos de la cita afectada
     */
    private function avisarEnVivo(string $tipo, array $cita): void
    {
        if ($this->realtime === null) {
            return;
            // El módulo de tiempo real no está montado en esta instalación.
        }

        try {
            $this->realtime->notificarCita($tipo, $cita);
        } catch (\Exception $e) {
            // Falla al publicar el aviso: se ignora a propósito (ver punto 1).
            // No se registra en el log porque en desarrollo aparecería en
            // pantalla y ocultaría el mensaje real de la operación.
        }
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    /**
     * Lista blanca de los estados válidos de una cita.
     * Un solo lugar donde está la verdad sobre los estados: el repositorio
     * usa la misma lista para decidir si el horario se libera.
     * @return array Lista de estados
     */
    public function estadosValidos(): array
    {
        return ['pendiente', 'confirmada', 'cancelada', 'completada', 'ausente'];
    }

    /**
     * Valida que una fecha tenga formato YYYY-MM-DD y sea una fecha real.
     *
     * Ojo con el detalle: '2026-02-31' tiene el formato correcto pero NO existe
     * como fecha. Por eso, después de comprobar el formato, se reconstruye la
     * fecha con date() y se compara: si no coincide, la fecha no existe.
     *
     * @param string $fecha Fecha a validar
     * @throws InvalidArgumentException Si el formato es inválido o la fecha no existe
     */
    private function validarFecha(string $fecha): void
    {
        // Expresión regular: exactamente 4 dígitos, guion, 2 dígitos, guion, 2 dígitos
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new \InvalidArgumentException('La fecha debe tener el formato AAAA-MM-DD', 422);
        }
        // strtotime devuelve false si la fecha no existe o no se puede interpretar
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            throw new \InvalidArgumentException('La fecha indicada no es válida', 422);
        }
        // La comprobación de existencia: al reformatear una fecha inexistente,
        // PHP la "corrige" sola (2026-02-31 se convierte en 2026-03-03)
        if (date('Y-m-d', $timestamp) !== $fecha) {
            throw new \InvalidArgumentException('La fecha indicada no existe', 422);
        }
    }

    /**
     * Valida y normaliza una hora a formato HH:MM:SS.
     *
     * Acepta 'HH:MM' y 'HH:MM:SS' porque así es como la escribe la gente
     * (el formulario manda "14:30") y así la guarda MySQL.
     * @param string $hora Hora a validar
     * @return string Hora normalizada en HH:MM:SS
     * @throws InvalidArgumentException Si la hora no es válida
     */
    private function validarHora(string $hora): string
    {
        // trim() quita espacios que el usuario pueda haber pegado
        $hora = trim($hora);
        // Acepta 'H:MM', 'HH:MM', 'HH:MM:SS' (el {1,2} permite ambos anchos)
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $hora, $m)) {
            throw new \InvalidArgumentException('La hora debe tener el formato HH:MM', 422);
        }
        $horas = (int)$m[1];
        $minutos = (int)$m[2];
        $segundos = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 0;

        // Rangos válidos de un reloj: 0-23 horas, 0-59 minutos, 0-59 segundos
        if ($horas > 23 || $minutos > 59 || $segundos > 59) {
            throw new \InvalidArgumentException('La hora indicada no es válida', 422);
        }

        // sprintf('%02d') rellena con ceros a la izquierda: 9:5 -> '09:05:00'
        return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
    }

    /**
     * Convierte una hora a segundos desde medianoche.
     * Trabajar en segundos hace que las comparaciones de solapamiento sean
     * aritmética simple, en vez de comparar strings.
     * @param string $hora Hora en HH:MM o HH:MM:SS
     * @return int Cantidad de segundos desde medianoche
     */
    private function aSegundos(string $hora): int
    {
        // explode() parte la hora en sus tres componentes
        $partes = explode(':', trim($hora));
        // ?: 0 cubre el caso en que falte el componente de los segundos
        $horas = (int)($partes[0] ?? 0);
        $minutos = (int)($partes[1] ?? 0);
        $segundos = (int)($partes[2] ?? 0);
        return ($horas * 3600) + ($minutos * 60) + $segundos;
    }

    /**
     * Convierte segundos desde medianoche a texto HH:MM.
     * @param int $segundos Segundos desde medianoche
     * @return string Hora en formato HH:MM
     */
    private function deSegundos(int $segundos): string
    {
        // Se aritmetiza: primero las horas, luego los minutos
        $horas = intdiv($segundos, 3600);
        $minutos = intdiv($segundos % 3600, 60);
        return sprintf('%02d:%02d', $horas, $minutos);
    }

    /**
     * Normaliza una hora de la base a HH:MM.
     * MySQL devuelve las columnas TIME como '08:00:00' y el frontend
     * trabalha con '08:00'. Esta función es la frontera entre ambos.
     * @param string $hora Hora en HH:MM:SS o HH:MM
     * @return string Hora en HH:MM
     */
    private function normalizarHora(string $hora): string
    {
        // substr($hora, 0, 5) se queda con los primeros 5 caracteres: '08:00'
        return substr(trim($hora), 0, 5);
    }

    /**
     * Determina si un turno ya pasó.
     * Solo aplica a la fecha de hoy: los turnos de mañana o de otro día
     * nunca están vencidos.
     * @param int $timestampFecha Timestamp de la fecha pedida a medianoche
     * @param int $cursor Segundos desde medianoche del turno
     * @param int $ahora Timestamp del momento actual
     * @return bool true si el turno ya transcurrió
     */
    private function yaPaso(int $timestampFecha, int $cursor, int $ahora): bool
    {
        // Si la fecha pedida no es hoy, ningún turno puede haber pasado todavía
        if (date('Y-m-d', $timestampFecha) !== date('Y-m-d', $ahora)) {
            return false;
        }
        // Instante completo del turno: medianoche del día + segundos del turno
        $instanteTurno = $timestampFecha + $cursor;
        // El turno ya pasó si su instante es anterior a ahora
        return $instanteTurno < $ahora;
    }

    /**
     * Busca el bloque de atención que contiene la hora pedida.
     * @param array $bloques Bloques del día
     * @param string $hora Hora normalizada a HH:MM:SS
     * @return array|null El bloque o null si la hora cae fuera de todo bloque
     */
    private function buscarBloqueQueContiene(array $bloques, string $hora): ?array
    {
        // Instante en segundos de la hora pedida
        $instante = $this->aSegundos($this->normalizarHora($hora));

        // Recorre los bloques del día buscando uno que contenga la hora
        foreach ($bloques as $bloque) {
            $inicio = $this->aSegundos($bloque['hora_inicio']);
            $fin = $this->aSegundos($bloque['hora_fin']);
            $duracion = max(5, (int)$bloque['duracion_minutos']);
            $instanteFin = $instante + $duracion;

            // El turno tiene que empezar dentro del bloque...
            if ($instante < $inicio || $instante >= $fin) {
                continue;
            }
            // ...y terminar antes de que el bloque se cierre. Sin este segundo
            // control se ofrecería un turno que se corta a la mitad.
            if ($instanteFin > $fin) {
                continue;
            }

            return $bloque;
        }

        // Ningún bloque contiene esa hora
        return null;
    }

    /**
     * Verifica que el usuario autenticado tenga derecho a operar sobre la cita.
     *
     * La autorización se hace sobre el DATO, no solo sobre el rol: además de
     * "¿sos médico?", pregunta "¿es TU cita?". Es lo que impide que un
     * paciente autenticado cancele el turno de otro solo por adivinar un id.
     *
     * @param array $cita La cita a verificar
     * @param array $contexto ['rol' => ..., 'id_paciente' => ..., 'id_medico' => ...]
     * @throws RuntimeException Si el usuario no es dueño de la cita (403)
     */
    private function verificarAccesoACita(array $cita, array $contexto): void
    {
        // El administrador tiene acceso a todas las citas: es el administrador
        if (($contexto['rol'] ?? '') === 'admin') {
            return;
        }

        // Si es paciente, tiene que ser su propia cita (comparación estricta de enteros)
        if (($contexto['rol'] ?? '') === 'paciente') {
            if ((int)$cita['id_paciente'] !== (int)($contexto['id_paciente'] ?? 0)) {
                throw new \RuntimeException('No tenés permisos sobre esta cita', 403);
            }
            return;
        }

        // Si es médico, tiene que ser una cita de SU agenda
        if (($contexto['rol'] ?? '') === 'medico') {
            if ((int)$cita['id_medico'] !== (int)($contexto['id_medico'] ?? 0)) {
                throw new \RuntimeException('No tenés permisos sobre esta cita', 403);
            }
            return;
        }

        // Cualquier otro rol no tiene acceso
        throw new \RuntimeException('No tenés permisos sobre esta cita', 403);
    }

    /**
     * Verifica que la transición de estado sea coherente Y que el rol tenga
     * permiso para ejecutarla.
     *
     * Reglas:
     *   - Una cita cancelada o ausente es un estado FINAL: no se puede volver atrás.
     *   - Una cita completada no se puede deshacer.
     *   - Solo se puede confirmar una cita que esté pendiente.
     *   - Cada rol solo puede llevar la cita a los estados que le corresponden.
     *
     * Sin esto, un doble clic o un reintento de red podría "revivir" un turno
     * cancelado y volver a tapar un horario que alguien más ya reservó.
     *
     * Y sin el control por rol, un paciente podría marcar su propio turno como
     * 'completada' o 'ausente' y falsear el histórico de asistencia, que es
     * justamente el dato con el que se mide el ausentismo real.
     *
     * @param string $estadoActual Estado actual de la cita
     * @param string $estadoNuevo Estado destino
     * @param string $rol Rol del usuario que intenta el cambio
     * @throws InvalidArgumentException Si la transición no tiene sentido
     * @throws RuntimeException Si el rol no puede ejecutar esa transición (403)
     */
    private function verificarTransicion(string $estadoActual, string $estadoNuevo, string $rol): void
    {
        // Si no cambia el estado, no hay nada que hacer y no es un error
        if ($estadoActual === $estadoNuevo) {
            return;
        }

        // Estados finales: una vez que se llega, no se sale
        $estadosFinales = ['cancelada', 'completada', 'ausente'];
        if (in_array($estadoActual, $estadosFinales, true)) {
            throw new \InvalidArgumentException(
                'No se puede modificar una cita que ya está en estado ' . $estadoActual,
                422
            );
        }

        // Solo se confirma una cita que sigue pendiente
        if ($estadoNuevo === 'confirmada' && $estadoActual !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se puede confirmar una cita pendiente', 422);
        }

        // --------------------------------------------------
        // Autorización por rol: qué estados puede aplicar cada uno
        // --------------------------------------------------
        if ($rol === 'admin') {
            // El administrador es la excepción: puede llevar la cita a
            // cualquier estado válido (ya se validó contra la lista blanca)
            return;
        }

        if ($rol === 'paciente') {
            // El paciente gestiona SU cita desde la autogestión: confirmar
            // que asiste o liberar el horario. No puede declararse atendido
            // ni ausente, porque eso lo registra quien atiende el turno.
            $permitidos = ['confirmada', 'cancelada'];
            if (!in_array($estadoNuevo, $permitidos, true)) {
                throw new \RuntimeException(
                    'Un paciente solo puede confirmar o cancelar su turno. '
                    . 'El estado "' . $estadoNuevo . '" lo registra el profesional o el consultorio.',
                    403
                );
            }
            return;
        }

        if ($rol === 'medico') {
            // El profesional atiende: marca completada, ausente si el paciente
            // no vino, o cancela (por ejemplo, si se bridó de un caso urgente).
            $permitidos = ['completada', 'ausente', 'cancelada'];
            if (!in_array($estadoNuevo, $permitidos, true)) {
                throw new \RuntimeException(
                    'El profesional solo puede completar, marcar ausente o cancelar el turno',
                    403
                );
            }
            return;
        }

        // Cualquier otro rol no puede cambiar estados
        throw new \RuntimeException('No tenés permisos para cambiar el estado de una cita', 403);
    }
}
