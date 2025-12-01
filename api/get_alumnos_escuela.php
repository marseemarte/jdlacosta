<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['escuela_id']) || empty($_SESSION['es_jefatura'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';

$escuelaId = isset($_GET['escuela_id']) ? (int)$_GET['escuela_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'nomina';

if ($escuelaId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'escuela_id requerido']);
    exit;
}

try {
    $pdo = getDBConnection();

    $estadoClause = '';
    $params = [':esc_id' => $escuelaId];
    if ($type === 'ingresan') {
        $estadoClause = 'AND a.entro = 1';
    } elseif ($type === 'no_ingresan') {
        $estadoClause = 'AND a.entro = 0';
    } elseif ($type === 'lista_espera') {
        $estadoClause = 'AND a.entro = 2';
    }

    if ($type === 'lista_espera') {
        // Para lista de espera, ordenar por el número de sorteo según la escuela
        $orderBy = 'COALESCE(s.orden, 999999) ASC, a.id ASC, a.apellido ASC, a.nombre ASC';
    } elseif ($type === 'no_ingresan') {
        $orderBy = 'a.id ASC, orden_lista_espera ASC';
    } else {
        // Para ingresan y nomina, ordenar por FID (id) y luego por orden del sorteo
        $orderBy = 'a.id ASC, COALESCE(s.orden, 999999) ASC, a.apellido ASC, a.nombre ASC';
    }

    $sql = "SELECT 
                ROW_NUMBER() OVER (ORDER BY COALESCE(a.fecha_insc,'0000-00-00') ASC, COALESCE(a.hora_insc,'00:00:00') ASC, a.id ASC) AS orden_lista_espera,
                a.id AS fid,
                a.dni,
                a.apellido,
                a.nombre,
                a.escuela,
                CASE 
                    WHEN a.vinculo = 0 OR a.vinculo IS NULL THEN 'Ninguno'
                    WHEN v.vinculo IS NOT NULL THEN v.vinculo
                    ELSE 'Ninguno'
                END AS vinculo,
                COALESCE(GROUP_CONCAT(DISTINCT p.telefono SEPARATOR ', '), '') AS telefono,
                COALESCE(GROUP_CONCAT(DISTINCT p.mail SEPARATOR ', '), '') AS mail,
                COALESCE(s.orden, 0) AS orden_sorteo,
                a.espera,
                a.fecha_insc,
                a.hora_insc,
                a.id_sec2,
                a.id_sec3,
                s2.id AS s2_id,
                s2.nombre AS escuela_opcion2,
                COALESCE(s2.vacantes,0) AS vacantes_op2,
                (SELECT COUNT(*) FROM alumnos ax WHERE ax.id_secundaria = s2.id AND ax.entro = 1) AS ocupados_op2,
                s3.id AS s3_id,
                s3.nombre AS escuela_opcion3,
                COALESCE(s3.vacantes,0) AS vacantes_op3,
                (SELECT COUNT(*) FROM alumnos ax2 WHERE ax2.id_secundaria = s3.id AND ax2.entro = 1) AS ocupados_op3
            FROM alumnos a
            LEFT JOIN padrealumno pa ON a.id = pa.dni_alumno
            LEFT JOIN padres p ON pa.dni_padre = p.id
            LEFT JOIN vinculoesc v ON a.vinculo = v.id
            LEFT JOIN sorteo s ON CAST(a.dni AS UNSIGNED) = s.dni AND s.id_secundaria = a.id_secundaria
            LEFT JOIN secundarias s2 ON a.id_sec2 = s2.id
            LEFT JOIN secundarias s3 ON a.id_sec3 = s3.id
            WHERE a.id_secundaria = :esc_id
            $estadoClause
            GROUP BY a.id, a.dni, a.apellido, a.nombre, a.escuela, v.vinculo, s.orden, a.espera, a.fecha_insc, a.hora_insc, a.id_sec2, a.id_sec3, s2.id, s2.nombre, s2.vacantes, s3.id, s3.nombre, s3.vacantes
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

