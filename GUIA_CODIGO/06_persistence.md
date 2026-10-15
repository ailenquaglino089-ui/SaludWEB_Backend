# Guía de código — Backend · persistence/

> Recorrido línea por línea de la capa de acceso a datos (la única que habla SQL).
> Cada repositorio recibe el PDO por constructor y no sabe nada de HTTP.
> Índice general en ../GUIA_DE_CODIGO.md
> Regla de esta guía: los números de línea salen de leer cada archivo, no se inventan.

## `persistence/CitaRepositoryInterface.php` (50 líneas)
- **Rol:** Contrato de persistencia de las citas (turnos) de la turnera. Solo firmas, sin SQL.
- **Recorrido línea por línea:**
  - L1–L6: cabecera y presentación del contrato de citas.
  - L7–L7: declaración de `interface CitaRepositoryInterface`.
  - L8–L8: llave de apertura del cuerpo.
  - L9–L14: docblock y firma de `obtenerPaginadas()` (offset, cantidad, filtros y restricción por médico).
  - L15–L16: `contar()` para el total paginado.
  - L17–L18: `obtenerPorId()` con retorno nullable.
  - L19–L20: `obtenerPorPacienteYRango()` para el historial del paciente.
  - L21–L23: `obtenerPorMedicoYFecha()`, la consulta que alimenta la agenda del profesional.
  - L24–L28: `crear()` con la advertencia de la `PDOException` 23000 por horario duplicado.
  - L29–L30: `actualizar()` de campos enviados.
  - L31–L33: `cambiarEstado()`, que centraliza la regla que libera el horario.
  - L34–L35: `eliminar()` de uso administrativo.
  - L36–L38: `marcarRecordatorioEnviado()` para no repetir recordatorios.
  - L39–L42: `obtenerResumen()` con total, `por_estado` y ausentismo.
  - L43–L45: `contarPorDia()` para el gráfico de demanda real.
  - L46–L47: `contarPorEspecialidad()`.
  - L48–L49: `contarPorMedico()` con ausentes por profesional.
  - L50–L50: cierre de la interfaz.
- **Se conecta con:** `persistence/CitaRepository.php` (la implementa) y `services/CitaService.php` (la usa como tipo).

