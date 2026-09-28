<?php
// ============================================================
// API REST del Backend - Programación IV
// ============================================================
// Este backend expone SOLO endpoints JSON, completamente desacoplado
// del frontend. El frontend (React/Vue/Mobile) consume estos endpoints.
//
// ARQUITECTURA PROFESIONAL:
// - Backend: PHP + API REST + JSON + Autenticación JWT
// - Frontend: React SPA (consumidor de API)
// - Mobile: App nativa (consumidor de API)
// ============================================================
// MÓDULO: "Autenticación en Aplicaciones Modernas"
// Clasificación de rutas:
//   • PÚBLICAS   → no requieren token (health, registro, login, catálogos)
//   • PROTEGIDAS → requieren Authorization: Bearer <jwt> (401 si falla)
//   • ADMIN      → además exigen rol admin (403 si no tiene permiso)
// ============================================================

// ============================================================
// CONFIGURACIÓN DE HEADERS (CORS + SEGURIDAD)
// Módulo: "Seguridad Básica para APIs"
// ------------------------------------------------------------
// CORS con WHITELIST explícita de orígenes (nunca "*" + credentials).
// Los navegadores bloquean automáticamente orígenes no permitidos.
// ============================================================

// Lista blanca de orígenes permitidos (configurable por CORS_ORIGINS)
// getenv() lee la variable de entorno CORS_ORIGINS; si no existe (?:) usa los valores de desarrollo por defecto
// explode(',', ...) convierte el string "a,b,c" en un arreglo ['a','b','c']
// array_map('trim', ...) aplica trim() a cada elemento para quitar espacios vacíos que puedan quedar
// array_filter() descarta elementos vacíos (por ejemplo un "http://..." seguido de coma al final)
// array_values() re-indexa el arreglo para que queden índices numéricos seguidos (0,1,2...)
$corsOrigenes = array_values(array_filter(array_map(
    'trim',
    explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173,http://localhost,http://127.0.0.1')
)));

// Si la petición trae un Origin, solo respondemos si está en la whitelist
// HTTP_ORIGIN es el encabezado que envía el navegador indicando desde qué sitio se hace la llamada
// El operador ?? (null coalescing) pone '' si la petición NO trae encabezado Origin (ej: Postman, curl)
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
// Solo se envían cabeceras CORS si hay Origin y además ese Origin está en la lista blanca
// in_array($origen, $corsOrigenes, true): $origen debe coincidir EXACTAMENTE (tercer parámetro true = comparación estricta)
if ($origen !== '' && in_array($origen, $corsOrigenes, true)) {
    // Devuelve al navegador el origen permitido (se refleja el mismo origin, nunca "*" con credentials)
    header("Access-Control-Allow-Origin: $origen");
    // Permite que la petición incluya cookies/credenciales (sesión BORRAR), solo válido con whitelist
    header('Access-Control-Allow-Credentials: true');
    // Declara qué métodos HTTP acepta el servidor para esta ruta cruzada
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    // Declara qué encabezados puede enviar el cliente: Content-Type (JSON) y Authorization (token JWT)
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    // Avisa a las cachés que la respuesta varía según el Origin, para no mezclar respuestas de orígenes distintos
    header('Vary: Origin');
}

// Headers de seguridad a nivel de protocolo/navegador
// Evita que el navegador interprete el archivo con otro tipo MIME (MIME sniffing)
header('X-Content-Type-Options: nosniff');
// Impide que la API sea embebida en un <iframe> (protege contra clickjacking)
header('X-Frame-Options: DENY');
// No envía la URL de origen (referrer) a sitios externos al navegar
header('Referrer-Policy: no-referrer');
// Activa el filtro anti-XSS del navegador en modo bloqueo (defensa en profundidad)
header('X-XSS-Protection: 1; mode=block');

// Establece que toda respuesta de esta API es JSON con codificación UTF-8
header('Content-Type: application/json; charset=utf-8');

// Preflight: los navegadores envían OPTIONS antes de peticiones cruzadas.
// Se responde 204 sin procesar la ruta.
// Si el método de la petición es OPTIONS significa que es una verificación previa del navegador (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // 204 = No Content: responde OK al preflight sin devolver cuerpo de datos
    http_response_code(204);
    // Detiene la ejecución del script; no se procesa ninguna ruta
    exit;
}

