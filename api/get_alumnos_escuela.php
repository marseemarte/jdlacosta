<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo JEFATURA puede acceder a datos de otras escuelas
if (!isset($_SESSION['escuela_id']) || empty($_SESSION['es_jefatura'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';

$escuelaId = isset($_GET['escuela_id']) ? (int)$_GET['escuela_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'nomina'; // 'nomina'|'ingresan'|'no_ingresan'|'lista_espera'

if ($escuelaId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'escuela_id requerido']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Armar filtro de estado
    $estadoClause = '';
    $params = [':esc_id' => $escuelaId];
    if ($type === 'ingresan') {
        $estadoClause = 'AND a.entro = 1';
    } elseif ($type === 'no_ingresan') {
        $estadoClause = 'AND a.entro = 0';
    } elseif ($type === 'lista_espera') {
        $estadoClause = 'AND a.entro = 2';
    } // 'nomina' no filtra por entro

    // Orden por tipo
    if ($type === 'lista_espera') {
        $orderBy = 'a.espera ASC, a.apellido ASC, a.nombre ASC';
    } else {
        $orderBy = 's.orden ASC, a.apellido ASC, a.nombre ASC';
    }

    $sql = "SELECT 
                a.dni,
                a.apellido,
                a.nombre,
                CASE 
                    WHEN a.vinculo = 0 OR a.vinculo IS NULL THEN 'Ninguno'
                    WHEN v.vinculo IS NOT NULL THEN v.vinculo
                    ELSE 'Ninguno'
                END AS vinculo,
                COALESCE(GROUP_CONCAT(DISTINCT p.telefono SEPARATOR ', '), '') AS telefono,
                COALESCE(GROUP_CONCAT(DISTINCT p.mail SEPARATOR ', '), '') AS mail,
                COALESCE(s.orden, 0) AS orden_sorteo,
                a.espera,
                a.id_sec2,
                a.id_sec3,
                s2.nombre AS escuela_opcion2,
                s3.nombre AS escuela_opcion3
            FROM alumnos a
            LEFT JOIN padrealumno pa ON a.id = pa.dni_alumno
            LEFT JOIN padres p ON pa.dni_padre = p.id
            LEFT JOIN vinculoesc v ON a.vinculo = v.id
            LEFT JOIN sorteo s ON CAST(a.dni AS UNSIGNED) = s.dni AND s.id_secundaria = a.id_secundaria
            LEFT JOIN secundarias s2 ON a.id_sec2 = s2.id
            LEFT JOIN secundarias s3 ON a.id_sec3 = s3.id
            WHERE a.id_secundaria = :esc_id
            $estadoClause
            GROUP BY a.id, a.dni, a.apellido, a.nombre, v.vinculo, s.orden, a.espera, a.id_sec2, a.id_sec3, s2.nombre, s3.nombre
            ORDER BY $orderBy";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'data'=>$rows]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>

