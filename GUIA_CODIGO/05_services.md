# Guía de código — Backend · services/

> Recorrido línea por línea de las reglas de negocio.
> Índice general en ../GUIA_DE_CODIGO.md

---

## `services/AuthService.php` (979 líneas)

- **Rol:** Lógica de autenticación: registro, login por contraseña, login SSO (Google/Microsoft), cambio de contraseña y vinculación de una cuenta con su ficha de paciente o médico.
- **Recorrido línea por línea:**
  - L1–L12: `<?php`, cabecera del servicio y los tres `use` de firebase/php-jwt (`JWT`, `Key`, `JWK`).
  - L13–L16: línea en blanco, comentario de la clase y apertura de `AuthService`.
  - L17–L21: propiedades `$pdo` y `$jwt` con sus comentarios.
  - L22–L27: docblock del constructor y comentario previo.
  - L28–L34: constructor con inyección de `PDO` y `JwtService`; guarda ambas en propiedades.
  - L35–L45: separador, docblock de `registro` y firma `registro(array $data): array`.
  - L46–L57: apertura del método y normalización del email con `Validador::email()`.
  - L58–L67: validación del nombre con `Validador::textoObligatorio()` y de la contraseña con `Validador::password()`.
  - L68–L84: SELECT parametrizado del email; si existe, `Logger::warn` y excepción 422.
  - L85–L98: hash bcrypt con `password_hash()` y validación del tipo de usuario contra whitelist.
  - L99–L114: vínculo de ficha siempre nulo (`$idPaciente`/`$idMedico`) y explicación de por qué.
  - L115–L132: INSERT parametrizado del usuario y `lastInsertId()` para el nuevo id.
  - L133–L147: `Logger::info` del registro y retorno de `obtenerPorId($userId)`.
  - L148–L158: cierre del método, docblock de `login` y firma `login(string, string): array`.
  - L159–L166: validación genérica del email (mensaje indistinguible a propósito).
  - L167–L174: SELECT del usuario activo por email y `fetch` asociativo.
  - L175–L186: usuario inexistente: `Logger::warn` del motivo real y `InvalidArgumentException` 401.
  - L187–L203: `password_verify()` contra el hash; si falla, log y excepción 401.
  - L204–L211: guarda `$_SESSION['usuario_id']` y delega el JWT en `$this->jwt->generar()`.
  - L212–L219: `Logger::info` del login correcto.
  - L220–L236: arma `$respuesta` (id, email, nombre, tipo, token, expires_at ISO) y la devuelve.
  - L237–L248: cierre, docblock de `loginSso` y firma `loginSso(string, string): array`.
  - L249–L260: valida la estructura del id_token (exactamente dos puntos) o lanza 401.
  - L261–L271: `validarIdToken()` y normalización del email de los claims.
  - L272–L292: busca la cuenta local activa; si no existe, log y excepción 401.
  - L293–L316: abre sesión, emite el JWT y devuelve la misma respuesta que `login()`.
  - L317–L330: `validarIdToken()`: despacha a Google, Microsoft o lanza 422.
  - L331–L372: `validarGoogle()`: client id, certificados X.509, `kid`, firma RS256, `iss`, `aud` y `email_verified`.
  - L373–L410: `validarMicrosoft()`: client id, tenant, discovery OpenID, JWKS, firma, `iss` y `aud`.
  - L411–L423: `obtenerHeader()`: decodifica solo el header base64url para leer el `kid`.
  - L424–L439: `certificadoPublico()`: convierte el certificado X.509 en clave pública PEM.
  - L440–L462: `httpGetJson()`: GET con cURL (timeout 10 s, redirecciones) y validación 2xx.
  - L463–L470: docblock y firma de `obtenerPorId(int $id): array`.
  - L471–L490: `obtenerPorId`: SELECT sin contraseña y excepción 404 si no existe.
  - L491–L497: docblock y firma de `obtenerPorEmail(string): ?array`.
  - L498–L508: `obtenerPorEmail`: SELECT sin contraseña y `?: null`.
  - L509–L518: docblock y firma de `cambiarContrasena(int, string, string): bool`.
  - L519–L532: trae el hash del usuario y lanza 404 si no existe.
  - L533–L546: verifica la contraseña actual (401) y el largo mínimo de la nueva (422).
  - L547–L555: genera el nuevo hash bcrypt y ejecuta el UPDATE.
  - L556–L570: docblock y firma de `contextoDePeticion(): array`.
  - L571–L584: `contextoDePeticion`: toma el payload verificado o lanza 401.
  - L585–L611: docblock de `contextoDesdePayload` (explica por qué no depende del middleware).
  - L612–L625: `contextoDesdePayload`: resuelve el usuario y devuelve rol, ids y vínculos.
  - L626–L656: docblock de `vincularEntidad` (cómo se verifica la identidad y sus límites).
  - L657–L669: `vincularEntidad`: limpia el documento y valida tipo y documento.
  - L670–L676: verifica que el usuario exista y esté activo.
  - L677–L700: exige coincidencia entre el tipo de la cuenta y la ficha; despacha a paciente o médico.
  - L701–L708: docblock y firma de `vincularPaciente(array, string): array`.
  - L709–L723: busca el paciente por DNI y lanza 422 si no existe.
  - L724–L729: rechaza fichas dadas de baja.
  - L730–L746: normaliza y compara nombres; lanza 422 si no coinciden.
  - L747–L766: impide vincular una ficha ya usada y guarda el vínculo.
  - L767–L774: docblock y firma de `vincularMedico(array, string): array`.
  - L775–L785: busca el médico por matrícula y lanza 422 si no existe.
  - L786–L804: rechaza baja, impide ficha ya usada y guarda el vínculo.
  - L805–L811: docblock y firma de `normalizarTexto(string): string`.
  - L812–L828: `normalizarTexto`: minúsculas UTF-8, quita tildes y colapsa espacios.
  - L829–L845: docblock de `coincidenNombres` (regla tolerante pero con dos palabras).
  - L846–L861: compara igualdad directa e invierte el formato "Apellido, Nombre".
  - L862–L873: obtiene palabras significativas y corta si alguna queda vacía.
  - L874–L906: exige dos palabras en común y que el nombre corto esté contenido en el largo.
  - L907–L919: docblock y firma de `palabrasSignificativas(string): array`.
  - L920–L944: `palabrasSignificativas`: descarta conjunciones y palabras de menos de dos letras.
  - L945–L957: docblock y firma de `invertirApellidoNombre(string): string`.
  - L958–L978: `invertirApellidoNombre`: convierte "apellido, nombre" en "nombre apellido".
  - L979–L979: cierre de la clase.
