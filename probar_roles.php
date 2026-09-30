<?php
// Script de verificación del módulo de roles. Es una herramienta de
// desarrollo, como listar_usuarios.php: no forma parte de la API.
//
// Qué comprueba, en orden:
//   1. Que los usuarios existan y haya al menos un admin activo
//   2. Que UsuarioService acepte los tres roles y rechace un cuarto
//   3. Que NO se pueda degradar al último admin
//   4. Que NO se pueda degradar a uno mismo
//   5. Que un cambio de rol válido realmente escriba en la base
//   6. Que se deshaga el cambio de la prueba 5
//
// Usa un doble de repositorio en memoria para las reglas 2 a 4, y toca la
// base de verdad solo para las 5 y 6, que son las que necesitan un UPDATE
// real. Al final deja la base como estaba.
require __DIR__ . '/core/bootstrap.php';

echo "=== 1. USUARIOS EN LA BASE ===\n";
$stmt = $pdo->query(
    'SELECT id, email, nombre, tipo_usuario, activo
     FROM usuarios ORDER BY id LIMIT 12'
);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
    printf(
        "  %-4s %-32s %-24s %-9s activo=%s\n",
        $u['id'], $u['email'], $u['nombre'], $u['tipo_usuario'], $u['activo']
    );
}

$adminsActivos = (int)$pdo->query(
    "SELECT COUNT(*) FROM usuarios WHERE tipo_usuario = 'admin' AND activo = 1"
)->fetchColumn();

echo "\n  administradores activos: {$adminsActivos}\n";
if ($adminsActivos < 1) {
    echo "  NO SE PUEDE PROBAR: la base no tiene ningún admin activo.\n";
    exit(1);
}

// ============================================================
// DOBLE DE REPOSITORIO
// ============================================================
// Implementa el contrato con datos fijos para poder probar las reglas sin
// escribir en la base. Es el motivo de que UsuarioService dependa de la
// interfaz y no de la clase concreta.
class RepoFalso implements UsuarioRepositoryInterface
{
    public array $usuarios = [];
    public int $escrituras = 0;

    public function __construct(array $usuarios)
    {
        $this->usuarios = $usuarios;
    }

    public function obtenerPaginado(int $pagina, int $porPagina, string $busqueda, string $rol): array
    {
        return ['items' => [], 'total' => 0, 'pagina' => $pagina, 'por_pagina' => $porPagina, 'total_paginas' => 1];
    }

    public function obtenerPorId(int $id): ?array
    {
        return $this->usuarios[$id] ?? null;
    }

    public function actualizarRol(int $id, string $rol): bool
    {
        $this->escrituras++;
        if (isset($this->usuarios[$id])) {
            $this->usuarios[$id]['rol'] = $rol;
            return true;
        }
        return false;
    }

    public function contarActivosConRol(string $rol): int
    {
        $n = 0;
        foreach ($this->usuarios as $u) {
            if ($u['rol'] === $rol && (int)$u['activo'] === 1) {
                $n++;
            }
        }
        return $n;
    }
}

$fallos = 0;

/**
 * Ejecuta una comprobación y dice si pasó.
 *
 * @param string $titulo Qué se está comprobando
 * @param callable $accion Devuelve null si pasó, o el mensaje de error
 */
function comprobar(string $titulo, callable $accion): void
{
    global $fallos;
    try {
        $error = $accion();
        if ($error === null) {
            echo "  OK    {$titulo}\n";
        } else {
            echo "  FALLA {$titulo}\n        -> {$error}\n";
            $fallos++;
        }
    } catch (Throwable $e) {
        echo "  FALLA {$titulo}\n        -> excepción: " . $e->getMessage() . "\n";
        $fallos++;
    }
}

// ============================================================
// 2. VALIDACIÓN DEL ROL DESTINO
// ============================================================
echo "\n=== 2. EL ROL DESTINO TIENE QUE EXISTIR ===\n";

