<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Consulta para obtener escuelas con sus localidades
    $query = "SELECT e.id, e.nombre, e.direccion, e.telefono, l.localidad 
              FROM secundarias e 
              INNER JOIN localidad l ON e.id_localidad = l.id 
              WHERE e.distrito = 123
              ORDER BY e.nombre
              ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $escuelas
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
}
?>
