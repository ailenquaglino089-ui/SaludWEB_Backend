<?php
// ============================================================
// limpiar_especialidades_duplicadas.php - Depura el catálogo
// ============================================================
// Deja UNA sola especialidad por nombre, aunque las variantes estén
// escritas distinto ("Cardiologia", "Cardiología", "CARDIOlogía").
//
// POR QUÉ EXISTE
// --------------
// El servicio ya bloquea altas duplicadas comparando sin tildes ni
// mayúsculas, pero los datos viejos quedaron inconsistentes: se podía
// crear "Cardiologia" y "Cardiología" desde una versión anterior de la
// API o desde phpMyAdmin. El catálogo mostraba las dos, una con "(0)"
// profesionales, y el select de la turnera parecía repetido.
//
// QUÉ HACE
// --------
//   1. Lee TODAS las especialidades (activas e inactivas).
//   2. Las agrupa por una clave normalizada: minúsculas, sin tildes y
//      con los espacios colapsados.
//   3. Si un grupo tiene más de una fila, elige una canónica:
//      primero la que tiene más médicos activos, después la que tiene
//      más médicos en total, después la que está activa y, por último,
//      la de menor id (la más antigua).
//   4. Reasigna los médicos de las variantes a la canónica y borra las
//      variantes sobrantes.
//
// Es IDEMPOTENTE: correrlo dos veces deja exactamente lo mismo y la
// segunda corrida informa que no hay duplicados.
//
// USO
// ---
//   php limpiar_especialidades_duplicadas.php
//
// Solo toca las tablas especialidades y medicos. No modifica usuarios,
// contraseñas, turnos ni ninguna otra tabla.
// ------------------------------------------------------------
declare(strict_types=1);

// db.php abre la conexión SQLite y deja la conexión en $pdo.
require __DIR__ . '/db.php';

// Devuelve la clave que se usa para comparar nombres.
// Se define como función para que el criterio quede en un solo lugar y
// no se repita dentro del bucle.
function claveEspecialidad(string $nombre): string
{
    // Todo a minúsculas para que "Cardiología" y "cardiología" sean iguales.
    $nombre = mb_strtolower(trim($nombre), 'UTF-8');

    // Tabla de reemplazo de tildes y diéresis. Se escribe a mano para no
    // depender de la extensión intl, que no está garantizada en el hosting.
    $conAcentos = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'];
    $sinAcentos = ['a', 'e', 'i', 'o', 'u', 'u', 'a', 'e', 'i', 'o', 'u', 'u'];
    $nombre = strtr($nombre, array_combine($conAcentos, $sinAcentos));

    // Varios espacios seguidos cuentan como uno: "Medicina   General"
    // tiene que ser la misma clave que "Medicina General".
    return (string) preg_replace('/\s+/', ' ', $nombre);
}

// Trae el catálogo completo con la cantidad de médicos activos y totales.
// El LEFT JOIN conserva las especialidades sin médicos (cantidad = 0).
$sql = "SELECT e.id,
               e.nombre,
               e.activo,
               SUM(CASE WHEN m.activo = 1 THEN 1 ELSE 0 END) AS medicos_activos,
               COUNT(m.id) AS medicos_totales
        FROM especialidades e
        LEFT JOIN medicos m ON m.especialidad = e.nombre
        GROUP BY e.id, e.nombre, e.activo
        ORDER BY e.nombre ASC";
$filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Agrupa por clave normalizada. La clave es el nombre comparable; el valor
// es la lista de filas que representan esa misma especialidad.
$grupos = [];
foreach ($filas as $fila) {
    $grupos[claveEspecialidad((string) $fila['nombre'])][] = $fila;
}

// Acumula el detalle de lo que se hizo para imprimirlo al final.
$fusiones = [];

// Una transacción evita dejar el catálogo a medio limpiar si algo falla:
// o se aplican todas las fusiones, o no se aplica ninguna.
$pdo->beginTransaction();

try {
    foreach ($grupos as $clave => $variantes) {
        // Un grupo con una sola fila no es un duplicado: se ignora.
        if (count($variantes) < 2) {
            continue;
        }

        // Ordena las variantes para elegir la canónica:
        //   1) más médicos activos;
        //   2) más médicos totales;
        //   3) activa antes que inactiva;
        //   4) id más bajo (la más antigua) para que la elección sea estable.
        usort($variantes, static function (array $a, array $b): int {
            return [(int) $b['medicos_activos'], (int) $b['medicos_totales'], (int) $b['activo'], -(int) $a['id']]
                <=> [(int) $a['medicos_activos'], (int) $a['medicos_totales'], (int) $a['activo'], -(int) $b['id']];
        });

        // La primera variante del orden pasa a ser la fila que se conserva.
        $canonica = array_shift($variantes);

        // Si alguna variante estaba activa, la canónica también queda activa.
        // Así no se "pierde" una especialidad visible al fusionarla.
        if ((int) $canonica['activo'] === 0) {
            $marcarActiva = $pdo->prepare('UPDATE especialidades SET activo = 1 WHERE id = ?');
            $marcarActiva->execute([(int) $canonica['id']]);
        }

        foreach ($variantes as $sobrante) {
            // Reasigna los médicos que usaban el nombre sobrante. Se hace por
            // nombre porque medicos.especialidad guarda texto, no un id.
            $moverMedicos = $pdo->prepare('UPDATE medicos SET especialidad = ? WHERE especialidad = ?');
            $moverMedicos->execute([$canonica['nombre'], $sobrante['nombre']]);

            // Borra la fila sobrante del catálogo.
            $borrar = $pdo->prepare('DELETE FROM especialidades WHERE id = ?');
            $borrar->execute([(int) $sobrante['id']]);

            $fusiones[] = [
                'conservada' => $canonica['nombre'],
                'eliminada' => $sobrante['nombre'],
                'medicos_movidos' => $moverMedicos->rowCount(),
            ];
        }
    }

    // Confirma todos los cambios si no hubo excepciones.
    $pdo->commit();
} catch (Throwable $error) {
    // Deshace todo si cualquier paso falló.
    $pdo->rollBack();
    fwrite(STDERR, 'Error al limpiar especialidades: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

// Informe legible para consola.
if ($fusiones === []) {
    echo 'No hay especialidades duplicadas. No se cambió nada.' . PHP_EOL;
    exit(0);
}

echo 'Especialidades fusionadas: ' . count($fusiones) . PHP_EOL;
foreach ($fusiones as $fusion) {
    echo '- Se conservó "' . $fusion['conservada'] . '" y se eliminó "' . $fusion['eliminada'] . '"';
    echo ' (médicos reasignados: ' . $fusion['medicos_movidos'] . ')' . PHP_EOL;
}