// Carga el archivo de arranque (bootstrap) que prepara:
// - Sesión, conexión DB, Router, Repositorio, Servicio, Controlador
// bootstrap.php crea las variables: $router, $medicoRepo, $medicoService,
// $pdo, $authMiddleware, $jwtService, $authService
require_once __DIR__ . '/core/bootstrap.php';

// ============================================================
// RUTAS PÚBLICAS
// ============================================================

// GET /api/health - Health check (pública)
// Registra una ruta GET en el Router; la función anónima (closure) es el manejador que se ejecuta cuando llega la petición
$router->get('/api/health', function () {
    // Response::ok() arma y devuelve una respuesta JSON con http 200 y la estructura estándar {data, message}
    Response::ok([
        'status' => 'ok',
        'message' => 'API funcionando',
        // date('c') = fecha/hora actual en formato ISO-8601 (ej: 2026-09-13T10:30:00-03:00)
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'auth' => 'JWT active',
    ], 'API SaludWEB - Programación IV');
});

// GET /api/obras-sociales - Catálogo público (para formularios)
// use ($obraSocialRepo) inyecta la variable del repositorio dentro de la función anónima
$router->get('/api/obras-sociales', function () use ($obraSocialRepo) {
    // try/catch: intenta ejecutar la consulta y si lanza una excepción responde 500 en vez de mostrar el error crudo
    try {
        // Obtiene todas las obras sociales desde la base de datos y las devuelve como JSON
        Response::ok($obraSocialRepo->obtenerTodas());
    } catch (Exception $e) {
        // Si falla la consulta, responde un error genérico 500 (Internal Server Error) sin exponer detalles internos
        Response::error('Error interno del servidor', 500);
    }
});

// GET /api/medicos - Catálogo público de médicos (listado JSON)
$router->get('/api/medicos', function () use ($medicoService) {
    // Crea el controlador pasándole el servicio (pila: Controlador -> Servicio -> Repositorio)
    $controller = new MedicoController($medicoService);
    // index() lista todos los médicos y responde JSON
    $controller->index();
});

// GET /api/medicos/{id} - Detalle público de un médico
// El Router captura el segmento {id} de la URL y lo pasa como parámetro $id a la función
$router->get('/api/medicos/{id}', function ($id) use ($medicoService) {
    // Instancia el controlador con el servicio de médicos
    $controller = new MedicoController($medicoService);
    // (int) $id castea el string de la URL a número entero; show() devuelve el detalle del médico
    $controller->show((int) $id);
});

