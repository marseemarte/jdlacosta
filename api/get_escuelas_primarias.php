<?php
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    $query = "SELECT e.id, e.nombre, l.localidad
              FROM secundarias e 
              INNER JOIN localidad l ON e.id_localidad = l.id 
              WHERE e.id_localidad > 0
              ORDER BY l.localidad, e.nombre";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $escuelas
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => true,
        'data' => []
    ]);
}

