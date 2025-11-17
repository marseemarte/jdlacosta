<?php
session_start();
require 'config.php';

$id_secundaria = $_GET['id_secundaria'] ?? '';

if (empty($id_secundaria)) {
    die(json_encode(['success' => false, 'error' => 'Falta escuela']));
}

try {
    $db = getDBConnection();
    
    // Obtener alumnos ordenados por sorteo
    $query = $db->prepare("
        SELECT a.id, a.apellido, a.nombre, a.dni, a.vinculo, s.orden
        FROM alumnos a
        LEFT JOIN sorteo s ON a.dni = s.dni AND s.id_secundaria = ?
        WHERE a.id_secundaria = ?
        ORDER BY s.orden, a.fecha_insc
    ");
    $query->execute([$id_secundaria, $id_secundaria]);
    $alumnos = $query->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $alumnos]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