- **Se conecta con:** `core/Validador`, `core/Logger`, `core/Config`, `core/JwtService` y `core/AuthMiddleware` (vía `usuarioActual()`); consulta las tablas `usuarios`, `pacientes` y `medicos`; usa las claves públicas de Google y el JWKS de Microsoft.

---

## `services/CitaService.php` (1138 líneas)

- **Rol:** Corazón de la turnera: calcula disponibilidad, arma la agenda del profesional, lista y detalla citas, da de alta turnos y controla los cambios de estado con autorización por rol y por dato.
- **Recorrido línea por línea:**
  - L1–L20: cabecera y las tres reglas de negocio de la autogestión.
  - L21–L24: apertura de la clase y propiedad `CitaRepository $repo`.
  - L25–L33: propiedades `$disponibilidadRepo`, `$medicoRepo` y `$pacienteRepo`.
  - L34–L54: propiedad nullable `$realtime` (justificación del diseño) y constante `DIAS_MAXIMOS_ANTICIPACION`.
  - L55–L63: docblock del constructor.
  - L64–L80: constructor con inyección de los cuatro repositorios y el `RealtimeService` opcional.
  - L81–L105: separador del bloque "Disponibilidad", docblock y firma de `obtenerDisponibilidad`.
  - L106–L116: valida la fecha y verifica que el médico exista y esté activo.
  - L117–L127: calcula el día ISO, lee bloques de atención y citas reservadas del día.
  - L128–L140: arma el mapa de horas ocupadas (`slot_reservado` no nulo).
  - L141–L148: instante actual, timestamp de la fecha e inicio del arreglo `$slots`.
  - L149–L178: recorre bloques; calcula duración (mínimo 5 min), convierte a segundos y genera horarios.
  - L179–L195: determina el `motivo_bloqueo` (ocupado, fuera de rango o pasado).
  - L196–L214: agrega el slot con hora, hora de fin, disponibilidad, motivo e id de cita.
  - L215–L223: cuenta los turnos libres.
  - L224–L247: arma la respuesta de disponibilidad (datos del médico, bloques, slots y totales).
  - L248–L259: docblock y firma de `obtenerAgendaMedico`.
  - L260–L279: valida ambas fechas, verifica el médico y ordena el rango.
  - L280–L288: genera todos los días del rango (incluso los sin citas).
  - L289–L303: trae las citas en una sola consulta y las reparte por día.
  - L304–L332: ordena por hora, arma la agenda por día y devuelve el total.
  - L333–L345: separador del bloque "Listado y detalle", docblock y firma de `obtenerPaginadas`.
  - L346–L370: normaliza página/por página y red de seguridad del filtro por paciente (id ≤ 0 → vacío).
  - L371–L398: sanea la búsqueda, valida el estado contra whitelist y las fechas.
  - L399–L419: calcula total, páginas, acota la página y devuelve el paginado.
  - L420–L435: `obtenerPorId`: delega y lanza 404 si no existe.
  - L436–L450: separador del bloque "Alta de citas", docblock y firma de `crear`.
  - L451–L473: PASO 0: si el rol es paciente, exige ficha y sobrescribe `id_paciente` con el del token.
  - L474–L498: deduce `creado_por_paciente` del rol y valida campos obligatorios.
  - L499–L509: normaliza ids a entero, valida la fecha y normaliza la hora.
  - L510–L532: PASO 2: rechaza fechas pasadas y más allá del tope de anticipación.
  - L533–L552: PASO 3: verifica que paciente y profesional existan y estén activos.
  - L553–L581: PASO 4: busca el bloque del día y el que contiene la hora; hereda la duración.
  - L582–L612: PASO 5: detecta solapamiento con otras citas vivas y lanza 409.
  - L613–L635: PASO 6: impide que el paciente tenga otro turno superpuesto.
  - L636–L651: PASO 7: sanea motivo y notas; decide `creadoPorPaciente`.
  - L652–L681: INSERT con try/catch; traduce la violación UNIQUE (23000) a 409.
  - L682–L695: PASO 8: publica el aviso en vivo y devuelve la cita creada.
  - L696–L718: separador del bloque de cambios, docblock y firma de `cambiarEstado`.
  - L719–L730: carga la cita, normaliza y valida el estado destino.
  - L731–L739: verifica acceso a la cita y coherencia de la transición.
  - L740–L758: delega el cambio y publica el aviso en vivo correspondiente.
  - L759–L772: `cancelar`: atajo a `cambiarEstado` con 'cancelada'.
  - L773–L787: docblock y firma de `eliminar`.
  - L788–L802: `eliminar`: solo admin; borra y publica el aviso con los datos previos.
  - L803–L827: separador del bloque de aviso, docblock de `avisarEnVivo`.
  - L828–L841: `avisarEnVivo`: no-op si no hay realtime; try/catch que ignora fallos.
  - L842–L856: separador de helpers, docblock y `estadosValidos`.
  - L857–L868: docblock y firma de `validarFecha`.
  - L869–L884: `validarFecha`: formato YYYY-MM-DD y comprobación de fecha real.
  - L885–L895: docblock y firma de `validarHora`.
  - L896–L914: `validarHora`: acepta HH:MM y HH:MM:SS, valida rangos y normaliza.
  - L915–L923: docblock y firma de `aSegundos`.
  - L924–L932: `aSegundos`: hora a segundos desde medianoche.
  - L933–L939: docblock y firma de `deSegundos`.
  - L940–L945: `deSegundos`: segundos a texto HH:MM.
  - L946–L954: docblock y firma de `normalizarHora`.
  - L955–L958: `normalizarHora`: recorta a los primeros cinco caracteres (HH:MM).
  - L959–L969: docblock y firma de `yaPaso`.
  - L970–L979: `yaPaso`: solo aplica si la fecha es hoy y compara el instante del turno.
  - L980–L987: docblock y firma de `buscarBloqueQueContiene`.
  - L988–L1014: `buscarBloqueQueContiene`: exige inicio dentro y fin antes del cierre del bloque.
  - L1015–L1027: docblock y firma de `verificarAccesoACita`.
  - L1028–L1052: `verificarAccesoACita`: admin pasa; paciente y médico solo por dato propio.
  - L1053–L1077: docblock de `verificarTransicion` (reglas de estados y roles).
  - L1078–L1096: no-op si no cambia; estados finales y confirmación solo desde pendiente.
  - L1097–L1120: autorización por rol: admin pasa; paciente solo confirmar/cancelar.
  - L1121–L1137: autorización del médico (completar, ausente, cancelar) y cierre del método.
  - L1138–L1138: cierre de la clase.
