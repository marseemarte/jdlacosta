<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT numero FROM num_sorteo LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo json_encode(['success'=>true,'numero'=>$row['numero']]);
    } else {
        echo json_encode(['success'=>false,'numero'=>null]);
    }
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>
