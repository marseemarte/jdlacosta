<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';
$pass  = isset($_POST['pass'])  ? trim($_POST['pass'])  : '';

if ($clave === '' || $pass === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Complete clave y pass']);
    exit;
}

// cargar config y obtener PDO
$config = __DIR__ . '/config.php';
if (!file_exists($config)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Falta api/config.php']);
    exit;
}
require_once $config;

try {
    // getDBConnection() está definido en config.php
    $pdo = getDBConnection();

    // tabla 'secundarias' (ajustar si tu tabla tiene otro nombre)
    $sql = "SELECT id, nombre FROM secundarias WHERE clave = :clave AND pass = :pass LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':clave' => $clave, ':pass' => $pass]);
    $esc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($esc) {
        // sesión mínima
        $_SESSION['escuela_id'] = $esc['id'];
        $_SESSION['escuela_nombre'] = $esc['nombre'];
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Clave o contraseña incorrecta']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
    exit;
}
?>