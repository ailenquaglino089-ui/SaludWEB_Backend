# Guía de código — Backend · Pruebas de la raíz

> Recorrido línea por línea de los scripts de prueba/verificación.
> Índice general en ../GUIA_DE_CODIGO.md

## `probar_roles.php` (420 líneas)
- **Rol:** Prueba el módulo de roles sin HTTP: usa un `RepoFalso` en memoria para las reglas de negocio y toca SQLite solo para comprobar una escritura real, que después revierte.
- **Recorrido línea por línea:**
  - L1–L16: apertura `<?php`, comentario de cabecera con el listado de lo que comprueba (usuarios, roles válidos, último admin, autodegradación, escritura real y su deshacer) y `require` del bootstrap.
  - L17–L28: sección 1; `echo` del título, consulta de usuarios ordenados por id con `LIMIT 12` y `foreach` que imprime cada fila con `printf`.
  - L29–L38: cuenta de admins activos con `COUNT(*)`; si no hay ninguno, avisa y corta con `exit(1)`.
  - L39–L45: separador y comentario que explica por qué hay un doble de repositorio en memoria.
  - L46–L86: `class RepoFalso implements UsuarioRepositoryInterface`: propiedades `$usuarios`/`$escrituras`, constructor y métodos `obtenerPaginado`, `obtenerPorId`, `actualizarRol` y `contarActivosConRol`.
  - L87–L88: línea en blanco y arranque del contador `$fallos = 0`.
  - L89–L111: docblock y función `comprobar()` que ejecuta la comprobación y escribe `OK`/`FALLA` sumando a `$fallos`.
  - L112–L116: separador y `echo` de la sección 2.
  - L117–L123: crea el primer `RepoFalso` (un médico y dos admins) y el `UsuarioService`.
  - L124–L134: `foreach` sobre los tres roles válidos llamando a `comprobar()` con `cambiarRol`.
  - L135–L143: comprueba que un rol inexistente (`superusuario`) se rechaza con 422.
  - L144–L152: comprueba que un rol vacío se rechaza con 422.
  - L153–L157: separador y `echo` de la sección 3.
  - L158–L163: segundo `RepoFalso` con dos admins y su servicio.
  - L164–L172: comprueba que con 2 admins se puede degradar a uno.
  - L173–L179: nuevo `RepoFalso`, servicio y degradación previa para dejar un único admin.
  - L180–L188: comprueba que degradar al último admin se bloquea con 409.
  - L189–L197: comprueba que promover a otro admin sigue permitido.
  - L198–L208: separador sección 4, `echo`, `RepoFalso` y servicio.
  - L209–L217: comprueba que un admin no puede degradarse a sí mismo (409).
  - L218–L226: comprueba que promoverse sin cambio real no dispara error.
  - L227–L235: comprueba que un admin sí puede degradar a otro.
  - L236–L244: separador sección 5, `echo`, `RepoFalso` y servicio.
  - L245–L253: comprueba que un id inexistente responde 404.
  - L254–L261: separador sección 6, `echo` y creación de `UsuarioRepository`/`UsuarioService` reales.
  - L262–L283: comentario largo que explica el cambio a cuentas descartables y define `$emailDescartable`.
  - L284–L298: prepara y ejecuta el `INSERT` de la cuenta descartable (con `password_hash`) y guarda `$idCandidato`.
  - L299–L308: busca otro admin distinto del candidato y define `$idAdminQueActua`.
  - L309–L320: si no hay otro admin avisa; si lo hay, comprueba que el listado paginado responde.
  - L321–L341: abre el `try` y comprueba la escritura real del cambio de rol y que quedó persistido.
  - L342–L363: comentario sobre la protección del último admin, búsqueda de `$ultimaAdmin` y `$idUltimaAdmin`.
  - L364–L383: comprueba que degradar a la última admin da 409 y que sigue siendo admin.
  - L384–L396: `catch` que registra la excepción y `finally` que borra siempre la cuenta descartable.
  - L397–L413: comprueba que la cuenta descartable ya no está; el `else` exterior borra la cuenta aunque la prueba se haya omitido.
  - L414–L420: cierre, línea separadora, resumen final y `exit` según `$fallos`.
- **Se conecta con:** `core/bootstrap.php`, `services/UsuarioService.php`, `persistence/UsuarioRepository.php` y `persistence/UsuarioRepositoryInterface.php` (implementada por `RepoFalso`).

---

