<?php
// ============================================================
// services/RealtimeService.php - Capa de negocio del tiempo real
// ============================================================
// Módulo: "Primera funcionalidad en tiempo real" (SSE)
//
// Este servicio concentra las DOS decisiones que definen el módulo:
//
//   1. CUÁNDO se publica un evento (notificarCita)
//      Se llama desde CitaService, o sea en el mismo momento en que un turno
//      se crea, cambia de estado o se borra. Publicar el aviso más tarde
//      (por ejemplo, con un cron) reproduciría el problema que se quería
//      evitar: el servidor mostrando algo viejo.
//
//   2. QUIÉN puede escuchar QUÉ (resolverCanal)
//      Un canal no es una dirección pública: si cualquiera pudiera abrir
//      'turnos:7' recibiría los movimientos de los turnos de un paciente
//      ajeno. El canal se resuelve contra el contexto del usuario, y el
//      nombre real del canal sale SIEMPRE del token, nunca de un parámetro
//      que el cliente pueda inventar.
//
// LA REGLA QUE RESUELVE EL ANTIPATRÓN DE LA GUÍA
// -------------------------------------------------
// La guía descarta usar tiempo real cuando el estado se duplica en una base
// "en vivo", porque ahí quedan dos fuentes de verdad que pueden discrepar.
// La solución aplicada acá NO es duplicar el estado: la tabla eventos_realtime
// solo guarda la señal
//
//     { tipo: 'cita_creada', id_cita: 12, id_medico: 1, id_paciente: 3 }
//
// sin nombre, sin DNI, sin motivo de consulta. Los datos que se muestran
// siguen viniendo por REST de /api/citas y /api/estadisticas, que son los
// que validan permisos. Si un turno cambia, el estado real se actualiza en un
// solo lugar (la tabla citas) y el evento solo dice "andá a preguntarlo otra
// vez". El evento es una PUNTUACIÓN, no un estado.
//
// El canal 'tablero' es global a propósito: los indicadores de ocupación y
// demanda del consultorio cambian con CUALQUIER turno, no solo con los de un
// médico en particular. Por eso el panel se suscribe a un solo canal y recibe
// todo lo que le importa.

class RealtimeService
{
    // Repositorio de eventos (persistencia), inyectado por constructor
    private RealtimeRepositoryInterface $repo;

    // Minutos que se conservan los eventos antes de purgarlos.
    //
    // No es "el tiempo que dura una notificación": es una holgura para que un
    // cliente que reconecta tarde (notebook que durmió, túnel de celular,
    // pestaña que el navegador congeló) siga reciban lo que se perdió. Si el
    // hueco fuera de cero, una reconexión de 30 segundos ya perdería eventos,
    // y el cliente se quedaría mostrando un dato viejo sin enterarse.
    private const RETENCION_MINUTOS = 30;

    // Cada cuántas publicaciones se intenta una purga.
    //
    // Purgar en cada escritura sería una consulta extra por cada turno
    // reservado, y esa tabla se escribe poco: no vale el costo. La purga se
    // hace de vez en cuando y, además, siempre alcanza: si un evento queda sin
    // borrar en una pasada, la siguiente lo encuentra, porque el corte se
    // calcula contra la hora actual y no contra "los últimos N eventos".
    private const PURGA_CADA = 25;

    // Contador interno de publicaciones, para decidir cuándo purgar
    private int $publicacionesDesdePurga = 0;

    /**
     * @param RealtimeRepositoryInterface $repo Repositorio de eventos
     */
    public function __construct(RealtimeRepositoryInterface $repo)
    {
        // Se guarda el repositorio inyectado
        $this->repo = $repo;
    }

    // ============================================================
    // EMISIÓN DE EVENTOS
    // ============================================================

