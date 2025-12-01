<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

// Solo JEFATURA puede acceder
if (!isset($_SESSION['escuela_id']) || empty($_SESSION['es_jefatura']) || empty($_SESSION['distrito'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $distrito = (int) $_SESSION['distrito'];

    // Devuelve todos los inscriptos del distrito, con secundaria, vínculo y orden de sorteo si existe
    $sql = "SELECT 
                a.dni,
                a.apellido,
                a.nombre,
                CASE 
                    WHEN a.vinculo = 0 OR a.vinculo IS NULL THEN 'Ninguno'
                    WHEN v.vinculo IS NOT NULL THEN v.vinculo
                    ELSE 'Ninguno'
                END AS vinculo,
                COALESCE(s.orden, 0) AS orden_sorteo,
                sec.nombre AS secundaria
            FROM alumnos a
            INNER JOIN secundarias sec ON sec.id = a.id_secundaria
            LEFT JOIN vinculoesc v ON a.vinculo = v.id
            LEFT JOIN sorteo s ON CAST(a.dni AS UNSIGNED) = s.dni AND s.id_secundaria = a.id_secundaria
            WHERE sec.distrito = :distrito
            ORDER BY a.apellido ASC, a.nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':distrito' => $distrito]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
    exit;
}
?>

