<?php
// Reglas de negocio de médicos en la variante backend.
// ============================================================
// services/MedicoService.php - Capa de negocio (lógica de aplicación)
// ============================================================
// Service = Servicio (capa de negocio)
// Es la capa intermedia entre el Controlador y el Repositorio.
// Contiene TODA la lógica de negocio: validaciones, reglas,
// transformaciones de datos, decisiones.
//
// NO debe tener código SQL ni código HTTP.
// Solo llama al repositorio para guardar/obtener datos.
// ============================================================

// Clase del servicio de médicos: contiene las reglas de negocio de la entidad
class MedicoService
{
    // Repositorio que usa para acceder a los datos
    private MedicoRepository $repo;

    /**
     * Constructor: recibe el repositorio por inyección de dependencias
     * 
     * @param MedicoRepository $repo Repositorio de médicos
     */
    public function __construct(MedicoRepository $repo)
    {
        // Inyecta y guarda el repositorio en la propiedad para usarlo en toda la clase
        $this->repo = $repo;
    }

    /**
     * Obtiene todos los médicos
     * Pasa directamente la llamada al repositorio (sin lógica adicional)
     * 
     * @return array Lista de médicos
     */
    public function obtenerTodos(): array
    {
        // Delega la consulta al repositorio (capa de persistencia)
        return $this->repo->obtenerTodos();
    }