    /**
     * Publica un aviso de cambio de turno en todos los canales a los que les importa.
     *
     * @param string $tipo Tipo de evento: cita_creada, cita_estado,
     *                      cita_cancelada o cita_eliminada
     * @param array  $cita Fila de la cita (o el subconjunto de campos
     *                      identificatorios que alcanza)
     * @return void
     */
    public function notificarCita(string $tipo, array $cita): void
    {
        // Se arman los canales destino una sola vez, para que publicarlos sea
        // un foreach y no tres bloques repetidos.
        foreach ($this->canalesDeCita($cita) as $canal) {
            // Cada canal recibe SU COPIA del payload. Se copia con spread
            // ($datos) y no por referencia: si se pasara la misma variable a
            // varias publicaciones y una de ellas la modificara, el cambio
            // se vería en los demás canales.
            $this->repo->publicar($canal, $tipo, [
                'tipo' => $tipo,
                'id_cita' => (int)($cita['id'] ?? 0),
                'id_medico' => (int)($cita['id_medico'] ?? 0),
                'id_paciente' => (int)($cita['id_paciente'] ?? 0),
                'fecha' => $cita['fecha'] ?? null,
                'hora' => $cita['hora'] ?? null,
                'estado' => $cita['estado'] ?? null,
            ]);
        }

        $this->publicacionesDesdePurga++;

        // Purga oportunista: se hace una vez cada PURGA_CADA publicaciones.
        if ($this->publicacionesDesdePurga >= self::PURGA_CADA) {
            $this->publicacionesDesdePurga = 0;
            try {
                $this->repo->purgarAntiguos(self::RETENCION_MINUTOS);
            } catch (\Exception $e) {
                // Si la purga falla, NO se interrumpe la operación de negocio.
                // Perder una pasada de limpieza es un problema menor; lo que
                // no puede pasar es que un turno reservado devuelva un error
                // porque una tabla de eventos no se pudo limpiar. El tiempo
                // real es un extra: nunca puede romper la operación principal.
            }
        }
    }

    /**
     * Calcula a qué canales le interesa un cambio de esta cita.
     *
     * @param array $cita Datos identificatorios de la cita
     * @return array Lista de nombres de canal
     */
    public function canalesDeCita(array $cita): array
    {
        $canales = [
            // Canal global: los indicadores del consultorio (ocupación,
            // demanda, ausentismo) cambian con cualquier turno, no solo con
            // los de un profesional en particular.
            self::CANAL_TABLERO,
        ];

        $idMedico = (int)($cita['id_medico'] ?? 0);
        if ($idMedico > 0) {
            // Canal de la agenda del profesional: la pantalla del médico y la
            // agenda pública del consultorio se actualizan al reservar o al
            // cancelar, que es cuando el horario cambia de disponible a ocupado.
            $canales[] = self::canalAgenda($idMedico);
        }

        $idPaciente = (int)($cita['id_paciente'] ?? 0);
        if ($idPaciente > 0) {
            // Canal del paciente: para que "Mis turnos" se actualice en el
            // momento en que su turno se confirma, se cancela o alguien lo
            // cancela por él, sin que tenga que recargar la página.
            $canales[] = self::canalTurnos($idPaciente);
        }

        return $canales;
    }

    // ============================================================
    // AUTORIZACIÓN DE SUSCRIPCIONES
    // ============================================================

