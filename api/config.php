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
?>
