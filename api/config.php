<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'jdlacosta');
define('DB_USER', 'root');
define('DB_PASS', '');

// Función para obtener la conexión a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", 
            DB_USER, 
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Error de conexión a la base de datos: " . $e->getMessage());
    }
}
?>