## `probar_roles_http.php` (386 líneas)
- **Rol:** Prueba los endpoints HTTP del módulo de roles contra la API real: declara rutas, verifica 401/403 y comprueba que el controlador traduzca excepciones a códigos HTTP.
- **Recorrido línea por línea:**
  - L1–L9: apertura, comentario de cabecera y `require` del bootstrap.
  - L10–L23: comentario sobre `API_BASE`, definición de `$base` y credenciales de admin/médico tomadas del entorno, e inicio de `$fallos`.
  - L24–L68: docblock y función `pedir()` que hace la petición con cURL y devuelve `status`, `body` y `json`.
  - L69–L102: función `comprobar()` que compara el HTTP esperado, corre un validador extra opcional y reporta OK/FALLA.
  - L103–L124: comentario largo sobre el cambio a sesiones descartables y `echo` de la sección 0.
  - L125–L132: sello de tiempo, emails/claves descartables y arranque de `$usuariosParaBorrar`.
  - L133–L151: docblock y función `crearCuentaDescartable()` que inserta una cuenta y devuelve su id.
  - L152–L167: docblock y función `borrarCuentasDescartables()` que borra por `LIKE 'descartable.%'`.
  - L168–L182: carga `db.php`, crea las cuentas admin/médico, registra el `shutdown` de limpieza e imprime los ids.
  - L183–L196: comentario y login real del admin por la API; si falla, corta con `exit(2)`.
  - L197–L208: comentario sobre la forma plana de la respuesta y extracción de `$tokenAdmin` e `$idAdminSesion`.
  - L209–L219: login del médico y obtención de `$tokenMedico` (con aviso si falla).
  - L220–L230: separador sección 1 y comprobaciones sin token (401) sobre usuarios, roles y patch.
  - L231–L244: separador sección 2 y comprobaciones de 403 para el médico (o aviso si no hay sesión).
  - L245–L264: separador sección 3 y comprobación de `GET /api/usuarios/roles` con validador de roles presentes.
  - L265–L302: comprobaciones de listado paginado, filtro inválido (422), búsqueda y acotado de `por_pagina`.
  - L303–L328: separador sección 4 y reglas de negocio por HTTP (404, 422, 409, 400).
  - L329–L344: separador sección 5, comentario del objetivo descartable, su creación e impresión del id.
  - L345–L374: comprueba promover, que se vea en el listado, revertir y dejarlo igual.
  - L375–L386: comprueba que la cuenta con la que se inició sesión no se autodegrada, más resumen y `exit`.
- **Se conecta con:** `core/bootstrap.php`, `db.php`, la API vía `/api/auth/login` y `/api/usuarios*`, y `persistence/UsuarioRepository.php`.

---

## `probar_turnera.php` (478 líneas)
- **Rol:** Prueba de humo de extremo a extremo de la turnera contra una API levantada; crea un usuario, una ficha y una cita, y los borra al terminar.
- **Recorrido línea por línea:**
  - L1–L44: cabecera completa (qué hace, cómo se usa, qué prueba), definición de `$base` por argumento y `require` de `db.php`.
  - L45–L51: contadores `$ok`/`$fallos` y array `$creado` para la limpieza.
  - L52–L101: docblock y función `pedir()` con cURL que devuelve `[código, respuesta decodificada]`.
  - L102–L124: docblock y función `paso()` que imprime `[OK]`/`[FALLA]` y suma al contador.
  - L125–L128: `echo` de arranque con la URL de la API.
  - L129–L161: docblock y función `limpiar()` (lee `$creado` global) y su registro con `register_shutdown_function`.
  - L162–L171: genera email/password únicos por corrida y define `$emailFicticio`.
  - L172–L179: separador sección 1, `echo` y prueba de `GET /api/especialidades`.
  - L180–L199: prueba de `GET /api/medicos`, búsqueda del primer médico activo, corte si no hay y asignación de `$idMedico`.
  - L200–L208: separador sección 2, `echo` y prueba de `GET /api/disponibilidades`.
  - L209–L237: búsqueda del próximo día con agenda (incluyendo el caso de hoy ya terminado) y corte si no hay bloques.
  - L238–L262: prueba de `GET /api/citas/disponibilidad`, verificación de duración mínima de turnos y búsqueda de un turno libre.
  - L263–L286: separador sección 3, registro, login y anotación del usuario creado.
  - L287–L311: separador sección 4; un paciente sin ficha no reserva (403) ni ve citas ajenas.
  - L312–L363: separador sección 5; vinculación con DNI inexistente (422) y con ficha temporal creada por la prueba.
  - L364–L405: separador sección 6; reserva, choque de doble reserva (409) y verificación de que el slot figura ocupado.
  - L406–L434: separador sección 7; el paciente no puede completar, sí confirmar y se rechaza un estado inválido.
  - L435–L463: separador sección 8; envío de JSON truncado y verificación de que da 400 y no 500.
  - L464–L478: resumen de correctos/fallidos y `exit` según `$fallos`.
