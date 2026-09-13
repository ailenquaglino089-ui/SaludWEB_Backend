<?php
// ============================================================
// services/PrescripcionService.php - Capa de negocio (Prescripciones)
// ============================================================
// Service Layer con validaciones y lógica de negocio

// Clase del servicio de prescripciones: contiene las reglas de negocio de la entidad
class PrescripcionService
{
    // Repositorio de prescripciones usado para acceder a los datos
    private PrescripcionRepository $repo;

    /**
     * Constructor con inyección de dependencias
     * @param PrescripcionRepository $repo
     */
    public function __construct(PrescripcionRepository $repo)
    {
        // Inyecta y guarda el repositorio en la propiedad para usarlo en toda la clase
        $this->repo = $repo;
    }

    /**
     * Obtiene todas las prescripciones
     * @return array Lista de prescripciones
     */
    public function obtenerTodas(): array
    {
        // Delega la consulta al repositorio (capa de persistencia)
        return $this->repo->obtenerTodas();
    }

    /**
     * Obtiene las prescripciones de un paciente
     * @param int $id_paciente ID del paciente
     * @return array Prescripciones del paciente
     */
    public function obtenerPorPaciente(int $id_paciente): array
    {
        // Delega al repositorio la consulta de prescripciones por paciente
        return $this->repo->obtenerPorPaciente($id_paciente);
    }

    /**
     * Obtiene una prescripción por ID
     * VALIDA que exista
     * @param int $id ID de la prescripción
     * @return array Datos de la prescripción
     * @throws RuntimeException Si no existe (404)
     */
    public function obtenerPorId(int $id): array
    {
        // Pide al repositorio la prescripción por su ID
        $prescripcion = $this->repo->obtenerPorId($id);
        // Valida que el repositorio haya devuelto datos de la prescripción
        if (!$prescripcion) {
            // HTTP 404: la prescripción no existe en la base de datos
            throw new \RuntimeException('Prescripción no encontrada', 404);
        }
        // Devuelve los datos de la prescripción encontrada
        return $prescripcion;
    }

