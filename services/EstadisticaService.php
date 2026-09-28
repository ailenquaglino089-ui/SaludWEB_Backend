<?php
// ============================================================
// services/EstadisticaService.php - Capa de negocio (Métricas de la turnera)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// La cuarta promesa de la turnera es "optimización de recursos": saber
// la demanda REAL, no la que uno imagina. Este servicio arma esos números.
//
// DECISIÓN DE DISEÑO IMPORTANTE (aplica la guía de datos en tiempo real)
// ---------------------------------------------------------------------
// Las estadísticas NO se calculan en vivo cada vez que alguien abre el
// panel, y NO se duplican en una base "en tiempo real" (Firebase, Supabase
// Realtime, etc.).
//
// Por qué no, aunque la guía mencione dashboards en tiempo real:
//   • Un panel de gestión NO necesita datos con 200 ms de latencia. Nadie
//     decide nada con menos de un minuto de diferencia en un reporte mensual.
//   • Recalcular agregados sobre la base relacional es barato (índices) y
//     siempre devuelve el dato correcto, sin posibilidad de desincronización.
//   • Duplicar el estado en una segunda base introduce el antipatrón de
//     "fuente de verdad difusa" que la propia guía advierte: dos sistemas
//     que pueden mostrar números distintos y nadie sabe cuál manda.
//
// La regla de la guía dice exactamente esto: "si el usuario no notaría
// negativamente un retraso de 30 segundos, no es candidato a tiempo real".
// Un panel de estadísticas no lo notaría. Por eso va por REST, sin push.
//
// Para cuando el volumen lo justifique, la vía es una tabla de agregados
// diarios precalculados (misma base, no otra) actualizada por una tarea
// programada. Esa decisión se documenta acá para que quede clara.
class EstadisticaService
{
    // Repositorio de citas (de dónde salen los números)
    private CitaRepository $citaRepo;

    // Repositorio de médicos (para saber quiénes están activos)
    private MedicoRepository $medicoRepo;

    // Repositorio de la agenda publicada (para medir los turnos ofrecidos).
    // Se inyecta el repositorio de disponibilidad y NO se calcula a mano desde
    // el de médicos: el cálculo de cuántos turnos caben en un bloque es lógica
    // de la turnera, y por su forma pertenece al módulo de disponibilidad.
    private DisponibilidadRepository $disponibilidadRepo;

    /**
     * Constructor con inyección de dependencias
     * @param CitaRepository $citaRepo
     * @param MedicoRepository $medicoRepo
     * @param DisponibilidadRepository $disponibilidadRepo
     */
    public function __construct(
        CitaRepository $citaRepo,
        MedicoRepository $medicoRepo,
        DisponibilidadRepository $disponibilidadRepo
    ) {
        $this->citaRepo = $citaRepo;
        $this->medicoRepo = $medicoRepo;
        $this->disponibilidadRepo = $disponibilidadRepo;
    }

