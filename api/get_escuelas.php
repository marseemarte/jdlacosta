<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Consulta para obtener escuelas con sus localidades
    $query = "SELECT e.id, e.nombre, l.localidad 
              FROM escuelas e 
              INNER JOIN localidad l ON e.id_localidad = l.id 
              ORDER BY e.nombre";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agregar datos adicionales para cada escuela (dirección y teléfono de ejemplo)
    $escuelasConDatos = array();
    foreach ($escuelas as $escuela) {
        $escuela['direccion'] = "Calle " . rand(1, 100) . " nro " . rand(1, 999);
        $escuela['telefono'] = "2244 " . rand(10, 99) . "-" . rand(1000, 9999);
        $escuelasConDatos[] = $escuela;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $escuelasConDatos
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
}
?>