- **Se conecta con:** `persistence/CitaRepository`, `persistence/DisponibilidadRepository`, `persistence/MedicoRepository`, `persistence/PacienteRepository`, `core/Validador` y, de forma opcional, `services/RealtimeService`.

---

## `services/DisponibilidadService.php` (465 líneas)

- **Rol:** Gestiona los bloques de atención que publica cada profesional: validación de días/horas/duración, autorización por rol, control de solapamientos y activación, edición o baja.
- **Recorrido línea por línea:**
  - L1–L17: cabecera y la decisión de negocio de que el profesional publica su agenda.
  - L18–L24: apertura de la clase y propiedades `$repo` y `$medicoRepo`.
  - L25–L35: docblock y constructor con inyección de dependencias.
  - L36–L54: docblock y `obtenerPorMedico` (verifica el médico y delega).
  - L55–L64: docblock y firma de `crear`.
  - L65–L102: PASO 1: valida día de la semana, horas, orden y rango de duración.
  - L103–L110: exige que el bloque deje al menos un turno completo.
  - L111–L127: PASO 2: resuelve el médico objetivo y verifica que exista y esté activo.
  - L128–L149: PASO 3: detecta bloques solapados del mismo día.
  - L150–L167: PASO 4: guarda el bloque (nacen activos) y lo devuelve.
  - L168–L190: docblock de `actualizar` (explica por qué no reutiliza `crear`).
  - L191–L212: carga el bloque, verifica acceso e impide cambiar de profesional.
  - L213–L258: valida formato solo de los campos que vienen (día, horas y duración).
  - L259–L264: verifica que siga quedando un turno completo.
  - L265–L288: solapamiento con otros bloques del mismo día, excluyendo el propio id.
  - L289–L307: filtra los cambios y ejecuta el update (o devuelve sin tocar la base).
  - L308–L322: docblock y firma de `cambiarActivo`.
  - L323–L337: `cambiarActivo`: carga, verifica acceso y aplica el cambio.
  - L338–L345: docblock y firma de `eliminar`.
  - L346–L360: `eliminar`: verifica existencia y exige rol admin.
  - L361–L374: docblock y firma de `resolverMedicoObjetivo`.
  - L375–L404: `resolverMedicoObjetivo`: admin elige; médico usa su id del token; otro rol 403.
  - L405–L412: docblock y firma de `verificarAccesoAMedico`.
  - L413–L427: `verificarAccesoAMedico`: admin pasa; médico solo su agenda; resto 403.
  - L428–L435: docblock y firma de `validarHora`.
  - L436–L451: `validarHora`: acepta HH:MM y HH:MM:SS, valida rangos y normaliza.
  - L452–L458: docblock y firma de `aSegundos`.
  - L459–L464: `aSegundos`: hora a segundos desde medianoche.
  - L465–L465: cierre de la clase.
