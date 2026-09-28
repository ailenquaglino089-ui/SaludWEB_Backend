<?php
// ============================================================
// services/DisponibilidadService.php - Capa de negocio (Agenda)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// Gestiona los bloques de atención que declara cada profesional.
// Sin este servicio la turnera no podría funcionar: los horarios que ve
// el paciente no son inventados, son los que el profesional publicó.
//
// Decisión de negocio relevante: el profesional PUBLICA su agenda.
// No es que el sistema le asigne horas automáticamente. Eso es
// intencional: cada profesional tiene una carga de trabajo y unos
// horarios distintos (un cardiólogo no atiende igual que un pediatra),
// y un automatismo que no consulte al profesional produce ausentismo
// y frustración, que es justo lo que la turnera viene a resolver.
class DisponibilidadService
{
    // Repositorio de la agenda horaria (persistencia)
    private DisponibilidadRepository $repo;

    // Repositorio de médicos (para validar que el profesional exista)
    private MedicoRepository $medicoRepo;

    /**
     * Constructor con inyección de dependencias
     * @param DisponibilidadRepository $repo
     * @param MedicoRepository $medicoRepo
     */
    public function __construct(DisponibilidadRepository $repo, MedicoRepository $medicoRepo)
    {
        // Inyecta y guarda los repositorios para usarlos en toda la clase
        $this->repo = $repo;
        $this->medicoRepo = $medicoRepo;
    }

    /**
     * Obtiene la agenda declarada por un profesional
     * @param int $idMedico ID del profesional
     * @param bool $soloActivos Si filtra por activo = 1
     * @return array Lista de bloques de atención
     * @throws InvalidArgumentException Si el profesional no existe
     */
    public function obtenerPorMedico(int $idMedico, bool $soloActivos = true): array
    {
        // Verifica que el profesional exista
        $medico = $this->medicoRepo->obtenerPorId($idMedico);
        if (!$medico) {
            throw new \InvalidArgumentException('El profesional no existe', 422);
        }

        // Delega en el repositorio y devuelve los bloques
        return $this->repo->obtenerPorMedico($idMedico, $soloActivos);
    }

