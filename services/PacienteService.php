<?php
// ============================================================
// services/PacienteService.php - Capa de negocio (Pacientes)
// ============================================================
// Service Layer: contiene toda la lógica de negocio
// Validaciones, reglas, transformaciones de datos

// Clase del servicio de pacientes: contiene las reglas de negocio de la entidad
class PacienteService
{
    // Repositorio de pacientes usado para acceder a los datos
    private PacienteRepository $repo;

    /**
     * Constructor: recibe el repositorio por inyección de dependencias
     * @param PacienteRepository $repo
     */
    public function __construct(PacienteRepository $repo)
    {
        // Inyecta y guarda el repositorio en la propiedad para usarlo en toda la clase
        $this->repo = $repo;
    }

    /**
     * Obtiene todos los pacientes
     * @return array Lista de pacientes
     */
    public function obtenerTodos(): array
    {
        // Delega la consulta al repositorio (capa de persistencia)
        return $this->repo->obtenerTodos();
    }

    /**
     * Obtiene una página de pacientes (paginado)
     * Regla de paginado: nunca se trae toda la tabla, solo la página pedida.
     * Valida y acota los parámetros (página >= 1, por_página entre 1 y 100).
     * @param int $pagina Número de página (empieza en 1)
     * @param int $porPagina Cantidad de items por página
     * @param string $busqueda Texto de búsqueda (nombre, DNI u obra social)
     * @return array Estructura paginada: { items, total, pagina, por_pagina, total_paginas }
     */
    public function obtenerPaginado(int $pagina = 1, int $porPagina = 10, string $busqueda = ''): array
    {
        // Sanitiza y acota la búsqueda (anti-XSS + espacios; el LIKE se arma en el repo)
        $busqueda = strip_tags(trim($busqueda));
        // Normaliza los parámetros numéricos para evitar valores inválidos
        $pagina = max(1, (int)$pagina);                       // La página mínima es 1
        $porPagina = max(1, min(100, (int)$porPagina));       // Entre 1 y 100 items por página

        // Total de registros que coinciden (necesario para calcular las páginas)
        $total = $this->repo->contar($busqueda);
        // ceil() redondea hacia arriba: 107 registros con 10 x página = 11 páginas
        $totalPaginas = (int) ceil($total / $porPagina);

        // Si la página pedida supera el total, se acota a la última (evita páginas vacías)
        if ($pagina > $totalPaginas && $totalPaginas > 0) {
            $pagina = $totalPaginas;
        }

        // OFFSET = cuántas filas saltar: página 1 -> 0, página 2 -> porPagina, etc.
        $offset = ($pagina - 1) * $porPagina;

        // Pide al repositorio solo los registros de esta página
        $items = $this->repo->obtenerPaginado($offset, $porPagina, $busqueda);

        // Devuelve la estructura paginada completa para que el frontend dibuje los controles
        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => $totalPaginas,
        ];
    }

    /**
     * Obtiene un paciente por ID
     * VALIDA que exista
     * @param int $id ID del paciente
     * @return array Datos del paciente
     * @throws RuntimeException Si no existe (404)
     */
    public function obtenerPorId(int $id): array
    {
        // Pide al repositorio el paciente por su ID
        $paciente = $this->repo->obtenerPorId($id);
        // Valida que el repositorio haya devuelto datos del paciente
        if (!$paciente) {
            // HTTP 404: el paciente no existe en la base de datos
            throw new \RuntimeException('Paciente no encontrado', 404);
        }
        // Devuelve los datos del paciente encontrado
        return $paciente;
    }

    /**
     * Obtiene un paciente por DNI
     * @param string $dni DNI del paciente
     * @return array|null Datos del paciente o null
     */
    public function obtenerPorDni(string $dni): ?array
    {
        // Normaliza el DNI (sin espacios extremos) y delega la consulta al repositorio
        return $this->repo->obtenerPorDni(trim($dni));
    }

    /**
     * Crea un nuevo paciente
     * VALIDA que el nombre sea obligatorio
     * VALIDA que no exista otro paciente con el mismo DNI
     * @param array $data Datos del paciente
     * @return array El paciente creado
     * @throws InvalidArgumentException Si hay error de validación
     */
    public function crear(array $data): array
    {
        // Validar nombre obligatorio + sanitizar (anti-XSS) y limitar
        // strip_tags() elimina etiquetas HTML/JS (mitiga XSS); trim() limpia espacios extremos
        $nombre = strip_tags(trim($data['nombre'] ?? ''));
        // Valida que el nombre no esté vacío y no supere los 150 caracteres
        if (empty($nombre) || strlen($nombre) > 150) {
            // HTTP 422: el nombre es obligatorio y con longitud máxima de 150
            throw new \InvalidArgumentException('El nombre del paciente es obligatorio (máx. 150 caracteres)', 422);
        }

        // Sanitiza el DNI (anti-XSS) y elimina espacios extremos; valor por defecto ''
        $dni = strip_tags(trim($data['dni'] ?? ''));
        // Valida la longitud máxima del DNI
        if (strlen($dni) > 50) {
            // HTTP 422: el DNI supera la longitud máxima permitida
            throw new \InvalidArgumentException('El DNI no puede superar los 50 caracteres', 422);
        }

        // Validar DNI único (si se proporciona)
        // Solo se valida la unicidad si el DNI viene completo
        if (!empty($dni)) {
            // Consulta si ya existe un paciente con ese DNI
            $existe = $this->repo->obtenerPorDni($dni);
            // Si el repositorio devolvió un paciente, el DNI está duplicado
            if ($existe) {
                // HTTP 422: impide registrar dos pacientes con el mismo DNI
                throw new \InvalidArgumentException('Ya existe un paciente con este DNI', 422);
            }
        }

        // Crear el paciente
        // Llama al repositorio con los campos validados y sanitizados
        $id = $this->repo->crear([
            'dni' => $dni,
            'nombre' => $nombre,
            'id_obra_social' => $data['id_obra_social'] ?? 1,
            'activo' => 1,
        ]);

        // Devolver el paciente creado
        // Recupera el paciente completo a partir del ID generado
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Actualiza un paciente
     * VALIDA que exista
     * @param int $id ID del paciente
     * @param array $data Campos a actualizar
     * @return array El paciente actualizado
     * @throws RuntimeException Si no existe
     * @throws InvalidArgumentException Si no hay datos
     */
    public function actualizar(int $id, array $data): array
    {
        // Verificar que existe
        // Si el paciente no existe, obtenerPorId() lanza excepción 404
        $this->obtenerPorId($id);

        // Limpiar datos
        // Array donde se acumularán los campos válidos a actualizar
        $limpios = [];

        // Si viene el nombre, se valida y sanitiza antes de actualizar
        if (isset($data['nombre'])) {
            // Saneamiento: elimina etiquetas HTML/JS y espacios extremos
            $nombre = strip_tags(trim($data['nombre']));
            // Valida que el nombre no quede vacío ni supere los 150 caracteres
            if (empty($nombre) || strlen($nombre) > 150) {
                // HTTP 422: el nombre no cumple los requisitos
                throw new \InvalidArgumentException('El nombre no puede estar vacío (máx. 150 caracteres)', 422);
            }
            // Agrega el nombre validado al conjunto de campos a actualizar
            $limpios['nombre'] = $nombre;
        }

        // Si viene el DNI, se valida y sanitiza antes de actualizar
        if (isset($data['dni'])) {
            // Saneamiento: elimina etiquetas HTML/JS y espacios extremos
            $dni = strip_tags(trim($data['dni']));
            // Valida la longitud máxima del DNI
            if (strlen($dni) > 50) {
                // HTTP 422: el DNI supera la longitud máxima permitida
                throw new \InvalidArgumentException('El DNI no puede superar los 50 caracteres', 422);
            }
            // Agrega el DNI validado al conjunto de campos a actualizar
            $limpios['dni'] = $dni;
        }

        // Si viene la obra social, se convierte a entero y se agrega a los campos
        if (isset($data['id_obra_social'])) {
            $limpios['id_obra_social'] = (int)$data['id_obra_social'];
        }

        // Si viene "activo", se normaliza su valor
        if (isset($data['activo'])) {
            // Operador ternario: convierte cualquier valor a 1 o 0 (booleano a entero)
            $limpios['activo'] = $data['activo'] ? 1 : 0;
        }

        // Valida que haya al menos un campo válido para actualizar
        if (empty($limpios)) {
            // HTTP 422: no hay datos procesables para la actualización
            throw new \InvalidArgumentException('No hay datos para actualizar', 422);
        }

        // Actualizar
        // Delega la actualización en el repositorio con los campos ya filtrados
        $this->repo->actualizar($id, $limpios);

        // Devolver actualizado
        // Recupera el paciente completo con los cambios aplicados
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Elimina un paciente
     * VALIDA que exista
     * @param int $id ID del paciente
     * @throws RuntimeException Si no existe
     */
    public function eliminar(int $id): void
    {
        $this->obtenerPorId($id);  // Verifica que exista
        // Ejecuta el borrado del paciente en el repositorio
        $this->repo->eliminar($id);
    }
}