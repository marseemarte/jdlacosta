<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

$numero_sorteo = isset($_POST['numero_sorteo']) ? trim($_POST['numero_sorteo']) : '';

if (empty($numero_sorteo)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Número de sorteo requerido']);
    exit;
}

// Validar que sea un número de 3 dígitos
if (!preg_match('/^\d{3}$/', $numero_sorteo)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'El número debe ser exactamente 3 dígitos']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    // Verificar si ya existe un número de sorteo
    $stmt = $pdo->prepare("SELECT numero FROM num_sorteo LIMIT 1");
    $stmt->execute();
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente) {
        // Actualizar el número existente
        $stmt = $pdo->prepare("UPDATE num_sorteo SET numero = :numero WHERE id = 1");
        $stmt->execute([':numero' => $numero_sorteo]);
    } else {
        // Insertar nuevo número
        $stmt = $pdo->prepare("INSERT INTO num_sorteo (numero) VALUES (:numero)");
        $stmt->execute([':numero' => $numero_sorteo]);
    }

    // Aplicar el sorteo a todas las escuelas
    $stmtEscuelas = $pdo->prepare("SELECT id, COALESCE(vacantes, 0) AS vacantes FROM secundarias");
    $stmtEscuelas->execute();
    $escuelas = $stmtEscuelas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($escuelas as $escuela) {
        $id_sec = $escuela['id'];
        $vacantes = (int)$escuela['vacantes'];

        // Primero: Marcar como entro = 1 a alumnos con comprobado = 1
        $stmtComprobados = $pdo->prepare("
            SELECT alumnos.id FROM alumnos
            INNER JOIN padrealumno ON alumnos.id = padrealumno.dni_alumno
            INNER JOIN padres ON padrealumno.dni_padre = padres.id
            WHERE alumnos.id_secundaria = :id_sec AND alumnos.comprobado = 1
        ");
        $stmtComprobados->execute([':id_sec' => $id_sec]);
        $comprobados = $stmtComprobados->fetchAll(PDO::FETCH_ASSOC);

        $cont = 1;
        foreach ($comprobados as $alumno) {
            $stmt = $pdo->prepare("UPDATE alumnos SET entro = 1 WHERE id = :id");
            $stmt->execute([':id' => $alumno['id']]);
            $cont++;
        }

        // Calcular cupos disponibles para sorteo (vacantes - ingresados por vínculo)
        $stmtVinculo = $pdo->prepare("
            SELECT COUNT(*) as count FROM alumnos
            WHERE id_secundaria = :id_sec AND entro = 1 AND vinculo != 0 AND vinculo IS NOT NULL
        ");
        $stmtVinculo->execute([':id_sec' => $id_sec]);
        $ingresados_vinculo = (int)$stmtVinculo->fetchColumn();
        
        $entran = max(0, $vacantes - $ingresados_vinculo - (count($comprobados) - 1));

        // Segundo: Asignar por sorteo >= número ingresado
        $stmtSorteo1 = $pdo->prepare("
            SELECT alumnos.id FROM alumnos
            INNER JOIN padrealumno ON alumnos.id = padrealumno.dni_alumno
            INNER JOIN padres ON padrealumno.dni_padre = padres.id
            INNER JOIN sorteo ON sorteo.dni = alumnos.dni
            WHERE alumnos.id_secundaria = :id_sec AND alumnos.comprobado = 0 
            AND CAST(RIGHT(alumnos.dni, 3) AS UNSIGNED) >= :numero
            ORDER BY CAST(RIGHT(alumnos.dni, 3) AS UNSIGNED) ASC
        ");
        $stmtSorteo1->execute([':id_sec' => $id_sec, ':numero' => $numero_sorteo]);
        $sorteo1 = $stmtSorteo1->fetchAll(PDO::FETCH_ASSOC);

        $cont = 1;
        $noentran = 0;
        foreach ($sorteo1 as $alumno) {
            if ($cont <= $entran) {
                $stmt = $pdo->prepare("UPDATE alumnos SET entro = 1 WHERE id = :id");
                $stmt->execute([':id' => $alumno['id']]);
            } else {
                $noentran++;
                $stmt = $pdo->prepare("UPDATE alumnos SET entro = 2, espera = :espera WHERE id = :id");
                $stmt->execute([':espera' => $noentran, ':id' => $alumno['id']]);
            }
            $cont++;
        }

        // Tercero: Si aún hay cupos, asignar los que tienen < número
        if ($entran >= $cont) {
            $stmtSorteo2 = $pdo->prepare("
                SELECT alumnos.id FROM alumnos
                INNER JOIN padrealumno ON alumnos.id = padrealumno.dni_alumno
                INNER JOIN padres ON padrealumno.dni_padre = padres.id
                INNER JOIN sorteo ON sorteo.dni = alumnos.dni
                WHERE alumnos.id_secundaria = :id_sec AND alumnos.comprobado = 0
                AND CAST(RIGHT(alumnos.dni, 3) AS UNSIGNED) < :numero
                AND CAST(RIGHT(alumnos.dni, 3) AS UNSIGNED) > 0
                ORDER BY CAST(RIGHT(alumnos.dni, 3) AS UNSIGNED) ASC
            ");
            $stmtSorteo2->execute([':id_sec' => $id_sec, ':numero' => $numero_sorteo]);
            $sorteo2 = $stmtSorteo2->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sorteo2 as $alumno) {
                if ($cont <= $entran) {
                    $stmt = $pdo->prepare("UPDATE alumnos SET entro = 1 WHERE id = :id");
                    $stmt->execute([':id' => $alumno['id']]);
                } else {
                    $noentran++;
                    $stmt = $pdo->prepare("UPDATE alumnos SET entro = 2, espera = :espera WHERE id = :id");
                    $stmt->execute([':espera' => $noentran, ':id' => $alumno['id']]);
                }
                $cont++;
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Número de sorteo guardado y aplicado a todas las escuelas']);
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error servidor: '.$e->getMessage()]);
    exit;
}
?>
