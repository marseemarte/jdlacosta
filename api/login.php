<?php
require_once __DIR__ . '/config.php';
// init secure session
init_session();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$clave = isset($_POST['clave']) ? trim($_POST['clave']) : '';
$pass  = isset($_POST['pass'])  ? trim($_POST['pass'])  : '';

// CSRF protection (expect token from client)
$csrf = $_POST['csrf_token'] ?? null;
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

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
    $pdo = getDBConnection();

    // rate limiting (simple, per-session)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
    // clean old attempts > 15 minutes
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($t){ return $t > time() - 900; });
    $attempts = count($_SESSION['login_attempts']);
    if ($attempts >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Demasiados intentos, espere 15 minutos']);
        write_app_log('login_blocked', ['ip' => $ip, 'attempts' => $attempts]);
        exit;
    }

    // Buscar por clave
    $sql = "SELECT id, nombre, abreviatura, distrito, pass FROM secundarias WHERE clave = :clave LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':clave' => $clave]);
    $esc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($esc) {
        $stored = $esc['pass'] ?? '';
        $ok = false;

        // If stored looks like a password hash use password_verify
        if (!empty($stored) && (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon2') === 0 || strpos($stored, '$2a$') === 0)) {
            $ok = password_verify($pass, $stored);
        } else {
            // fallback: legacy plaintext comparison
            if ($pass === $stored) {
                $ok = true;
                // migrate to hashed password
                $newHash = password_hash($pass, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE secundarias SET pass = :pass WHERE id = :id LIMIT 1");
                $upd->execute([':pass' => $newHash, ':id' => $esc['id']]);
                $stored = $newHash;
            }
        }

        if ($ok) {
        // sesión mínima
            session_regenerate_id(true);
            $_SESSION['escuela_id'] = $esc['id'];
            $_SESSION['escuela_nombre'] = $esc['nombre'];
        
        // Detectar si es usuario JEFATURA
        if (isset($esc['abreviatura']) && $esc['abreviatura'] === 'JEFATURA') {
            $_SESSION['es_jefatura'] = true;
            $_SESSION['distrito'] = (int)$esc['distrito'];
        } else {
            $_SESSION['es_jefatura'] = false;
        }
        
        // Retornar información sobre si es JEFATURA para redirección
            write_app_log('login_success', ['id' => $esc['id'], 'clave' => $clave]);
            echo json_encode([
                'success' => true,
                'es_jefatura' => isset($esc['abreviatura']) && $esc['abreviatura'] === 'JEFATURA'
            ]);
        exit;
        }

        // failed: store an attempt timestamp
        $_SESSION['login_attempts'][] = time();
        write_app_log('login_failed', ['clave' => $clave, 'ip' => $ip]);
    }

    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Clave o contraseña incorrecta']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    // don't leak internal errors to the client
    write_app_log('login_error', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Error servidor']);
    exit;
}
?>