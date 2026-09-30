<?php
// ============================================================
// services/UsuarioService.php - Gestión de roles (RBAC) de usuarios
// ============================================================
// Módulo: "Gestión de roles y permisos"
//
// QUÉ RESUELVE ESTE SERVICIO
// -------------------------
// El proyecto tiene tres roles fijos desde el schema: paciente, medico y
// admin, con las rutas ya protegidas por AuthMiddleware::requireRol().
//
// Lo que NO existía era la capacidad de cambiar el rol de una persona. Sin
// ella, promover a un médico a administrador —o degradar a alguien que ya no
// corresponde— exige tocar la base a mano, y la fecha de vencimiento del
// permiso es siempre la fecha en que alguien se acuerde.
//
// Este servicio es el único lugar del proyecto donde se puede cambiar un
// tipo_usuario. Que el endpoint sea admin-only alcanza para la mayoría de los
// casos, pero la regla de "no te quedes sin administradores" NO puede
// depender de eso: si el único admin se degrada a sí mismo, el sistema queda
// sin nadie que pueda volver a despertarlo por la vía normal, y hay que
// entrar a la base. Esa comprobación vive acá, en la capa de negocio, y no
// en la ruta.
//
// REGLAS DEL MÓDULO
// -----------------
//   1. El nuevo rol tiene que ser uno de los tres permitidos. Cualquier otro
//      valor se rechaza antes de tocar la base.
//   2. Nadie se degrada a sí mismo. Se responde 409, no 403: la petición
//      está bien formada y el usuario sí tiene permiso, lo que falla es la
//      operación.
//   3. El último administrador activo no se puede degradar. Hay al menos uno
//      que pueda restaurar los roles.
//
// LAS REGLAS 2 Y 3 SON LA DIFERENCIA ENTRE UN SISTEMA Y UN INCIDENTE
// Un admin que se quita el permiso a mano no rompe nada, porque puede
// volver a ponerlo. Un admin que se quita el permiso desde la pantalla
// equivocada y ya no puede volver, deja la plataforma completa sin
// administración y con datos clínicos adentro. Por eso están dos controles,
// y el segundo (el último admin) existe porque hay más de una forma de
// llegar al mismo lugar: un solo admin se degrada a sí mismo, o dos admins
// se degradan en paralelo desde dos sesiones distintas.
class UsuarioService
{
    /**
     * Los tres roles que admite la columna tipo_usuario.
     *
     * Es una constante de clase y no un array suelto en cada método porque
     * este es el ÚNICO lugar del proyecto donde tiene que estar la lista
     * verdadera. Si el schema acepta un cuarto rol mañana, se agrega acá y
     * el endpoint de cambio de rol lo acepta sin tocar más código.
     *
     * El orden es el que ve la persona en el <select> de la pantalla de
     * permisos: de menos a más privilegio.
     */
    public const ROLES = ['paciente', 'medico', 'admin'];

    /**
     * Rol con permiso para administrar roles.
     */
    public const ROL_ADMIN = 'admin';

    // Acceso a datos de usuarios
    private UsuarioRepositoryInterface $repo;

