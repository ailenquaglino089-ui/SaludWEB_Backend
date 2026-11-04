<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$especialidades = [
    'Cardiología',
    'Dermatología',
    'Clínica Médica',
];

$medicos = [
    [
        'nombre' => 'Dr. Hernán Rivas',
        'matricula' => 'MP-1005',
        'especialidad' => 'Cardiología',
    ],
    [
        'nombre' => 'Dra. Camila Ferreyra',
        'matricula' => 'MP-1006',
        'especialidad' => 'Dermatología',
    ],
    [
        'nombre' => 'Dr. Tomás Aguirre',
        'matricula' => 'MP-1007',
        'especialidad' => 'Clínica Médica',
    ],
];

$buscarEspecialidad = $pdo->prepare('SELECT id FROM especialidades WHERE nombre = ? LIMIT 1');
$crearEspecialidad = $pdo->prepare('INSERT INTO especialidades (nombre, activo) VALUES (?, 1)');
$activarEspecialidad = $pdo->prepare('UPDATE especialidades SET activo = 1 WHERE nombre = ?');
$buscarMedico = $pdo->prepare('SELECT id FROM medicos WHERE matricula = ? LIMIT 1');
$crearMedico = $pdo->prepare(
    'INSERT INTO medicos (nombre, matricula, especialidad, activo) VALUES (?, ?, ?, 1)'
);
$actualizarMedico = $pdo->prepare(
    'UPDATE medicos SET nombre = ?, especialidad = ?, activo = 1 WHERE id = ?'
);
$buscarBloque = $pdo->prepare(
    'SELECT id FROM disponibilidades
     WHERE id_medico = ? AND dia_semana = ? AND hora_inicio = ? LIMIT 1'
);
$crearBloque = $pdo->prepare(
    'INSERT INTO disponibilidades
     (id_medico, dia_semana, hora_inicio, hora_fin, duracion_minutos, activo)
     VALUES (?, ?, ?, ?, 30, 1)'
);
$activarBloque = $pdo->prepare(
    'UPDATE disponibilidades
     SET hora_fin = ?, duracion_minutos = 30, activo = 1
     WHERE id = ?'
);

$pdo->beginTransaction();

try {
    foreach ($especialidades as $nombre) {
        $buscarEspecialidad->execute([$nombre]);
        if (!$buscarEspecialidad->fetchColumn()) {
            $crearEspecialidad->execute([$nombre]);
            echo "Especialidad creada: {$nombre}\n";
        } else {
            $activarEspecialidad->execute([$nombre]);
        }
    }

    $idsMedicos = [];

    foreach ($medicos as $medico) {
        $buscarMedico->execute([$medico['matricula']]);
        $idMedico = $buscarMedico->fetchColumn();

        if ($idMedico) {
            $idMedico = (int) $idMedico;
            $actualizarMedico->execute([
                $medico['nombre'],
                $medico['especialidad'],
                $idMedico,
            ]);
            echo "Profesional actualizado: {$medico['nombre']} ({$medico['especialidad']})\n";
        } else {
            $crearMedico->execute([
                $medico['nombre'],
                $medico['matricula'],
                $medico['especialidad'],
            ]);
            $idMedico = (int) $pdo->lastInsertId();
            echo "Profesional creado: {$medico['nombre']} ({$medico['especialidad']})\n";
        }

        $idsMedicos[] = $idMedico;
    }

    foreach ($idsMedicos as $idMedico) {
        for ($dia = 1; $dia <= 5; $dia++) {
            $buscarBloque->execute([$idMedico, $dia, '09:00:00']);
            $idBloque = $buscarBloque->fetchColumn();

            if ($idBloque) {
                $activarBloque->execute(['13:00:00', (int) $idBloque]);
            } else {
                $crearBloque->execute([$idMedico, $dia, '09:00:00', '13:00:00']);
            }
        }
        echo "Agenda asegurada: profesional #{$idMedico} de lunes a viernes, 09:00 a 13:00\n";
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'Especialidades con profesionales activos:' . PHP_EOL;

$consulta = $pdo->query(
    "SELECT e.nombre, COUNT(m.id) AS total
     FROM especialidades e
     LEFT JOIN medicos m ON m.especialidad = e.nombre AND m.activo = 1
     WHERE e.activo = 1
     GROUP BY e.id
     ORDER BY e.nombre"
);

foreach ($consulta as $fila) {
    echo "  {$fila['nombre']}: {$fila['total']}" . PHP_EOL;
}
