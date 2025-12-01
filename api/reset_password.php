<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';
$securityCode = isset($_POST['security_code']) ? trim($_POST['security_code']) : '';
$newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

if ($clave === '' || $securityCode === '' || $newPassword === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Complete todos los campos']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    $sql = "SELECT id FROM secundarias WHERE clave = :clave AND security_code = :code LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':clave' => $clave,
        ':code' => $securityCode
    ]);
    $escuela = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$escuela) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Clave o código de seguridad incorrecto']);
        exit;
    }

    $update = $pdo->prepare("UPDATE secundarias SET pass = :pass WHERE id = :id LIMIT 1");
    $update->execute([
        ':pass' => $newPassword,
        ':id' => $escuela['id']
    ]);

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada con éxito']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
}
