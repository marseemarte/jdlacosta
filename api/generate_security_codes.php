<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if (!isset($_SESSION['distrito'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Distrito no definido en la sesión']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $distrito = (int)$_SESSION['distrito'];

    $stmt = $pdo->prepare("SELECT id FROM secundarias WHERE distrito = :distrito AND (abreviatura IS NULL OR abreviatura != 'JEFATURA')");
    $stmt->execute([':distrito' => $distrito]);
    $escuelas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$escuelas) {
        echo json_encode(['success' => true, 'updated' => 0, 'message' => 'No hay escuelas para actualizar']);
        exit;
    }

    $pdo->beginTransaction();

    $update = $pdo->prepare("UPDATE secundarias SET security_code = :code WHERE id = :id");
    $codesGenerados = [];

    foreach ($escuelas as $id) {
        $code = '';
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        } while (in_array($code, $codesGenerados, true));

        $codesGenerados[] = $code;
        $update->execute([
            ':code' => $code,
            ':id' => $id
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'updated' => count($escuelas),
        'message' => 'Códigos generados correctamente'
    ]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
}