    /**
     * Arma el informe completo de la turnera para un rango de fechas.
     *
     * Las cuatro preguntas que responde son las cuatro promesas de la turnera:
     *   1. ¿Hay demanda?            → total, por día, por especialidad
     *   2. ¿Se viene trabajando?     → por estado, ocupación por profesional
     *   3. ¿Cuánta gente falta?      → ausentismo
     *   4. ¿Dónde hay que reforzar?  → carga por médico
     *
     * @param string $desde Fecha inicial (YYYY-MM-DD); vacío = hace 30 días
     * @param string $hasta Fecha final (YYYY-MM-DD); vacío = hoy
     * @return array Informe con todas las métricas
     */
    public function generarInforme(string $desde = '', string $hasta = '', int $idMedico = 0): array
    {
        // Si no se pasó rango, se usa uno por defecto útil: los últimos 30 días.
        // El motivo es que "todo el histórico" no sirve para decidir nada: con
        // dos años de datos la tendencia actual se diluye y el panel no ayuda.
        $desde = $desde !== '' ? $desde : date('Y-m-d', strtotime('-30 days'));
        $hasta = $hasta !== '' ? $hasta : date('Y-m-d');

        // Se normaliza el orden de las fechas por si el usuario las invirtió
        if (strtotime($desde) > strtotime($hasta)) {
            list($desde, $hasta) = [$hasta, $desde];
        }

        // Contadores generales de la tabla (total y ausentismo histórico).
        // Con $idMedico > 0, todas las consultas del informe se limitan a ese
        // profesional: es el informe de "mi agenda", no el del consultorio.
        $resumen = $this->citaRepo->obtenerResumen($idMedico);

        // Citas del rango, agrupadas por día: alimenta el gráfico de demanda
        $porDia = $this->citaRepo->contarPorDia($desde, $hasta, $idMedico);

        // Citas del rango agrupadas por especialidad
        $porEspecialidad = $this->citaRepo->contarPorEspecialidad($desde, $hasta, $idMedico);

        // Citas del rango agrupadas por profesional, con su ausentismo
        $porMedico = $this->citaRepo->contarPorMedico($desde, $hasta, $idMedico);

        // Total de citas del rango (para calcular porcentajes coherentes)
        $totalRango = array_sum($porDia);

        // --------------------------------------------------
        // Indicadores derivados que son los que realmente interesan mirar
        // --------------------------------------------------

        //-------------------------------------------------
        // Promedio de turnos por día activo.
        //
        // No se divide por todos los días del rango sino por los días en los
        // que hubo al menos una cita. Si no, un fin de semana sin turnos
        // "bajaría" el promedio y sugeriría menos demanda de la que hay.
        //-------------------------------------------------
        $diasConTurnos = count($porDia);
        $promedioDiario = $diasConTurnos > 0 ? round($totalRango / $diasConTurnos, 1) : 0;

        //-------------------------------------------------
        // Tasa de ocupación: qué proporción de los turnos posibles se usó.
        //
        // Es el indicador que responde "¿se están aprovechando los huecos?".
        // Se calcula sobre los turnos efectivamente ofrecidos, no sobre los
        // días del calendario: offering 8 horas un domingo y 2 el lunes
        // tiene que reflejarse en el número, no promediarse.
        //-------------------------------------------------
        $turnosOfrecidos = $this->contarTurnosOfrecidos($desde, $hasta, $idMedico);

        // -------------------------------------------------
        // Tasa de cancelación anticipada: cuántos turnos se liberaron
        // antes de la fecha de atención.
        // Es la señal de ausentismo silencioso: el paciente que cancela
        // con tiempo sí liberó el horario, pero reveals intención de faltar.
        // -------------------------------------------------
        $canceladas = $this->contarPorEstadoEnRango($desde, $hasta, 'cancelada', $idMedico);
        $tasaCancelacion = $totalRango > 0 ? round(($canceladas / $totalRango) * 100, 1) : 0;

        // -------------------------------------------------
        // Uso de la autogestión online: cuántas citas reservó el propio
        // paciente contra cuántas cargó el consultorio.
        // Sirve para medir si la turnera está cumpliendo su objetivo de
        // sacar filas presenciales y llamados telefónicos.
        // -------------------------------------------------
        $reservaOnline = $this->contarReservaOnline($desde, $hasta, $idMedico);

        // Armado de la respuesta final
        return [
            'rango' => [
                'desde' => $desde,
                'hasta' => $hasta,
                'dias' => (int)((strtotime($hasta) - strtotime($desde)) / 86400) + 1,
            ],
            'demanda' => [
                // Total de citas reservadas en el rango
                'total_citas' => $totalRango,
                // Distribución día por día, para el gráfico
                'por_dia' => $porDia,
                // Distribución por especialidad, para ver qué se pide más
                'por_especialidad' => $porEspecialidad,
                // Promedio de turnos por día con actividad
                'promedio_diario' => $promedioDiario,
            ],
            'ocupacion' => [
                // Turnos que el sistema ofreció en el rango
                'turnos_ofrecidos' => $turnosOfrecidos,
                // Turnos que alguien reservó
                'turnos_reservados' => $totalRango,
                // Porcentaje de aprovechamiento de la agenda
                'porcentaje' => $turnosOfrecidos > 0
                    ? round(($totalRango / $turnosOfrecidos) * 100, 1)
                    : 0,
            ],
            'asistencia' => [
                // Estados históricos de toda la tabla
                'por_estado' => $resumen['por_estado'],
                // Porcentaje de pacientes que no *}-asistieron a su turno
                'ausentismo_porcentaje' => $resumen['ausentismo_porcentaje'],
                // Porcentaje de cancelaciones anticipadas en el rango
                'tasa_cancelacion' => $tasaCancelacion,
                // Detalle de los estados del rango
                'canceladas_en_rango' => $canceladas,
            ],
            'autogestion' => [
                // Turnos reservados por el propio paciente desde la app/web
                'reservadas_por_paciente' => $reservaOnline['por_paciente'],
                // Turnos cargados por el consultorio (por teléfono o Mostrador)
                'reservadas_por_consultorio' => $reservaOnline['por_consultorio'],
                // Porcentaje de uso de la autogestión online
                'porcentaje_online' => $reservaOnline['total'] > 0
                    ? round(($reservaOnline['por_paciente'] / $reservaOnline['total']) * 100, 1)
                    : 0,
            ],
            'profesionales' => $porMedico,
        ];
    }