## `persistence/CitaRepository.php` (598 líneas)
- **Rol:** Repository de las citas. Es el archivo con más SQL del proyecto; ningún valor del cliente se concatena, todo va con marcadores `?`.
- **Recorrido línea por línea:**
  - L1–L13: cabecera, la regla de no concatenar valores y el atributo `$pdo`.
  - L14–L27: constantes `ESTADOS_QUE_RESERVAN` y `ESTADOS_QUE_LIBERAN`, con sus docblocks.
  - L28–L37: docblock y constructor con inyección de PDO.
  - L38–L51: docblock de `obtenerPaginadas()`, incluido por qué los JOIN son LEFT.
  - L52–L62: firma y SELECT base con nombres de paciente y médico.
  - L63–L70: armado del WHERE reutilizando el helper `construirFiltros()`.
  - L71–L74: comentario y ORDER BY por fecha y hora.
  - L75–L89: prepare, bindeo de filtros, LIMIT/OFFSET con `PDO::PARAM_INT` y `fetchAll`.
  - L90–L99: docblock y firma de `contar()`.
  - L100–L111: COUNT base y WHERE con el mismo helper de filtros.
  - L112–L119: `query()` vs `prepare()` según haya parámetros, y devolución del total.
  - L120–L138: docblock y firma de `construirFiltros()`.
  - L139–L149: inicialización de `$where` y `$parametros` y filtro por estado.
  - L150–L165: filtros por fecha puntual y por rango `desde`/`hasta`.
  - L166–L181: comentario largo y filtro por `id_paciente` usando `isset()` en vez de `empty()`.
  - L182–L187: filtro por `id_medico` con el mismo criterio.
  - L188–L196: filtro por origen `creado_por_paciente`.
  - L197–L202: filtro `solo_activas`, que excluye los estados que liberaron el horario.
  - L203–L214: búsqueda de texto libre con LIKE repetido en motivo, notas y nombres.
  - L215–L217: retorno de `[where, parametros]` y cierre del helper.
  - L218–L239: docblock, firma y código de `obtenerPorId()`.
  - L240–L262: docblock, firma y código de `obtenerPorPacienteYRango()`.
  - L263–L285: docblock, firma y código de `obtenerPorMedicoYFecha()`.
  - L286–L299: docblock de `crear()` con la explicación del índice UNIQUE y la concurrencia.
  - L300–L308: firma y prepare del INSERT de la cita.
  - L309–L335: bindeo campo por campo con valores por defecto y `lastInsertId()`.
  - L336–L348: docblock y arranque de `actualizar()` con los acumuladores.
  - L349–L379: campos permitidos del SET dinámico, cada uno con su `isset()`.
  - L380–L390: corte si no hay campos, armado del UPDATE y ejecución.
  - L391–L404: docblock y comentario previo de `cambiarEstado()`, que evita el estado intermedio.
  - L405–L416: firma, cálculo de `slot_reservado` con `in_array` y UPDATE en una sola operación.
  - L417–L428: docblock, firma y DELETE de `eliminar()`.
  - L429–L440: docblock, firma y UPDATE de `marcarRecordatorioEnviado()`.
  - L441–L457: docblock, firma y WHERE opcional por médico de `obtenerResumen()`.
  - L458–L469: GROUP BY estado y armado del arreglo `por_estado`.
  - L470–L489: total por suma, cálculo de ausentismo sin dividir por cero y retorno.
  - L490–L498: docblock de `contarPorDia()`.
  - L499–L516: firma, SQL, filtro opcional por médico y armado de parámetros.
  - L517–L523: ejecución y armado del resultado indexado por fecha.
  - L524–L536: docblock y firma de `contarPorEspecialidad()`.
  - L537–L552: SQL con `COALESCE(NULLIF(...))`, LEFT JOIN por nombre y parámetros.
  - L553–L555: ejecución y retorno de filas.
  - L556–L570: docblock y firma de `contarPorMedico()`.
  - L571–L581: SQL con `SUM(CASE WHEN ...)` para contar ausentes.
  - L582–L589: armado de parámetros y ejecución.
  - L590–L597: casteo de `total` y `ausentes` a int y retorno.
  - L598–L598: cierre de la clase.
- **Se conecta con:** `services/CitaService.php` y `services/EstadisticaService.php`; se ensambla en `core/bootstrap.php`.

## `persistence/ContactoRepository.php` (117 líneas)
- **Rol:** Resuelve direcciones de contacto (email de paciente/médico) para los avisos. Es chico y sin interfaz a propósito.
- **Recorrido línea por línea:**
  - L1–L18: cabecera y fundamentación de por qué no define interfaz (consulta puntual, sin mock).
  - L19–L32: declaración de clase, atributo `$pdo` y constructor.
  - L33–L59: docblock, firma y código de `obtenerEmailDePaciente()`, que devuelve null si no hay cuenta.
  - L60–L77: docblock, firma y código de `obtenerEmailDeMedico()`.
  - L78–L107: docblock, firma, SQL y guarda de paciente inexistente en `obtenerContactoDePaciente()`.
  - L108–L117: retorno con `email`, `nombre`, `tiene_cuenta` e `id_usuario`.
- **Se conecta con:** `services/NotificacionService.php` (único consumidor); no tiene interfaz.

## `persistence/DisponibilidadRepositoryInterface.php` (37 líneas)
- **Rol:** Contrato de la agenda horaria del profesional (bloques de atención semanales). Solo firmas, sin SQL.
- **Recorrido línea por línea:**
  - L1–L7: cabecera y propósito del contrato de disponibilidad.
  - L8–L12: interfaz y firma de `obtenerPorMedico()` con `$soloActivos`.
  - L13–L18: firmas de `obtenerPorMedicoYDia()` y `obtenerPorId()`.
  - L19–L26: firmas de `crear()` y `actualizar()`, con la nota sobre campos omitidos que conservan su valor.
  - L27–L33: firmas de `eliminar()` y `cambiarActivo()`, prefiriendo desactivar antes que borrar.
  - L34–L37: firma y comentario de `esDeMedico()` para autorizar pertenencia, y cierre.