- **Se conecta con:** `db.php` (fixtures) y la API vía `/api/especialidades`, `/api/medicos`, `/api/disponibilidades`, `/api/citas`, `/api/auth/*`.

---

## `probar_vinculacion.php` (109 líneas)
- **Rol:** Congela el comportamiento de la regla `coincidenNombres()` que decide si una cuenta puede vincularse a una ficha, con listas de casos buenos y malos.
- **Recorrido línea por línea:**
  - L1–L29: docblock que explica el motivo del script y `require` de `Config`, `JwtService`, `db.php` y `AuthService`.
  - L30–L40: crea un `JwtService` de prueba, instancia `AuthService` y accede a `coincidenNombres` por reflexión.
  - L41–L56: docblock y array `$casosBuenos` con los casos que deben aceptarse.
  - L57–L69: docblock y array `$casosMalos` con los casos que deben rechazarse.
  - L70–L84: inicializa `$fallos` y recorre `$casosBuenos` invocando el método por reflexión.
  - L85–L95: recorre `$casosMalos` comprobando que la comparación devuelva `false`.
  - L96–L109: resumen de casos y fallos, y `exit` según `$fallos`.
- **Se conecta con:** `services/AuthService.php` (método privado `coincidenNombres`), `core/JwtService.php`, `core/Config.php` y `db.php`.

---

## `probar_tiempo_real.php` (589 líneas)
- **Rol:** Verificación del módulo de tiempo real (SSE): prueba las piezas (tabla, repositorio, servicio, seguridad) y, en el navegador, mide la latencia de punta a punta.
- **Recorrido línea por línea:**
  - L1–L58: cabecera, nota sobre tiempo de ejecución, `ini_set`/`error_reporting`, `header` HTML y `require` del bootstrap.
  - L59–L98: separador de utilidades, función `seccion()` y función `prueba()` que pinta cada resultado.
  - L99–L120: contadores `$total`/`$fallas`, docblock y función `contar()`.
  - L121–L143: separador parte 1, título, sección 1 y comprobación de que exista la tabla `eventos_realtime`.
  - L144–L160: sección 1.2; verifica que exista el índice `idx_eventos_canal_id` listando el `PRAGMA index_list`.
  - L161–L188: sección 1.3; limpia el canal de prueba y publica un evento con el repositorio.
  - L189–L213: sección 1.4/1.5; prueba el cursor (`obtenerDesde`) y que el payload JSON sobreviva el viaje.
  - L214–L261: sección 2; array `$casos` de autorización de canales y recorrido comprobando el canal resuelto.
  - L262–L299: array `$rechazos` de accesos que deben negarse y recorrido que espera excepción.
  - L300–L347: sección 3; inserta un evento viejo y uno reciente, purga y comprueba que baje el conteo.
  - L348–L366: cabecera parte 2, detección de navegador y mensaje de omisión por consola.
  - L367–L419: rama navegador; arma `$baseApi`, obtiene un token por login y define `$urlCanal`.
  - L420–L470: `curl_multi_init`, `$corte`, acumulador `$recibido` y `CURLOPT_WRITEFUNCTION` que detecta "conectado".
  - L471–L491: espera la apertura del canal y comprueba que se reciba el evento de conexión.
  - L492–L555: mide la latencia desde la publicación hasta la llegada del evento y valida el umbral de la guía.
  - L556–L564: cierra handles y llaves de las ramas navegador/consola.
  - L565–L589: resumen final con totales y nota sobre la limpieza de la tabla.
- **Se conecta con:** `core/bootstrap.php`, `RealtimeService`/`realtimeRepo`, `db.php` (tabla `eventos_realtime`) y la API vía `/api/auth/login` y `/api/eventos`.

---

## `verificar_turnera.php` (106 líneas)
- **Rol:** Herramienta de desarrollo que informa el estado de las tablas y datos de la turnera; devuelve 1 si falta algo. No modifica nada.
- **Recorrido línea por línea:**
  - L1–L21: docblock que explica el propósito y `require` de `db.php`.
  - L22–L30: contador `$problemas`, lista `$tablasTurnera` y `echo` de cabecera.
  - L31–L51: consulta a `sqlite_master` para listar tablas y `foreach` que informa OK con conteo o FALTA.
  - L52–L69: sección datos de apoyo; cuenta médicos activos y bloques publicados, con avisos si son cero.
  - L70–L94: cuenta pacientes activos y muestra los índices de `citas` con `PRAGMA index_list`/`index_info`.
  - L95–L106: resumen final y `exit` según `$problemas`.
- **Se conecta con:** `db.php` (solo consulta: `$pdo`).

---