    /**
     * Resuelve el canal pedido contra el contexto del usuario.
     *
     * Devuelve el NOMBRE REAL del canal que se va a escuchar, o lanza una
     * excepción si el usuario no tiene derecho a escucharlo.
     *
     * El cliente pide un canal "declarativo" (tablero, mis-turnos, mi-agenda)
     * en lugar del canal interno con ids (agenda:7, turnos:12). Esa diferencia
     * no es estética: si el cliente mandara 'agenda:7', bastaría con cambiar el
     * número en la URL para escuchar la agenda de otro profesional. Con los
     * canales declarativos, el id sale siempre del token y el backend decide.
     *
     * @param string $solicitado Canal pedido por el cliente
     * @param array  $contexto   Contexto del usuario (rol, id_paciente, id_medico)
     * @return string Nombre real del canal
     * @throws \RuntimeException 403 si el rol no puede escuchar ese canal
     * @throws \InvalidArgumentException 422 si el canal pedido no existe
     */
    public function resolverCanal(string $solicitado, array $contexto): string
    {
        $rol = (string)($contexto['rol'] ?? '');

        // trim() y strtolower() para que 'Tablero' o ' TABLERO ' se
        // acepten igual que 'tablero'. Es una concesión real, no teórica:
        // el valor viene de una URL escrita a mano o de un código que arma el
        // nombre con mayúsculas.
        $canal = strtolower(trim($solicitado));

        // ---- Canal global del panel de gestión ----
        if ($canal === self::CANAL_TABLERO) {
            // Solo admin y médicos. Un paciente no entra: los indicadores de
            // ocupación y ausentismo son de gestión, y /api/estadisticas ya
            // le responde 403 por lo mismo. Acá se repite la regla para que
            // el canal tampoco sea una puerta trasera a la información.
            if (!in_array($rol, ['admin', 'medico'], true)) {
                throw new \RuntimeException('No podés suscribirte a los indicadores del consultorio', 403);
            }
            return self::CANAL_TABLERO;
        }

        // ---- Canal de los turnos propios ----
        if ($canal === 'mis-turnos') {
            $idPaciente = (int)($contexto['id_paciente'] ?? 0);
            if ($idPaciente <= 0) {
                // El caso real: una cuenta sin ficha vinculada. No se inventa
                // un canal: se explica, porque sin ficha no hay turnos que
                // escuchar y la web necesita distinguir "no tenés turnos" de
                // "tu cuenta no está vinculada".
                throw new \RuntimeException(
                    'Tu usuario no está vinculado a una ficha de paciente, '
                    . 'por lo que todavía no hay turnos que seguir en vivo',
                    403
                );
            }
            // El id sale del token: el paciente no puede pedir los turnos de
            // otro cambiando el canal.
            return self::canalTurnos($idPaciente);
        }

        // ---- Canal de la agenda propia ----
        if ($canal === 'mi-agenda') {
            $idMedico = (int)($contexto['id_medico'] ?? 0);
            if ($idMedico <= 0) {
                throw new \RuntimeException(
                    'Tu usuario no está asociado a un profesional del catálogo',
                    403
                );
            }
            if ($rol !== 'medico' && $rol !== 'admin') {
                throw new \RuntimeException('Ese canal no está disponible para tu rol', 403);
            }
            return self::canalAgenda($idMedico);
        }

        // ---- Canal explícito de una agenda (agenda:12) ----
        // Es el único caso donde el cliente manda un id, y solo se lo acepta
        // al administrador: el profesional usa siempre 'mi-agenda', así que si
        // alguien manda 'agenda:12' es o el admin mirando la agenda de otro,
        // o un médico intentando ver la de un colega.
        if (preg_match('/^agenda:(\d+)$/', $canal, $m)) {
            if ($rol !== 'admin') {
                throw new \RuntimeException('Solo un administrador puede mirar la agenda de otro profesional', 403);
            }
            $id = (int)$m[1];
            if ($id <= 0) {
                throw new \InvalidArgumentException('El canal no es válido', 422);
            }
            return self::canalAgenda($id);
        }

        // Cualquier otra cosa es un canal inexistente. Se responde 422 y no
        // 403: no es un problema de permisos sino de que la dirección está
        // mal escrita, y la diferencia ayuda a entender el error.
        throw new \InvalidArgumentException(
            'Canal desconocido. Usá tablero, mis-turnos, mi-agenda o agenda:<id_medico>',
            422
        );
    }

    // ============================================================
    // NOMBRES DE LOS CANALES
    // ============================================================
    // Viven en métodos y no en constantes "agenda:" . $id porque el prefijo y
    // el id van juntos: separarlos sería la forma de que uno se cambie sin el
    // otro y el canal quede imposible de encontrar.

    // Canal global de los indicadores del consultorio
    const CANAL_TABLERO = 'tablero';

    /**
     * Nombre del canal de la agenda de un profesional.
     * @param int $idMedico Id del profesional
     * @return string
     */
    public static function canalAgenda(int $idMedico): string
    {
        return 'agenda:' . $idMedico;
    }

    /**
     * Nombre del canal de los turnos de un paciente.
     * @param int $idPaciente Id del paciente
     * @return string
     */
    public static function canalTurnos(int $idPaciente): string
    {
        return 'turnos:' . $idPaciente;
    }