- **Se conecta con:** `persistence/DisponibilidadRepository.php`, `services/DisponibilidadService.php` y `services/CitaService.php`.

## `persistence/DisponibilidadRepository.php` (195 líneas)
- **Rol:** Repository de los bloques de disponibilidad que publica cada médico.
- **Recorrido línea por línea:**
  - L1–L9: cabecera, declaración de clase y atributo `$pdo`.
  - L10–L19: docblock y constructor con inyección de dependencias.
  - L20–L38: docblock, firma y SQL de `obtenerPorMedico()`, con filtro de activos condicional.
  - L39–L58: docblock, firma y SQL de `obtenerPorMedicoYDia()`, apoyado en el índice por médico y día.
  - L59–L71: docblock, firma y código de `obtenerPorId()`.
  - L72–L102: docblock, firma e INSERT de `crear()` con seis marcadores.
  - L103–L124: docblock de `actualizar()` y lista blanca contra mass assignment.
  - L125–L150: armado dinámico del SET y ejecución del UPDATE.
  - L151–L161: docblock, firma y DELETE de `eliminar()`.
  - L162–L177: docblock, firma y UPDATE de `cambiarActivo()` con conversión a 1/0.
  - L178–L195: docblock, firma y verificación de pertenencia de `esDeMedico()`.
- **Se conecta con:** `services/DisponibilidadService.php` y `services/CitaService.php`.

## `persistence/EspecialidadRepositoryInterface.php` (27 líneas)
- **Rol:** Contrato del catálogo de especialidades. Solo firmas, sin SQL.
- **Recorrido línea por línea:**
  - L1–L8: cabecera y explicación de que la clase concreta decide el SQL.
  - L9–L13: interfaz y firma de `obtenerTodas()` con `$soloActivas`.
  - L14–L19: firmas de `obtenerPorId()`, `obtenerPorNombre()` y `crear()`.
  - L20–L26: firmas de `actualizar()`, `eliminar()` y `contarMedicos()`.
  - L27–L27: cierre de la interfaz.
- **Se conecta con:** `persistence/EspecialidadRepository.php` y `services/EspecialidadService.php`.

## `persistence/EspecialidadRepository.php` (135 líneas)
- **Rol:** Repository del catálogo de especialidades. Es el único lugar con SQL de esa tabla.
- **Recorrido línea por línea:**
  - L1–L10: cabecera, declaración de clase y atributo `$pdo`.
  - L11–L20: docblock y constructor con inyección de dependencias.
  - L21–L44: docblock, firma y SQL de `obtenerTodas()` con la subconsulta de `cantidad_medicos`.
  - L45–L61: docblock, firma y código de `obtenerPorId()`.
  - L62–L76: docblock, firma y código de `obtenerPorNombre()`.
  - L77–L91: docblock, firma e INSERT de `crear()`.
  - L92–L104: docblock, firma y UPDATE de `actualizar()`.
  - L105–L116: docblock, firma y DELETE de `eliminar()`.
  - L117–L135: docblock, firma y conteo por JOIN de `contarMedicos()`.
- **Se conecta con:** `services/EspecialidadService.php`.

## `persistence/MedicoRepositoryInterface.php` (41 líneas)
- **Rol:** Contrato del repositorio de médicos. El servicio depende del contrato y no de la clase concreta.
- **Recorrido línea por línea:**
  - L1–L9: cabecera y propósito de la interfaz (inversión de dependencias).
  - L10–L16: interfaz y firma de `obtenerTodos()` con filtros.
  - L17–L23: firmas de `obtenerPaginado()` y `contar()` con búsqueda, especialidad y estado.
  - L24–L32: firmas de `obtenerPorId()` y `obtenerPorMatricula()`, clave en la vinculación.
  - L33–L39: firmas de `crear()`, `actualizar()` y `desvincularPrescripciones()`.
  - L40–L41: firma de `eliminar()` y cierre.
