<?php
// Inicialización de dependencias del backend separado.
// ============================================================
// core/bootstrap.php - Archivo de arranque (bootstrap) del sistema
// ============================================================
// bootstrap significa "arranque" o "inicialización".
// Es el primer archivo que se ejecuta, prepara todo lo necesario
// para que el resto de la aplicación funcione.
// ============================================================

// ============================================================
// core/bootstrap.php - Archivo de arranque (bootstrap) del sistema
// ============================================================

// Carga el autoload de Composer (librería firebase/php-jwt)
require_once __DIR__ . '/../vendor/autoload.php';

// Carga la configuración del entorno (APP_ENV y validación de secretos)
require_once __DIR__ . '/Config.php';

// Seguridad: en producción nunca se muestran errores en pantalla.
// display_errors = Off evita filtrar stack traces a los clientes de la API.
$appEnv = Config::appEnv();
$showErrors = ($appEnv === 'development') ? '1' : '0';
// Activa o desactiva la muestra de errores en pantalla según el entorno
ini_set('display_errors', $showErrors);
// Aplica la misma política a los errores que ocurren durante el arranque de PHP
ini_set('display_startup_errors', $showErrors);
// Reporta todos los tipos de error (E_ALL) para no perder ningún problema al depurar
error_reporting(E_ALL);

// Valida que en producción todas las variables obligatorias estén definidas
Config::validar();

// Inicia la sesión del usuario (para compatibilidad con clientes por cookies)
session_start();

// Carga la clase Router (enrutador de URLs)
require_once __DIR__ . '/Router.php';

// Carga el módulo de gestión del secreto JWT
require_once __DIR__ . '/Secret.php';

// Carga el servicio de tokens JWT (emisión y verificación con firebase/php-jwt)
require_once __DIR__ . '/JwtService.php';

// Carga el middleware de autenticación y autorización (401/403)
require_once __DIR__ . '/AuthMiddleware.php';

// Carga el rate limiter (mitigación de fuerza bruta)
require_once __DIR__ . '/RateLimiter.php';

// Carga la conexión a la base de datos
// Esto ejecuta db.php que crea la variable $pdo (objeto PDO)
require_once __DIR__ . '/../db.php';

// Carga el helper centralizado de respuestas JSON
require_once __DIR__ . '/Response.php';

// Carga el helper de lectura del cuerpo JSON de las peticiones.
// Se carga acá (y no dentro de cada controlador) para que el orden de
// require_once sea explícito y no dependa del archivo que se cargue primero.
require_once __DIR__ . '/CuerpoJson.php';

// Carga los contratos (interfaces) de acceso a datos
require_once __DIR__ . '/../persistence/MedicoRepositoryInterface.php';
require_once __DIR__ . '/../persistence/PacienteRepositoryInterface.php';
require_once __DIR__ . '/../persistence/PrescripcionRepositoryInterface.php';

// Carga el repositorio de obras sociales (usado por pacientes)
require_once __DIR__ . '/../persistence/ObraSocialRepository.php';

// Carga la capa de persistencia (repositorio de médicos)
require_once __DIR__ . '/../persistence/MedicoRepository.php';

// Carga la capa de negocio (servicio de médicos)
require_once __DIR__ . '/../services/MedicoService.php';

// Carga el controlador de médicos (maneja HTTP)
require_once __DIR__ . '/../controllers/MedicoController.php';

// Carga repositorio, servicio y controlador de Pacientes
require_once __DIR__ . '/../persistence/PacienteRepository.php';
require_once __DIR__ . '/../services/PacienteService.php';
require_once __DIR__ . '/../controllers/PacienteController.php';

// Carga repositorio, servicio y controlador de Prescripciones
require_once __DIR__ . '/../persistence/PrescripcionRepository.php';
require_once __DIR__ . '/../services/PrescripcionService.php';
require_once __DIR__ . '/../controllers/PrescripcionController.php';

