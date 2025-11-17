<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$id_secundaria = isset($_POST['id_secundaria']) ? (int)$_POST['id_secundaria'] : 0;

if (empty($dni) || $id_secundaria <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $db = getDBConnection();
    
    // Buscar alumno por DNI
    $sql = "SELECT id, dni, apellido, nombre, id_secundaria 
            FROM alumnos 
            WHERE dni = :dni 
            LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':dni' => $dni]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alumno) {
        // Alumno no existe: guardar DNI y escuela en sesión para continuar inscripción
        $_SESSION['alumno_dni'] = $dni;
        $_SESSION['alumno_apellido'] = '';
        $_SESSION['alumno_nombre'] = '';
        $_SESSION['id_secundaria'] = $id_secundaria;

        echo json_encode([
            'success' => true,
            'found' => false,
            'already_inscribed' => false,
            'message' => 'Alumno no registrado'
        ]);
        exit;
    }
    
    // Alumno existe, verificar si ya está inscripto
    $already_inscribed = !empty($alumno['id_secundaria']) && (int)$alumno['id_secundaria'] > 0;
    
    $response = [
        'success' => true,
        'found' => true,
        'already_inscribed' => $already_inscribed,
        'alumno' => $alumno
    ];
    
    if ($already_inscribed) {
        // Obtener datos de la escuela donde está inscripto
        $sql2 = "SELECT id, nombre FROM secundarias WHERE id = :id LIMIT 1";
        $stmt2 = $db->prepare($sql2);
        $stmt2->execute([':id' => $alumno['id_secundaria']]);
        $inscribed_school = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        $response['inscribed_school'] = $inscribed_school;
    } else {
        // Guardar datos en sesión para inscription.html
        $_SESSION['alumno_dni'] = $alumno['dni'];
        $_SESSION['alumno_apellido'] = $alumno['apellido'];
        $_SESSION['alumno_nombre'] = $alumno['nombre'];
        $_SESSION['id_secundaria'] = $id_secundaria;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