    /**
     * Lista los tipos de evento que el módulo emite.
     *
     * Está en un solo lugar porque es el contrato entre el backend y el
     * frontend: si el backend publica un tipo nuevo y el frontend no lo
     * escucha, el evento llega y se pierde en silencio.
     *
     * @return array Tipos válidos
     */
    public function tiposValidos(): array
    {
        return ['cita_creada', 'cita_estado', 'cita_cancelada', 'cita_eliminada'];
    }

    // ============================================================
    // LECTURA PARA EL STREAM
    // ============================================================
    // Estos dos métodos existen para que el controlador no hable con el
    // repositorio. La razón es práctica y es la misma que en el resto del
    // proyecto: si mañana el módulo deja de usar MySQL y pasa a usar Redis,
    // el controlador no cambia. El formato del evento (ya desempaquetado y
    // normalizado) es parte del contrato del servicio, no de la consulta.

    /**
     * Devuelve los eventos nuevos de un canal, listos para escribir.
     *
     * @param string $canal    Canal ya autorizado
     * @param int    $ultimoId Último id ya entregado
     * @param int    $limite   Máximo por vuelta
     * @return array Eventos normalizados
     */
    public function leerEventos(string $canal, int $ultimoId, int $limite = 50): array
    {
        // El repositorio devuelve filas crudas; se pasan tal cual porque el
        // desempaquetado del JSON lo hace el controlador, que es quien escribe
        // el texto del protocolo SSE y por lo tanto es quien sabe qué forma
        // necesita.
        return $this->repo->obtenerDesde($canal, $ultimoId, $limite);
    }

    /**
     * Último id existente del canal, para arrancar desde el presente.
     *
     * @param string $canal Canal ya autorizado
     * @return int Id máximo del canal
     */
    public function ultimoIdDelCanal(string $canal): int
    {
        return $this->repo->ultimoIdDe($canal);
    }

    // ============================================================
    // DIAGNÓSTICO
    // ============================================================

    /**
     * Fuerza una purga inmediata de los eventos vencidos.
     *
     * POR QUÉ ESTE MÉTODO EXISTE
     * --------------------------
     * El servicio purga solo, cada PURGA_CADA publicaciones. Eso funciona
     * bien cuando el sistema está en uso, pero deja una consecuencia
     * incómoda para probar: si el módulo recibe pocos eventos, la tabla puede
     * quedar con filas viejas mucho tiempo y no hay forma de comprobar que la
     * limpieza funciona sin esperar a que se cumulen 25 publicaciones.
     *
     * Con este método el script de verificación puede llamar a la purga cuando
     * quiere y comprobar el resultado. Es público justamente para eso: la
     * limpieza es una parte del módulo que hay que poder probar, y una
     * operación que solo se puede disparar por un camino interno no es
     * verificable.
     *
     * @return int Filas borradas
     */
    public function purgarAhora(): int
    {
        // Se usa el mismo valor de retención que la purga automática. No se
        // permite pasar otro a propósito: si el script pudiera purgar con otra
        // ventana, estaría probando un código que el sistema no ejecuta.
        return $this->repo->purgarAntiguos(self::RETENCION_MINUTOS);
    }

    /**
     * Estado del módulo, en la forma que consume GET /api/eventos/estado.
     *
     * @return array Información del canal y de la retención de eventos
     */
    public function diagnostico(): array
    {
        return [
            'canales' => [
                // Los nombres "públicos" que el frontend puede pedir.
                'tablero' => 'Indicadores del consultorio (admin y médicos)',
                'mis-turnos' => 'Turnos del paciente autenticado',
                'mi-agenda' => 'Agenda del profesional autenticado',
                'agenda:<id_medico>' => 'Agenda de un profesional (solo admin)',
            ],
            'tipos_evento' => $this->tiposValidos(),
            // La ventana de retención es el dato que explica por qué un
            // cliente que reconecta pasado ese tiempo puede perderse algo.
            'retencion_minutos' => self::RETENCION_MINUTOS,
            // Cada cuánto se barren los eventos viejos. Se expone porque es
            // la garantía de que la tabla no crece de forma indefinida.
            'purga_cada_publicaciones' => self::PURGA_CADA,
        ];
    }
}