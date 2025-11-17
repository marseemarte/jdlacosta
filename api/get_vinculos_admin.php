<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

if (!isset($_SESSION['es_jefatura']) || $_SESSION['es_jefatura'] != 1) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

try {
    $db = getDBConnection();
    
    $query = $db->prepare("SELECT id, vinculo, visible FROM vinculoesc ORDER BY id");
    $query->execute();
    $vinculos = $query->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $vinculos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