    /**
     * Obtiene una página de médicos (paginado)
     * Regla de paginado: nunca se trae toda la tabla, solo la página pedida.
     * Valida y acota los parámetros (página >= 1, por_página entre 1 y 100).
     *
     * @param int $pagina Número de página (empieza en 1)
     * @param int $porPagina Cantidad de items por página
     * @param string $busqueda Texto de búsqueda (nombre, matrícula o especialidad)
     * @return array Estructura paginada: { items, total, pagina, por_pagina, total_paginas }
     */
    public function obtenerPaginado(int $pagina = 1, int $porPagina = 10, string $busqueda = ''): array
    {
        // Sanitiza y acota la búsqueda (anti-XSS + espacios; el LIKE se arma en el repo)
        $busqueda = strip_tags(trim($busqueda));
        // Normaliza los parámetros numéricos para evitar valores inválidos
        $pagina = max(1, (int)$pagina);                     // La página mínima es 1
        $porPagina = max(1, min(100, (int)$porPagina));     // Entre 1 y 100 items por página

        // Total de registros que coinciden (necesario para calcular las páginas)
        $total = $this->repo->contar($busqueda);
        // ceil() redondea hacia arriba: 37 registros con 10 x página = 4 páginas
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
     * Obtiene un médico por su ID
     * VALIDA que el médico exista, si no lanza una excepción
     * 
     * @param int $id ID del médico
     * @return array Datos del médico
     * @throws RuntimeException Si el médico no existe (código 404)
     */
    public function obtenerPorId(int $id): array
    {
        // Pide al repositorio el médico por su ID
        $medico = $this->repo->obtenerPorId($id);
        // Valida que el repositorio haya devuelto datos del médico
        if (!$medico) {
            // RuntimeException = excepción en tiempo de ejecución
            // El código 404 indica "No encontrado"
            throw new \RuntimeException('Médico no encontrado', 404);
        }
        // Devuelve los datos del médico encontrado
        return $medico;
    }

    /**
     * Crea un nuevo médico
     * VALIDA que el nombre sea obligatorio y no esté vacío
     * 
     * @param array $data Datos del médico: nombre, matricula, especialidad
     * @return array El médico recién creado (con su ID)
     * @throws InvalidArgumentException Si el nombre está vacío
     */
    public function crear(array $data): array
    {
        // trim() elimina espacios en blanco al inicio y final de un string
        // ?? null: si no existe la clave 'nombre', usa null
        // empty() verifica si está vacío (null, '', false, 0, [])
        // Valida que el nombre sea obligatorio y no esté vacío (ni solo espacios)
        if (empty(trim($data['nombre'] ?? ''))) {
            // InvalidArgumentException = excepción por argumento inválido
            // El código 422 indica "Unprocessable Entity" (validación fallida)
            throw new \InvalidArgumentException('El nombre del médico es obligatorio', 422);
        }

        // Sanitiza el nombre: elimina etiquetas HTML/JS (anti-XSS) y espacios extremos
        $nombre = strip_tags(trim($data['nombre']));
        // Valida la longitud máxima del nombre
        if (strlen($nombre) > 150) {
            // HTTP 422: el nombre supera la longitud máxima permitida
            throw new \InvalidArgumentException('El nombre no puede superar los 150 caracteres', 422);
        }

        // Sanitización de entradas (módulo "Seguridad Básica - Validación")
        // Limpia la matrícula (anti-XSS + espacios) con valor por defecto ''
        $matricula = strip_tags(trim($data['matricula'] ?? ''));
        // Limpia la especialidad (anti-XSS + espacios) con valor por defecto ''
        $especialidad = strip_tags(trim($data['especialidad'] ?? ''));
        // Valida las longitudes máximas de ambos campos
        if (strlen($matricula) > 50 || strlen($especialidad) > 100) {
            // HTTP 422: algún campo supera la longitud máxima configurada
            throw new \InvalidArgumentException('Algunos campos superan la longitud máxima', 422);
        }

        // Inserta el médico llamando al repositorio
        // Llama al repositorio con los campos ya sanitizados y validados
        $id = $this->repo->crear([
            'nombre' => $nombre,
            'matricula' => $matricula,
            'especialidad' => $especialidad,
            'activo' => 1,  // Por defecto el médico se crea ACTIVO
        ]);

        // Devuelve el médico recién creado (con todos sus datos)
        // Recupera y devuelve el médico completo a partir del ID generado
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Actualiza un médico existente
     * VALIDA que el médico exista antes de actualizar
     * Solo actualiza los campos que vienen en $data
     * 
     * @param int $id ID del médico
     * @param array $data Campos a actualizar
     * @return array El médico actualizado
     * @throws RuntimeException Si el médico no existe
     * @throws InvalidArgumentException Si no hay datos para actualizar
     */
    public function actualizar(int $id, array $data): array
    {
        // Verifica que el médico exista (si no, lanza excepción 404)
        $this->obtenerPorId($id);

        // Filtra SOLO los campos permitidos y los limpia
        // Array donde se acumularán los campos válidos a actualizar
        $limpios = [];

        // isset() verifica si la clave existe y no es null
        // Si viene el nombre, se agrega al conjunto de campos a actualizar (sin sanitizar HTML)
        if (isset($data['nombre'])) {
            $limpios['nombre'] = trim($data['nombre']);
        }
        // Si viene la matrícula, se agrega al conjunto de campos a actualizar
        if (isset($data['matricula'])) {
            $limpios['matricula'] = trim($data['matricula']);
        }
        // Si viene la especialidad, se agrega al conjunto de campos a actualizar
        if (isset($data['especialidad'])) {
            $limpios['especialidad'] = trim($data['especialidad']);
        }
        // Si viene "activo", se normaliza su valor
        if (isset($data['activo'])) {
            // Operador ternario: condición ? valor_si_true : valor_si_false
            // Convierte cualquier valor a 1 o 0 (booleano a entero)
            $limpios['activo'] = $data['activo'] ? 1 : 0;
        }

        // Si después de filtrar no quedó ningún campo, lanza error
        // Valida que haya al menos un campo válido para actualizar
        if (empty($limpios)) {
            // HTTP 422: no hay datos procesables para la actualización
            throw new \InvalidArgumentException('No hay datos para actualizar', 422);
        }

        // Ejecuta la actualización en el repositorio
        // Delega la actualización con los campos ya filtrados y limpios
        $this->repo->actualizar($id, $limpios);

        // Devuelve los datos actualizados del médico
        // Recupera el médico completo con los cambios aplicados
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Elimina un médico
     * VALIDA que el médico exista antes de eliminar
     * 
     * @param int $id ID del médico a eliminar
     * @throws RuntimeException Si el médico no existe
     */
    public function eliminar(int $id): void
    {
        // Verifica que exista (lanza excepción si no)
        $this->obtenerPorId($id);
        // Desvincula las recetas asociadas antes de borrar
        // Evita errores de integridad referencial: libera las prescripciones del médico
        $this->repo->desvincularPrescripciones($id);
        // Elimina el médico
        // Ejecuta el borrado del médico en el repositorio
        $this->repo->eliminar($id);
    }
}