    /**
     * Cuenta los turnos que el sistema ofreció (disponibilidad) en un rango.
     *
     * Se recorre cada día del rango, se lee la agenda declarada de cada
     * médico para ese día de la semana y se cuentan los turnos que caben.
     * Es una cuenta en memoria: no agrega consultas por turno, solo una por
     * combinación médico+día que exista, gracias al índice de disponibilidades.
     *
     * @param string $desde Fecha inicial
     * @param string $hasta Fecha final
     * @return int Cantidad total de turnos ofrecidos
     */
    private function contarTurnosOfrecidos(string $desde, string $hasta, int $idMedico = 0): int
    {
        $total = 0;

        // Se obtienen los médicos activos. Si no hay ninguno, el cálculo da 0
        // y el frontend muestra "sin agenda publicada" en vez de dividir por cero.
        //
        // El filtro 'activo' no es decorativo: obtenerTodos() devuelve también
        // los profesionales dados de baja, y contar sus bloques de atención
        // inflaría 'turnos_ofrecidos' y haría que la tasa de ocupación
        // pareciera mejor de lo que realmente es.
        $filtros = ['activo' => 1];

        // Cuando el informe es de un profesional concreto, solo se mira su
        // agenda: el denominador de la ocupación tiene que ser el mismo
        // universo que el numerador.
        if ($idMedico > 0) {
            $filtros['id'] = $idMedico;
        }

        $medicos = $this->medicoRepo->obtenerTodos($filtros);
        if (empty($medicos)) {
            return 0;
        }

        // Se recorre día por día del rango
        $timestampDesde = strtotime($desde . ' 00:00:00');
        $timestampHasta = strtotime($hasta . ' 23:59:59');
        // El salto de un día son 86400 segundos
        for ($ts = $timestampDesde; $ts <= $timestampHasta; $ts += 86400) {
            // 1 = lunes ... 7 = domingo (convención de la tabla disponibilidades)
            $diaSemana = (int)date('N', $ts);

            // Se recorren los médicos de ese día
            foreach ($medicos as $medico) {
                // Se leen los bloques de atención de ese médico para ese día
                $bloques = $this->leerDisponibilidad((int)$medico['id'], $diaSemana);
                foreach ($bloques as $bloque) {
                    // Se cuentan cuántos turnos caben en el bloque:
                    // (duración del bloque / duración del turno), redondeado hacia abajo
                    $segundosBloque = $this->aSegundos($bloque['hora_fin']) - $this->aSegundos($bloque['hora_inicio']);
                    $duracionTurno = max(5, (int)$bloque['duracion_minutos']);
                    // intdiv() hace la división entera sin redondear
                    $total += intdiv($segundosBloque, $duracionTurno * 60);
                }
            }
        }

        return $total;
    }

