<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
$pdo = getDBConnection();

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';
if ($dni === '') {
    echo json_encode(['success' => false, 'message' => 'Falta dni']);
    exit;
}

try {
    // buscamos en padrealumnos por dni_alumno
    $stmt = $pdo->prepare("SELECT dni_padre, id_vinculo FROM padrealumnos WHERE dni_alumno = :dni ORDER BY id DESC LIMIT 1");
    $stmt->execute([':dni' => $dni]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No encontrado']);
        exit;
    }

    $id_vinculo = $row['id_vinculo'];
    $dni_padre = $row['dni_padre'];

    // intentamos resolver nombre del vínculo desde una tabla 'vinculos' si existe
    $vinculoNombre = null;
    if ($id_vinculo !== null && $id_vinculo !== '') {
        try {
            $vst = $pdo->prepare("SELECT nombre FROM vinculos WHERE id = :id LIMIT 1");
            $vst->execute([':id' => $id_vinculo]);
            $vn = $vst->fetchColumn();
            if ($vn) $vinculoNombre = $vn;
        } catch (Exception $e) {
            $vinculoNombre = null;
        }
    }

    echo json_encode([
        'success' => true,
        'id_vinculo' => $id_vinculo,
        'vinculo' => $vinculoNombre,
        'dni_padre' => $dni_padre
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB']);
}