// GET /api/pacientes - Lista pública de pacientes
$router->get('/api/pacientes', function () use ($pacienteService) {
    try {
        // Crea el controlador con el servicio de pacientes
        $controller = new PacienteController($pacienteService);
        // index() lista todos los pacientes
        $controller->index();
    } catch (Exception $e) {
        // Ante cualquier excepción se responde con código 500 (error interno)
        http_response_code(500);
        // Serializa el mensaje de error a JSON con JSON_UNESCAPED_UNICODE (conserva tildes y ñ)
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// GET /api/pacientes/{id} - Detalle público de un paciente
$router->get('/api/pacientes/{id}', function ($id) use ($pacienteService) {
    try {
        // Crea el controlador con el servicio de pacientes
        $controller = new PacienteController($pacienteService);
        // show() devuelve el paciente cuyo id coincide con el de la URL
        $controller->show((int) $id);
    } catch (Exception $e) {
        // Si algo falla, responde 500 con el detalle en JSON
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// ============================================================
// AUTENTICACIÓN (pública: login y registro)
// ============================================================

// POST /api/auth/registro - Registrar nuevo usuario (pública)
// use ($authService, $rateLimiter): inyecta el servicio de autenticación y el limitador de peticiones
$router->post('/api/auth/registro', function () use ($authService, $rateLimiter) {
    try {
        // Instancia el controlador de autenticación con sus dependencias (servicio + rate limiter)
        $controller = new AuthController($authService, $rateLimiter);
        // registro() crea el usuario nuevo: valida datos, hashea la contraseña y lo guarda
        $controller->registro();
    } catch (Exception $e) {
        // Ante un error inesperado se responde 500 y se envía el detalle en JSON
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// POST /api/auth/login - Autenticación de usuario (pública)
// Devuelve un JWT firmado que el cliente debe usar en "Bearer <token>"
$router->post('/api/auth/login', function () use ($authService, $rateLimiter) {
    try {
        // Crea el controlador de autenticación
        $controller = new AuthController($authService, $rateLimiter);
        // login() valida credenciales y, si son correctas, emite el token JWT
        $controller->login();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// POST /api/auth/sso - Login con SSO (Google / Microsoft) (pública)
// El cliente envía { provider, id_token }; el backend valida la firma
// contra el proveedor y devuelve el JWT de SaludWEB (igual que /auth/login)
$router->post('/api/auth/sso', function () use ($authService, $rateLimiter) {
    try {
        // Crea el controlador de autenticación (mismo servicio que el login)
        $controller = new AuthController($authService, $rateLimiter);
        // sso() valida el id_token del proveedor y emite el token JWT propio
        $controller->sso();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// ============================================================
// RUTAS PROTEGIDAS (requieren JWT)
// ============================================================

// GET /api/auth/me - Datos del usuario autenticado (protegida)
$router->get('/api/auth/me', function () use ($authService, $authMiddleware, $rateLimiter) {
    $authMiddleware->verificarToken();   // 401 si no hay token válido
    // Si pasó la verificación, se instancia el controlador para responder con el usuario actual
    $controller = new AuthController($authService, $rateLimiter);
    // obtenerUsuarioActual() lee el payload del JWT y devuelve los datos del usuario logueado
    $controller->obtenerUsuarioActual();
});

// POST /api/auth/vincular - Vincular la cuenta con la ficha (protegida)
// Módulo Turnera: sin este vínculo el usuario no puede reservar, porque la API
// necesita saber a QUIÉN se le asigna el turno.
// Body: { "tipo": "paciente", "documento": "30111222" }  (DNI)
//       { "tipo": "medico",   "documento": "44556" }    (matrícula)
$router->post('/api/auth/vincular', function () use ($authService, $authMiddleware, $rateLimiter) {
    $authMiddleware->verificarToken();
    $controller = new AuthController($authService, $rateLimiter);
    $controller->vincular();
});

// POST /api/auth/logout - Cerrar sesión (protegida)
$router->post('/api/auth/logout', function () use ($authService, $authMiddleware, $rateLimiter) {
    // verificarToken() valida el JWT recibido en el header Authorization; si falta/expiró responde 401
    $authMiddleware->verificarToken();
    $controller = new AuthController($authService, $rateLimiter);
    // logout() invalida la sesión/token del usuario
    $controller->logout();
});

// POST /api/auth/cambiar-contrasena - Cambiar contraseña (protegida)
$router->post('/api/auth/cambiar-contrasena', function () use ($authService, $authMiddleware, $rateLimiter) {
    // Primero se exige un token válido: nadie puede cambiar su contraseña sin estar autenticado
    $authMiddleware->verificarToken();
    $controller = new AuthController($authService, $rateLimiter);
    // cambiarContrasena() valida la contraseña actual y guarda la nueva (hasheada)
    $controller->cambiarContrasena();
});

// POST /api/medicos - Crear médico (SOLO ADMIN → 403 si no)
// Regla de negocio: la gestión del catálogo de médicos es administrativa.
// Un paciente (o médico) no tiene permitido crear médicos.
$router->post('/api/medicos', function () use ($medicoService, $authMiddleware) {
    // Exige token JWT válido antes de permitir la creación (ruta protegida)
    $payload = $authMiddleware->verificarToken();
    // requireRol() comprueba que el rol del payload sea 'admin'; si no lo es responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new MedicoController($medicoService);
    // store() toma los datos del body JSON, valida y persiste el nuevo médico
    $controller->store();
});

// PUT/PATCH /api/medicos/{id} - Actualizar médico (SOLO ADMIN → 403 si no)
// Regla de negocio: la gestión del catálogo de médicos es administrativa.
// Un paciente (o médico) no tiene permitido editar médicos.
$router->put('/api/medicos/{id}', function ($id) use ($medicoService, $authMiddleware) {
    // Autentica al usuario antes de permitir la modificación
    $payload = $authMiddleware->verificarToken();
    // requireRol() comprueba que el rol del payload sea 'admin'; si no lo es responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new MedicoController($medicoService);
    // update() actualiza el médico con el id recibido; ($id viene como string, (int) lo convierte)
    $controller->update((int) $id);
});

// PATCH también permite actualizar parcialmente el médico (misma lógica que PUT)
$router->patch('/api/medicos/{id}', function ($id) use ($medicoService, $authMiddleware) {
    // Verificación de token obligatoria para rutas protegidas
    $payload = $authMiddleware->verificarToken();
    // Misma regla de negocio que PUT: solo admin puede editar médicos
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new MedicoController($medicoService);
    $controller->update((int) $id);
});

// DELETE /api/medicos/{id} - Eliminar médico (SOLO ADMIN → 403 si no)
$router->delete('/api/medicos/{id}', function ($id) use ($medicoService, $authMiddleware) {
    // verificarToken() valida el JWT y además devuelve su payload (datos del usuario: id, rol, etc.)
    $payload = $authMiddleware->verificarToken();
    // requireRol() comprueba que el rol del payload sea 'admin'; si no lo es responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new MedicoController($medicoService);
    // destroy() elimina el médico de la base de datos (se ejecuta solo si pasó la verificación de rol)
    $controller->destroy((int) $id);
});

// POST /api/pacientes - Crear paciente (protegida)
$router->post('/api/pacientes', function () use ($pacienteService, $authMiddleware) {
    // Ruta protegida: primero se exige un token JWT válido
    $authMiddleware->verificarToken();
    try {
        $controller = new PacienteController($pacienteService);
        // store() lee el body, valida y crea el paciente
        $controller->store();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// PUT/PATCH /api/pacientes/{id} - Actualizar paciente (protegida)
$router->put('/api/pacientes/{id}', function ($id) use ($pacienteService, $authMiddleware) {
    // Autenticación obligatoria antes de modificar datos
    $authMiddleware->verificarToken();
    try {
        $controller = new PacienteController($pacienteService);
        // update() actualiza los datos del paciente identificado por $id
        $controller->update((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// PATCH: actualización parcial de paciente (mismo comportamiento que PUT)
$router->patch('/api/pacientes/{id}', function ($id) use ($pacienteService, $authMiddleware) {
    // Verificación de token
    $authMiddleware->verificarToken();
    try {
        $controller = new PacienteController($pacienteService);
        $controller->update((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// DELETE /api/pacientes/{id} - Eliminar paciente (SOLO ADMIN)
$router->delete('/api/pacientes/{id}', function ($id) use ($pacienteService, $authMiddleware) {
    // Se valida el token y se captura el payload para conocer el rol del usuario
    $payload = $authMiddleware->verificarToken();
    // Se exige rol 'admin'; de lo contrario el middleware responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['admin']);
    try {
        $controller = new PacienteController($pacienteService);
        // destroy() borra el paciente solo si el usuario es administrador
        $controller->destroy((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// GET /api/prescripciones - Listar prescripciones (protegida: dato clínico)
$router->get('/api/prescripciones', function () use ($prescripcionService, $authMiddleware) {
    // Dato sensible (salud): solo accesible con token válido
    $authMiddleware->verificarToken();
    try {
        $controller = new PrescripcionController($prescripcionService);
        // index() lista todas las prescripciones (decodificando el JSON de medicamentos)
        $controller->index();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// GET /api/prescripciones/{id} - Ver una prescripción (protegida)
$router->get('/api/prescripciones/{id}', function ($id) use ($prescripcionService, $authMiddleware) {
    // Exige token antes de exponer el dato clínico de la prescripción
    $authMiddleware->verificarToken();
    try {
        $controller = new PrescripcionController($prescripcionService);
        // show() devuelve la prescripción con el id indicado
        $controller->show((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// POST /api/prescripciones - Crear prescripción (SOLO MÉDICO → 403 si no)
// Regla de negocio: "Las prescripciones sólo pueden ser hechas por Médicos".
// Ni un paciente ni un admin pueden recetar; el backend valida el rol.
$router->post('/api/prescripciones', function () use ($prescripcionService, $authMiddleware) {
    // Solo usuarios autenticados pueden generar una prescripción
    $payload = $authMiddleware->verificarToken();
    // requireRol() comprueba que el rol sea 'medico'; si no, responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['medico']);
    try {
        $controller = new PrescripcionController($prescripcionService);
        // store() valida y guarda la nueva prescripción (medicamentos como JSON)
        $controller->store();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// PUT/PATCH /api/prescripciones/{id} - Actualizar prescripción (SOLO MÉDICO → 403 si no)
// Regla de negocio: editar una prescripción (dato clínico) requiere rol médico.
$router->put('/api/prescripciones/{id}', function ($id) use ($prescripcionService, $authMiddleware) {
    // Autenticación requerida para editar el dato clínico
    $payload = $authMiddleware->verificarToken();
    // Se exige rol 'medico'; de lo contrario el middleware responde 403 Forbidden
    $authMiddleware->requireRol($payload, ['medico']);
    try {
        $controller = new PrescripcionController($prescripcionService);
        // update() modifica los campos enviados de la prescripción indicada
        $controller->update((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// PATCH: actualización parcial de prescripción
$router->patch('/api/prescripciones/{id}', function ($id) use ($prescripcionService, $authMiddleware) {
    // Verificación de token
    $payload = $authMiddleware->verificarToken();
    // Misma regla de negocio que PUT: solo médico edita prescripciones
    $authMiddleware->requireRol($payload, ['medico']);
    try {
        $controller = new PrescripcionController($prescripcionService);
        $controller->update((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// PATCH /api/prescripciones/{id}/estado - Cambiar estado (protegida)
$router->patch('/api/prescripciones/{id}/estado', function ($id) use ($prescripcionService, $authMiddleware) {
    // Ruta protegida: primero token válido
    $authMiddleware->verificarToken();
    try {
        $controller = new PrescripcionController($prescripcionService);
        // cambiarEstado() actualiza el campo estado (activa, vencida, dispensada...) de la prescripción
        $controller->cambiarEstado((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// DELETE /api/prescripciones/{id} - Eliminar prescripción (SOLO ADMIN)
$router->delete('/api/prescripciones/{id}', function ($id) use ($prescripcionService, $authMiddleware) {
    // Se valida el token y se obtiene el payload para chequear el rol
    $payload = $authMiddleware->verificarToken();
    // El borrado de prescripciones queda reservado a administradores (403 si no lo es)
    $authMiddleware->requireRol($payload, ['admin']);
    try {
        $controller = new PrescripcionController($prescripcionService);
        // destroy() elimina la prescripción del id indicado
        $controller->destroy((int) $id);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
});

// ============================================================
// SISTEMA DE GESTIÓN DE CITAS ONLINE (TURNERA)
// ============================================================
// Módulo: "Sistema de gestión de citas online"
// ------------------------------------------------------------
// Estas rutas se suman a las que ya existen (médicos, pacientes,
// prescripciones) sin modificarlas, para que el módulo nuevo quede
// aislado y se pueda revisar por separado.
//
// APLICACIÓN DE LA GUÍA DE DATOS EN TIEMPO REAL
// No se usa SSE, WebSocket, Firebase ni Supabase Realtime. El motivo está
// escrito al inicio de CitaService y en EstadisticaService, pero se resume:
//   • Response::json() hace exit, y SSE exige mantener el proceso vivo
//   • No hay credenciales ni secretos disponibles en este entorno
//   • La guía indica usar push SOLO cuando el retraso se percibe; una
//     agenda de turnos se puede refrescar por polling sin que se note
//   • Duplicar el estado en una base "en tiempo real" genera dos fuentes
//     de verdad que pueden discrepar, que es peor que estar 30 s desfasado
// En su lugar, polling adaptativo en el cliente: rápido cuando la pantalla
// está activa y hay cambios, y con espera larga cuando nada cambia.
// ============================================================

// ============================================================
// TURNERA - CATÁLOGOS PÚBLICOS
// Se pueden consultar sin iniciar sesión: son datos de catálogo, no clínicos.
// ============================================================

// GET /api/especialidades - Listado de especialidades con conteo de médicos
// Es la primera pantalla del flujo de reserva: el paciente elige especialidad
// y recién después ve los profesionales disponibles.
$router->get('/api/especialidades', function () use ($especialidadService, $authService) {
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->index();
});

// GET /api/especialidades/{id} - Detalle de una especialidad
$router->get('/api/especialidades/{id}', function ($id) use ($especialidadService, $authService) {
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->show((int) $id);
});

// ============================================================
// TURNERA - AGENDA PÚBLICA
// ============================================================

// GET /api/disponibilidades?id_medico=1 - Agenda publicada de un profesional
// PÚBLICA: el paciente tiene que ver los horarios ANTES de comprometerse
// a reservar. Si exigiera login, se perdería a quien solo está consultando.
$router->get('/api/disponibilidades', function () use ($disponibilidadService, $authService) {
    $controller = new DisponibilidadController($disponibilidadService, $authService);
    $controller->index();
});

// GET /api/citas/disponibilidad?id_medico=1&fecha=2026-10-01 - Turnos concretos
// PÚBLICA a propósito, pero devuelve SOLO si el horario está libre u ocupado:
// nunca el nombre del paciente ni el motivo de la consulta. La disponibilidad
// es información pública; los turnos ya tomados son datos de terceros.
$router->get('/api/citas/disponibilidad', function () use ($citaService, $authService) {
    $controller = new CitaController($citaService, $authService);
    $controller->disponibilidad();
});

// GET /api/citas/agenda?id_medico=1&desde=...&hasta=... - Agenda unificada
//
// PROTEGIDA, y no pública como estaba antes. La vista de "reservado / libre"
// que sí puede ver cualquiera ya la cubre /api/citas/disponibilidad, que no
// expone datos de terceros. Este endpoint devuelve la agenda con los DATOS
// COMPLETOS de cada turno (nombre del paciente, DNI y motivo de la consulta),
// así que dejarlo abierto significa que cualquiera que consultara la URL veía
// la lista de pacientes del consultorio, con sus documentos y su motivo de
// consulta.
//
// El polling del frontend no se rompe con esto: el panel del profesional y el
// del paciente ya trabajan con token.
$router->get('/api/citas/agenda', function () use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();   // Exige un JWT válido antes de seguir
    $controller = new CitaController($citaService, $authService);
    $controller->agenda();
});

// ============================================================
// TURNERA - ACCIONES POR TOKEN (públicas, sin login)
// ============================================================

// El paciente recibe un aviso con un link. El link lleva un token, no un JWT,
// porque desde el correo o el WhatsApp no hay sesión iniciada en el navegador.
// El token es aleatorio, dura 7 días y resuelve a un único turno.

// GET /api/notificaciones/token/{token} - Ver de qué turno se trata
$router->get('/api/notificaciones/token/{token}', function ($token) use ($notificacionService, $authService) {
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->resolverPorToken((string) $token);
});

// POST /api/notificaciones/token/{token}/confirmar - Confirmar que asiste
$router->post('/api/notificaciones/token/{token}/confirmar', function ($token) use ($notificacionService, $authService) {
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->confirmarPorToken((string) $token);
});

// POST /api/notificaciones/token/{token}/cancelar - Liberar el horario
$router->post('/api/notificaciones/token/{token}/cancelar', function ($token) use ($notificacionService, $authService) {
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->cancelarPorToken((string) $token);
});

// ============================================================
// TURNERA - RUTAS PROTEGIDAS (requieren JWT)
// ============================================================

// GET /api/citas - Mis turnos (paciente) / agenda filtrable (médico, admin)
// El alcance NO se decide con un parámetro de la URL: el controlador fuerza
// que un paciente solo vea los suyos, tomándolo del token.
$router->get('/api/citas', function () use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new CitaController($citaService, $authService);
    $controller->index();
});

// GET /api/citas/{id} - Ver un turno puntual
$router->get('/api/citas/{id}', function ($id) use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new CitaController($citaService, $authService);
    $controller->show((int) $id);
});

// POST /api/citas - Reservar un turno
$router->post('/api/citas', function () use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new CitaController($citaService, $authService);
    $controller->store();
});

// POST /api/citas/{id}/cancelar - Cancelar y liberar el horario
// Tiene ruta propia y no solo PATCH de estado porque es la acción más
// frecuente de la autogestión y merece un botón claro en la app móvil.
$router->post('/api/citas/{id}/cancelar', function ($id) use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new CitaController($citaService, $authService);
    $controller->cancelar((int) $id);
});

// PATCH /api/citas/{id}/estado - Cambiar el estado del turno
// El servicio define qué estados puede aplicar cada rol: el paciente solo
// confirma o cancela; el profesional completa o marca ausente.
$router->patch('/api/citas/{id}/estado', function ($id) use ($citaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new CitaController($citaService, $authService);
    $controller->cambiarEstado((int) $id);
});

// DELETE /api/citas/{id} - Borrar un turno (SOLO ADMIN)
// Borrar destruye evidencia histórica; en el uso normal corresponde cancelar.
$router->delete('/api/citas/{id}', function ($id) use ($citaService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new CitaController($citaService, $authService);
    $controller->destroy((int) $id);
});

// POST /api/disponibilidades - Publicar un bloque de atención
// Solo el propio profesional o un admin: es su agenda, no un dato global.
$router->post('/api/disponibilidades', function () use ($disponibilidadService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new DisponibilidadController($disponibilidadService, $authService);
    $controller->store();
});

// PUT/PATCH /api/disponibilidades/{id} - Editar u ocultar un bloque
$router->put('/api/disponibilidades/{id}', function ($id) use ($disponibilidadService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new DisponibilidadController($disponibilidadService, $authService);
    $controller->update((int) $id);
});

$router->patch('/api/disponibilidades/{id}', function ($id) use ($disponibilidadService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new DisponibilidadController($disponibilidadService, $authService);
    $controller->update((int) $id);
});

// DELETE /api/disponibilidades/{id} - Quitar un bloque de la agenda
$router->delete('/api/disponibilidades/{id}', function ($id) use ($disponibilidadService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new DisponibilidadController($disponibilidadService, $authService);
    $controller->destroy((int) $id);
});

// GET /api/notificaciones - Avisos del usuario autenticado
$router->get('/api/notificaciones', function () use ($notificacionService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->index();
});

// POST /api/notificaciones/recordatorios - Generar los recordatorios pendientes
// Es idempotente: se puede llamar cada vez que se abre el panel sin enviar
// avisos duplicados, porque no vuelve a crear uno si la cita ya lo tiene.
$router->post('/api/notificaciones/recordatorios', function () use ($notificacionService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->generarRecordatorios();
});

// POST /api/notificaciones/procesar - Enviar los avisos pendientes (SOLO ADMIN)
// A diferencia del anterior, este sí despacha mensajes, por eso se restringe.
$router->post('/api/notificaciones/procesar', function () use ($notificacionService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->procesar();
});

// POST /api/notificaciones/{id}/reintentar - Reintentar un aviso fallido
$router->post('/api/notificaciones/{id}/reintentar', function ($id) use ($notificacionService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new NotificacionController($notificacionService, $authService);
    $controller->reintentar((int) $id);
});

// ============================================================
// TURNERA - PANEL DE GESTIÓN (médico o admin)
// ============================================================

// GET /api/estadisticas - Demanda, ocupación, ausentismo y uso de la autogestión
// NO va por tiempo real: son métricas de gestión y un retraso de 30 s es
// irrelevante para decidir. Va por REST, que es exactamente lo que pide
// la guía cuando el usuario no notaría la diferencia.
$router->get('/api/estadisticas', function () use ($estadisticaService, $authService, $authMiddleware) {
    $authMiddleware->verificarToken();
    $controller = new EstadisticaController($estadisticaService, $authService);
    $controller->index();
});

// ============================================================
// TURNERA - GESTIÓN DEL CATÁLOGO (solo admin)
// ============================================================

// POST /api/especialidades - Crear especialidad
$router->post('/api/especialidades', function () use ($especialidadService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->store();
});

// PUT/PATCH /api/especialidades/{id} - Actualizar especialidad
$router->put('/api/especialidades/{id}', function ($id) use ($especialidadService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->update((int) $id);
});

$router->patch('/api/especialidades/{id}', function ($id) use ($especialidadService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->update((int) $id);
});

// DELETE /api/especialidades/{id} - Eliminar especialidad
$router->delete('/api/especialidades/{id}', function ($id) use ($especialidadService, $authService, $authMiddleware) {
    $payload = $authMiddleware->verificarToken();
    $authMiddleware->requireRol($payload, ['admin']);
    $controller = new EspecialidadController($especialidadService, $authService);
    $controller->destroy((int) $id);
});

// ============================================================
// Manejador de rutas no encontradas (404)
// Devuelve error JSON en lugar de redirigir
// ============================================================
// notFound() registra un manejador por defecto para cualquier ruta sin definir
$router->notFound(function () {
    // Responde con el código HTTP 404 (Not Found) para que el cliente sepa que la ruta no existe
    http_response_code(404);
    // Devuelve el error en formato JSON (JSON_UNESCAPED_UNICODE conserva caracteres acentuados)
    echo json_encode(['error' => 'Ruta no encontrada'], JSON_UNESCAPED_UNICODE);
});