// Carga servicio y controlador de Autenticación
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// ============================================================
// MÓDULO: "Gestión de roles y permisos"
// ============================================================
// Permite cambiar el tipo_usuario (paciente/medico/admin) de una cuenta.
// Sigue el mismo orden de carga que el resto del proyecto: contrato,
// implementación, servicio y controlador.
//
// El require del contrato va PRIMERO porque UsuarioRepository lo implementa:
// si se invirtiera, PHP no encontraría la interfaz al declarar la clase y
// fallaría con un error fatal en el arranque, no con una excepción
// manejable.
require_once __DIR__ . '/../persistence/UsuarioRepositoryInterface.php';
require_once __DIR__ . '/../persistence/UsuarioRepository.php';
require_once __DIR__ . '/../services/UsuarioService.php';
require_once __DIR__ . '/../controllers/UsuarioController.php';

// ============================================================
// MÓDULO: "Sistema de gestión de citas online" (Turnera)
// ============================================================
// Orden de carga: primero los contratos (interfaces), después las
// implementaciones, después los servicios y por último los controladores.
// PHP no tiene autoload propio en este proyecto, así que el orden lo define
// este archivo; por eso está comentado y no es "decorativo".

// Contratos (interfaces) de la turnera
require_once __DIR__ . '/../persistence/EspecialidadRepositoryInterface.php';
require_once __DIR__ . '/../persistence/DisponibilidadRepositoryInterface.php';
require_once __DIR__ . '/../persistence/CitaRepositoryInterface.php';
require_once __DIR__ . '/../persistence/NotificacionRepositoryInterface.php';

// Implementaciones de acceso a datos de la turnera
require_once __DIR__ . '/../persistence/EspecialidadRepository.php';
require_once __DIR__ . '/../persistence/DisponibilidadRepository.php';
require_once __DIR__ . '/../persistence/CitaRepository.php';
require_once __DIR__ . '/../persistence/NotificacionRepository.php';
// ContactoRepository se carga junto a los anteriores: resuelve el email del
// paciente/médico para saber a quién se le puede notificar
require_once __DIR__ . '/../persistence/ContactoRepository.php';

// Capa de negocio (servicios) de la turnera
require_once __DIR__ . '/../services/EspecialidadService.php';
require_once __DIR__ . '/../services/CitaService.php';
require_once __DIR__ . '/../services/DisponibilidadService.php';
require_once __DIR__ . '/../services/NotificacionService.php';
require_once __DIR__ . '/../services/EstadisticaService.php';

// Proveedor de notificaciones. Hoy es un stub (simula el envío y registra
// el resultado). Cuando se integre un proveedor real (email, WhatsApp, SMS)
// se cambia solo este archivo: la interfaz es el contrato estable.
require_once __DIR__ . '/../services/Notificacion/ProveedorNotificacionesInterface.php';
require_once __DIR__ . '/../services/Notificacion/ProveedorNotificacionesStub.php';

// Capa HTTP (controladores) de la turnera
require_once __DIR__ . '/../controllers/CitaController.php';
require_once __DIR__ . '/../controllers/DisponibilidadController.php';
require_once __DIR__ . '/../controllers/NotificacionController.php';
require_once __DIR__ . '/../controllers/EspecialidadController.php';
require_once __DIR__ . '/../controllers/EstadisticaController.php';

// Calcula la ruta base del proyecto
// dirname($_SERVER['SCRIPT_NAME']) devuelve algo como "/Organizacion_Modulos"
// rtrim() saca la barra del final si la hay
// Ej: si la URL es http://localhost/Organizacion_Modulos/index.php,
// entonces SCRIPT_NAME es "/Organizacion_Modulos/index.php"
// y basePath queda como "/Organizacion_Modulos"
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Crea el enrutador (Router) pasándole la ruta base
// El Router usará esto para recortar el prefijo de las URLs
// y trabajar solo con rutas relativas como /medicos, /api/medicos, etc.
$router = new Router($basePath);

