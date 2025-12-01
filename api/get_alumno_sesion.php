<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$alumno = [
    'dni' => $_SESSION['alumno_dni'] ?? '',
    'apellido' => $_SESSION['alumno_apellido'] ?? '',
    'nombre' => $_SESSION['alumno_nombre'] ?? '',
    'id_secundaria' => $_SESSION['id_secundaria'] ?? 0
];

echo json_encode(['success' => true, 'alumno' => $alumno]);