- **Se conecta con:** `persistence/MedicoRepository.php`, `services/MedicoService.php`, `services/CitaService.php`, `services/DisponibilidadService.php` y `services/AuthService.php`.

## `persistence/MedicoRepository.php` (373 líneas)
- **Rol:** Repository de médicos, con listado, paginado, búsqueda, matrícula y desvinculación de prescripciones.
- **Recorrido línea por línea:**
  - L1–L19: comentarios de cabecera, declaración de clase y atributo `$pdo`.
  - L20–L32: docblock y constructor con inyección de dependencias.
  - L33–L43: docblock de `obtenerTodos()`.
  - L44–L51: firma e inicialización de condiciones en `obtenerTodos()`.
  - L52–L63: filtros por `activo` y por `id`.
  - L64–L74: armado del SQL con WHERE opcional, ORDER BY y ejecución.
  - L75–L79: comentario de `fetchAll` y retorno.
  - L80–L93: docblock de `obtenerPaginado()` con la explicación del LIMIT/OFFSET.
  - L94–L99: firma e inicio del SELECT en `obtenerPaginado()`.
  - L100–L111: filtro de búsqueda con LIKE en nombre, matrícula y especialidad.
  - L112–L120: filtro exacto por especialidad, con la diferencia frente a LIKE.
  - L121–L129: filtro por estado activo.
  - L130–L140: WHERE, ORDER y prepare del listado paginado.
  - L141–L153: bindeo de parámetros, LIMIT/OFFSET y retorno de la página.
  - L154–L167: docblock y firma de `contar()`.
  - L168–L181: comentario y filtro de búsqueda en `contar()`, con patrón LIKE reutilizado.
  - L182–L191: filtros por especialidad y estado en `contar()`.
  - L192–L204: `query()` vs `prepare()` y retorno del total como int.
  - L205–L211: docblock de `obtenerPorId()`.
  - L212–L230: firma y código de `obtenerPorId()`.
  - L231–L242: docblock de `obtenerPorMatricula()`.
  - L243–L253: firma, `trim` y corte si la matrícula viene vacía.
  - L254–L261: consulta parametrizada y retorno de `obtenerPorMatricula()`.
  - L262–L268: docblock de `crear()`.
  - L269–L290: firma, INSERT y valores por defecto de `crear()`.
  - L291–L313: docblock, firma y armado dinámico del SET en `actualizar()`.
  - L314–L331: corte por falta de campos, armado del UPDATE y ejecución.
  - L332–L353: docblock y cuerpo de `desvincularPrescripciones()`, que pone `id_medico` en NULL.
  - L354–L372: docblock, firma y DELETE de `eliminar()` con `rowCount()`.
  - L373–L373: cierre de la clase.
- **Se conecta con:** `services/MedicoService.php`, `services/CitaService.php`, `services/DisponibilidadService.php` y `services/AuthService.php`.

## `persistence/NotificacionRepositoryInterface.php` (50 líneas)
- **Rol:** Contrato de la bandeja de comunicaciones (recordatorios). Aclara que la tabla registra avisos, no el estado de las citas.
- **Recorrido línea por línea:**
  - L1–L10: cabecera y aclaración de diseño de que la fuente de verdad del turno es la tabla `citas`.
  - L11–L15: interfaz y firma de `obtenerPaginadas()`.
  - L16–L19: firmas de `contar()` y `obtenerPorId()`.
  - L20–L26: firma de `obtenerPorToken()` y de `crear()`.
  - L27–L38: docblock y firma de `crearSiNoExiste()`, con la unicidad delegada al índice.
  - L39–L44: firmas de `marcarEnviada()`, `marcarFallida()` y `registrarIntento()`.
  - L45–L50: firma de `obtenerPendientes()`, de `eliminar()` y cierre.
