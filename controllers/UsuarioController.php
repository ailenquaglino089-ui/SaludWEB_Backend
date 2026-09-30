<?php
// ============================================================
// controllers/UsuarioController.php - Capa HTTP de la gestión de roles
// ============================================================
// Módulo: "Gestión de roles y permisos"
//
// El controlador hace TRES cosas y ninguna más:
//
//   1. Leer los datos de la petición (query string o cuerpo JSON)
//   2. Delegar la decisión en UsuarioService
//   3. Traducir la excepción que vuelve del servicio a un código HTTP
//
// No valida reglas de negocio ni escribe SQL. La separación no es purismo:
// es lo que permite que las reglas críticas del módulo (no degradar al
// último administrador, no degradarse a sí mismo) estén escritas una sola
// vez y se apliquen igual si mañana el endpoint se llama desde otro lado.
//
// SOBRE EL CÓDIGO 403 vs 409 EN ESTE CONTROLADOR
// ----------------------------------------------
// Estas dos respuestas significan cosas distintas y conviene no mezclarlas:
//
//   403 Forbidden → el que llama NO PUEDE. Le faltan permisos. Un médico
//                   llamando a /api/usuarios recibe esto, siempre.
//
//   409 Conflict  → el que llama SÍ PUEDE, pero la operación no se puede
//                   llevar a cabo en el estado actual. Un administrador que
//                   intenta degradarse a sí mismo, o borrar al único
//                   administrador, recibe esto.
//
// Confundirlos produce el peor de los dos mundos: un frontend que trate el
// 409 como "no tenés permiso" cerraría la sesión o escondería la pantalla
// de permisos, cuando en realidad el problema es que esa operación concreta
// no se puede hacer ahora.
//
// NOTA SOBRE requireRol()
// -----------------------
// El chequeo de "solo admin" NO está en este archivo, está en routes.php
// (requireRol) y en AuthMiddleware. Está deliberadamente afuera: la capa
// HTTP tiene que decidir la autorización ANTES de tocar el servicio, y por
// eso esa decisión vive en el router, donde se lee con las demás rutas
// protegidas. Repetirla acá sería redundante y daría dos lugares distintos
// donde cambiar una regla de permisos.
class UsuarioController
{
    // Capa de negocio con las reglas de roles
    private UsuarioService $service;

    // Datos del administrador autenticado, inyectados por la ruta
    private array $payload;

    /**
     * @param UsuarioService $service Servicio de usuarios
     * @param array          $payload Payload del token JWT verificado
     */
    public function __construct(UsuarioService $service, array $payload = [])
    {
        $this->service = $service;
        $this->payload = $payload;
    }

