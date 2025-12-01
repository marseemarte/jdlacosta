<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

echo json_encode(['success' => true, 'csrf_token' => get_csrf_token()]);