- **Se conecta con:** `persistence/DisponibilidadRepository` y `persistence/MedicoRepository`.

---

## `services/EspecialidadService.php` (232 líneas)

- **Rol:** Reglas del catálogo de especialidades: normalización del nombre, detección de duplicados ignorando tildes/mayúsculas y bloqueo del borrado cuando hay profesionales asociados.
- **Recorrido línea por línea:**
  - L1–L28: cabecera y por qué existe el servicio (separación Router → Controller → Service → Repository).
  - L29–L40: apertura de la clase, repositorio y constructor.
  - L41–L54: separador "Consultas" y `listar`.
  - L55–L69: docblock y `obtenerPorId` (404 si no existe).
  - L70–L81: separador "Alta y modificación", docblock y firma de `crear`.
  - L82–L101: `crear`: normaliza, valida no vacío, verifica duplicados e inserta.
  - L102–L111: docblock y firma de `actualizar`.
  - L112–L130: `actualizar`: verifica existencia, normaliza, valida duplicados y actualiza.
  - L131–L147: separador "Baja", docblock y firma de `eliminar`.
  - L148–L161: `eliminar`: bloquea con 409 si hay médicos asociados.
  - L162–L177: separador "Reglas internas", docblock y firma de `normalizarNombre`.
  - L178–L185: `normalizarNombre`: trim, colapsa espacios y acota a 100 caracteres.
  - L186–L193: docblock y firma de `verificarDuplicado`.
  - L194–L208: `verificarDuplicado`: compara claves ignorando el id al editar.
  - L209–L219: docblock y firma de `claveComparacion`.
  - L220–L231: `claveComparacion`: minúsculas, sin tildes y con espacios colapsados.
  - L232–L232: cierre de la clase.
