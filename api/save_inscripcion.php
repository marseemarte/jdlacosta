<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

// Log inicial
write_app_log('save_inscripcion_init', ['method' => $_SERVER['REQUEST_METHOD']]);

// Crear directorio para imágenes si no existe
$uploadDir = __DIR__ . '/../img/dni/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    $pdo = getDBConnection();
    
    // Log de datos recibidos
    write_app_log('save_inscripcion_post', [
        'has_csrf' => !empty($_POST['csrf_token']),
        'escuela_id' => $_POST['escuela_id'] ?? 'missing',
        'dni_est' => $_POST['dni_estudiante'] ?? 'missing',
        'dni_tutor' => $_POST['dni_tutor'] ?? 'missing',
        'files_count' => count($_FILES)
    ]);
    
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!validate_csrf_token($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        write_app_log('csrf_validation_failed', []);
        exit;
    }
    $pdo->beginTransaction();

    // Obtener datos del formulario
    $escuela_id = isset($_POST['escuela_id']) ? (int)$_POST['escuela_id'] : 0;
    
    if ($escuela_id <= 0) {
        throw new Exception('ID de escuela no válido');
    }

    // If the user is logged in as a school, the posted escuela_id must match the session
    if (isset($_SESSION['escuela_id']) && (int)$_SESSION['escuela_id'] > 0) {
        if ((int)$_SESSION['escuela_id'] !== (int)$escuela_id) {
            throw new Exception('ID de escuela no coincide con la sesión');
        }
    }

    // Datos del estudiante
    $dni_estudiante = trim($_POST['dni_estudiante'] ?? '');
    $nombre_estudiante = trim($_POST['nombre_estudiante'] ?? '');
    $apellido_estudiante = trim($_POST['apellido_estudiante'] ?? '');
    $fecha_nac_estudiante = $_POST['fecha_nacimiento_estudiante'] ?? '';
    $domicilio_estudiante = trim($_POST['domicilio_estudiante'] ?? '');
    $localidad_id = isset($_POST['localidad_estudiante']) ? (int)$_POST['localidad_estudiante'] : 0;
    $escuela_procedencia_id = isset($_POST['escuela_procedencia']) ? (int)$_POST['escuela_procedencia'] : 0;
    $segunda_opcion = isset($_POST['segunda_opcion']) ? (int)$_POST['segunda_opcion'] : 0;
    $tercera_opcion = isset($_POST['tercera_opcion']) ? (int)$_POST['tercera_opcion'] : 0;
    $turno = trim($_POST['turno_preferencia'] ?? '');
    $vinculo_id = isset($_POST['vinculo']) ? (int)$_POST['vinculo'] : 0;

    // Datos dinámicos según el vínculo
    $dni_hermano = '';
    $nombre_hermano = '';
    $dni_profesor = '';
    $nombre_profesor = '';
    $escuela_profesor = '';
    $numero_ppi = '';
    $documentacion_ppi = '';

    // Procesar campos dinámicos según el vínculo
    if ($vinculo_id == 2) { // Hermano/a de alumno/a
        $dni_hermano = trim($_POST['dni_hermano'] ?? '');
        $nombre_hermano = trim($_POST['nombre_hermano'] ?? '');
    } elseif ($vinculo_id == 3) { // Hijo/a de profesor/a
        $dni_profesor = trim($_POST['dni_profesor'] ?? '');
        $nombre_profesor = trim($_POST['nombre_profesor'] ?? '');
        $escuela_profesor = trim($_POST['escuela_profesor'] ?? '');
    } elseif ($vinculo_id == 4) { // PPI
        $numero_ppi = trim($_POST['numero_ppi'] ?? '');
        // Manejar archivo de documentación PPI
        if (isset($_FILES['documentacion_ppi']) && $_FILES['documentacion_ppi']['error'] === UPLOAD_ERR_OK) {
            // validate upload
            $file = $_FILES['documentacion_ppi'];
            if ($file['size'] > 3 * 1024 * 1024) throw new Exception('Archivo PPI demasiado grande');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg','image/png','application/pdf'];
            if (!in_array($mime, $allowed, true)) throw new Exception('Tipo de archivo PPI no permitido');
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $documentacion_ppi = bin2hex(random_bytes(8)) . '_ppi.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $documentacion_ppi)) throw new Exception('Error al guardar documento PPI');
        }
    }

    // Datos del tutor
    $dni_tutor = trim($_POST['dni_tutor'] ?? '');
    $nombre_tutor = trim($_POST['nombre_tutor'] ?? '');
    $apellido_tutor = trim($_POST['apellido_tutor'] ?? '');
    $fecha_nac_tutor = $_POST['fecha_nacimiento_tutor'] ?? '';
    $telefono_tutor = trim($_POST['telefono_tutor'] ?? '');
    $email_tutor = trim($_POST['email_tutor'] ?? '');

    // Validaciones básicas
    if (empty($dni_estudiante) || empty($nombre_estudiante) || empty($apellido_estudiante)) {
        throw new Exception('Faltan datos obligatorios del estudiante');
    }

    if (empty($dni_tutor) || empty($nombre_tutor) || empty($apellido_tutor)) {
        throw new Exception('Faltan datos obligatorios del tutor');
    }

    // Validaciones de campos dinámicos según el vínculo
    if ($vinculo_id == 2) { // Hermano/a de alumno/a
        if (empty($dni_hermano) || empty($nombre_hermano)) {
            throw new Exception('Faltan datos del hermano/a (DNI y nombre)');
        }
    } elseif ($vinculo_id == 3) { // Hijo/a de profesor/a
        if (empty($dni_profesor) || empty($nombre_profesor) || empty($escuela_profesor)) {
            throw new Exception('Faltan datos del profesor/a (DNI, nombre y escuela)');
        }
    } elseif ($vinculo_id == 4) { // PPI
        if (empty($numero_ppi) || empty($documentacion_ppi)) {
            throw new Exception('Faltan datos del PPI (número y documentación)');
        }
    }

    // Obtener nombre de la localidad
    $localidad_nombre = '';
    if ($localidad_id > 0) {
        $stmt = $pdo->prepare("SELECT localidad FROM localidad WHERE id = :id");
        $stmt->execute([':id' => $localidad_id]);
        $loc = $stmt->fetch();
        $localidad_nombre = $loc['localidad'] ?? '';
    }

    // Obtener nombre de la escuela de procedencia
    $escuela_procedencia_nombre = '';
    if ($escuela_procedencia_id > 0) {
        $stmt = $pdo->prepare("SELECT nombre FROM escuelas WHERE id = :id");
        $stmt->execute([':id' => $escuela_procedencia_id]);
        $esc = $stmt->fetch();
        $escuela_procedencia_nombre = $esc['nombre'] ?? '';
    }

    // Subir imágenes del DNI del estudiante
    $dni_frente_estudiante = '';
    $dni_reverso_estudiante = '';
    if (isset($_FILES['dni_frente_estudiante']) && $_FILES['dni_frente_estudiante']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['dni_frente_estudiante'];
        if ($file['size'] > 3 * 1024 * 1024) throw new Exception('Archivo DNI frente (estudiante) demasiado grande');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) throw new Exception('Tipo de archivo no permitido para DNI frente (estudiante)');
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dni_frente_estudiante = $dni_estudiante . '_es.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $dni_frente_estudiante)) throw new Exception('Error al guardar DNI frente (estudiante)');
    } else {
        throw new Exception('Debe subir la foto del frente del DNI del estudiante');
    }
    if (isset($_FILES['dni_reverso_estudiante']) && $_FILES['dni_reverso_estudiante']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['dni_reverso_estudiante'];
        if ($file['size'] > 3 * 1024 * 1024) throw new Exception('Archivo DNI reverso (estudiante) demasiado grande');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) throw new Exception('Tipo de archivo no permitido para DNI reverso (estudiante)');
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dni_reverso_estudiante = $dni_estudiante . '_es_r.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $dni_reverso_estudiante)) throw new Exception('Error al guardar DNI reverso (estudiante)');
    } else {
        throw new Exception('Debe subir la foto del reverso del DNI del estudiante');
    }

    // Subir imágenes del DNI del tutor
    $dni_frente_tutor = '';
    $dni_reverso_tutor = '';
    if (isset($_FILES['dni_frente_tutor']) && $_FILES['dni_frente_tutor']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['dni_frente_tutor'];
        if ($file['size'] > 3 * 1024 * 1024) throw new Exception('Archivo DNI frente (tutor) demasiado grande');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) throw new Exception('Tipo de archivo no permitido para DNI frente (tutor)');
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dni_frente_tutor = $dni_tutor . '_pf.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $dni_frente_tutor)) throw new Exception('Error al guardar DNI frente (tutor)');
    } else {
        throw new Exception('Debe subir la foto del frente del DNI del tutor');
    }
    if (isset($_FILES['dni_reverso_tutor']) && $_FILES['dni_reverso_tutor']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['dni_reverso_tutor'];
        if ($file['size'] > 3 * 1024 * 1024) throw new Exception('Archivo DNI reverso (tutor) demasiado grande');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) throw new Exception('Tipo de archivo no permitido para DNI reverso (tutor)');
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $dni_reverso_tutor = $dni_tutor . '_pf_r.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $dni_reverso_tutor)) throw new Exception('Error al guardar DNI reverso (tutor)');
    } else {
        throw new Exception('Debe subir la foto del reverso del DNI del tutor');
    }

    // Insertar o actualizar tutor en tabla padres
    $dni_tutor_int = (int)$dni_tutor;
    $stmt = $pdo->prepare("SELECT id FROM padres WHERE dni = :dni");
    $stmt->execute([':dni' => $dni_tutor_int]);
    $tutor_existente = $stmt->fetch();

    if ($tutor_existente) {
        // Actualizar tutor existente
        $stmt = $pdo->prepare("UPDATE padres SET nombre = :nombre, apellido = :apellido, fecha = :fecha, telefono = :telefono, mail = :mail WHERE dni = :dni");
        $stmt->execute([
            ':dni' => $dni_tutor_int,
            ':nombre' => $nombre_tutor,
            ':apellido' => $apellido_tutor,
            ':fecha' => $fecha_nac_tutor,
            ':telefono' => $telefono_tutor,
            ':mail' => $email_tutor
        ]);
        $tutor_id = $tutor_existente['id'];
    } else {
        // Insertar nuevo tutor
        $stmt = $pdo->prepare("INSERT INTO padres (nombre, apellido, dni, fecha, telefono, mail) VALUES (:nombre, :apellido, :dni, :fecha, :telefono, :mail)");
        $stmt->execute([
            ':nombre' => $nombre_tutor,
            ':apellido' => $apellido_tutor,
            ':dni' => $dni_tutor_int,
            ':fecha' => $fecha_nac_tutor,
            ':telefono' => $telefono_tutor,
            ':mail' => $email_tutor
        ]);
        $tutor_id = $pdo->lastInsertId();
    }

    // Insertar estudiante en tabla alumnos
    // Nota: Algunos campos tienen valores por defecto según la estructura de la BD
    $stmt = $pdo->prepare("INSERT INTO alumnos (
        dni, apellido, nombre, fecha, direccion, localidad, escuela, vinculo, 
        entro, formulario_6to, id_secundaria, comprobado, dni_hermano, nombre_hermano, 
        curso, dni_personal, nombre_personal, turno, espera, matricula, 
        fecha_insc, hora_insc, id_sec2, id_sec3
    ) VALUES (
        :dni, :apellido, :nombre, :fecha, :direccion, :localidad, :escuela, :vinculo,
        0, 0, :id_secundaria, 0, :dni_hermano, :nombre_hermano,
        '0', :dni_profesor, :nombre_profesor, :turno, 0, 0,
        CURDATE(), CURTIME(), :id_sec2, :id_sec3
    )");
    
    $stmt->execute([
        ':dni' => $dni_estudiante,
        ':apellido' => $apellido_estudiante,
        ':nombre' => $nombre_estudiante,
        ':fecha' => $fecha_nac_estudiante,
        ':direccion' => $domicilio_estudiante,
        ':localidad' => $localidad_nombre,
        ':escuela' => $escuela_procedencia_nombre,
        ':vinculo' => $vinculo_id,
        ':id_secundaria' => $escuela_id,
        ':dni_hermano' => $dni_hermano,
        ':nombre_hermano' => $nombre_hermano,
        ':dni_profesor' => $dni_profesor,
        ':nombre_profesor' => $nombre_profesor,
        ':turno' => $turno,
        ':id_sec2' => $segunda_opcion > 0 ? $segunda_opcion : 0,
        ':id_sec3' => $tercera_opcion > 0 ? $tercera_opcion : 0
    ]);

    $alumno_id = $pdo->lastInsertId();

    // Crear relación en padrealumno
    $dni_estudiante_int = (int)$dni_estudiante;
    $stmt = $pdo->prepare("INSERT INTO padrealumno (dni_alumno, dni_padre, id_vinculo) VALUES (:dni_alumno, :dni_padre, :id_vinculo)");
    $stmt->execute([
        ':dni_alumno' => $dni_estudiante_int,
        ':dni_padre' => $dni_tutor_int,
        ':id_vinculo' => $vinculo_id
    ]);

    $pdo->commit();

    write_app_log('inscripcion_saved', ['escuela' => $escuela_id, 'alumno_dni' => $dni_estudiante]);

    echo json_encode([
        'success' => true,
        'message' => 'Inscripción guardada exitosamente',
        'alumno_id' => $alumno_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la inscripción: ' . $e->getMessage()
    ]);
}
?>

