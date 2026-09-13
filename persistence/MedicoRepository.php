<?php
// Persistencia PDO de médicos en la variante backend.
// ============================================================
// persistence/MedicoRepository.php - Capa de persistencia
// ============================================================
// Repository = Repositorio (patrón de diseño)
// Es la capa encargada de acceder a los datos (base de datos)
// Solo contiene consultas SQL, NO lógica de negocio
//
// En la arquitectura por capas:
// Controlador -> Servicio -> Repositorio -> Base de Datos
// ============================================================

// La clase implementa la interfaz MedicoRepositoryInterface: garantiza que tendrá todos los métodos del contrato
class MedicoRepository implements MedicoRepositoryInterface
{
    // PDO = PHP Data Objects (extensión para conectar a bases de datos)
    // Es la conexión a MySQL que se usa para hacer consultas
    private $pdo;

    /**
     * Constructor: recibe la conexión PDO por inyección de dependencias
     * Inyección de dependencias = pasar las dependencias desde afuera
     * en vez de crearlas adentro. Hace el código más testeable y flexible.
     *
     * @param PDO $pdo Conexión activa a la base de datos MySQL
     */
    public function __construct(PDO $pdo)
    {
        // Guarda la conexión PDO en el atributo de la instancia para usarla en todos los métodos
        $this->pdo = $pdo;
    }

    /**
     * Obtiene TODOS los médicos de la base de datos
     * Ordenados por: activos primero (activo=1), luego alfabéticamente por nombre
     * 
     * SQL: SELECT * = seleccionar todas las columnas
     * DESC = descendente (1 antes que 0)
     * ASC = ascendente (A-Z)
     * 
     * @return array Arreglo asociativo con todos los médicos
     */
    public function obtenerTodos(): array
    {
        // query() ejecuta una consulta SQL directamente (sin parámetros)
        $stmt = $this->pdo->query(
            // SELECT * : trae todas las columnas de la tabla medicos
            // ORDER BY activo DESC: los activos (1) aparecen primero; nombre ASC: orden alfabético dentro del grupo
            "SELECT * FROM medicos ORDER BY activo DESC, nombre ASC"
        );
        // fetchAll() obtiene TODAS las filas como un arreglo
        // PDO::FETCH_ASSOC: cada fila es un array asociativo [columna => valor]
        // Ej: [ ['id' => 1, 'nombre' => 'Dr. Pérez'], ... ]
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un médico por su ID
     * 
     * @param int $id Identificador único del médico
     * @return array|null El médico como array asociativo, o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        // Consulta preparada (prepared statement)
        // El ? es un marcador de posición que se reemplaza con el valor real
        // Esto PREVIENE inyección SQL (hackeo por datos maliciosos)
        $stmt = $this->pdo->prepare("SELECT * FROM medicos WHERE id = ?");

        // execute() ejecuta la consulta con los valores reales
        // Pasa [ $id ] como arreglo para reemplazar los ?
        $stmt->execute([$id]);

        // fetch() obtiene UNA sola fila (la primera)
        // Devuelve false si no hay resultados
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Operador ?: si $result es false/null, devuelve null
        // Si no, devuelve $result
        return $result ?: null;
    }

    /**
     * Crea (inserta) un nuevo médico en la base de datos
     * 
     * @param array $data Datos del médico: nombre, matricula, especialidad, activo
     * @return int El ID autogenerado del nuevo médico
     */
    public function crear(array $data): int
    {
        // INSERT INTO = insertar una nueva fila en la tabla
        // Los ? son marcadores de posición para los valores
        $stmt = $this->pdo->prepare(
            "INSERT INTO medicos (nombre, matricula, especialidad, activo) VALUES (?, ?, ?, ?)"
        );

        // Ejecuta la consulta con los datos recibidos
        // ?? = null coalescing operator: si no existe la clave, usa el valor por defecto
        // $data['matricula'] ?? null = si no viene matricula, pone null
        $stmt->execute([
            $data['nombre'],            // Obligatorio
            $data['matricula'] ?? null,  // Opcional
            $data['especialidad'] ?? null, // Opcional
            $data['activo'] ?? 1         // Por defecto activo=1 (operativo)
        ]);

        // lastInsertId() devuelve el ID autogenerado por el AUTO_INCREMENT
        // (int) asegura que sea un número entero
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza un médico existente
     * Solo actualiza los campos que vienen en $data
     * 
     * @param int $id ID del médico a actualizar
     * @param array $data Campos a actualizar (nombre, matricula, especialidad, activo)
     * @return bool true si se actualizó correctamente, false si no
     */
    public function actualizar(int $id, array $data): bool
    {
        // Construye la consulta DINÁMICAMENTE según los campos recibidos
        // array_key_exists() verifica si la clave existe en el array
        $campos = [];  // Partes SET de la consulta
        $valores = []; // Valores a reemplazar

        // Itera sobre los campos permitidos que pueden actualizarse
        foreach (['nombre', 'matricula', 'especialidad', 'activo'] as $campo) {
            // array_key_exists() = verifica si la clave está presente (incluso si es null)
            if (array_key_exists($campo, $data)) {
                $campos[] = "$campo = ?";  // Ej: "nombre = ?"
                $valores[] = $data[$campo]; // Valor correspondiente
            }
        }

        // Si no hay campos para actualizar, retorna false
        if (empty($campos)) {
            return false;
        }

        // Agrega el ID al final del arreglo de valores
        $valores[] = $id;

        // implode() une los campos con coma: "nombre = ?, matricula = ?"
        $sql = "UPDATE medicos SET " . implode(', ', $campos) . " WHERE id = ?";
        // Ej: "UPDATE medicos SET nombre = ?, matricula = ? WHERE id = ?"

        // Se prepara la consulta dinámica (los ? se rellenan con valores reales, sin riesgo de inyección SQL)
        $stmt = $this->pdo->prepare($sql);
        // execute($valores) asigna cada ? en orden; devuelve true si la actualización tuvo éxito
        return $stmt->execute($valores);
    }

    /**
     * Desvincula las recetas asociadas a un médico (setea id_medico a NULL)
     * 
     * @param int $id ID del médico
     */
    public function desvincularPrescripciones(int $id): void
    {
        // Primero asegura que la columna acepte NULL
        try {
            // exec() ejecuta una sentencia SQL de estructura (DDL) directamente: permite que id_medico sea NULL
            $this->pdo->exec("ALTER TABLE prescripciones MODIFY id_medico INT NULL");
        } catch (Exception $e) { /* ignorar */ }
        // Desvincula las recetas
        // Consulta preparada: UPDATE ... SET id_medico = NULL pone null en las prescripciones que referencian al médico
        $stmt = $this->pdo->prepare("UPDATE prescripciones SET id_medico = NULL WHERE id_medico = ?");
        // Ejecuta pasando el id del médico como parámetro (evita inyección SQL)
        $stmt->execute([$id]);
    }

    /**
     * Elimina un médico de la base de datos
     * 
     * @param int $id ID del médico a eliminar
     * @return bool true si se eliminó, false si no existía
     */
    public function eliminar(int $id): bool
    {
        // Consulta preparada: DELETE FROM borra la fila cuyo id coincida (el ? protege contra inyección SQL)
        $stmt = $this->pdo->prepare("DELETE FROM medicos WHERE id = ?");
        // Ejecuta la eliminación con el id como parámetro real
        $stmt->execute([$id]);

        // rowCount() = cantidad de filas afectadas por la consulta
        // Si se borró una fila, rowCount() > 0 -> true
        // Si no existía el médico, rowCount() == 0 -> false
        return $stmt->rowCount() > 0;
    }
}
