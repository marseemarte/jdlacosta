<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($search)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingrese un DNI o nombre']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    
    $search_limpio = preg_replace('/[^0-9]/', '', $search);
    
    $sql = "SELECT 
                a.id, 
                a.dni, 
                a.apellido, 
                a.nombre, 
                a.fecha,
                a.direccion, 
                a.localidad, 
                a.vinculo,
                a.id_secundaria,
                a.turno,
                a.fecha_insc,
                s.nombre as escuela_nombre
            FROM alumnos a
            LEFT JOIN secundarias s ON a.id_secundaria = s.id
            WHERE a.dni = :dni OR a.apellido LIKE :nombre OR a.nombre LIKE :nombre
            ORDER BY a.apellido, a.nombre
            LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':dni' => $search_limpio,
        ':nombre' => '%' . $search . '%'
    ]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alumnos)) {
        echo json_encode(['success' => false, 'count' => 0, 'data' => []]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'count' => count($alumnos),
        'data' => $alumnos
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