    /**
     * Crea un nuevo bloque de atención para un profesional.
     * @param array $data Datos del bloque
     * @param array $contexto Contexto del usuario (rol, id_medico)
     * @return array El bloque creado
     * @throws InvalidArgumentException Si los datos o el horario no son válidos
     * @throws RuntimeException Si el usuario no puede modificar esa agenda
     */
    public function crear(array $data, array $contexto = []): array
    {
        // --------------------------------------------------
        // PASO 1: Validaciones de formato
        // --------------------------------------------------

        // El día de la semana es obligatorio
        if (!isset($data['dia_semana'])) {
            throw new \InvalidArgumentException('Debe indicar el día de la semana', 422);
        }
        $diaSemana = (int)$data['dia_semana'];
        // Rango válido: 1 (lunes) a 7 (domingo)
        if ($diaSemana < 1 || $diaSemana > 7) {
            throw new \InvalidArgumentException('El día de la semana no es válido', 422);
        }

        // Las dos horas son obligatorias
        if (empty($data['hora_inicio']) || empty($data['hora_fin'])) {
            throw new \InvalidArgumentException('Debe indicar la hora de inicio y de fin', 422);
        }

        // Se validan los formatos de las horas
        $horaInicio = $this->validarHora($data['hora_inicio']);
        $horaFin = $this->validarHora($data['hora_fin']);

        // La hora de fin debe ser POSTERIOR a la de inicio.
        // Sin esta comprobación, un bloque "de 12:00 a 08:00" se guardaría
        // y el cálculo de turnos no generaría ningún horario, dejando al
        // profesional con una agenda que parece configurada pero está vacía.
        if ($horaFin <= $horaInicio) {
            throw new \InvalidArgumentException('La hora de fin debe ser posterior a la de inicio', 422);
        }

        // La duración del turno, acotada a un rango razonable.
        // Mínimo 5 minutos (por debajo es inviable), máximo 480 (una jornada entera).
        $duracion = isset($data['duracion_minutos']) ? (int)$data['duracion_minutos'] : 30;
        if ($duracion < 5 || $duracion > 480) {
            throw new \InvalidArgumentException('La duración del turno debe estar entre 5 y 480 minutos', 422);
        }

        // El bloque tiene que dejar al menos un turno completo.
        // Un bloque más corto que un turno no puede generar ningún horario
        // y sería una configuración inútil.
        $segundos = $this->aSegundos($horaFin) - $this->aSegundos($horaInicio);
        if ($segundos < $duracion * 60) {
            throw new \InvalidArgumentException('El bloque es demasiado corto para un turno completo', 422);
        }

        // --------------------------------------------------
        // PASO 2: Autorización
        // --------------------------------------------------

        // Se determina sobre qué profesional se está modificando la agenda.
        // Un admin puede crear la agenda de cualquiera; un médico, solo la suya.
        $idMedico = $this->resolverMedicoObjetivo($data, $contexto);

        // El profesional tiene que existir y estar activo
        $medico = $this->medicoRepo->obtenerPorId($idMedico);
        if (!$medico) {
            throw new \InvalidArgumentException('El profesional no existe', 422);
        }
        if (empty($medico['activo'])) {
            throw new \InvalidArgumentException('El profesional está dado de baja', 422);
        }

        // --------------------------------------------------
        // PASO 3: Evitar bloques solapados el mismo día
        // --------------------------------------------------

        // Se leen los bloques activos de ese profesional para ese día
        $existentes = $this->repo->obtenerPorMedicoYDia($idMedico, $diaSemana);
        $nuevoInicio = $this->aSegundos($horaInicio);
        $nuevoFin = $this->aSegundos($horaFin);

        foreach ($existentes as $bloque) {
            $existenteInicio = $this->aSegundos($bloque['hora_inicio']);
            $existenteFin = $this->aSegundos($bloque['hora_fin']);
            // Misma lógica de solapamiento por intervalos que en las citas:
            // se cruzan si el inicio de uno es menor al fin del otro
            if ($nuevoInicio < $existenteFin && $existenteInicio < $nuevoFin) {
                throw new \InvalidArgumentException(
                    'Ese horario se superpone con otro bloque de atención del mismo día',
                    422
                );
            }
        }

        // --------------------------------------------------
        // PASO 4: Guardado
        // --------------------------------------------------

        $id = $this->repo->crear([
            'id_medico' => $idMedico,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'duracion_minutos' => $duracion,
            // Todo bloque nace activo
            'activo' => 1,
        ]);

        // Se devuelve el bloque recién creado
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Edita un bloque de atención existente.
     *
     * Se implementa un update real y no se reutiliza crear() a propósito:
     * llamar a crear() con un id dentro del cuerpo creaba un bloque NUEVO
     * en lugar de modificar el pedido, dejando el bloque viejo intacto y
     * duplicando el horario en la agenda. Ese comportamiento era invisible
     * en la API (respondía 200 con el bloque creado) y solo se veía
     * como "la agenda tiene horarios repetidos".
     *
     * Solo se modifican los campos que vienen en el cuerpo, así que una
     * edición parcial (por ejemplo, cambiar solo la duración del turno) no
     * borra el resto de la configuración.
     *
     * @param int $id ID del bloque a editar
     * @param array $data Campos a modificar
     * @param array $contexto Contexto del usuario (rol, id_medico)
     * @return array El bloque ya actualizado
     * @throws InvalidArgumentException Si algún dato no es válido (422)
     * @throws RuntimeException Si el bloque no existe (404) o no es suya la agenda (403)
     */
    public function actualizar(int $id, array $data, array $contexto = []): array
    {
        // El bloque tiene que existir. obtenerPorId() devuelve null si no,
        // y el mensaje 404 se maneja acá porque el servicio es el que sabe
        // de qué se trata el id.
        $bloque = $this->repo->obtenerPorId($id);
        if (!$bloque) {
            throw new \RuntimeException('El bloque de atención no existe', 404);
        }

        // Se verifica que el usuario pueda modificar ESA agenda.
        // Un médico no puede editar el bloque de otro aunque conozca el id.
        $this->verificarAccesoAMedico((int)$bloque['id_medico'], $contexto);

        // El id del profesional no se puede cambiar por esta vía: mover un
        // bloque de agenda entre profesionales dejaría citas ya tomadas
        // colgando de un horario que ahora pertenece a otro.
        if (array_key_exists('id_medico', $data) && (int)$data['id_medico'] !== (int)$bloque['id_medico']) {
            throw new \InvalidArgumentException(
                'No se puede cambiar el profesional de un bloque de atención existente',
                422
            );
        }

        // --------------------------------------------------
        // VALIDACIONES DE FORMATO (solo de lo que viene)
        // --------------------------------------------------
        $cambios = [];

        // Día de la semana
        if (array_key_exists('dia_semana', $data)) {
            $diaSemana = (int)$data['dia_semana'];
            if ($diaSemana < 1 || $diaSemana > 7) {
                throw new \InvalidArgumentException('El día de la semana no es válido', 422);
            }
            $cambios['dia_semana'] = $diaSemana;
        } else {
            $diaSemana = (int)$bloque['dia_semana'];
        }

        // Horas: se validan solo si vienen; si no, se conserva la actual
        if (array_key_exists('hora_inicio', $data)) {
            $cambios['hora_inicio'] = $this->validarHora((string)$data['hora_inicio']);
        } else {
            $cambios['hora_inicio'] = $this->validarHora($bloque['hora_inicio']);
        }

        if (array_key_exists('hora_fin', $data)) {
            $cambios['hora_fin'] = $this->validarHora((string)$data['hora_fin']);
        } else {
            $cambios['hora_fin'] = $this->validarHora($bloque['hora_fin']);
        }

        // La hora de fin tiene que quedar después de la de inicio, usando los
        // valores finales (los nuevos si vienen, los viejos si no)
        if ($this->aSegundos($cambios['hora_fin']) <= $this->aSegundos($cambios['hora_inicio'])) {
            throw new \InvalidArgumentException('La hora de fin debe ser posterior a la de inicio', 422);
        }

        // Duración del turno
        if (array_key_exists('duracion_minutos', $data)) {
            $duracion = (int)$data['duracion_minutos'];
            if ($duracion < 5 || $duracion > 480) {
                throw new \InvalidArgumentException('La duración del turno debe estar entre 5 y 480 minutos', 422);
            }
            $cambios['duracion_minutos'] = $duracion;
        } else {
            $duracion = (int)$bloque['duracion_minutos'];
        }

        // El bloque tiene que seguir dejando al menos un turno completo
        $segundos = $this->aSegundos($cambios['hora_fin']) - $this->aSegundos($cambios['hora_inicio']);
        if ($segundos < $duracion * 60) {
            throw new \InvalidArgumentException('El bloque es demasiado corto para un turno completo', 422);
        }

        // --------------------------------------------------
        // SOLAPAMIENTO CON OTROS BLOQUES DEL MISMO DÍA
        // --------------------------------------------------
        // Se comparan los otros bloques del mismo profesional y día, pero se
        // excluye el propio id: si no, el bloque se solaparía consigo mismo
        // y ninguna edición sería posible.
        $existentes = $this->repo->obtenerPorMedicoYDia((int)$bloque['id_medico'], $diaSemana);
        $nuevoInicio = $this->aSegundos($cambios['hora_inicio']);
        $nuevoFin = $this->aSegundos($cambios['hora_fin']);

        foreach ($existentes as $otro) {
            if ((int)$otro['id'] === $id) {
                continue;
            }
            $otroInicio = $this->aSegundos($otro['hora_inicio']);
            $otroFin = $this->aSegundos($otro['hora_fin']);
            if ($nuevoInicio < $otroFin && $otroInicio < $nuevoFin) {
                throw new \InvalidArgumentException(
                    'Ese horario se superpone con otro bloque de atención del mismo día',
                    422
                );
            }
        }

        // Si el cliente mandó menos campos de los que se completan arriba,
        // se filtra la lista: solo se escriben los que realmente cambiaron.
        $enviar = [];
        foreach (['dia_semana', 'hora_inicio', 'hora_fin', 'duracion_minutos'] as $columna) {
            if (array_key_exists($columna, $data)) {
                $enviar[$columna] = $cambios[$columna];
            }
        }

        // Nada que cambiar: se devuelve el bloque como está, sin tocar la base
        if (count($enviar) === 0) {
            return $bloque;
        }

        $this->repo->actualizar($id, $enviar);

        return $this->repo->obtenerPorId($id);
    }

    /**
     * Activa o desactiva un bloque de atención.
     *
     * Desactivar es la operación preferida frente a borrar: si el bloque ya
     * tiene citas tomadas, borrarlo deja turnos huerfanos en la agenda de los
     * pacientes. Desactivarlo hace que dejen de ofrecerse sin romper nada.
     *
     * @param int $id ID del bloque
     * @param bool $activo true para activar, false para desactivar
     * @param array $contexto Contexto del usuario
     * @return array El bloque actualizado
     * @throws RuntimeException Si el bloque no existe o el usuario no es dueño
     */
    public function cambiarActivo(int $id, bool $activo, array $contexto = []): array
    {
        // Se carga el bloque (lanza 404 si no existe)
        $bloque = $this->repo->obtenerPorId($id);
        if (!$bloque) {
            throw new \RuntimeException('El bloque de atención no existe', 404);
        }

        // Se verifica que el usuario pueda modificar ESA agenda
        $this->verificarAccesoAMedico((int)$bloque['id_medico'], $contexto);

        // Se aplica el cambio
        $this->repo->cambiarActivo($id, $activo);

        return $this->repo->obtenerPorId($id);
    }

    /**
     * Elimina un bloque de atención (uso administrativo).
     * @param int $id ID del bloque
     * @param array $contexto Contexto del usuario
     * @throws RuntimeException Si el bloque no existe o el usuario no es admin
     */
    public function eliminar(int $id, array $contexto = []): void
    {
        // Verifica que el bloque exista
        $bloque = $this->repo->obtenerPorId($id);
        if (!$bloque) {
            throw new \RuntimeException('El bloque de atención no existe', 404);
        }

        // Borrar sí es exclusivo del administrador. Para el uso diario
        // (dejar de ofrecer un horario) está cambiarActivo().
        if (($contexto['rol'] ?? '') !== 'admin') {
            throw new \RuntimeException('Solo un administrador puede eliminar bloques de atención', 403);
        }

        $this->repo->eliminar($id);
    }

    /**
     * Resuelve sobre qué profesional se va a operar.
     *
     * Regla: el admin puede indicar cualquier id_medico; el médico SIEMPRE
     * opera sobre el suyo, ignorando cualquier id que mande en el cuerpo.
     * Esto evita que un médico modifique la agenda de otro enviando otro id.
     *
     * @param array $data Datos recibidos
     * @param array $contexto ['rol' => ..., 'id_medico' => ...]
     * @return int ID del profesional objetivo
     * @throws RuntimeException Si un médico no tiene id_medico asignado (403)
     */
    private function resolverMedicoObjetivo(array $data, array $contexto): int
    {
        $rol = $contexto['rol'] ?? '';

        // El admin elige el profesional, explícitamente
        if ($rol === 'admin') {
            if (empty($data['id_medico'])) {
                throw new \InvalidArgumentException('Debe indicar el profesional', 422);
            }
            return (int)$data['id_medico'];
        }

        // El médico se trabaja a sí mismo: usa su id del token
        if ($rol === 'medico') {
            $idMedico = (int)($contexto['id_medico'] ?? 0);
            if ($idMedico <= 0) {
                // Situación real: existe un usuario con rol 'medico' en la tabla
                // usuarios pero sin id_medico asociado. Sin ese vínculo no hay
                // forma de saber de qué agenda se trata, así que se rechaza
                // explícitamente en lugar de operar sobre un id que venía del cliente.
                throw new \RuntimeException(
                    'Tu usuario no está asociado a un profesional del catálogo',
                    403
                );
            }
            return $idMedico;
        }

        // Cualquier otro rol no gestiona agendas
        throw new \RuntimeException('No tenés permisos para gestionar la agenda', 403);
    }

    /**
     * Verifica que el usuario pueda operar sobre la agenda de un profesional
     * @param int $idMedico ID del profesional cuya agenda se quiere tocar
     * @param array $contexto Contexto del usuario
     * @throws RuntimeException Si el usuario no es admin ni el dueño de la agenda
     */
    private function verificarAccesoAMedico(int $idMedico, array $contexto): void
    {
        $rol = $contexto['rol'] ?? '';

        // El administrador puede tocar cualquier agenda
        if ($rol === 'admin') {
            return;
        }

        // El médico solo la suya
        if ($rol === 'medico' && (int)($contexto['id_medico'] ?? 0) === $idMedico) {
            return;
        }

        throw new \RuntimeException('No tenés permisos para gestionar esa agenda', 403);
    }

    /**
     * Valida y normaliza una hora a HH:MM:SS
     * @param string $hora Hora a validar
     * @return string Hora normalizada
     * @throws InvalidArgumentException Si la hora no es válida
     */
    private function validarHora(string $hora): string
    {
        // trim() quita espacios externos
        $hora = trim($hora);
        // Acepta HH:MM y HH:MM:SS
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $hora, $m)) {
            throw new \InvalidArgumentException('Las horas deben tener el formato HH:MM', 422);
        }
        $horas = (int)$m[1];
        $minutos = (int)$m[2];
        $segundos = isset($m[3]) && $m[3] !== '' ? (int)$m[3] : 0;
        // Rango de un reloj válido
        if ($horas > 23 || $minutos > 59 || $segundos > 59) {
            throw new \InvalidArgumentException('La hora indicada no es válida', 422);
        }
        return sprintf('%02d:%02d:%02d', $horas, $minutos, $segundos);
    }

    /**
     * Convierte una hora a segundos desde medianoche
     * @param string $hora Hora en HH:MM o HH:MM:SS
     * @return int Segundos desde medianoche
     */
    private function aSegundos(string $hora): int
    {
        // explode() separa la hora en sus componentes
        $partes = explode(':', trim($hora));
        // ?: 0 cubre que falte el componente de segundos
        return ((int)($partes[0] ?? 0) * 3600) + ((int)($partes[1] ?? 0) * 60) + (int)($partes[2] ?? 0);
    }
}