- **Se conecta con:** `persistence/EspecialidadRepository`.

---

## `services/EstadisticaService.php` (346 líneas)

- **Rol:** Calcula las métricas de la turnera bajo demanda por REST: demanda, ocupación, ausentismo y uso de la autogestión, sin duplicar estado en otra base.
- **Recorrido línea por línea:**
  - L1–L32: cabecera y la decisión de diseño de no usar tiempo real para el panel.
  - L33–L45: apertura de la clase y propiedades `$citaRepo`, `$medicoRepo` y `$disponibilidadRepo`.
  - L46–L60: docblock y constructor con inyección de dependencias.
  - L61–L75: docblock y firma de `generarInforme`.
  - L76–L86: rango por defecto (últimos 30 días) y normalización del orden.
  - L87–L104: resumen general, agrupaciones por día/especialidad/médico y total del rango.
  - L105–L117: promedio de turnos por día activo.
  - L118–L127: turnos ofrecidos (denominador de la ocupación).
  - L128–L136: tasa de cancelación anticipada en el rango.
  - L137–L144: uso de la autogestión online.
  - L145–L162: bloque `demanda` de la respuesta final.
  - L163–L172: bloque `ocupacion` (ofrecidos, reservados y porcentaje).
  - L173–L182: bloque `asistencia` (por estado, ausentismo y cancelaciones).
  - L183–L194: bloque `autogestion` y profesionales.
  - L195–L208: docblock y firma de `contarTurnosOfrecidos`.
  - L209–L232: filtra médicos activos (y por id si corresponde) y corta si no hay ninguno.
  - L233–L257: recorre día a día y cuenta los turnos que caben en cada bloque.
  - L258–L266: docblock y firma de `leerDisponibilidad`.
  - L267–L273: `leerDisponibilidad`: consulta directa al repositorio de disponibilidad.
  - L274–L282: docblock y firma de `contarPorEstadoEnRango`.
  - L283–L299: `contarPorEstadoEnRango`: reutiliza el filtro del repositorio.
  - L300–L307: docblock y firma de `contarReservaOnline`.
  - L308–L332: `contarReservaOnline`: total, consultorio (`creado_por_paciente = 0`) y paciente.
  - L333–L339: docblock y firma de `aSegundos`.
  - L340–L346: `aSegundos` y cierre de la clase.
- **Se conecta con:** `persistence/CitaRepository`, `persistence/MedicoRepository` y `persistence/DisponibilidadRepository`.

---

## `services/MedicoService.php` (249 líneas)

- **Rol:** CRUD de médicos con paginado: valida y sanea el nombre y filtra los campos permitidos al crear y actualizar; desvincula prescripciones al eliminar.
- **Recorrido línea por línea:**
  - L1–L19: cabecera explicativa de la capa y apertura de la clase con la propiedad `$repo`.
  - L20–L30: docblock y constructor con inyección del repositorio.
  - L31–L42: docblock y `obtenerTodos` (delegación directa).
  - L43–L56: docblock y firma de `obtenerPaginado`.
  - L57–L70: sanea búsqueda y especialidad con `Validador::textoLimpio`.
  - L71–L98: normaliza página/por página, calcula total y páginas y devuelve el paginado.
  - L99–L109: docblock y firma de `obtenerPorId`.
  - L110–L121: `obtenerPorId`: 404 si el repositorio no devuelve datos.
  - L122–L131: docblock y firma de `crear`.
  - L132–L159: valida nombre obligatorio y acota matrícula y especialidad con `Validador`.
  - L160–L173: inserta el médico (activo por defecto) y lo devuelve completo.
  - L174–L186: docblock y firma de `actualizar`.
  - L187–L213: verifica existencia y filtra solo los campos permitidos.
  - L214–L228: exige al menos un campo y delega el update.
  - L229–L238: docblock y firma de `eliminar`.
  - L239–L249: `eliminar`: verifica, desvincula prescripciones y borra; cierre de la clase.
