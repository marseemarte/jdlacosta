<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo JEFATURA puede reasignar
if (!isset($_SESSION['escuela_id']) || empty($_SESSION['es_jefatura'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';

$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;

if (empty($dni) || $targetId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Parámetros inválidos']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Obtener alumno y sus opciones
    $stmt = $pdo->prepare("SELECT id, dni, id_secundaria, id_sec2, id_sec3 FROM alumnos WHERE dni = :dni LIMIT 1");
    $stmt->execute([':dni' => $dni]);
    $al = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$al) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Alumno no encontrado']);
        exit;
    }

    // Verificar que target corresponde a opción 2 o 3 del alumno
    if ($al['id_sec2'] != $targetId && $al['id_sec3'] != $targetId) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'La escuela objetivo no coincide con las opciones del alumno']);
        exit;
    }

    // Chequear vacantes / ocupados en la escuela destino
    $stmt = $pdo->prepare("SELECT COALESCE(vacantes,0) AS vacantes, nombre FROM secundarias WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $targetId]);
    $sec = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sec) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Escuela destino no encontrada']);
        exit;
    }
    $vacantes = (int)$sec['vacantes'];
    $targetName = $sec['nombre'] ?? '';

    $stmt = $pdo->prepare("SELECT COUNT(*) AS ocupados FROM alumnos WHERE id_secundaria = :id AND entro = 1");
    $stmt->execute([':id' => $targetId]);
    $ocup = (int)$stmt->fetchColumn();

    if ($vacantes <= 0 || $ocup >= $vacantes) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'message'=>'La escuela destino está completa']);
        exit;
    }

    // Realizar reasignación: actualizar id_secundaria al target y marcar como ingresado (entro = 1)
    $stmt = $pdo->prepare("UPDATE alumnos SET id_secundaria = :target, entro = 1, espera = 0 WHERE dni = :dni LIMIT 1");
    $stmt->execute([':target' => $targetId, ':dni' => $dni]);

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Alumno reasignado','school_name'=>$targetName]);
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>