<?php
// ============================================================
// persistence/UsuarioRepository.php - Acceso a datos de usuarios
// ============================================================
// Módulo: "Gestión de roles y permisos"
//
// Es el ÚNICO archivo del módulo que escribe SQL. Recibe PDO por inyección
// de dependencias (igual que el resto de los repositorios del proyecto) y
// devuelve datos limpios: no decide códigos HTTP ni lee $_REQUEST.
//
// UNA NOTA SOBRE LA COLUMNA `password`
// ------------------------------------
// Ninguna consulta de este repositorio trae `password` ni `password_hash`.
// No es una costumbre ni un descuido: es una decisión de seguridad. Esta clase
// alimenta una pantalla de listado de usuarios y un endpoint de cambio de
// rol, y ninguno de los dos necesita el hash. Cuanto menos dato viaje
// desde la base hacia el controlador, menos margen hay de que termine en un
// log o en una respuesta HTTP. El hash se sigue leyendo donde hace falta,
// adentro de AuthService, en una consulta que solo trae lo que verifica.
class UsuarioRepository implements UsuarioRepositoryInterface
{
    // Conexión a MySQL usada para ejecutar las consultas
    private PDO $pdo;

    /**
     * @param PDO $pdo Conexión ya abierta
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @inheritdoc
     */
    public function obtenerPaginado(int $pagina, int $porPagina, string $busqueda, string $rol): array
    {
        // OFFSET = (pagina - 1) * porPagina. Es la fórmula estándar para
        // saltar las páginas anteriores. A costa de que en páginas muy
        // altas MySQL tiene que descartar las anteriores: con el volumen de
        // este proyecto no es un problema, y migrar a paginación por cursor
        // solo se justifica si el listado crece a decenas de miles de filas.
        $offset = ($pagina - 1) * $porPagina;

        // NOTA SOBRE LIMIT Y OFFSET INTERPOLADOS
        // ---------------------------------------
        // Más abajo $porPagina y $offset van concatenados en el SQL en lugar
        // de ir como placeholders ?. Es deliberado, y la seguridad depende de
        // la declaración de tipos de la firma: ambos parámetros son int, así
        // que PHP se garantiza que a este punto son enteros. Si alguien
        // llegara con un string, PHP lanza TypeError antes de que la
        // consulta se arme.
        //
        // La razón de no usar placeholders es que MySQL no los acepta de
        // forma fiable en LIMIT/OFFSET cuando las consultas preparadas no
        // están emuladas, y este proyecto usa PDO nativo. La alternativa
        // sería interpolated=0 o usar consultas emuladas, que es peor.
        // El ORDER BY id de más abajo es constante, no viene de la
        // petición, y por eso tampoco lleva placeholder.

        // ------------------------------------------------------------
        // CONSTRUCCIÓN DEL WHERE
        // ------------------------------------------------------------
        // El WHERE se arma por partes con placeholders de PDO (?).
        //
        // La razón de armarlo dinámico y no escribir tres consultas enteras
        // (una por combinación de filtros) es que las combinaciones de
        // "sin filtro" + "con búsqueda" + "con rol" + "con ambos" son cuatro,
        // y con dos filtros más serían ocho. Las cuatro consultas se
        // desincronizan en cuanto alguien agrega un ORDER BY.
        //
        // Los valores NUNCA se concatenan en el SQL. Van siempre como
        // placeholder, y es el PDO el que los escapa. Concatenar
        // $busqueda directamente en la consulta sería inyección SQL.
        $where = [];
        $params = [];

        // Filtro de búsqueda parcial. Se busca en nombre y email con LIKE,
        // que es lo que espera la persona que escribe en el buscador: "buscar
        // García" tiene que devolver todo lo que se parezca, no una
        // coincidencia exacta.
        if ($busqueda !== '') {
            // El % se pone en el VALOR del parámetro, no en el SQL. Si fuera
            // parte de la cadena del LIKE, el PDO lo trataría como texto
            // literal y no como comodín.
            $where[] = "(nombre LIKE ? OR email LIKE ?)";
            $params[] = '%' . $busqueda . '%';
            $params[] = '%' . $busqueda . '%';
        }

        // Filtro EXACTO de rol. Distinto del buscador a propósito: acá se
        // quiere "los administradores", no "los usuarios cuyo nombre tenga
        // algo de admin".
        if ($rol !== '') {
            $where[] = 'tipo_usuario = ?';
            $params[] = $rol;
        }

        // Si no se agregó ninguna condición, el WHERE queda como cadena
        // vacía en lugar de "WHERE" suelto, que sería SQL inválido.
        $sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        // ------------------------------------------------------------
        // COUNT: total de registros que cumplen el filtro
        // ------------------------------------------------------------
        // Va en una consulta aparte del SELECT principal porque el total
        // tiene que contar TODO lo filtrado, no lo de la página actual. Por
        // eso no lleva LIMIT ni OFFSET.
        $sqlCount = "SELECT COUNT(*) AS total FROM usuarios" . $sqlWhere;
        $stmtCount = $this->pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        // fetchColumn() devuelve la primera columna de la primera fila, que
        // es exactamente el COUNT. Es más liviano que fetch() porque no arma
        // un arreglo asociativo para un solo número.
        $total = (int)$stmtCount->fetchColumn();

        // ------------------------------------------------------------
        // SELECT: los datos de la página pedida
        // ------------------------------------------------------------
        // Las columnas se listan una por una y en ese orden por dos motivos:
        // el primero es que la pantalla de permisos tiene que mostrar
        // exactamente esos datos, y el segundo es que un SELECT * haría que
        // agregar una columna en el futuro cambiara la respuesta de la API
        // sin que nadie lo pidiera.
        //
        // tipo_usuario se devuelve como `rol` (alias) para que el frontend
        // lea el mismo nombre que ya usa en el claim del JWT. Así la
        // pantalla de permisos y el token hablan el mismo idioma.
        $sql = "SELECT id, email, nombre, tipo_usuario AS rol, id_paciente, id_medico, activo, creado_at
                FROM usuarios"
            . $sqlWhere
            // El orden es por id y no por nombre: el id es el índice
            // PRIMARY KEY, así que MySQL lo resuelve sin archivos
            // temporales. Ordenar por nombre sería más lindo de leer, pero
            // sobre esta tabla no se justifica el costo.
            . " ORDER BY id ASC"
            . " LIMIT $porPagina OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);

        // execute() con el arreglo de parámetros los vincula a los
        // placeholders en el orden en que se protegieron.
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Se arma la misma envoltura que usan el resto de los listados del
        // proyecto, para que el PagedList del frontend pueda paginar igual
        // en los tres módulos.
        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            // ceil() redondea hacia arriba: con 21 registros y páginas de 10
            // hay 3 páginas, no 2. Sin el ceil, la última fila nunca se
            // alcanzaría.
            'total_paginas' => $porPagina > 0 ? (int)ceil($total / $porPagina) : 1,
        ];
    }

    /**
     * @inheritdoc
     */
    public function obtenerPorId(int $id): ?array
    {
        // prepare() + execute() con el id como parámetro. El placeholder
        // evita que el id se interprete como parte del SQL.
        $stmt = $this->pdo->prepare(
            "SELECT id, email, nombre, tipo_usuario AS rol, id_paciente, id_medico, activo, creado_at
             FROM usuarios
             WHERE id = ?"
        );
        $stmt->execute([$id]);

        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // fetch() devuelve false cuando no hay fila. Se traduce a null
        // porque para el servicio "no existe" es un dato de la API, no un
        // error de la consulta: las dos cosas se distinguen mejor así.
        return $fila === false ? null : $fila;
    }

    /**
     * @inheritdoc
     */
    public function actualizarRol(int $id, string $rol): bool
    {
        // UPDATE ... WHERE id = ? con el rol como parámetro. Los dos van
        // por placeholder: son los dos valores que vienen de la petición y
        // por lo tanto los dos que no se pueden concatenar.
        $stmt = $this->pdo->prepare(
            'UPDATE usuarios
             SET tipo_usuario = ?
             WHERE id = ?'
        );
        $stmt->execute([$rol, $id]);

        // rowCount() devuelve cuántas filas cambiaron. Se usa para que un
        // id inexistente no se reporte como "actualizado": la API contesta
        // con el resultado real de la escritura, no con un "OK" incondicional.
        return $stmt->rowCount() > 0;
    }

    /**
     * @inheritdoc
     */
    public function contarActivosConRol(string $rol): int
    {
        // Se cuenta con activo = 1 a propósito. Un administrador desactivado
        // no puede entrar ni administrar, así que no sirve de respaldo: si
        // se contara también, la base podría quedarse sin ningún admin
        // operativo sin que la regla lo detectara.
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM usuarios
             WHERE tipo_usuario = ? AND activo = 1'
        );
        $stmt->execute([$rol]);

        return (int)$stmt->fetchColumn();
    }
}
