<?php
session_start();

// solo procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // aceptar campos enviados por el formulario (username/password) o por fetch (clave/pass)
    $clave = isset($_POST['clave']) ? trim($_POST['clave']) : (isset($_POST['username']) ? trim($_POST['username']) : '');
    $pass  = isset($_POST['pass'])  ? trim($_POST['pass'])  : (isset($_POST['password']) ? trim($_POST['password']) : '');

    if ($clave === '' || $pass === '') {
        $msg = 'Complete ambos campos';
        // detectar petición AJAX (fetch) por header X-Requested-With
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        header('Location: ' . basename(__FILE__) . '?error=' . urlencode($msg));
        exit;
    }

    // usar conex.php (wrapper) para obtener $pdo o la función conex()
    $conexFile = __DIR__ . '/conex.php';
    if (!file_exists($conexFile)) {
        $msg = 'Falta conexión a la base de datos (conex.php)';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        header('Location: ' . basename(__FILE__) . '?error=' . urlencode($msg));
        exit;
    }

    require_once $conexFile;

    try {
        // preferimos la variable $pdo si fue inicializada por conex.php
        if (!empty($pdo) && $pdo instanceof PDO) {
            $db = $pdo;
        } else {
            // conex() puede lanzar excepción si falla
            $db = conex();
        }

        $sql = "SELECT id, nombre FROM secundarias WHERE clave = :clave AND pass = :pass LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':clave' => $clave, ':pass' => $pass]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $_SESSION['escuela_id'] = $row['id'];
            $_SESSION['escuela_nombre'] = $row['nombre'];

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true]);
                exit;
            }

            header('Location: dashboard.php');
            exit;
        }

        // credenciales inválidas
        $msg = 'Clave o contraseña incorrecta';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }

        header('Location: ' . basename(__FILE__) . '?error=' . urlencode($msg));
        exit;

    } catch (Exception $e) {
        $msg = 'Error servidor';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        header('Location: ' . basename(__FILE__) . '?error=' . urlencode($msg));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inscripción Secundaria</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/login.css">
    <style>
        .instituciones-text { 
            margin-top: 12px; 
            color:#333; 
            font-weight:500; }

        .site-footer {
            margin-top:30px;
            padding:14px 0;
            text-align:center;
            font-size:0.9rem;
            color:#666;
            border-top: none;
            background: transparent;
            width: 100%;
        }

        .error-message { 
            color: #b71c1c; }

    </style>
</head>
<body class="d-flex flex-column" style="min-height:100vh; background: #ffffff;">
    <main class="d-flex align-items-center justify-content-center flex-grow-1">
    <div class="login-container shadow-sm">
        <h2 class="mb-3 text-center">Inscripcion 2025</h2>
        <p class="instituciones-text text-center mb-3">Ingreso para instituciones</p>
        <form id="loginForm" class="needs-validation" novalidate>
            <div class="form-group mb-3">
                <label for="username">Clave provincial</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group mb-3">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div id="errorMsg" class="error-message mb-2" role="alert" style="display:none;"></div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            © 2025 JDLacosta - Todos los derechos reservados.
        </div>
    </footer>

<script>
// Mostrar error pasado por servidor (login.php?error=...)
(() => {
    const params = new URLSearchParams(window.location.search);
    const serverErr = params.get('error');
    if (serverErr) {
        const err = document.getElementById('errorMsg');
        err.textContent = decodeURIComponent(serverErr);
        err.style.display = 'block';
    }
})();

document.getElementById('loginForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const clave = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value;
    const err = document.getElementById('errorMsg');
    err.style.display = 'none';

    if(!clave || !pass){
        err.textContent = 'Complete ambos campos.';
        err.style.display = 'block';
        return;
    }

    try {
        const fd = new FormData();
        fd.append('username', clave);
        fd.append('password', pass);
        // marcar petición como AJAX para que el servidor devuelva JSON
        const res = await fetch(window.location.pathname.replace(/.*\//, '') || 'login.php', { method:'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const text = await res.text();

        // intentar parsear JSON; si falla mostrar texto bruto para depuración
        let json;
        try {
            json = JSON.parse(text);
        } catch (parseErr) {
            err.textContent = 'Respuesta no válida del servidor: ' + text;
            err.style.display = 'block';
            console.error('Respuesta no-JSON:', text);
            return;
        }

        if (res.ok && json.success) {
            window.location.href = 'dashboard.php';
        } else {
            err.textContent = json.message || 'Credenciales incorrectas.';
            err.style.display = 'block';
        }
    } catch (e) {
        err.textContent = 'Error de conexión al servidor. Ver consola/Network.';
        err.style.display = 'block';
        console.error(e);
    }
});
</script>
</body>
</html>