    /**
     * @param UsuarioRepositoryInterface $repo Repositorio inyectado
     */
    public function __construct(UsuarioRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    // ============================================================
    // CONSULTAS
    // ============================================================

    /**
     * Lista usuarios para la pantalla de permisos.
     *
     * Los parámetros llegan acotados desde el controlador: acá no se
     * revalida el rango de páginas porque esa validación ya está hecha una
     * vez, en la frontera HTTP.
     *
     * @param int    $pagina    Página a devolver
     * @param int    $porPagina Registros por página
     * @param string $busqueda  Búsqueda por nombre o email
     * @param string $rol       Filtro exacto de rol ('' = todos)
     * @return array Envoltura de paginación con los usuarios
     */
    public function listar(int $pagina, int $porPagina, string $busqueda, string $rol): array
    {
        // El filtro de rol se valida contra la lista verdadera antes de
        // llegar a la base. Un filtro con un valor inventado devolvería
        // cero resultados silenciosamente, y en una pantalla de permisos
        // eso se lee como "no hay usuarios con ese rol" en lugar de como
        // "el filtro no existe".
        if ($rol !== '' && !in_array($rol, self::ROLES, true)) {
            throw new \InvalidArgumentException('El rol indicado no existe', 422);
        }

        return $this->repo->obtenerPaginado($pagina, $porPagina, $busqueda, $rol);
    }

    /**
     * Los roles disponibles, para que el frontend no los tenga duplicados.
     *
     * Se expone como endpoint porque el <select> de la pantalla de
     * permisos y el validador del backend tienen que mostrar exactamente lo
     * mismo. Si el frontend tuviera su propia lista, agregar un rol
     * obligaría a cambiar los dos lados y tarde o temprano se desincronizan.
     *
     * @return array Lista de roles
     */
    public function listarRoles(): array
    {
        return self::ROLES;
    }

    // ============================================================
    // CAMBIO DE ROL
    // ============================================================

    /**
     * Cambia el rol de un usuario.
     *
     * @param int    $idUsuario     ID del usuario cuyo rol se cambia
     * @param string $rolNuevo      Rol destino
     * @param int    $idAdminActual ID del administrador que hace el cambio
     * @return array El usuario ya actualizado
     * @throws \RuntimeException          Si el usuario no existe (404)
     * @throws \InvalidArgumentException Si el rol no es válido (422),
     *                                    si se intenta degradarse a sí mismo (409)
     *                                    o si se degrada al último admin (409)
     */
    public function cambiarRol(int $idUsuario, string $rolNuevo, int $idAdminActual): array
    {
        // ----------------------------------------------------
        // 1. EL ROL DESTINO TIENE QUE EXISTIR
        // ----------------------------------------------------
        // Se valida antes de leer el usuario a propósito: si el rol es
        // inválido, la respuesta no debería depender de si el usuario existe
        // o no. Primero se responde "ese rol no existe", y recién después se
        // mira la base.
        if (!in_array($rolNuevo, self::ROLES, true)) {
            throw new \InvalidArgumentException(
                'Rol inválido. Valores permitidos: ' . implode(', ', self::ROLES),
                422
            );
        }

        // ----------------------------------------------------
        // 2. EL USUARIO TIENE QUE EXISTIR
        // ----------------------------------------------------
        $usuario = $this->repo->obtenerPorId($idUsuario);
        if ($usuario === null) {
            throw new \RuntimeException('Usuario no encontrado', 404);
        }

        $rolActual = (string)$usuario['rol'];

        // Si el rol ya es el que se pidió, se devuelve el usuario sin
        // escribir. Se evita el UPDATE y, sobre todo, se evita que un
        // "cambiar admin a admin" dispare las comprobaciones de seguridad
        // de más abajo, que están pensadas para degradaciones reales.
        if ($rolActual === $rolNuevo) {
            return $this->enriquecer($usuario);
        }

        // ----------------------------------------------------
        // 3. NADIE SE DEGRADA A SÍ MISMO
        // ----------------------------------------------------
        // Es un caso particular del 4, pero se trata aparte porque el
        // mensaje es más claro y evita una confusión frecuente: el
        // administrador que se quita su propio permiso esperando "sacarse
        // un rol de prueba se queda sin poder volver a ser admin él mismo.
        if ($idUsuario === $idAdminActual) {
            throw new \RuntimeException(
                'No podés cambiarte el rol a vos mismo. Pedile a otro administrador que lo haga.',
                409
            );
        }

        // ----------------------------------------------------
        // 4. NO SE DEGRADA AL ÚLTIMO ADMINISTRADOR
        // ----------------------------------------------------
        // Solo tiene sentido revisar esto cuando la operación BAJA el
        // privilegio: promover a admin nunca puede dejar el sistema sin
        // administradores.
        if ($rolActual === self::ROL_ADMIN && $rolNuevo !== self::ROL_ADMIN) {
            $adminsActivos = $this->repo->contarActivosConRol(self::ROL_ADMIN);

            if ($adminsActivos <= 1) {
                throw new \RuntimeException(
                    'No podés degradar al único administrador activo: '
                    . 'la plataforma quedaría sin nadie que pueda administrar los roles.',
                    409
                );
            }
        }

        // ----------------------------------------------------
        // 5. ESCRIBIR
        // ----------------------------------------------------
        // A partir de acá ya se comprobó todo lo que puede fallar por
        // decisión de negocio, así que la escritura no necesita otra
        // validación.
        $this->repo->actualizarRol($idUsuario, $rolNuevo);

        // Se vuelve a leer el usuario para devolverlo con el rol ya
        // actualizado. Reutilizar el array anterior y cambiarle el rol a
        // mano sería más barato, pero devolvería datos que nadie verificó
        // contra la base; releer es una línea más y no puede mentir.
        $actualizado = $this->repo->obtenerPorId($idUsuario);

        return $actualizado === null ? $this->enriquecer($usuario) : $this->enriquecer($actualizado);
    }

    // ============================================================
    // REGLAS INTERNAS
    // ============================================================

    /**
     * Agrega a un usuario los datos derivados que la pantalla necesita.
     *
     * La pantalla de permisos muestra si el usuario tiene ficha de paciente
     * o de médico, y esa información no está en la tabla usuarios sino en
     * las foreign keys. Acá se traduce id_paciente/id_medico a algo legible
     * en lugar de dejar que el frontend adivine qué significa un 1 o un null.
     *
     * Se hace en el servicio y no en el controlador porque es una decisión de
     * negocio (qué es una cuenta "vinculada"), no de presentación.
     *
     * @param array $usuario Fila del usuario
     * @return array El usuario con los campos derivados agregados
     */
    private function enriquecer(array $usuario): array
    {
        $rol = (string)($usuario['rol'] ?? '');
        $idPaciente = $usuario['id_paciente'] ?? null;
        $idMedico = $usuario['id_medico'] ?? null;

        // `vinculado` responde "¿esta cuenta puede operar sobre datos del
        // dominio?". Un admin no necesita ficha para administrar: opera con
        // su rol. Un médico sin ficha de médico no puede publicar agenda ni
        // recetar, y por eso el frontend lo tiene que poder avisar.
        $usuario['vinculado'] = match ($rol) {
            'admin' => true,
            'medico' => $idMedico !== null,
            'paciente' => $idPaciente !== null,
            default => false,
        };

        // Motivo de bloqueo, en texto listo para mostrar. Se calcula acá
        // para que el mensaje sea el mismo en la web y en el móvil.
        $usuario['nota_vinculacion'] = $this->notaVinculacion($rol, $idPaciente, $idMedico);

        return $usuario;
    }

    /**
     * Explica en una línea por qué una cuenta no puede operar.
     *
     * @param string   $rol         Rol del usuario
     * @param int|null $idPaciente  Ficha de paciente vinculada (o null)
     * @param int|null $idMedico    Ficha de médico vinculada (o null)
     * @return string Texto explicativo, o cadena vacía si no hay nada que avisar
     */
    private function notaVinculacion(string $rol, $idPaciente, $idMedico): string
    {
        return match ($rol) {
            'admin' => '',
            'medico' => $idMedico === null
                ? 'Sin ficha de médico: no puede publicar agenda ni prescribir.'
                : '',
            'paciente' => $idPaciente === null
                ? 'Sin ficha de paciente: no puede reservar turnos.'
                : '',
            default => '',
        };
    }
}