// Crea el repositorio de médicos, inyectándole la conexión PDO
// Inyección de dependencias: en lugar de crear la conexión adentro,
// se la pasamos desde afuera. Esto hace el código más testeable y flexible.
$medicoRepo = new MedicoRepository($pdo);

// Crea el servicio de médicos, inyectándole el repositorio
// El servicio contiene la lógica de negocio (validaciones, reglas)
$medicoService = new MedicoService($medicoRepo);

// Crea el repositorio, servicio para Pacientes
$pacienteRepo = new PacienteRepository($pdo);
$pacienteService = new PacienteService($pacienteRepo);

// Crea el repositorio de obras sociales (para el formulario de pacientes)
$obraSocialRepo = new ObraSocialRepository($pdo);

// Crea el repositorio, servicio para Prescripciones
$prescripcionRepo = new PrescripcionRepository($pdo);
$prescripcionService = new PrescripcionService($prescripcionRepo);

// Crea el servicio de JWT (inyectándole el secreto firmado)
$jwtService = new JwtService(Secret::obtener());

// Crea el middleware de autenticación/autorización (lo usan las rutas protegidas)
$authMiddleware = new AuthMiddleware($jwtService);

// Crea el rate limiter (anti fuerza bruta; persiste en storage/rate/)
$rateLimiter = new RateLimiter(__DIR__ . '/../storage/rate');

// Crea el servicio de Autenticación (recibe PDO + JwtService por inyección)
$authService = new AuthService($pdo, $jwtService);

// ============================================================
// ENSAMBLADO DE LA GESTIÓN DE ROLES
// ============================================================
// Solo se instancia el repositorio acá. El servicio se arma en la ruta, como
// el resto de los módulos, porque cada endpoint construye su propio
// controlador con lo que necesita y el payload del token cambia en cada
// petición.
$usuarioRepo = new UsuarioRepository($pdo);

// ============================================================
// ENSAMBLADO DE LA TURNERA (inyección de dependencias)
// ============================================================
// Se instancia de abajo hacia arriba: cada capa recibe por constructor lo
// que necesita de la capa de abajo. Así el servicio se puede probar con un
// dobles de repositorio sin tocar la base de datos.

// Especialidades: solo necesita la conexión
$especialidadRepo = new EspecialidadRepository($pdo);

// Disponibilidad: la tabla de agenda la usa el servicio de disponibilidad
// y también el de citas (para saber qué turnos se pueden generar)
$disponibilidadRepo = new DisponibilidadRepository($pdo);

// Citas: es la fuente de verdad de los turnos
$citaRepo = new CitaRepository($pdo);

// Notificaciones: es la bandeja de salida (outbox), no el estado del turno
$notificacionRepo = new NotificacionRepository($pdo);

// Contacto: resuelve a quién se le puede avisar (email del paciente/médico)
$contactoRepo = new ContactoRepository($pdo);

// Proveedor de notificaciones: hoy simula el envío. Si algún día se conecta
// un proveedor real, este es el ÚNICO punto que hay que cambiar.
$proveedorNotificaciones = new ProveedorNotificacionesStub();

// Servicios de la turnera
$citaService = new CitaService(
    $citaRepo,
    $disponibilidadRepo,
    $medicoRepo,
    $pacienteRepo
);
$disponibilidadService = new DisponibilidadService($disponibilidadRepo, $medicoRepo);

// Especialidades: el servicio concentra las reglas del catálogo (duplicados
// ignorando tildes, no borrar con profesionales asociados)
$especialidadService = new EspecialidadService($especialidadRepo);
$notificacionService = new NotificacionService(
    $notificacionRepo,
    $citaRepo,
    $contactoRepo,
    $proveedorNotificaciones
);
// Las estadísticas necesitan las citas, los médicos (quién atiende) y la
// disponibilidad (cuántos turnos se ofrecieron en cada bloque)
$estadisticaService = new EstadisticaService($citaRepo, $medicoRepo, $disponibilidadRepo);

// Las variables $router, $pdo, y todos los servicios
// quedan disponibles en routes.php que es quien incluye este archivo
