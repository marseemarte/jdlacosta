<?php
require_once __DIR__ . '/config.php';
init_session();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

$email = $_GET['email'] ?? '';

if (empty($email)) {
    die(json_encode(['valid' => false, 'error' => 'Falta email']));
}

$email = trim($email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['valid' => false, 'error' => 'Email inválido']));
}

try {
    $db = getDBConnection();
    
    // Verificar si el email ya existe en padres
    $check = $db->prepare("SELECT id FROM padres WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->rowCount() > 0) {
        die(json_encode(['valid' => false, 'error' => 'Este email ya está registrado']));
    }
    
    echo json_encode(['valid' => true, 'email' => $email]);
} catch (Exception $e) {
    echo json_encode(['valid' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
