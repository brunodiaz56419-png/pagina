<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Solo acepta envíos por POST; si abren el archivo directo, regresa al formulario.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: encuesta.html');
    exit;
}

// Valores permitidos en las preguntas de opción (evita datos inventados).
$permitidos = [
    'profesores'  => ['Excelente', 'Bueno', 'Regular', 'Malo'],
    'instalacion' => ['Aulas', 'Laboratorios', 'Biblioteca', 'Cafetería', 'Baños', 'Estacionamiento'],
    'wifi'        => ['Muy buena', 'Aceptable', 'Mala', 'No hay señal donde estoy'],
    'biblioteca'  => ['Diario', 'Semanal', 'Casi nunca'],
];

$d = [];
foreach (['profesores', 'instalacion', 'wifi', 'biblioteca', 'sugerencia'] as $campo) {
    $valor = trim((string)($_POST[$campo] ?? ''));
    if ($valor === '') {
        header('Location: encuesta.html?error=vacio');
        exit;
    }
    if (isset($permitidos[$campo]) && !in_array($valor, $permitidos[$campo], true)) {
        header('Location: encuesta.html?error=vacio');
        exit;
    }
    $d[$campo] = $valor;
}
$d['sugerencia'] = mb_substr($d['sugerencia'], 0, 300);

try {
    $sql = 'INSERT INTO respuestas (profesores, instalacion, wifi, biblioteca, sugerencia)
            VALUES (:profesores, :instalacion, :wifi, :biblioteca, :sugerencia)';
    db()->prepare($sql)->execute($d);
} catch (PDOException $ex) {
    header('Location: encuesta.html?error=bd');
    exit;
}

header('Location: regalo.html');
exit;
