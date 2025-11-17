<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] != 'GET') {
    die(json_encode(['success' => false, 'error' => 'Método no permitido']));
}

$dni = $_GET['dni'] ?? '';

if (empty($dni)) {
    die(json_encode(['valid' => false, 'error' => 'Falta DNI']));
}

// Limpiar DNI
$limpio = preg_replace('/[\s.]/i', '', $dni);

if (!preg_match('/^\d+$/', $limpio)) {
    die(json_encode(['valid' => false, 'error' => 'DNI debe contener solo números']));
}

if (strlen($limpio) < 7 || strlen($limpio) > 8) {
    die(json_encode(['valid' => false, 'error' => 'DNI debe tener entre 7 y 8 dígitos']));
}

echo json_encode(['valid' => true, 'dni' => $limpio]);