- **Se conecta con:** `persistence/NotificacionRepository.php` y `services/NotificacionService.php`.

## `persistence/NotificacionRepository.php` (388 líneas)
- **Rol:** Repository de notificaciones. Además del CRUD implementa el patrón outbox y la creación idempotente.
- **Recorrido línea por línea:**
  - L1–L10: cabecera, declaración de clase y atributo `$pdo`.
  - L11–L19: docblock y constructor.
  - L20–L33: docblock y firma de `obtenerPaginadas()`, con el porqué de los JOIN INNER.
  - L34–L43: SELECT con datos de la cita, del paciente y del médico.
  - L44–L72: acumuladores y filtros por estado, tipo, canal, usuario y cita.
  - L73–L91: WHERE, ORDER por fecha desc, bindeo de LIMIT/OFFSET y retorno.
  - L92–L106: docblock, firma y arranque de `contar()`.
  - L107–L126: filtros de `contar()` equivalentes a los del listado.
  - L127–L137: WHERE, `query()`/`prepare()` y retorno del total.
  - L138–L158: docblock, firma y código de `obtenerPorId()`.
  - L159–L181: docblock, firma y búsqueda por token en `obtenerPorToken()`.
  - L182–L218: docblock, firma e INSERT de `crear()`, que nace en estado pendiente.
  - L219–L261: docblock extenso y firma de `crearSiNoExiste()`, con la comparación entre MySQL y SQLite.
  - L262–L290: INSERT con `ON CONFLICT ... DO NOTHING RETURNING` y retorno de id o null.
  - L291–L311: docblock, firma y UPDATE de `marcarEnviada()`, con `datetime('now','localtime')`.
  - L312–L327: docblock, firma y UPDATE de `marcarFallida()` con `substr` del motivo.
  - L328–L347: docblock, firma y doble consulta de `registrarIntento()`.
  - L348–L376: docblock, firma y consulta por lotes de `obtenerPendientes()`.
  - L377–L388: docblock, firma y DELETE de `eliminar()`.
- **Se conecta con:** `services/NotificacionService.php`.

## `persistence/ObraSocialRepository.php` (39 líneas)
- **Rol:** Repository chico del catálogo de obras sociales. Sin interfaz a propósito y con un único método de lectura.
- **Recorrido línea por línea:**
  - L1–L13: cabecera, justificación de por qué no implementa interfaz y atributo tipado `PDO $pdo`.
  - L14–L20: comentario y constructor por inyección de dependencias.
  - L21–L39: docblock, firma y SELECT `ORDER BY nombre_obra` de `obtenerTodas()`.
- **Se conecta con:** los catálogos de la turnera (`routes.php`); no tiene interfaz.

## `persistence/PacienteRepositoryInterface.php` (27 líneas)
- **Rol:** Contrato del repositorio de pacientes. Solo firmas, sin implementación.
- **Recorrido línea por línea:**
  - L1–L9: cabecera, propósito del contrato y apertura de la interfaz.
  - L10–L16: firmas de `obtenerTodos()`, `obtenerPaginado()` y `contar()`.
  - L17–L22: firmas de `obtenerPorId()`, `obtenerPorDni()` y `crear()`.
  - L23–L27: firmas de `actualizar()`, `eliminar()` y cierre.
- **Se conecta con:** `persistence/PacienteRepository.php`, `services/PacienteService.php` y `services/AuthService.php`.

