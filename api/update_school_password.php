<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$escuelaId = isset($_POST['escuela_id']) ? (int)$_POST['escuela_id'] : 0;
$nuevaPass = isset($_POST['pass']) ? trim($_POST['pass']) : '';

if ($escuelaId <= 0 || $nuevaPass === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Complete todos los campos']);
    exit;
}

if (strlen($nuevaPass) < 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres']);
    exit;
}

if (!isset($_SESSION['distrito']) || (int)$_SESSION['distrito'] <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Distrito no definido en la sesión']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $distrito = (int)$_SESSION['distrito'];

    $sql = "UPDATE secundarias SET pass = :pass WHERE id = :id AND distrito = :distrito";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pass' => $nuevaPass,
        ':id' => $escuelaId,
        ':distrito' => $distrito
    ]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Escuela no encontrada en su distrito']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
}
