<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

if (!isset($_SESSION['es_jefatura']) || $_SESSION['es_jefatura'] != 1) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

$id = $_POST['id'] ?? null;
$vinculo = trim($_POST['vinculo'] ?? '');

if (!$id || empty($vinculo)) {
    die(json_encode(['success' => false, 'error' => 'Faltan datos']));
}

try {
    $db = getDBConnection();
    
    // Verificar que existe
    $check = $db->prepare("SELECT id FROM vinculoesc WHERE id = ?");
    $check->execute([$id]);
    
    if ($check->rowCount() == 0) {
        die(json_encode(['success' => false, 'error' => 'Vínculo no encontrado']));
    }
    
    // Actualizar
    $update = $db->prepare("UPDATE vinculoesc SET vinculo = ? WHERE id = ?");
    $update->execute([$vinculo, $id]);
    
    echo json_encode(['success' => true, 'message' => 'Vínculo actualizado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