- **Se conecta con:** `persistence/MedicoRepository` y `core/Validador`.

---

## `services/NotificacionService.php` (758 líneas)

- **Rol:** Recordatorios de citas bajo el patrón outbox: genera notificaciones pendientes, las procesa por un proveedor con reintentos y resuelve las acciones públicas por token (confirmar/cancelar).
- **Recorrido línea por línea:**
  - L1–L31: cabecera, el problema del login para confirmar y la arquitectura del envío.
  - L32–L51: apertura de la clase, repositorios, proveedor, canal por defecto y `DIAS_VIGENCIA_TOKEN`.
  - L52–L73: docblock y constructor con inyección de las dependencias.
  - L74–L100: separador "Generación", docblock y firma de `generarRecordatorios`.
  - L101–L116: acota la ventana, resuelve el canal y arma los filtros de citas vivas.
  - L117–L141: alcance por rol (médico solo su agenda; otro rol 403) y consulta de citas.
  - L142–L162: dobles barreras de idempotencia (`recordatorio_enviado` y notificación previa).
  - L163–L185: resuelve el contacto; si no tiene cuenta, registra 'fallido' con motivo.
  - L186–L224: genera token, crea el pendiente con `crearSiNoExiste` y acumula el detalle.
  - L225–L233: devuelve generados, omitidos, canal, ventana y detalle.
  - L234–L245: docblock y firma de `procesarPendientes`.
  - L246–L274: acota el límite, toma pendientes FIFO y prepara el resultado por notificación.
  - L275–L294: recupera la cita; si no existe, la marca fallida y arma el mensaje.
  - L295–L318: envía con try/catch; marca enviada/fallida y actualiza la cita.
  - L319–L335: acumula el detalle real y devuelve los contadores.
  - L336–L344: docblock y firma de `enviarRecordatorios`.
  - L345–L353: `enviarRecordatorios`: genera y procesa en un paso.
  - L354–L371: separador "Confirmación/cancelación", docblock y firma de `resolverPorToken`.
  - L372–L395: valida el formato del token, lo busca y verifica su vigencia (410 si venció).
  - L396–L427: carga la cita y devuelve la vista filtrada (público, sin datos clínicos).
  - L428–L441: docblock y firma de `confirmarPorToken`.
  - L442–L467: resuelve el token y controla estados atendido/cancelado/confirmado.
  - L468–L478: confirma sobre la tabla citas y devuelve la vista filtrada.
  - L479–L488: docblock y firma de `cancelarPorToken`.
  - L489–L509: `cancelarPorToken`: idempotente, no permite cancelar turnos atendidos.
  - L510–L522: separador "Listado y reintentos", docblock y firma de `obtenerPaginadas`.
  - L523–L535: normaliza parámetros y valida estado y tipo contra listas blancas.
  - L536–L562: acotado por rol (no admin solo ve lo suyo; id inválido → vacío).
  - L563–L577: paginación y retorno.
  - L578–L591: docblock y firma de `reintentar`.
  - L592–L611: carga la notificación, verifica pertenencia y rechaza las ya enviadas.
  - L612–L636: cuenta el intento, recupera la cita y reintenta el envío.
  - L637–L660: separador de helpers, docblock y firma de `verificarPertenencia`.
  - L661–L696: `verificarPertenencia`: admin audita; paciente y médico se autorizan por vínculo.
  - L697–L710: docblock y firma de `armarMensaje`.
  - L711–L740: `armarMensaje`: arma fecha/hora legibles, cuerpo y `datos_extra` con el token.
  - L741–L751: docblock y firma de `generarToken`.
  - L752–L758: `generarToken`: `bin2hex(random_bytes(32))` y cierre de la clase.
- **Se conecta con:** `persistence/NotificacionRepository`, `persistence/CitaRepository`, `persistence/ContactoRepository` y `services/Notificacion/ProveedorNotificacionesInterface`.

---

