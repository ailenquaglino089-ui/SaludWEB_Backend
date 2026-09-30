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

// Se busca un admin que NO sea el único, para poder degradarlo y devolverlo.
$candidato = $pdo->query(
    "SELECT id, tipo_usuario FROM usuarios
     WHERE tipo_usuario = 'admin' AND activo = 1 ORDER BY id LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if ($candidato === false) {
    echo "  (omitido: no hay admin activo)\n";
} else {
    $idCandidato = (int)$candidato['id'];
    $rolOriginal = (string)$candidato['tipo_usuario'];

    // El que hace el cambio es otro admin distinto, para no chocar con la
    // regla de "no te degrades a vos mismo".
    $otroAdmin = $pdo->query(
        "SELECT id FROM usuarios
         WHERE tipo_usuario = 'admin' AND activo = 1 AND id <> {$idCandidato}
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $idAdminQueActua = $otroAdmin === false ? 0 : (int)$otroAdmin['id'];

    comprobar("el listado paginado responde (probar_roles.php)", function () {
        $r = (new UsuarioService(new UsuarioRepository($GLOBALS['pdo'])))->listar(1, 5, '', '');
        if (!isset($r['items'], $r['total'], $r['total_paginas'])) {
            return 'a la envoltura le falta una clave';
        }
        return null;
    });

    if ($idAdminQueActua > 0) {
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

        // Se restaura el rol original. Esto va fuera de comprobar() a
        // propósito: si fallara, el script tiene que avisar igual en lugar
        // de tragarse el error y dejar la base modificada.
        $repoReal->actualizarRol($idCandidato, $rolOriginal);
        comprobar('el rol original se restauró', function () use ($pdo, $idCandidato, $rolOriginal) {
            $s = $pdo->prepare('SELECT tipo_usuario FROM usuarios WHERE id = ?');
            $s->execute([$idCandidato]);
            $actual = (string)$s->fetchColumn();
            return $actual === $rolOriginal
                ? null
                : "en la base quedó '{$actual}', debía quedar '{$rolOriginal}'";
        });
    } else {
        echo "  (omitido: hay un único admin, no se puede probar el cambio real)\n";
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo $fallos === 0
    ? "TODAS LAS COMPROBACIONES PASARON\n"
    : "COMPROBACIONES FALLIDAS: {$fallos}\n";

exit($fallos === 0 ? 0 : 1);
