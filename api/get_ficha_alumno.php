<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$dni = $_GET['dni'] ?? '';
if (!$dni) { echo json_encode(['success'=>false]); exit; }
$pdo = getDBConnection();

// Alumno
$stmt = $pdo->prepare("SELECT a.*, v.vinculo as vinculo_nombre FROM alumnos a LEFT JOIN vinculoesc v ON a.vinculo = v.id WHERE a.dni = :dni LIMIT 1");
$stmt->execute([':dni' => $dni]);
$alumno = $stmt->fetch(PDO::FETCH_ASSOC);

// Tutor
$tutor = ['dni'=>'','nombre'=>'','apellido'=>'','fecha'=>'','telefono'=>'','mail'=>''];
if ($alumno) {
    // Usar el ID del alumno en lugar del DNI para la relación
    $padre = $pdo->prepare("SELECT p.* FROM padres p
        JOIN padrealumno pa ON pa.dni_padre = p.id
        WHERE pa.dni_alumno = :id LIMIT 1");
    $padre->execute([':id' => $alumno['id']]);
    $t = $padre->fetch(PDO::FETCH_ASSOC);
    if ($t) $tutor = array_merge($tutor, $t);
}

echo json_encode([
    'success' => !!$alumno,
    'alumno' => $alumno ?: [],
    'tutor' => $tutor
]);