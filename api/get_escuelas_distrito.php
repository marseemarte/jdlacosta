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

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $distrito = (int)$_SESSION['distrito'];
    
    // Obtener todas las escuelas del distrito (excluyendo las jefaturas)
    $sql = "SELECT 
                s.id,
                s.nombre,
                s.vacantes,
                COUNT(DISTINCT a.id) as anotados,
                MAX(l.fecha) as ultima_fecha,
                MAX(l.hora) as ultima_hora
            FROM secundarias s
            LEFT JOIN alumnos a ON s.id = a.id_secundaria
            LEFT JOIN log l ON s.id = l.id_secundaria
            WHERE s.distrito = :distrito 
            AND (s.abreviatura IS NULL OR s.abreviatura != 'JEFATURA')
            GROUP BY s.id, s.nombre, s.vacantes
            ORDER BY s.nombre";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':distrito' => $distrito]);
    $escuelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear los datos
    $resultado = [];
    foreach ($escuelas as $escuela) {
        $ultimo_acceso = '';
        if ($escuela['ultima_fecha'] && $escuela['ultima_hora']) {
            $fecha = new DateTime($escuela['ultima_fecha']);
            $hora = new DateTime($escuela['ultima_hora']);
            $ultimo_acceso = $fecha->format('d/m/y') . ' - ' . $hora->format('H:i:s');
        } else {
            $ultimo_acceso = '-';
        }
        
        $resultado[] = [
            'id' => (int)$escuela['id'],
            'nombre' => $escuela['nombre'],
            'vacantes' => (int)$escuela['vacantes'],
            'anotados' => (int)$escuela['anotados'],
            'ultimo_acceso' => $ultimo_acceso
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

