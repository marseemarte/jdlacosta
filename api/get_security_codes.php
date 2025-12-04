<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if (!isset($_SESSION['distrito'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Distrito no definido']);
    exit;
}

try {
    $pdo = getDBConnection();
    $distrito = (int)$_SESSION['distrito'];
    
    // Obtener todas las escuelas del distrito con sus códigos de seguridad
    $sql = "SELECT 
                s.id,
                s.nombre,
                s.security_code
            FROM secundarias s
            WHERE s.distrito = :distrito 
            AND s.abreviatura != 'JEFATURA' 
            AND s.abreviatura != ''
            ORDER BY s.nombre";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':distrito' => $distrito]);
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos
    $resultado = [];
    foreach ($escuelas as $escuela) {
        $resultado[] = [
            'id' => (int)$escuela['id'],
            'nombre' => $escuela['nombre'],
            'codigo' => $escuela['security_code'] ?? '-'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $resultado
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error servidor: ' . $e->getMessage()
    ]);
}
?>
