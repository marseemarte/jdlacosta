<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

if (!isset($_SESSION['es_jefatura']) || $_SESSION['es_jefatura'] != 1) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

$vinculo = trim($_POST['vinculo'] ?? '');

if (empty($vinculo)) {
    die(json_encode(['success' => false, 'error' => 'Falta el tipo de vínculo']));
}

try {
    $db = getDBConnection();
    
    // Verificar duplicado
    $check = $db->prepare("SELECT id FROM vinculoesc WHERE vinculo = ?");
    $check->execute([$vinculo]);
    
    if ($check->rowCount() > 0) {
        die(json_encode(['success' => false, 'error' => 'Este vínculo ya existe']));
    }
    
    // Insertar
    $insert = $db->prepare("INSERT INTO vinculoesc (vinculo, visible) VALUES (?, 1)");
    $insert->execute([$vinculo]);
    
    $id = $db->lastInsertId();
    
    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Vínculo creado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
