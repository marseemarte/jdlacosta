<?php

// Si falta el archivo de configuración no provocamos un fatal al incluir: definimos
// una función conex() que lanza una excepción y dejamos $pdo = null.
if (!file_exists(__DIR__ . '/api/config.php')) {
    if (!function_exists('conex')) {
        function conex(): PDO {
            throw new RuntimeException('Falta api/config.php en el proyecto.');
        }
    }
    $pdo = null;
    return;
}

require_once __DIR__ . '/api/config.php';

if (!function_exists('conex')) {
    function conex(): PDO {
        // getDBConnection() viene de api/config.php y puede lanzar excepciones
        return getDBConnection();
    }
}

// Intentar crear $pdo para usos rápidos; si falla dejamos $pdo = null
try {
    $pdo = conex();
} catch (Throwable $e) {
    // En local puede ser útil ver el mensaje; aquí lo silenciamos y dejamos $pdo nulo.
    $pdo = null;
}

?>
