<?php
require_once __DIR__ . '/config.php';
init_session();

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

if (!isset($_SESSION['es_jefatura']) || $_SESSION['es_jefatura'] != 1) {
    die(json_encode(['success' => false, 'error' => 'No autorizado']));
}

$id = $_POST['id'] ?? null;

if (!$id) {
    die(json_encode(['success' => false, 'error' => 'Falta el ID']));
}

try {
    $db = getDBConnection();
    
    // No permitir eliminar vínculos en uso
    $used = $db->prepare("SELECT COUNT(*) as cnt FROM alumnos WHERE vinculo = ?");
    $used->execute([$id]);
    $result = $used->fetch(PDO::FETCH_ASSOC);
    
    if ($result['cnt'] > 0) {
        die(json_encode(['success' => false, 'error' => 'No se puede eliminar: hay alumnos usando este vínculo']));
    }
    
    // Eliminar
    $delete = $db->prepare("DELETE FROM vinculoesc WHERE id = ?");
    $delete->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => 'Vínculo eliminado']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