## `persistence/PacienteRepository.php` (239 líneas)
- **Rol:** Repository de pacientes, con listado enriquecido con la obra social, paginado y búsqueda.
- **Recorrido línea por línea:**
  - L1–L12: cabecera, declaración de clase y atributo `$pdo`.
  - L13–L22: docblock y constructor.
  - L23–L43: docblock, firma y SELECT con LEFT JOIN a obras sociales de `obtenerTodos()`.
  - L44–L62: docblock, firma y SELECT base de `obtenerPaginado()`.
  - L63–L68: filtro de búsqueda con LIKE en nombre, DNI u obra social.
  - L69–L87: ORDER, bindeo de LIMIT/OFFSET y retorno de la página.
  - L88–L111: docblock, firma y COUNT de `contar()`, con prepare o query según haya búsqueda.
  - L112–L115: retorno del total.
  - L116–L138: docblock, firma y código de `obtenerPorId()`.
  - L139–L155: docblock, firma y código de `obtenerPorDni()`.
  - L156–L177: docblock, firma e INSERT de `crear()` con valores por defecto.
  - L178–L210: docblock, firma y armado dinámico del SET en `actualizar()`.
  - L211–L225: corte por falta de campos, armado del UPDATE y ejecución.
  - L226–L239: docblock, firma y DELETE de `eliminar()`.
- **Se conecta con:** `services/PacienteService.php` y `services/AuthService.php`.

## `persistence/PrescripcionRepositoryInterface.php` (29 líneas)
- **Rol:** Contrato del repositorio de prescripciones. Solo firmas, sin SQL.
- **Recorrido línea por línea:**
  - L1–L9: cabecera, propósito del contrato y apertura de la interfaz.
  - L10–L16: firmas de `obtenerTodas()`, `obtenerPaginadas()` y `contar()`.
  - L17–L22: firmas de `obtenerPorPaciente()`, `obtenerPorId()` y `crear()`.
  - L23–L29: firmas de `actualizar()`, `eliminar()`, `cambiarEstado()` y cierre.
- **Se conecta con:** `persistence/PrescripcionRepository.php` y `services/PrescripcionService.php`.

## `persistence/PrescripcionRepository.php` (324 líneas)
- **Rol:** Repository de prescripciones, con decodificación del JSON de medicamentos.
- **Recorrido línea por línea:**
  - L1–L11: cabecera, declaración de clase y atributo `$pdo`.
  - L12–L21: docblock y constructor.
  - L22–L48: docblock, firma y SELECT de `obtenerTodas()`, con `array_map` a `formatearFila()`.
  - L49–L60: docblock de `obtenerPaginadas()`.
  - L61–L73: firma, SELECT base e inicialización de filtros.
  - L74–L86: filtros por texto y por estado exacto.
  - L87–L95: WHERE, ORDER y prepare.
  - L96–L110: bindeo de parámetros, LIMIT/OFFSET y retorno con `formatearFila`.
  - L111–L128: docblock, firma e inicio de `contar()`.
  - L129–L141: filtros y WHERE de `contar()`.
  - L142–L150: `query()`/`prepare()` y retorno del total.
  - L151–L177: docblock, firma y consulta por paciente de `obtenerPorPaciente()`.
  - L178–L203: docblock, firma y consulta por id de `obtenerPorId()`.
  - L204–L222: docblock, firma y helper `formatearFila()`, que decodifica el JSON.
  - L223–L248: docblock, firma e INSERT de `crear()`.
  - L249–L281: docblock, firma y armado dinámico del SET en `actualizar()`.
  - L282–L296: corte por falta de campos, UPDATE y ejecución.
  - L297–L309: docblock, firma y DELETE de `eliminar()`.
  - L310–L324: docblock, firma y UPDATE de `cambiarEstado()`.
- **Se conecta con:** `services/PrescripcionService.php`.

## `persistence/RealtimeRepositoryInterface.php` (69 líneas)
- **Rol:** Contrato de persistencia de los eventos de tiempo real (SSE). Permite cambiar el almacenamiento sin tocar el servicio.
- **Recorrido línea por línea:**
  - L1–L18: cabecera, justificación de la interfaz y la nota sobre el orden de carga en bootstrap.
  - L19–L28: apertura de interfaz, docblock y firma de `publicar()`.
  - L29–L43: docblock y firma de `obtenerDesde()`, con el cursor del encabezado `Last-Event-ID`.
  - L44–L55: docblock y firma de `ultimoIdDe()`.
  - L56–L68: docblock y firma de `purgarAntiguos()`.
  - L69–L69: cierre de la interfaz.