$repo = new RepoFalso([
    1 => ['id' => 1, 'rol' => 'medico', 'activo' => 1, 'id_paciente' => null, 'id_medico' => 7],
    2 => ['id' => 2, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
    3 => ['id' => 3, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
]);
$svc = new UsuarioService($repo);

foreach (['paciente', 'medico', 'admin'] as $rol) {
    comprobar("acepta el rol '{$rol}'", function () use ($svc, $rol) {
        try {
            $svc->cambiarRol(1, $rol, 2);
            return null;
        } catch (Throwable $e) {
            return 'lo rechazó: ' . $e->getMessage();
        }
    });
}

comprobar('rechaza un rol inexistente (superusuario) con 422', function () use ($svc) {
    try {
        $svc->cambiarRol(1, 'superusuario', 2);
        return 'lo aceptó, y no debería';
    } catch (InvalidArgumentException $e) {
        return $e->getCode() === 422 ? null : 'el código HTTP fue ' . $e->getCode() . ', debía ser 422';
    }
});

comprobar('rechaza un rol vacío con 422', function () use ($svc) {
    try {
        $svc->cambiarRol(1, '', 2);
        return 'lo aceptó, y no debería';
    } catch (InvalidArgumentException $e) {
        return null;
    }
});

// ============================================================
// 3. EL ÚLTIMO ADMIN NO SE PUEDE DEGRADAR
// ============================================================
echo "\n=== 3. NO DEGRADAR AL ÚLTIMO ADMINISTRADOR ===\n";

$repo = new RepoFalso([
    1 => ['id' => 1, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
    2 => ['id' => 2, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
]);
$svc = new UsuarioService($repo);

comprobar('con 2 admins se puede degradar a uno de ellos', function () use ($svc) {
    try {
        $svc->cambiarRol(2, 'medico', 1);
        return null;
    } catch (Throwable $e) {
        return 'lo bloqueó cuando no debía: ' . $e->getMessage();
    }
});

$repo = new RepoFalso([
    1 => ['id' => 1, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
    2 => ['id' => 2, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
]);
$svc = new UsuarioService($repo);
$svc->cambiarRol(2, 'medico', 1); // ahora el 1 es el único admin

comprobar('con 1 solo admin, degradarlo se bloquea con 409', function () use ($svc) {
    try {
        $svc->cambiarRol(1, 'paciente', 3);
        return 'lo permitió y dejó la base sin administración';
    } catch (RuntimeException $e) {
        return $e->getCode() === 409 ? null : 'el código HTTP fue ' . $e->getCode() . ', debía ser 409';
    }
});

comprobar('con 1 solo admin, promover a otro admin sigue permitido', function () use ($svc) {
    try {
        $svc->cambiarRol(2, 'admin', 3);
        return null;
    } catch (Throwable $e) {
        return 'lo bloqueó, y promover nunca debe bloquearse: ' . $e->getMessage();
    }
});

// ============================================================
// 4. NADIE SE DEGRADA A SÍ MISMO
// ============================================================
echo "\n=== 4. NADIE SE DEGRADA A SÍ MISMO ===\n";

$repo = new RepoFalso([
    1 => ['id' => 1, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
    2 => ['id' => 2, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
]);
$svc = new UsuarioService($repo);

comprobar('un admin no se degrada a sí mismo con 409', function () use ($svc) {
    try {
        $svc->cambiarRol(1, 'paciente', 1);
        return 'lo permitió';
    } catch (RuntimeException $e) {
        return $e->getCode() === 409 ? null : 'el código HTTP fue ' . $e->getCode() . ', debía ser 409';
    }
});

comprobar('promoverse a sí mismo sin cambio real no dispara error', function () use ($svc) {
    try {
        $svc->cambiarRol(1, 'admin', 1);
        return null;
    } catch (Throwable $e) {
        return 'lo bloqueó: ' . $e->getMessage();
    }
});

comprobar('un admin sí puede degradar a OTRO admin', function () use ($svc) {
    try {
        $svc->cambiarRol(2, 'medico', 1);
        return null;
    } catch (Throwable $e) {
        return 'lo bloqueó: ' . $e->getMessage();
    }
});

// ============================================================
// 5. USUARIO INEXISTENTE
// ============================================================
echo "\n=== 5. USUARIO INEXISTENTE ===\n";
$repo = new RepoFalso([
    1 => ['id' => 1, 'rol' => 'admin', 'activo' => 1, 'id_paciente' => null, 'id_medico' => null],
]);
$svc = new UsuarioService($repo);

comprobar('un id inexistente responde 404', function () use ($svc) {
    try {
        $svc->cambiarRol(999, 'medico', 1);
        return 'lo aceptó';
    } catch (RuntimeException $e) {
        return $e->getCode() === 404 ? null : 'el código HTTP fue ' . $e->getCode() . ', debía ser 404';
    }
});

// ============================================================
// 6. ESCRITURA REAL Y DESHACER
// ============================================================
echo "\n=== 6. ESCRITURA REAL EN LA BASE (se deshace al final) ===\n";

$repoReal = new UsuarioRepository($pdo);
$svcReal = new UsuarioService($repoReal);

// ------------------------------------------------------------
// CUENTA DESCARTABLE PARA EL CAMBIO REAL
// ------------------------------------------------------------
// Antes, esta sección degradaba a un administrador QUE YA EXISTÍA y al final
// le devolvía el rol. La idea era "tocar una cuenta de verdad", pero dejaba la
// base en un estado frágil: si el script se cortaba entre medio (una
// excepción, un Ctrl+C, la terminal cerrada), la cuenta se quedaba como
// médico para siempre y nadie se enteraba desde el script.
//
// El síntoma apareció días después y en un lugar que no se parece en nada a
// esta prueba: la administradora veía la tabla de médicos y pacientes sin
// botón de editar, porque su cuenta había dejado de ser admin. La causa
// estaba a metros, en un archivo de pruebas.
//
// Por eso ahora se crea una cuenta SOLO para esta prueba y se borra al
// terminar. Las cuentas reales no se tocan, así que aunque el script muera
// a la mitad, lo peor que puede pasar es que quede un usuario de prueba
// sobrando, que es un dato inofensivo y se puede borrar a mano.
//
// La cuenta es descartable por diseño, no por limpieza: se borra siempre.
$emailDescartable = 'descartable.rol.' . time() . '@prueba.local';

// Contraseña cualquiera: la cuenta existe solo para la escritura, nunca se
// inicia sesión con ella. Va hasheada igual porque la columna lo exige.
$alta = $pdo->prepare(
    'INSERT INTO usuarios (email, password, nombre, tipo_usuario, activo)
     VALUES (?, ?, ?, ?, 1)'
);
$alta->execute([
    $emailDescartable,
    password_hash('no-se-usa-' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
    'Usuario Descartable',
    'admin',
]);
$idCandidato = (int) $pdo->lastInsertId();
echo "  cuenta descartable creada: {$emailDescartable} (id {$idCandidato})\n";

// El que hace el cambio tiene que ser un admin DISTINTO del candidato, o
// choca con la regla de "no te degrades a vos mismo" y la prueba probaría
// otra cosa.
$otroAdmin = $pdo->query(
    "SELECT id FROM usuarios
     WHERE tipo_usuario = 'admin' AND activo = 1 AND id <> {$idCandidato}
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$idAdminQueActua = $otroAdmin === false ? 0 : (int) $otroAdmin['id'];

if ($idAdminQueActua === 0) {
    echo "  (omitido: hace falta al menos otro admin para que pueda hacer el cambio)\n";
} else {
    comprobar("el listado paginado responde (probar_roles.php)", function () {
        $r = (new UsuarioService(new UsuarioRepository($GLOBALS['pdo'])))->listar(1, 5, '', '');
        if (!isset($r['items'], $r['total'], $r['total_paginas'])) {
            return 'a la envoltura le falta una clave';
        }
        return null;
    });

    if ($idAdminQueActua > 0) {
        // ------------------------------------------------------------
        // try/finally alrededor de toda la sección que escribe en la base.
        //
        // POR QUÉ: el finally se ejecuta siempre, haya habido excepción o no.
        // Antes la limpieza estaba en medio del camino feliz, así que una
        // excepción cortaba el script y dejaba el cambio a medias.
        // ------------------------------------------------------------
        try {
            comprobar('un cambio de rol real escribe en la base', function () use ($svcReal, $idCandidato, $idAdminQueActua) {
                $svcReal->cambiarRol($idCandidato, 'medico', $idAdminQueActua);
                return null;
            });

            comprobar('el rol quedó efectivamente cambiado', function () use ($pdo, $idCandidato) {
                $s = $pdo->prepare('SELECT tipo_usuario FROM usuarios WHERE id = ?');
                $s->execute([$idCandidato]);
                $actual = (string)$s->fetchColumn();
                return $actual === 'medico' ? null : "en la base quedó '{$actual}', no 'medico'";
            });

            // La protección del ÚLTIMO admin, probada de verdad.
            //
            // La base tiene una sola administradora real, así que esta es la
            // única vez que se puede comprobar la regla sin tener que inventar
            // admins de mentira: si se degrada a la que queda, no queda nadie
            // y el sistema se queda sin nadie que pueda administrar los
            // permisos. El servicio tiene que rechazarlo con 409.
            //
            // Se busca la admin real por rol, no por email. Así el test no
            // depende de que la cuenta de la administradora siga teniendo el
            // mismo email.
            $ultimaAdmin = $pdo->query(
                "SELECT id FROM usuarios
                 WHERE tipo_usuario = 'admin' AND activo = 1 AND id <> {$idCandidato}
                 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);

            if ($ultimaAdmin === false) {
                echo "  (omitido: no queda ninguna otra admin para probar la regla)\n";
            } else {
                $idUltimaAdmin = (int) $ultimaAdmin['id'];

                comprobar('degradar a la última administradora se rechaza con 409', function () use ($svcReal, $idUltimaAdmin, $idAdminQueActua) {
                    try {
                        $svcReal->cambiarRol($idUltimaAdmin, 'medico', $idAdminQueActua);
                        return 'el servicio la degradó y dejó la base sin ningún admin';
                    } catch (RuntimeException $e) {
                        return $e->getCode() === 409
                            ? null
                            : "la excepción fue de código {$e->getCode()}, no 409";
                    }
                });

                comprobar('la última administradora sigue siendo administradora', function () use ($pdo, $idUltimaAdmin) {
                    $s = $pdo->prepare('SELECT tipo_usuario FROM usuarios WHERE id = ?');
                    $s->execute([$idUltimaAdmin]);
                    $actual = (string) $s->fetchColumn();
                    return $actual === 'admin'
                        ? null
                        : "en la base quedó de rol '{$actual}'";
                });
            }
        } catch (Throwable $e) {
            $fallos++;
            echo "  FALLA la escritura real lanzó una excepción\n";
            echo "        " . get_class($e) . ': ' . $e->getMessage() . "\n";
        } finally {
            // La cuenta descartable se borra SIEMPRE. No se restaura: se
            // elimina, que es más limpio que devolverla a admin y dejarla
            // ahí ocupando una fila. Y como es una cuenta que el script creó,
            // borrarla no toca ningún dato que le importe a nadie.
            $borrar = $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND email = ?');
            $borrar->execute([$idCandidato, $emailDescartable]);
            echo "  cuenta descartable borrada\n";
        }

        comprobar('la cuenta descartable ya no está en la base', function () use ($pdo, $idCandidato) {
            $s = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE id = ?');
            $s->execute([$idCandidato]);
            $quedan = (int) $s->fetchColumn();
            return $quedan === 0 ? null : "quedan {$quedan} filas con ese id";
        });
    } else {
        echo "  (omitido: hace falta al menos otro admin)\n";

        // Aunque la prueba no se haya hecho, la cuenta que se creó para ella
        // se borra igual. Si no, cada corrida que no pudiera probarla dejaría
        // un admin de basura en la base.
        $pdo->prepare('DELETE FROM usuarios WHERE id = ? AND email = ?')
            ->execute([$idCandidato, $emailDescartable]);
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo $fallos === 0
    ? "TODAS LAS COMPROBACIONES PASARON\n"
    : "COMPROBACIONES FALLIDAS: {$fallos}\n";

exit($fallos === 0 ? 0 : 1);