    /**
     * Lee la disponibilidad de un médico para un día de la semana.
     * @param int $idMedico ID del médico
     * @param int $diaSemana 1 = lunes ... 7 = domingo
     * @return array Bloques de atención
     */
    private function leerDisponibilidad(int $idMedico, int $diaSemana): array
    {
        // Se consulta directamente el repositorio de disponibilidad, que ya
        // tiene el índice (id_medico, dia_semana) para responder esto rápido.
        // Si algún día este cálculo se vuelve pesado, el lugar natural para
        // cachearlo es acá, no en el controlador.
        return $this->disponibilidadRepo->obtenerPorMedicoYDia($idMedico, $diaSemana);
    }

    /**
     * Cuenta las citas del rango que están en un estado puntual
     * @param string $desde Fecha inicial
     * @param string $hasta Fecha final
     * @param string $estado Estado a contar
     * @return int Cantidad de citas
     */
    private function contarPorEstadoEnRango(string $desde, string $hasta, string $estado, int $idMedico = 0): int
    {
        // Se reutiliza el filtro del repositorio en vez de escribir un
        // segundo SQL: una sola fuente de verdad para los filtros
        $filtros = [
            'desde' => $desde,
            'hasta' => $hasta,
            'estado' => $estado,
        ];

        // Cuando el informe es de un profesional, el estado se cuenta sobre
        // su agenda y no sobre todo el consultorio
        if ($idMedico > 0) {
            $filtros['id_medico'] = $idMedico;
        }

        return $this->citaRepo->contar($filtros);
    }

    /**
     * Cuenta cuántas citas se reservaron online y cuántas por el consultorio
     * @param string $desde Fecha inicial
     * @param string $hasta Fecha final
     * @return array ['por_paciente' => int, 'por_consultorio' => int, 'total' => int]
     */
    private function contarReservaOnline(string $desde, string $hasta, int $idMedico = 0): array
    {
        // Se arma el filtro una sola vez y se reutiliza para los tres conteos,
        // de modo que numerador y denominador miden siempre el mismo universo
        $base = ['desde' => $desde, 'hasta' => $hasta];
        if ($idMedico > 0) {
            $base['id_medico'] = $idMedico;
        }

        $total = $this->citaRepo->contar($base);

        // Las citas cargadas por el consultorio son las que no son solo_activas
        // de la autogestión. Se cuentan con la única diferencia detectable:
        // created_by_patient = 0.
        $filtroConsultorio = $base;
        $filtroConsultorio['creado_por_paciente'] = 0;
        $porConsultorio = $this->citaRepo->contar($filtroConsultorio);

        $porPaciente = max(0, $total - $porConsultorio);

        return [
            'por_paciente' => $porPaciente,
            'por_consultorio' => $porConsultorio,
            'total' => $total,
        ];
    }

    /**
     * Convierte una hora a segundos desde medianoche
     * @param string $hora Hora en HH:MM:SS
     * @return int Segundos desde medianoche
     */
    private function aSegundos(string $hora): int
    {
        // explode() separa la hora en horas, minutos y segundos
        $partes = explode(':', trim($hora));
        // ?: 0 cubre que falte algún componente
        return ((int)($partes[0] ?? 0) * 3600) + ((int)($partes[1] ?? 0) * 60) + (int)($partes[2] ?? 0);
    }
}