## `services/PacienteService.php` (242 líneas)

- **Rol:** CRUD de pacientes con paginado: valida y sanea el nombre, controla la unicidad del DNI y filtra los campos permitidos.
- **Recorrido línea por línea:**
  - L1–L23: cabecera, apertura de la clase, repositorio y constructor.
  - L24–L32: docblock y `obtenerTodos`.
  - L33–L43: docblock y firma de `obtenerPaginado`.
  - L44–L75: `obtenerPaginado`: sanea, normaliza, calcula páginas y devuelve el paginado.
  - L76–L95: docblock y `obtenerPorId` (404 si no existe).
  - L96–L106: docblock y `obtenerPorDni` (trim y delegación).
  - L107–L116: docblock y firma de `crear`.
  - L117–L143: valida nombre, sanea/acota el DNI y controla su unicidad.
  - L144–L157: inserta (activo por defecto, obra social 1) y devuelve el paciente.
  - L158–L168: docblock y firma de `actualizar`.
  - L169–L213: verifica existencia y arma `$limpios` con los campos válidos.
  - L214–L228: exige al menos un campo y delega el update.
  - L229–L236: docblock y firma de `eliminar`.
  - L237–L242: `eliminar` y cierre de la clase.
- **Se conecta con:** `persistence/PacienteRepository` y `core/Validador`.

---

## `services/PrescripcionService.php` (290 líneas)

- **Rol:** CRUD de prescripciones con paginado y cambio de estado: normaliza medicamentos (array/JSON), sanea indicaciones y valida estados contra whitelist.
- **Recorrido línea por línea:**
  - L1–L21: cabecera, apertura de la clase, repositorio y constructor.
  - L22–L31: docblock y `obtenerTodas`.
  - L32–L43: docblock y firma de `obtenerPaginadas`.
  - L44–L77: `obtenerPaginadas`: sanea, normaliza, calcula páginas y devuelve el paginado.
  - L78–L88: docblock y `obtenerPorPaciente`.
  - L89–L108: docblock y `obtenerPorId` (404 si no existe).
  - L109–L118: docblock y firma de `crear`.
  - L119–L149: exige paciente y medicamentos; valida array/JSON y el máximo de 50.
  - L150–L174: sanea indicaciones, crea con estado 'activa' y devuelve la prescripción.
  - L175–L185: docblock y firma de `actualizar`.
  - L186–L216: normaliza medicamentos y sanea indicaciones.
  - L217–L235: valida el estado contra whitelist y acepta la fecha de vencimiento.
  - L236–L250: exige al menos un campo y delega el update.
  - L251–L258: docblock y firma de `eliminar`.
  - L259–L263: `eliminar`: verifica existencia y borra.
  - L264–L273: docblock y firma de `cambiarEstado`.
  - L274–L289: `cambiarEstado`: verifica, valida el estado y delega.
  - L290–L290: cierre de la clase.
- **Se conecta con:** `persistence/PrescripcionRepository` y `core/Validador`.

---

## `services/RealtimeService.php` (405 líneas)

- **Rol:** Lógica del tiempo real (SSE): decide cuándo publicar un evento, a qué canales mandarlo y quién puede suscribirse; el evento es una puntuación, no una copia del estado.
- **Recorrido línea por línea:**
  - L1–L42: cabecera, las dos decisiones del módulo y la regla del antipatrón.
  - L43–L66: apertura de la clase, repositorio, `RETENCION_MINUTOS`, `PURGA_CADA` y contador.
  - L67–L75: docblock y constructor con inyección del repositorio.
  - L76–L90: separador "Emisión", docblock y firma de `notificarCita`.
  - L91–L113: publica en cada canal el payload con solo identificadores.
  - L114–L130: cuenta la publicación y ejecuta la purga oportunista cada 25 (con try/catch).
  - L131–L138: docblock y firma de `canalesDeCita`.
  - L139–L164: `canalesDeCita`: tablero global + agenda del médico + turnos del paciente.
  - L165–L188: separador "Autorización", docblock y firma de `resolverCanal`.
  - L189–L208: normaliza el canal y autoriza 'tablero' (admin y médicos).
  - L209–L227: autoriza 'mis-turnos' con el id del token; sin ficha lanza 403.
  - L228–L242: autoriza 'mi-agenda' con el id del token y valida el rol.
  - L243–L266: canal explícito `agenda:<id>`, solo admin; canal desconocido 422.
  - L267–L277: separador "Nombres de canales", comentario y constante `CANAL_TABLERO`.
  - L278–L297: `canalAgenda` y `canalTurnos` (nombre con prefijo e id).
  - L298–L311: docblock y `tiposValidos`.
  - L312–L337: separador "Lectura para el stream", comentario y `leerEventos`.
  - L338–L348: docblock y `ultimoIdDelCanal`.
  - L349–L379: separador "Diagnóstico", docblock y `purgarAhora`.
  - L380–L386: docblock de `diagnostico`.
  - L387–L404: `diagnostico`: canales públicos, tipos, retención y frecuencia de purga.
  - L405–L405: cierre de la clase.
