<?php
// ============================================================
// services/EspecialidadService.php - Capa de negocio (Catálogo de especialidades)
// ============================================================
// Módulo: "Sistema de gestión de citas online" (Turnera)
//
// POR QUÉ ESTE SERVICIO EXISTE
// ----------------------------
// El controlador de especialidades antes llamaba al repositorio directamente
// y hacía ahí las validaciones (nombre obligatorio, duplicados, no borrar si
// hay médicos asociados). Eso rompía la arquitectura del proyecto, que separa
// Router → Controller → Service → Repository, y dejaba la lógica de negocio
// pegada a la capa HTTP: reutilizarla desde otro lado (por ejemplo, un
// importador de profesionales) obligaría a duplicarla.
//
// Este servicio concentra las reglas del catálogo:
//
//   - el nombre se limpia y se acota antes de guardarse
//   - no se admiten dos especialidades con el mismo nombre, ignorando
//     mayúsculas, tildes y espacios de sobra
//   - no se borra una especialidad que todavía tiene profesionales asociados
//
// La comparación de duplicados es el detalle que justifica el servicio: se
// normaliza el texto (minúsculas, sin acentos, espacios colapsados) antes de
// comparar, así "Dermatologia" y "Dermatología" se reconocen como la misma
// especialidad. Sin eso, el catálogo se llenaba de especialidades casi
// duplicadas por un clásico error de tipeo.
class EspecialidadService
{
    // Repositorio de especialidades (capa de persistencia)
    private EspecialidadRepository $repo;

    /**
     * Constructor con inyección de dependencias
     * @param EspecialidadRepository $repo
     */
    public function __construct(EspecialidadRepository $repo)
    {
        $this->repo = $repo;
    }

    // ============================================================
    // CONSULTAS
    // ============================================================

    /**
     * Lista las especialidades del catálogo
     * @param bool $soloActivas Si true (por defecto) oculta las dadas de baja
     * @return array Lista de especialidades
     */
    public function listar(bool $soloActivas = true): array
    {
        return $this->repo->obtenerTodas($soloActivas);
    }

    /**
     * Obtiene una especialidad por su id
     * @param int $id ID de la especialidad
     * @return array Datos de la especialidad
     * @throws RuntimeException Si no existe (404)
     */
    public function obtenerPorId(int $id): array
    {
        $especialidad = $this->repo->obtenerPorId($id);
        if ($especialidad === null) {
            throw new \RuntimeException('Especialidad no encontrada', 404);
        }
        return $especialidad;
    }

    // ============================================================
    // ALTA Y MODIFICACIÓN
    // ============================================================

    /**
     * Crea una especialidad en el catálogo
     * @param string $nombre Nombre informado
     * @return array La especialidad creada
     * @throws InvalidArgumentException Si el nombre falta o ya existe (422/409)
     */
    public function crear(string $nombre): array
    {
        $nombre = $this->normalizarNombre($nombre);

        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la especialidad es obligatorio', 422);
        }

        // --------------------------------------------------
        // DUPLICADOS
        // --------------------------------------------------
        // Se compara contra todas las especialidades, no solo contra una
        // búsqueda exacta, porque la comparación tiene que ignorar tildes y
        // mayúsculas: "Cardiologia" y "Cardiología" son la misma especialidad
        // y meter las dos produce un catálogo confuso.
        $this->verificarDuplicado($nombre, null);

        $id = $this->repo->crear($nombre);