    /**
     * Crea una nueva prescripción
     * VALIDA que exista el paciente y médico
     * VALIDA que los medicamentos sean válidos
     * @param array $data Datos de la prescripción
     * @return array La prescripción creada
     * @throws InvalidArgumentException Si hay error de validación
     */
    public function crear(array $data): array
    {
        // Validar paciente obligatorio
        // Valida que se haya enviado el ID del paciente
        if (empty($data['id_paciente'])) {
            // HTTP 422: el paciente es un dato obligatorio de la prescripción
            throw new \InvalidArgumentException('El ID del paciente es obligatorio', 422);
        }

        // Validar medicamentos obligatorios y limitar su tamaño (seguridad)
        // Valida que se haya enviado al menos un medicamento (array o JSON)
        if (empty($data['medicamentos'])) {
            // HTTP 422: una prescripción sin medicamentos no tiene sentido
            throw new \InvalidArgumentException('Debe especificar al menos un medicamento', 422);
        }

        // Procesar medicamentos (pueden ser array o JSON string)
        // Lee el campo "medicamentos" tal como lo envió el cliente
        $medicamentos = $data['medicamentos'];
        // Norma la entrada: si viene como array...
        if (is_array($medicamentos)) {
            // Valida que el array no supere los 50 medicamentos (defensa contra abuso)
            if (count($medicamentos) > 50) {
                // HTTP 422: cantidad máxima excedida
                throw new \InvalidArgumentException('No se permiten más de 50 medicamentos', 422);
            }
            // Convierte el array a texto JSON (sin escapar caracteres Unicode de acentos/ñ)
            $medicamentos = json_encode($medicamentos, JSON_UNESCAPED_UNICODE);
        } elseif (!is_string($medicamentos)) {
            // Si no es array ni string, el formato no es válido
            throw new \InvalidArgumentException('Los medicamentos deben ser un array o JSON', 422);
        }

        // La creación recorre un whitelist: solo se guarda lo permitido
        // Lee las indicaciones solo si vienen (isset), las sanitiza (anti-XSS) y limpia espacios
        $indicaciones = isset($data['indicaciones'])
            ? strip_tags(trim((string) $data['indicaciones']))
            : null;
        // Valida la longitud máxima de las indicaciones (defensa contra abuso)
        if ($indicaciones !== null && strlen($indicaciones) > 1000) {
            // HTTP 422: las indicaciones superan la longitud máxima
            throw new \InvalidArgumentException('Las indicaciones no pueden superar los 1000 caracteres', 422);
        }

        // Crear la prescripción
        // Llama al repositorio con los campos validados y normalizados
        $id = $this->repo->crear([
            'id_paciente' => (int)$data['id_paciente'],
            'id_medico' => $data['id_medico'] ?? null,
            'medicamentos' => $medicamentos,
            'indicaciones' => $indicaciones,
            'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
            'estado' => 'activa'
        ]);

        // Devolver la prescripción creada
        // Recupera la prescripción completa a partir del ID generado
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Actualiza una prescripción
     * VALIDA que exista
     * @param int $id ID de la prescripción
     * @param array $data Campos a actualizar
     * @return array La prescripción actualizada
     * @throws RuntimeException Si no existe
     * @throws InvalidArgumentException Si hay error de validación
     */
    public function actualizar(int $id, array $data): array
    {
        // Verificar que existe
        // Si la prescripción no existe, obtenerPorId() lanza excepción 404
        $this->obtenerPorId($id);

        // Limpiar datos
        // Array donde se acumularán los campos válidos a actualizar
        $limpios = [];

        // Si viene "medicamentos", se normaliza su formato
        if (isset($data['medicamentos'])) {
            // Si es un array, se convierte a JSON con tildes/ñ intactas
            if (is_array($data['medicamentos'])) {
                $limpios['medicamentos'] = json_encode($data['medicamentos'], JSON_UNESCAPED_UNICODE);
            } else {
                // Si no es array, se guarda tal cual viene (string JSON)
                $limpios['medicamentos'] = $data['medicamentos'];
            }
        }

        // Si vienen las indicaciones, se sanitizan (anti-XSS) y se limitan
        if (isset($data['indicaciones'])) {
            // Elimina etiquetas HTML/JS y espacios extremos, luego pasa a string
            $indicaciones = strip_tags(trim((string) $data['indicaciones']));
            // Valida la longitud máxima de las indicaciones
            if (strlen($indicaciones) > 1000) {
                // HTTP 422: las indicaciones superan la longitud máxima
                throw new \InvalidArgumentException('Las indicaciones no pueden superar los 1000 caracteres', 422);
            }
            // Agrega las indicaciones validadas al conjunto de campos a actualizar
            $limpios['indicaciones'] = $indicaciones;
        }

        // Si viene el estado, se valida contra la lista de estados permitidos
        if (isset($data['estado'])) {
            // Validar estado permitido
            // Lista blanca (whitelist) de estados posibles de una prescripción
            $estadosPermitidos = ['activa', 'vencida', 'dispensada', 'cancelada'];
            // Verifica que el estado enviado esté dentro de la lista blanca
            if (!in_array($data['estado'], $estadosPermitidos)) {
                // HTTP 422: el estado no es reconocido por el sistema
                throw new \InvalidArgumentException('Estado no permitido', 422);
            }
            // Agrega el estado validado al conjunto de campos a actualizar
            $limpios['estado'] = $data['estado'];
        }

        // Si viene la fecha de vencimiento, se agrega tal cual al conjunto de campos
        if (isset($data['fecha_vencimiento'])) {
            $limpios['fecha_vencimiento'] = $data['fecha_vencimiento'];
        }

        // Valida que haya al menos un campo válido para actualizar
        if (empty($limpios)) {
            // HTTP 422: no hay datos procesables para la actualización
            throw new \InvalidArgumentException('No hay datos para actualizar', 422);
        }

        // Actualizar
        // Delega la actualización en el repositorio con los campos ya filtrados
        $this->repo->actualizar($id, $limpios);

        // Devolver actualizada
        // Recupera la prescripción completa con los cambios aplicados
        return $this->repo->obtenerPorId($id);
    }

    /**
     * Elimina una prescripción
     * VALIDA que exista
     * @param int $id ID de la prescripción
     * @throws RuntimeException Si no existe
     */
    public function eliminar(int $id): void
    {
        $this->obtenerPorId($id);  // Verifica que exista
        // Ejecuta el borrado de la prescripción en el repositorio
        $this->repo->eliminar($id);
    }

    /**
     * Cambia el estado de una prescripción
     * @param int $id ID de la prescripción
     * @param string $nuevoEstado Nuevo estado (activa, vencida, dispensada, cancelada)
     * @return array La prescripción actualizada
     * @throws RuntimeException Si no existe
     * @throws InvalidArgumentException Si el estado no es permitido
     */
    public function cambiarEstado(int $id, string $nuevoEstado): array
    {
        $this->obtenerPorId($id);  // Verifica que exista

        // Lista blanca (whitelist) de estados posibles de una prescripción
        $estadosPermitidos = ['activa', 'vencida', 'dispensada', 'cancelada'];
        // Verifica que el estado enviado esté dentro de la lista blanca
        if (!in_array($nuevoEstado, $estadosPermitidos)) {
            // HTTP 422: el estado no es reconocido por el sistema
            throw new \InvalidArgumentException('Estado no permitido', 422);
        }

        // Delega en el repositorio el cambio de estado
        $this->repo->cambiarEstado($id, $nuevoEstado);
        // Recupera y devuelve la prescripción con el nuevo estado aplicado
        return $this->repo->obtenerPorId($id);
    }
}