- **Se conecta con:** `persistence/RealtimeRepository.php` y `services/RealtimeService.php`.

## `persistence/RealtimeRepository.php` (149 líneas)
- **Rol:** Repository de los eventos de la tabla `eventos_realtime`: publicar, leer por cursor, último id y purga.
- **Recorrido línea por línea:**
  - L1–L19: cabecera, regla anti inyección, declaración de clase y atributo `$pdo`.
  - L20–L27: docblock y constructor.
  - L28–L37: docblock y firma de `publicar()`.
  - L38–L53: serialización a JSON con `JSON_UNESCAPED_UNICODE` y prepare del INSERT.
  - L54–L59: `lastInsertId()` con casteo a int, que es el cursor del canal.
  - L60–L69: docblock y firma de `obtenerDesde()`.
  - L70–L86: preparación del SELECT filtrado por canal e `id > ?`.
  - L87–L96: bindeo de canal, cursor y límite, ejecución y retorno.
  - L97–L115: docblock, firma y `COALESCE(MAX(id), 0)` de `ultimoIdDe()`.
  - L116–L148: docblock, firma y DELETE con `datetime('now','localtime', ?)` de `purgarAntiguos()`.
  - L149–L149: cierre de la clase.
- **Se conecta con:** `services/RealtimeService.php`.

## `persistence/UsuarioRepositoryInterface.php` (76 líneas)
- **Rol:** Contrato del repositorio de usuarios. Existe para dar coherencia al proyecto y permitir testear `UsuarioService` con un doble.
- **Recorrido línea por línea:**
  - L1–L29: cabecera y las dos razones (coherencia y testeabilidad) para declarar la interfaz.
  - L30–L40: apertura de interfaz, docblock y firma de `obtenerPaginado()`.
  - L41–L48: docblock y firma de `obtenerPorId()`.
  - L49–L62: docblock y firma de `actualizarRol()`, que no toca los vínculos con las fichas.
  - L63–L76: docblock, firma de `contarActivosConRol()` y cierre.
- **Se conecta con:** `persistence/UsuarioRepository.php`, `services/UsuarioService.php` y el `RepoFalso` de `probar_roles.php`.

## `persistence/UsuarioRepository.php` (222 líneas)
- **Rol:** Repository de usuarios. Es el único archivo del módulo de roles que escribe SQL y nunca trae el hash de contraseña.
- **Recorrido línea por línea:**
  - L1–L19: cabecera y la nota sobre por qué ninguna consulta trae `password`.
  - L20–L31: declaración de clase, atributo tipado `PDO $pdo` y constructor.
  - L32–L36: docblock y firma de `obtenerPaginado()`.
  - L37–L43: cálculo del OFFSET y nota sobre la fórmula estándar.
  - L44–L74: nota sobre LIMIT/OFFSET interpolados y armado del WHERE seguro por partes.
  - L75–L89: acumuladores y filtro de búsqueda por nombre y email.
  - L90–L101: filtro exacto por rol y armado de `$sqlWhere`.
  - L102–L115: consulta COUNT y total de registros.
  - L116–L138: comentario y SELECT de la página con columnas explícitas y alias `rol`.
  - L139–L144: prepare, execute y fetch de los items.
  - L145–L159: envoltura de paginación con `total_paginas` y cierre del método.
  - L160–L181: docblock, firma y código de `obtenerPorId()`.
  - L182–L202: docblock, firma y UPDATE de `actualizarRol()`, con `rowCount()`.
  - L203–L222: docblock, firma y COUNT de `contarActivosConRol()`.
- **Se conecta con:** `services/UsuarioService.php` y `services/AuthService.php`.

---

## Cierre

- Archivos documentados: 20 (las 20 piezas de `persistence/`).
- Ningún archivo de `persistence/` quedó afuera.
- Los repositorios sin interfaz (`ContactoRepository`, `ObraSocialRepository`) están documentados y justifican esa decisión de diseño en su propia cabecera.
