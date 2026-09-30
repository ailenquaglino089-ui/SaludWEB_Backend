<?php
// ============================================================
// persistence/UsuarioRepositoryInterface.php - Contrato de acceso a usuarios
// ============================================================
// Módulo: "Gestión de roles y permisos"
//
// QUÉ ES ESTE ARCHIVO Y POR QUÉ EXISTE
// -----------------------------------
// La tabla `usuarios` es la única del proyecto que antes NO tenía
// repositorio propio: se consultaba con SQL suelto dentro de AuthService
// (que hace autenticación, no gestión de cuentas). Esa mezcla de
// responsabilidades es justamente lo que este módulo viene a corregir.
//
// Se declara una interfaz por dos razones concretas:
//
//  1. COHERENCIA. MedicoRepository, PacienteRepository y
//     PrescripcionRepository ya tienen su interfaz. Un cuarto acceso a datos
//     sin contrato rompe la regla del proyecto y hace que no se pueda saber
//     de un vistazo qué necesita cada repositorio.
//
//  2. TESTEABILIDAD. UsuarioService se puede probar con un doble de
//     repositorio, sin levantar MySQL. La regla crítica de este módulo
//     (no dejar la base sin administradores) se prueba en memoria.
//
// La interfaz además obliga a que el repositorio devuelva SIEMPRE las
// columnas que la interfaz de roles necesita. Si mañana alguien agrega una
// columna al SELECT y olvida otra, el error aparece al compilar la clase,
// no al romper la pantalla de permisos en producción.
interface UsuarioRepositoryInterface
{
    /**
     * Lista usuarios con filtros y paginación.
     *
     * @param int    $pagina     Página a devolver (desde 1)
     * @param int    $porPagina  Cantidad de registros por página
     * @param string $busqueda   Búsqueda parcial por nombre o email (opcional)
     * @param string $rol        Filtro EXACTO de tipo_usuario (opcional)
     * @return array{items: array, total: int, pagina: int, por_pagina: int, total_paginas: int}
     */
    public function obtenerPaginado(int $pagina, int $porPagina, string $busqueda, string $rol): array;

    /**
     * Obtiene un usuario por su id.
     *
     * @param int $id ID del usuario
     * @return array|null La fila del usuario, o null si no existe
     */
    public function obtenerPorId(int $id): ?array;

    /**
     * Cambia el tipo_usuario (rol) de un usuario.
     *
     * Solo escribe la columna del rol. No toca id_paciente ni id_medico a
     * propósito: son vínculos con las fichas del dominio y moverlos
     * automáticamente al cambiar el rol produciría datos incoherentes
     * (ver la nota sobre esto en UsuarioService::cambiarRol).
     *
     * @param int    $id  ID del usuario a modificar
     * @param string $rol Nuevo tipo_usuario
     * @return bool True si se modificó una fila
     */
    public function actualizarRol(int $id, string $rol): bool;

    /**
     * Cuenta cuántos usuarios tienen un rol determinado y están activos.
     *
     * Se usa para la regla de seguridad que impide quedarse sin
     * administradores. Cuenta solo los activos a propósito: un admin
     * desactivado no puede operar la plataforma, así que no sirve como
     * respaldo.
     *
     * @param string $rol Rol a contar
     * @return int Cantidad de usuarios activos con ese rol
     */
    public function contarActivosConRol(string $rol): int;
}
