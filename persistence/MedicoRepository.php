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
    public function obtenerTodos(array $filtros = []): array
    {
        // Se arman las condiciones una parte por vez, con placeholders, para
        // no concatenar valores. Todos los tipos de filtro aceptados son
        // enteros o el valor fijo 1, así que no hay superficie de inyección.
        $where = [];
        $parametros = [];

        // Filtro por activo: es el que usan las estadísticas, que no deben
        // contar los turnos ofrecidos por profesionales dados de baja
        if (isset($filtros['activo'])) {
            $where[] = 'activo = ?';
            $parametros[] = (int)$filtros['activo'];
        }

        // Filtro por un profesional puntual
        if (!empty($filtros['id'])) {
            $where[] = 'id = ?';
            $parametros[] = (int)$filtros['id'];
        }

        $sql = 'SELECT * FROM medicos';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        // ORDER BY activo DESC: los activos (1) aparecen primero; nombre ASC: orden alfabético dentro del grupo
        $sql .= ' ORDER BY activo DESC, nombre ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        // fetchAll() obtiene TODAS las filas como un arreglo
        // PDO::FETCH_ASSOC: cada fila es un array asociativo [columna => valor]
        // Ej: [ ['id' => 1, 'nombre' => 'Dr. Pérez'], ... ]
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene una página de médicos (paginado)
     * Evita traer TODOS los médicos en un solo request cuando hay muchos:
     * trae solo la página solicitada con LIMIT ... OFFSET ... (performance).
     * Puede filtrar por texto (nombre, matrícula o especialidad) con LIKE.
     *
     * @param int $offset Desde qué fila empezar ((página - 1) * por_página)
     * @param int $porPagina Cuántos médicos trae la página
     * @param string $busqueda Texto de búsqueda opcional
     * @param string $especialidad Filtro exacto de especialidad (opcional)
     * @param int|null $activo 1 = solo activos, 0 = solo inactivos, null = todos
     * @return array Arreglo con los médicos de la página
     */
    public function obtenerPaginado(int $offset, int $porPagina, string $busqueda = '', string $especialidad = '', ?int $activo = null): array
    {
        // SELECT base con todas las columnas de medicos
        $sql = "SELECT * FROM medicos";
        // Parámetros que se bindean después en orden (protección anti inyección SQL)
        $parametros = [];

        // Los filtros se acumulan en un arreglo de condiciones AND.
        // Se hace así (y no con un if/else) porque texto de búsqueda,
        // especialidad y estado son filtros INDEPENDIENTES: el catálogo de la
        // web los usa juntos ("los dermatólogos activos que se llamen García").
        $where = [];

        // Si hay texto de búsqueda, se filtra con LIKE en nombre, matrícula o especialidad
        if ($busqueda !== '') {
            $where[] = "(nombre LIKE ? OR matricula LIKE ? OR especialidad LIKE ?)";
            $parametros = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
        }

        // Filtro por especialidad con coincidencia EXACTA (=), no con LIKE.
        // La diferencia importa: el catálogo pide "los médicos de
        // Cardiología", y un LIKE traería también "Cardiología Pediátrica"
        // o "No-Cardiología", que no es lo que el filtro pidió.
        if ($especialidad !== '') {
            $where[] = "especialidad = ?";
            $parametros[] = $especialidad;
        }

        // Filtro por estado. El catálogo PÚBLICO lo usa con activo=1 porque un
        // profesional dado de baja no puede recibir turnos: mostrarlo ofrecería
        // un turno que después el backend no va a permitir reservar. El panel
        // de administración, en cambio, lo deja en null para ver ambos.
        if ($activo !== null) {
            $where[] = "activo = ?";
            $parametros[] = (int) $activo;
        }

        // Se aplica el WHERE solo si hay alguna condición acumulada
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        // Orden (igual que obtenerTodos) + LIMIT/OFFSET para recortar la página
        $sql .= " ORDER BY activo DESC, nombre ASC LIMIT ? OFFSET ?";

        // Consulta preparada: ningún valor se concatena al SQL, todos van por ?
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        // Se bindean primero los LIKE de la búsqueda (como texto)
        foreach ($parametros as $parametro) {
            $stmt->bindValue($i++, $parametro);
        }
        // LIMIT y OFFSET con tipo entero explícito (requerido por MySQL nativo)
        $stmt->bindValue($i++, $porPagina, PDO::PARAM_INT);
        $stmt->bindValue($i++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        // Devuelve solo los médicos de la página
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta el total de médicos (respetando la búsqueda)
     * Se usa junto a obtenerPaginado() para calcular las páginas del listado.
     *
     * @param string $busqueda Texto de búsqueda opcional
     * @param string $especialidad Filtro exacto de especialidad (opcional)
     * @param int|null $activo 1 = solo activos, 0 = solo inactivos, null = todos
     * @return int Total de médicos
     */
    public function contar(string $busqueda = '', string $especialidad = '', ?int $activo = null): int
    {
        // COUNT(*) devuelve un número, no las filas completas: es barato y rápido
        $sql = "SELECT COUNT(*) FROM medicos";

        // Mismas condiciones que obtenerPaginado(), por dos motivos: el total
        // tiene que contar exactamente lo que el listado devuelve (si no, la
        // paginación muestra páginas vacías) y se evita duplicar la lógica
        // de filtros en dos lugares distintos.
        $where = [];
        $parametros = [];

        if ($busqueda !== '') {
            $where[] = "(nombre LIKE ? OR matricula LIKE ? OR especialidad LIKE ?)";
            // Patrón LIKE único reutilizado en los tres campos
            $like = "%$busqueda%";
            $parametros = [$like, $like, $like];
        }

        if ($especialidad !== '') {
            $where[] = "especialidad = ?";
            $parametros[] = $especialidad;
        }

        if ($activo !== null) {
            $where[] = "activo = ?";
            $parametros[] = (int) $activo;
        }

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($parametros);
        } else {
            // Sin búsqueda: query() directo (no tiene parámetros, sin riesgo de inyección)
            $stmt = $this->pdo->query($sql);
        }

        // fetchColumn() devuelve el número; (int) lo garantiza como entero
        return (int) $stmt->fetchColumn();
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
     * Obtiene un médico por su número de matrícula profesional
     *
     * Módulo "Sistema de gestión de citas online": la matrícula es el
     * identificador con el que un profesional demuestra quién es al vincular
     * su cuenta de usuario con su ficha. Por eso hace falta una búsqueda
     * directa por ese campo.
     *
     * @param string $matricula Número de matrícula
     * @return array|null El médico encontrado o null si no existe
     */
    public function obtenerPorMatricula(string $matricula): ?array
    {
        // Se limpia el dato: el usuario puede escribir " 44556 " con espacios
        $matricula = trim($matricula);

        // Si viene vacío, no hay nada que buscar: se devuelve null directo
        // (una consulta con cadena vacasa devolvería cualquier coincidencia)
        if ($matricula === '') {
            return null;
        }

        // Consulta parametrizada: el ? evita inyección SQL
        $stmt = $this->pdo->prepare("SELECT * FROM medicos WHERE matricula = ? LIMIT 1");
        $stmt->execute([$matricula]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Devuelve la fila o null si no encontró nada
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