    /**
     * GET /api/usuarios - Listar usuarios para la pantalla de permisos
     *
     * Query params: pagina, por_pagina, q (búsqueda) y rol (filtro exacto).
     *
     * @return void Responde 200 con la página de usuarios
     */
    public function index(): void
    {
        // ------------------------------------------------------------
        // LECTURA DE PARÁMETROS
        // ------------------------------------------------------------
        // Los valores crudo del query string son SIEMPRE texto. Lo que
        // viene en $_GET['pagina'] es la cadena "2", no el número 2. Por eso
        // se castean con (int) y no se usan directo: si llegara "abc", el
        // cast produce 0, y recién en el servicio se decide qué hacer con
        // una página 0.
        $pagina = (int)($_GET['pagina'] ?? 1);
        $porPagina = (int)($_GET['por_pagina'] ?? 10);

        // trim() en los dos filtros de texto: " " (solo espacios) no es una
        // búsqueda, es un caso vacío. Sin el trim, el repositorio lo
        // tomaría como búsqueda válida y devolvería cero resultados, que es
        // distinto de "sin filtro".
        $busqueda = trim((string)($_GET['q'] ?? ''));
        $rol = trim((string)($_GET['rol'] ?? ''));

        // ------------------------------------------------------------
        // ACOTADO DE PAGINACIÓN
        // ------------------------------------------------------------
        // Estas dos líneas son la razón por la que el resto del módulo
        // puede confiar en los tipos.
        //
        // Sin acotar, un por_pagina=1000000 haría que el backend genere un
        // HAVING enorme y que la base demore varios segundos, cuántos
        // datos se pidan. Es un denegación de servicio enviada por el
        // propio cliente, y es la más común de las APIs mal hechas.
        //
        // El máximo de 100 coincide con el límite que ya usa el resto de
        // listados del proyecto, para que la paginación se comporte igual
        // en pacientes, médicos, prescripciones y usuarios.
        $pagina = max(1, $pagina);
        $porPagina = max(1, min(100, $porPagina));

        try {
            Response::ok(
                $this->service->listar($pagina, $porPagina, $busqueda, $rol),
                'Usuarios obtenidos correctamente'
            );
        } catch (\InvalidArgumentException $e) {
            // Rol de filtro inexistente: es un error de lo que pidió el
            // cliente, no del servidor.
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/usuarios/roles - Listar los roles válidos
     *
     * Endpoint aparte y no un campo dentro del listado porque el frontend
     * lo necesita ANTES de tener usuarios en pantalla: es lo que arma las
     * opciones del <select> de cambio de rol.
     *
     * @return void Responde 200 con la lista de roles
     */
    public function roles(): void
    {
        Response::ok(
            $this->service->listarRoles(),
            'Roles disponibles'
        );
    }

    /**
     * PATCH /api/usuarios/{id}/rol - Cambiar el rol de un usuario
     *
     * Se expone como PATCH y no como PUT porque la operación es parcial:
     * solo se modifica el rol, sin tocar email, nombre, estado ni vínculos.
     * Un PUT sugeriría que se está reemplazando el usuario entero.
     *
     * @param int $id ID del usuario cuyo rol cambia
     * @return void Responde 200 con el usuario actualizado
     */
    public function cambiarRol(int $id): void
    {
        try {
            // --------------------------------------------------------
            // LECTURA DEL CUERPO
            // --------------------------------------------------------
            // CuerpoJson::leer() separa "vino vacío" de "vino roto", que es
            // justo el caso de esta pantalla: si el <select> se manda vacío
            // porque el usuario cerró el formulario a mitad de camino, el
            // mensaje tiene que decir que no vino el rol, no que el JSON está
            // mal formado.
            $datos = CuerpoJson::leer();

            // Se busca 'rol' y no se exige que exista todavía: la
            // validación de que el valor sea uno de los tres la hace el
            // servicio, que es donde vive la lista verdadera de roles.
            // Acá solo se traduce "no vino" a un error legible.
            if (!isset($datos['rol'])) {
                throw new \InvalidArgumentException('Falta el campo "rol" en el cuerpo de la petición', 422);
            }

            // El valor se pasa a string y se limpia. (string) evita un error
            // de tipo si mandan un número; trim() evita que " admin " no
            // coincida con "admin" y se reporte como un rol inexistente
            // cuando en realidad es un error de tipeo del cliente.
            $rolNuevo = trim((string)$datos['rol']);

            // --------------------------------------------------------
            // IDENTIDAD DE QUIEN HACE EL CAMBIO
            // --------------------------------------------------------
            // El id del administrador sale del token verificado, NO del
            // cuerpo de la petición. Esta es la parte que hace que la
            // regla de "no te degrades a vos mismo" sea confiable: si el id
            // viniera del body, un administrador podría mandar el de otro
            // y degradarse saltándose la comprobación.
            $idAdminActual = (int)($this->payload['sub'] ?? 0);

            $usuario = $this->service->cambiarRol($id, $rolNuevo, $idAdminActual);

            Response::ok($usuario, 'Rol actualizado correctamente');
        } catch (\InvalidArgumentException $e) {
            // 422: el cuerpo no pasó la validación (rol ausente o inválido)
            Response::error($e->getMessage(), $e->getCode() ?: 422);
        } catch (\RuntimeException $e) {
            // El servicio lanza RuntimeException para los casos de estado
            // (404 usuario inexistente, 409 degradación imposible). El
            // código HTTP viaja en el segundo argumento de la excepción,
            // que es donde el servicio lo dejó explícito.
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        } catch (\Throwable $e) {
            // Cualquier otra cosa es un fallo nuestro, no del cliente.
            Response::error($e->getMessage(), 500);
        }
    }
}
