<?php

session_start();
header('Content-Type: application/json; charset=utf-8');

// requiere sesión iniciada por login
if (!isset($_SESSION['escuela_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';
$pdo = getDBConnection();

$escuela_id = (int) $_SESSION['escuela_id'];
$type = isset($_GET['type']) ? $_GET['type'] : 'ingresan'; // 'ingresan'|'no_ingresan'|'lista_espera'|'both'

// Obtener alumnos con teléfono, mail y nombre del vínculo desde las tablas correctas
// IMPORTANTE: Esta consulta NO tiene límite - muestra TODOS los alumnos según el tipo
// entro puede ser: 0 (no ingresan), 1 (ingresan), 2 (lista de espera)
try {
    // Determinar el valor de entro según el tipo solicitado
    if ($type === 'no_ingresan') {
        $ingresoVal = 0;
    } elseif ($type === 'lista_espera') {
        $ingresoVal = 2;
    } else {
        $ingresoVal = 1; // por defecto 'ingresan'
    }
    
    // Query con JOINs para obtener telefono y mail de padres, y nombre del vínculo
    // Nota: dni en alumnos es varchar, pero en padrealumno es int, así que usamos CAST
    // Para lista de espera, ordenamos por el campo 'espera', sino por orden del sorteo
    // NO aplicamos LIMIT porque queremos mostrar TODOS los registros del tipo solicitado
    if ($type === 'lista_espera') {
        // Para lista de espera, ordenar por el campo espera (orden en lista de espera)
        $orderByClause = "a.espera ASC, a.apellido ASC, a.nombre ASC";
    } else {
        // Para ingresan y no_ingresan, ordenar por orden del sorteo
        $orderByClause = "orden_sorteo ASC, a.apellido ASC, a.nombre ASC";
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
                COALESCE(s.orden, 999999) AS orden_sorteo,
                a.espera
            FROM alumnos a
            LEFT JOIN padrealumno pa ON CAST(a.dni AS UNSIGNED) = pa.dni_alumno
            LEFT JOIN padres p ON pa.dni_padre = p.dni
            LEFT JOIN vinculoesc v ON a.vinculo = v.id
            LEFT JOIN sorteo s ON CAST(a.dni AS UNSIGNED) = s.dni AND s.id_secundaria = a.id_secundaria
            WHERE a.id_secundaria = :esc_id 
            AND a.entro = :ingreso 
            GROUP BY a.id, a.dni, a.apellido, a.nombre, v.vinculo, s.orden, a.espera
            ORDER BY " . $orderByClause;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':esc_id' => $escuela_id, ':ingreso' => $ingresoVal]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $rows]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>