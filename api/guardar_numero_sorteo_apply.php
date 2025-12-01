<?php
require_once __DIR__ . '/config.php';
init_session();
header('Content-Type: application/json; charset=utf-8');

// Requiere sesión de jefatura
if (!isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    // Si se envía un número por POST lo guardamos; si no, intentamos leer el existente
    $inputNumero = isset($_POST['numero']) ? trim($_POST['numero']) : null;

    if ($inputNumero !== null) {
        // Acepta valores numéricos entre 0 y 999, permite ceros a la izquierda
        if (!preg_match('/^\d{1,3}$/', $inputNumero)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Número inválido. Debe ser 0-999 (hasta 3 dígitos).']);
            exit;
        }
        $numero = (int)$inputNumero;
    } else {
        // Leer el número actual en la tabla num_sorteo
        $stmt = $pdo->query("SELECT numero FROM num_sorteo ORDER BY id DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['numero'] === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No existe número de sorteo. Envíe "numero" por POST.']);
            exit;
        }
        $numero = (int)$row['numero'];
    }

    // Normalizar el número a entero entre 0 y 999
    $numero = max(0, min(999, $numero));

    // Si vino por POST, guardar/actualizar la tabla num_sorteo
    if ($inputNumero !== null) {
        $pdo->beginTransaction();
        // Insertamos una nueva fila con timestamp (mantenemos histórico)
        $stmt = $pdo->prepare("INSERT INTO num_sorteo (numero, created_at) VALUES (:numero, NOW())");
        $stmt->execute([':numero' => sprintf('%03d', $numero)]);
        $pdo->commit();
    }

    // Empezamos la aplicación del sorteo a todas las escuelas
    $pdo->beginTransaction();

    // Obtener todas las secundarias con sus vacantes
    $stmtSec = $pdo->query("SELECT id, COALESCE(vacantes,0) AS vacantes FROM secundarias");
    $secundarias = $stmtSec->fetchAll(PDO::FETCH_ASSOC);

    $summary = [];

    foreach ($secundarias as $sec) {
        $secId = (int)$sec['id'];
        $vacantes = (int)$sec['vacantes'];

        // Resetear estado previo solamente para alumnos no comprobados (vamos a recalcular)
        $resetStmt = $pdo->prepare("UPDATE alumnos SET entro = 0, espera = 0 WHERE id_secundaria = :sec AND comprobado = 0");
        $resetStmt->execute([':sec' => $secId]);

        // Marcar los comprobados como ingresan (entro = 1)
        $markComprobados = $pdo->prepare("UPDATE alumnos SET entro = 1, espera = 0 WHERE id_secundaria = :sec AND comprobado = 1");
        $markComprobados->execute([':sec' => $secId]);

        // Contar cuantos ya ocupan (incluye comprobados y cualquier otro que ya tenga entro=1)
        $countOcupados = $pdo->prepare("SELECT COUNT(*) as cnt FROM alumnos WHERE id_secundaria = :sec AND entro = 1");
        $countOcupados->execute([':sec' => $secId]);
        $ocupados = (int)$countOcupados->fetchColumn();

        $disponibles = $vacantes - $ocupados;
        if ($disponibles < 0) $disponibles = 0;

        // Seleccionar candidatos al sorteo: alumnos asignados a esa secundaria, no comprobados, y sin vínculo (vinculo = 0 o NULL)
        // Obtener ultimos3 desde tabla sorteo si existe, si no, usar el %1000 del DNI
        $sqlCandidates = "SELECT a.id, a.dni, COALESCE(NULLIF(s.ultimos3,''), (CAST(a.dni AS UNSIGNED) % 1000)) AS ultimos3
            FROM alumnos a
            LEFT JOIN sorteo s ON s.dni = a.dni AND s.id_secundaria = a.id_secundaria
            WHERE a.id_secundaria = :sec AND a.comprobado = 0 AND (a.vinculo = 0 OR a.vinculo IS NULL)
            ORDER BY (CASE WHEN COALESCE(NULLIF(s.ultimos3,''), (CAST(a.dni AS UNSIGNED) % 1000)) >= :num THEN 0 ELSE 1 END),
                     COALESCE(NULLIF(s.ultimos3,''), (CAST(a.dni AS UNSIGNED) % 1000)) ASC";

        $candStmt = $pdo->prepare($sqlCandidates);
        $candStmt->execute([':sec' => $secId, ':num' => $numero]);
        $candidates = $candStmt->fetchAll(PDO::FETCH_ASSOC);

        $esperaCounter = 0;
        $admitidos = 0;
        $noAdmitidos = 0;

        foreach ($candidates as $c) {
            $alumnoId = (int)$c['id'];
            if ($disponibles > 0) {
                $upd = $pdo->prepare("UPDATE alumnos SET entro = 1, espera = 0 WHERE id = :id");
                $upd->execute([':id' => $alumnoId]);
                $disponibles--;
                $admitidos++;
            } else {
                $esperaCounter++;
                $upd = $pdo->prepare("UPDATE alumnos SET entro = 2, espera = :espera WHERE id = :id");
                $upd->execute([':espera' => $esperaCounter, ':id' => $alumnoId]);
                $noAdmitidos++;
            }
        }

        $summary[] = [
            'secundaria_id' => $secId,
            'vacantes' => $vacantes,
            'ocupados_previos' => $ocupados,
            'admitidos_por_sorteo' => $admitidos,
            'en_espera' => $noAdmitidos
        ];
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Sorteo aplicado correctamente', 'numero' => sprintf('%03d', $numero), 'summary' => $summary]);
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
    exit;
}

?>