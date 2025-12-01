<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
// Nota: asegúrate que el nombre coincide con la BD importada desde jdlacosta.sql
// Si tu BD local se llama 'u227597108_jdlacosta' (como en el dump), usa ese nombre.
define('DB_NAME', 'jdlacosta');
define('DB_USER', 'root');
define('DB_PASS', '');

// Función para obtener la conexión a la base de datos
function getDBConnection() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // En desarrollo devuelve mensaje; en producción registrar y mostrar genérico.
        http_response_code(500);
        echo json_encode(['success'=>false,'message'=>'Error conectando a la BD: '.$e->getMessage()]);
        exit;
    }
}

// Initialize session securely (set cookie params, start session if needed)
function init_session(): void {
    // sensible defaults
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    $cookieParams = session_get_cookie_params();

    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'] ?? 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

// CSRF helpers
function get_csrf_token(): string {
    if (!isset($_SESSION)) init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(?string $token): bool {
    if (!isset($_SESSION)) init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Simple logging utility: appends json entries into api/logs/app.log (create directory writable by webserver)
function write_app_log(string $event, array $data = []): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    $logFile = $logDir . '/app.log';
    $entry = [
        'ts' => date('c'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'data' => $data
    ];
    @file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
?>
