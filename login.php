

<?php
// use central config to initialize sessions securely
$configFile = __DIR__ . '/api/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
    init_session();
} else {
    // fallback
    session_start();
}

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

    // usar api/config.php para obtener la conexión
    if (!file_exists($configFile)) {
        $msg = 'Falta fichero de configuración (api/config.php)';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
        header('Location: ' . basename(__FILE__) . '?error=' . urlencode($msg));
        exit;
    }

    // config already required above
    try {
        $db = getDBConnection();

        $sql = "SELECT id, nombre, abreviatura, distrito, pass FROM secundarias WHERE clave = :clave LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':clave' => $clave]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user found, check password (supports legacy plaintext -> migration)
        if ($row) {
            $stored = $row['pass'] ?? '';
            $valid = false;

            if (!empty($stored) && (strpos($stored, '$2y$') === 0 || strpos($stored, '$argon2') === 0 || strpos($stored, '$2a$') === 0)) {
                $valid = password_verify($pass, $stored);
            } else {
                if ($pass === $stored) {
                    $valid = true;
                    // migrate to hashed
                    $newHash = password_hash($pass, PASSWORD_DEFAULT);
                    $upd = $db->prepare("UPDATE secundarias SET pass = :pass WHERE id = :id LIMIT 1");
                    $upd->execute([':pass' => $newHash, ':id' => $row['id']]);
                }
            }

            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['escuela_id'] = $row['id'];
                $_SESSION['escuela_nombre'] = $row['nombre'];
            
            // Detectar si es usuario JEFATURA
            $es_jefatura = isset($row['abreviatura']) && $row['abreviatura'] === 'JEFATURA';
                if ($es_jefatura) {
                $_SESSION['es_jefatura'] = true;
                $_SESSION['distrito'] = (int)$row['distrito'];
            } else {
                $_SESSION['es_jefatura'] = false;
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                    write_app_log('login_success', ['id' => $row['id'], 'clave' => $clave]);
                    echo json_encode([
                        'success' => true,
                        'es_jefatura' => $es_jefatura
                    ]);
                exit;
                }
            }

            // Redirigir según tipo de usuario
            if ($es_jefatura) {
                header('Location: dashboard_jefatura.php');
            } else {
                header('Location: dashboard.php');
            }
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
                <small><a href="#" id="resetPasswordLink" class="text-decoration-none">¿Olvidaste tu contraseña?</a></small>
            </div>
            <div id="errorMsg" class="error-message mb-2" role="alert" style="display:none;"></div>
            <button type="submit" class="btn btn-primary w-100">Ingresar</button>
        </form>
    </div>

    <!-- Modal: Recuperar contraseña -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="resetPasswordLabel">Recuperar contraseña</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body">
            <form id="resetPasswordForm" class="vstack gap-3">
              <div>
                <label for="resetClave" class="form-label">Clave provincial</label>
                <input type="text" id="resetClave" class="form-control" required>
              </div>
              <div>
                <label for="resetSecurityCode" class="form-label">Código de seguridad</label>
                <input type="text" id="resetSecurityCode" class="form-control" required>
                <small class="text-muted">Código privado asignado a la escuela (no compartirlo).</small>
              </div>
              <div>
                <label for="resetNewPassword" class="form-label">Nueva contraseña</label>
                <input type="password" id="resetNewPassword" class="form-control" minlength="6" required>
              </div>
              <div>
                <label for="resetConfirmPassword" class="form-label">Confirmar contraseña</label>
                <input type="password" id="resetConfirmPassword" class="form-control" minlength="6" required>
              </div>
              <div id="resetPasswordFeedback" class="small"></div>
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            &copy; 2025 JDLacosta - Todos los derechos reservados.
        </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let CSRF_TOKEN = '';
async function loadCsrfToken(){
    try{
        const r = await fetch('api/get_csrf.php', { credentials: 'same-origin' });
        const j = await r.json();
        if (j && j.csrf_token) CSRF_TOKEN = j.csrf_token;
    }catch(e){ console.warn('No se pudo obtener CSRF token', e); }
}
// try to load token early
loadCsrfToken();

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

// Manejar click en "¿Olvidaste tu contraseña?"
const resetModalInstance = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
document.getElementById('resetPasswordLink').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('resetPasswordForm').reset();
    const feedback = document.getElementById('resetPasswordFeedback');
    feedback.textContent = '';
    feedback.className = 'small';
    resetModalInstance.show();
});

document.getElementById('resetPasswordForm').addEventListener('submit', async function(e){
    e.preventDefault();
    const clave = document.getElementById('resetClave').value.trim();
    const code = document.getElementById('resetSecurityCode').value.trim();
    const pass1 = document.getElementById('resetNewPassword').value;
    const pass2 = document.getElementById('resetConfirmPassword').value;
    const feedback = document.getElementById('resetPasswordFeedback');
    feedback.textContent = '';
    feedback.className = 'small text-muted';

    if (!clave || !code) {
        feedback.textContent = 'Complete clave y código.';
        feedback.classList.add('text-danger');
        return;
    }
    if (pass1.length < 6) {
        feedback.textContent = 'La contraseña debe tener al menos 6 caracteres.';
        feedback.classList.add('text-danger');
        return;
    }
    if (pass1 !== pass2) {
        feedback.textContent = 'Las contraseñas no coinciden.';
        feedback.classList.add('text-danger');
        return;
    }

    try {
        const fd = new FormData();
        fd.append('clave', clave);
        fd.append('security_code', code);
        fd.append('new_password', pass1);
            fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch('api/reset_password.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const json = await res.json();

        if (res.ok && json.success) {
            feedback.textContent = json.message || 'Contraseña actualizada correctamente';
            feedback.className = 'small text-success';
            setTimeout(() => {
                resetModalInstance.hide();
            }, 1200);
        } else {
            feedback.textContent = json.message || 'No se pudo actualizar la contraseña';
            feedback.className = 'small text-danger';
        }
    } catch (err) {
        console.error(err);
        feedback.textContent = 'Error de conexión al intentar actualizar la contraseña.';
        feedback.className = 'small text-danger';
    }
});

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
            fd.append('csrf_token', CSRF_TOKEN);
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
            // Redirigir según tipo de usuario
            if (json.es_jefatura) {
                window.location.href = 'dashboard_jefatura.php';
            } else {
                window.location.href = 'dashboard.php';
            }
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