        return ['id' => $id, 'nombre' => $nombre];
    }

    /**
     * Cambia el nombre de una especialidad
     * @param int $id ID de la especialidad
     * @param string $nombre Nombre nuevo
     * @return array La especialidad ya actualizada
     * @throws RuntimeException Si la especialidad no existe (404)
     * @throws InvalidArgumentException Si el nombre falta o ya existe (422/409)
     */
    public function actualizar(int $id, string $nombre): array
    {
        // Verifica que exista: si no, el UPDATE no cambiaría nada y la API
        // respondería "actualizado" sobre una especialidad inexistente
        $this->obtenerPorId($id);

        $nombre = $this->normalizarNombre($nombre);

        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la especialidad es obligatorio', 422);
        }

        // Se pasa el id para que la propia especialidad no cuente como
        // duplicado de sí misma al guardar el mismo nombre
        $this->verificarDuplicado($nombre, $id);

        $this->repo->actualizar($id, $nombre);

        return ['id' => $id, 'nombre' => $nombre];
    }

    // ============================================================
    // BAJA
    // ============================================================

    /**
     * Elimina una especialidad del catálogo
     *
     * No se borra si hay profesionales activos asociados: dejarla huérfana
     * rompería el filtro por especialidad del listado de médicos, que
     * resuelve por nombre. Por eso se informa el conflicto con un 409 en
     * lugar de borrar en silencio.
     *
     * @param int $id ID de la especialidad
     * @throws RuntimeException Si no existe (404) o tiene profesionales (409)
     */
    public function eliminar(int $id): void
    {
        // Verifica que exista
        $this->obtenerPorId($id);

        $conteo = $this->repo->contarMedicos($id);
        if ($conteo > 0) {
            throw new \RuntimeException(
                'No se puede eliminar la especialidad: tiene ' . $conteo . ' médico(s) asociado(s)',
                409
            );
        }

        $this->repo->eliminar($id);
    }

    // ============================================================
    // REGLAS INTERNAS
    // ============================================================

    /**
     * Limpia el nombre de una especialidad
     *
     * Colapsa espacios y acota la longitud. El límite protege la base de
     * un nombre absurdo ("especialidad de 500 caracteres") que después
     * rompería los listados y los formularios.
     *
     * @param string $nombre Nombre crudo
     * @return string Nombre limpio
     */
    private function normalizarNombre(string $nombre): string
    {
        $nombre = trim($nombre);

        // Se colapsan los espacios repetidos: "Clínica   Médica" = "Clínica Médica"
        $nombre = (string)preg_replace('/\s+/', ' ', $nombre);

        return mb_substr($nombre, 0, 100, 'UTF-8');
    }

    /**
     * Verifica que no exista otra especialidad con el mismo nombre
     * @param string $nombre Nombre ya normalizado
     * @param int|null $idIgnorar ID a excluir de la comparación (al editar)
     * @throws InvalidArgumentException Con código 409 si ya existe
     */
    private function verificarDuplicado(string $nombre, ?int $idIgnorar = null): void
    {
        $referencia = $this->claveComparacion($nombre);

        foreach ($this->repo->obtenerTodas(false) as $otra) {
            // Al editar, la especialidad con el mismo id no es un duplicado:
            // es ella misma
            if ($idIgnorar !== null && (int)$otra['id'] === $idIgnorar) {
                continue;
            }

            if ($this->claveComparacion($otra['nombre']) === $referencia) {
                throw new \InvalidArgumentException('Ya existe una especialidad con ese nombre', 409);
            }
        }
    }

    /**
     * Reduce un nombre a una forma comparable
     *
     * Minúsculas, sin tildes y sin espacios sobrantes, para que dos
     * escrituras distintas de la misma especialidad se reconozcan.
     *
     * @param string $nombre Nombre a comparar
     * @return string Clave de comparación
     */
    private function claveComparacion(string $nombre): string
    {
        $nombre = mb_strtolower(trim($nombre), 'UTF-8');

        // Se quitan las tildes con la misma tabla de sustitución que usa
        // AuthService: es la única forma de comparar en PHP sin depender
        // de la extensión intl, que no está garantizada en el hosting
        $conAcentos = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'];
        $sinAcentos = ['a', 'e', 'i', 'o', 'u', 'u', 'a', 'e', 'i', 'o', 'u', 'u'];
        $nombre = str_replace($conAcentos, $sinAcentos, $nombre);

        return (string)preg_replace('/\s+/', ' ', $nombre);
    }
}
