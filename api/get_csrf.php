<?php
// Este archivo es para obtener el token CSRF que usamos para proteger los formularios
// Lo llamamos desde JavaScript (inscripcion.js) antes de enviar formularios
// Si no hay token en la sesión, genera uno nuevo. Si ya existe, devuelve ese.
// Devuelve un JSON con el token para que lo usemos en los formularios
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['success' => true, 'csrf_token' => get_csrf_token()]);
