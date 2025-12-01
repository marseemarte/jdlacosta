<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$id_secundaria = isset($_GET['id_secundaria']) ? (int)$_GET['id_secundaria'] : 0;

if ($id_secundaria <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'id_secundaria requerido']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Obtener vacantes totales
    $stmt = $pdo->prepare("SELECT COALESCE(vacantes,0) AS vacantes FROM secundarias WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id_secundaria]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $vacantes_totales = (int)($row['vacantes'] ?? 0);

    // Contar alumnos ingresados por vínculo en esta escuela
    // vinculo != 0 Y entro = 1 significa que entraron por vínculo/hermano/docente
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM alumnos WHERE id_secundaria = :id AND entro = 1 AND vinculo != 0 AND vinculo IS NOT NULL");
    $stmt->execute([':id' => $id_secundaria]);
    $ingresados_vinculo = (int)$stmt->fetchColumn();

    // Calcular vacantes para sorteo
    $vacantes_sorteo = max(0, $vacantes_totales - $ingresados_vinculo);

    echo json_encode([
        'success' => true,
        'vacantes_totales' => $vacantes_totales,
        'ingresados_vinculo' => $ingresados_vinculo,
        'vacantes_sorteo' => $vacantes_sorteo
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>