- **Se conecta con:** `persistence/RealtimeRepositoryInterface` y, como emisor, `services/CitaService`.

---

## `services/UsuarioService.php` (301 líneas)

- **Rol:** Gestión de roles (RBAC): lista usuarios enriquecidos y cambia el rol aplicando las reglas de no quedar sin administradores y de no degradarse a sí mismo.
- **Recorrido línea por línea:**
  - L1–L43: cabecera, qué resuelve el servicio y las reglas del módulo.
  - L44–L56: apertura de la clase y constante `ROLES`.
  - L57–L64: constante `ROL_ADMIN` y propiedad `$repo`.
  - L65–L72: docblock y constructor con inyección del repositorio.
  - L73–L91: separador "Consultas", docblock y firma de `listar`.
  - L92–L102: valida el filtro de rol contra `ROLES` y pide el paginado.
  - L103–L122: enriquece cada ítem con `enriquecer()` antes de devolver.
  - L123–L137: docblock y `listarRoles`.
  - L138–L155: separador "Cambio de rol", docblock y firma de `cambiarRol`.
  - L156–L169: valida que el rol destino exista (422).
  - L170–L187: verifica que el usuario exista (404) y corta si el rol no cambia.
  - L188–L201: impide que nadie se degrade a sí mismo (409).
  - L202–L219: impide degradar al último administrador activo (409).
  - L220–L236: escribe el nuevo rol y relee el usuario actualizado.
  - L237–L256: separador "Reglas internas", docblock y firma de `enriquecer`.
  - L257–L278: `enriquecer`: calcula `vinculado` y `nota_vinculacion`.
  - L279–L288: docblock y firma de `notaVinculacion`.
  - L289–L301: `notaVinculacion`: texto por rol y cierre de la clase.
- **Se conecta con:** `persistence/UsuarioRepositoryInterface`.

---

## `services/Notificacion/ProveedorNotificacionesInterface.php` (48 líneas)

- **Rol:** Contrato para cualquier proveedor de notificaciones (email, WhatsApp, sistema), de modo que el servicio de negocio no dependa del canal concreto.
- **Recorrido línea por línea:**
  - L1–L25: cabecera, por qué existe la interfaz y el criterio de contratos en la frontera.
  - L26–L27: apertura de la interfaz.
  - L28–L43: docblock del método `enviar` (forma de `$datos` y de la respuesta `ok`/`detalle`).
  - L44–L47: comentario sobre la ausencia de cuerpo y declaración `public function enviar(array $datos): array;`.
  - L48–L48: cierre de la interfaz.
- **Se conecta con:** `services/NotificacionService` (que la recibe por constructor) y la implementación concreta (`ProveedorNotificacionesStub`).

---

## `services/Notificacion/ProveedorNotificacionesStub.php` (59 líneas)

- **Rol:** Implementación de desarrollo que no envía nada: simula el envío y responde éxito para poder probar el circuito completo de recordatorios sin proveedor real.
- **Recorrido línea por línea:**
  - L1–L29: cabecera: qué hace, por qué existe y qué NO hace.
  - L30–L39: cómo pasar a producción y declaración de la clase que implementa la interfaz.
  - L40–L47: docblock y firma de `enviar`.
  - L48–L58: cuerpo de `enviar`: devuelve `ok => true` y un detalle que delata la simulación.
  - L59–L59: cierre de la clase.
- **Se conecta con:** `services/Notificacion/ProveedorNotificacionesInterface` y `services/NotificacionService`; se reemplaza desde `core/bootstrap.php`.
