<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción - Secundaria 2025</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #ffffff;
            min-height: 100vh;
        }

        .login-container {
            background: #ffffff;
            border-radius: 8px;
            padding: 40px;
            max-width: 500px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .school-selector {
            background: #f8f9fa;
            border: 2px solid #667eea;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }

        .school-selector h5 {
            color: #667eea;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .school-info {
            background: #e8eef7;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 4px;
            margin-top: 20px;
            display: none;
        }

        .school-info.show {
            display: block;
        }

        .school-info h4 {
            color: #667eea;
            margin: 0 0 5px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .school-info p {
            color: #666;
            margin: 3px 0;
            font-size: 0.95rem;
        }

        .intro-text {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-label {
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-select, .form-control {
            border: 1px solid #ddd;
            padding: 10px 12px;
            font-size: 1rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: #667eea;
            border: none;
            padding: 10px;
            font-weight: 500;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: #764ba2;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error-message {
            color: #dc3545;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .site-footer {
            margin-top: 30px;
            padding: 14px 0;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }

        h1 {
            color: #333;
            margin-bottom: 8px;
            text-align: center;
            font-weight: 600;
        }

        .spinner-border {
            width: 20px;
            height: 20px;
            margin-right: 8px;
        }
    </style>
</head>
<body class="d-flex flex-column">
    <main class="d-flex align-items-center justify-content-center flex-grow-1">
        <div class="login-container">
            <h1>Inscripción Secundaria 2025</h1>
            
            <p class="intro-text">
                Selecciona tu escuela y luego ingresa el DNI sin puntos del estudiante
            </p>

                <div id="errorMsg" class="error-message" role="alert"></div>

            <!-- Modal: alumno ya inscripto -->
            <div class="modal fade" id="inscritoModal" tabindex="-1" aria-labelledby="inscritoModalLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="inscritoModalLabel">Alumno ya inscripto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  <div class="modal-body" id="inscritoModalBody">
                    <!-- contenido dinámico -->
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="index.html" class="btn btn-primary">Volver al listado de escuelas</a>
                  </div>
                </div>
              </div>
            </div>            <form id="loginForm" class="needs-validation" novalidate>
                <input type="hidden" id="escuela" name="escuela">

                <!-- Información de la escuela seleccionada -->
                <div class="school-selector">
                    <label class="form-label">
                        <i class="fas fa-school me-2"></i>Escuela seleccionada
                    </label>
                    <div id="schoolInfo" class="school-info">
                        <h4 id="schoolName"></h4>
                        <p id="schoolLocation"></p>
                    </div>
                </div>

                <!-- DNI del estudiante -->
                <div class="form-group mb-3" id="dniSection" style="display: none;">
                    <label for="dni" class="form-label">Ingrese el DNI, sin puntos, del estudiante</label>
                    <input type="text" id="dni" name="dni" class="form-control" placeholder="ej: 12345678" inputmode="numeric" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="submitBtn" disabled>
                    <i class="fas fa-arrow-right me-2"></i>Continuar
                </button>
            </form>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container">
            © 2025 JDLacosta - Todos los derechos reservados.
        </div>
    </footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Localidades: cargadas desde la base de datos junto con cada escuela

let escuelas = [];

// Cargar la escuela seleccionada desde la URL
async function cargarEscuelaSeleccionada(escuelaId) {
    const errorMsg = document.getElementById('errorMsg');
    const schoolInfo = document.getElementById('schoolInfo');
    const dniSection = document.getElementById('dniSection');
    const submitBtn = document.getElementById('submitBtn');
    const inputEscuela = document.getElementById('escuela');

    if (!escuelaId) {
        errorMsg.textContent = 'Falta el parámetro de escuela. Regrese al listado e intente nuevamente.';
        errorMsg.classList.add('show');
        submitBtn.disabled = true;
        return;
    }

    try {
        const res = await fetch('api/get_escuelas_primarias.php');
        const json = await res.json();

        if (!json.success || !json.data || json.data.length === 0) {
            throw new Error('No se pudieron cargar las escuelas');
        }

        escuelas = json.data;
        const escuela = escuelas.find(e => e.id == escuelaId);

        if (!escuela) {
            errorMsg.textContent = 'La escuela seleccionada no está disponible. Regrese al listado.';
            errorMsg.classList.add('show');
            submitBtn.disabled = true;
            return;
        }

        inputEscuela.value = escuela.id;
        document.getElementById('schoolName').textContent = escuela.nombre;
        document.getElementById('schoolLocation').textContent = escuela.localidad;
        schoolInfo.classList.add('show');
        dniSection.style.display = 'block';
        submitBtn.disabled = false;
        document.getElementById('dni').focus();
    } catch (e) {
        console.error('Error cargando escuela seleccionada:', e);
        errorMsg.textContent = 'Error al cargar la escuela seleccionada. Intente más tarde.';
        errorMsg.classList.add('show');
        submitBtn.disabled = true;
    }
}

// Enviar formulario
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const escuelaId = document.getElementById('escuela').value;
    const dni = document.getElementById('dni').value.trim();
    const errorMsg = document.getElementById('errorMsg');
    
    errorMsg.classList.remove('show');
    
    // Validar DNI
    if (!dni) {
        errorMsg.textContent = 'Ingrese el DNI del estudiante';
        errorMsg.classList.add('show');
        return;
    }
    
    const dni_limpio = dni.replace(/[^0-9]/g, '');
    if (dni_limpio.length < 7 || dni_limpio.length > 8) {
        errorMsg.textContent = 'DNI debe tener entre 7 y 8 dígitos';
        errorMsg.classList.add('show');
        return;
    }
    
    if (!escuelaId) {
        errorMsg.textContent = 'Selecciona una escuela';
        errorMsg.classList.add('show');
        return;
    }
    
    // Cambiar botón a estado de carga
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>Buscando...';
    
    try {
        // Enviar a PHP para guardar en sesión y buscar alumno
        const fd = new FormData();
        fd.append('dni', dni_limpio);
        fd.append('id_secundaria', escuelaId);
        
        const res = await fetch('api/buscar_alumno.php', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const text = await res.text();
        let json;
        
        try {
            json = JSON.parse(text);
        } catch (parseErr) {
            errorMsg.textContent = 'Error en la respuesta del servidor';
            errorMsg.classList.add('show');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Continuar';
            return;
        }
        
        if (res.ok && json.success) {
            // Verificar si ya está inscripto
            if (json.already_inscribed && json.inscribed_school) {
                // Mostrar modal de alumno ya inscripto
                const modalBody = document.getElementById('inscritoModalBody');
                modalBody.innerHTML = `
                    <p><strong>El alumno ya está inscripto en:</strong></p>
                    <p style="font-size: 1.1rem; color: #667eea; font-weight: 600;">
                        ${json.inscribed_school.nombre}
                    </p>
                    <p class="text-muted">No es posible realizar una nueva inscripción.</p>
                `;
                const modal = new bootstrap.Modal(document.getElementById('inscritoModal'));
                modal.show();
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Continuar';
            } else {
                // Redirigir a inscripción
                window.location.href = 'inscripcion.html';
            }
        } else {
            errorMsg.textContent = json.message || 'Error al procesar la solicitud';
            errorMsg.classList.add('show');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Continuar';
        }
    } catch (e) {
        errorMsg.textContent = 'Error de conexión al servidor';
        errorMsg.classList.add('show');
        console.error(e);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-arrow-right me-2"></i>Continuar';
    }
});

// Cargar escuela al iniciar
const urlParams = new URLSearchParams(window.location.search);
const escuelaIdFromUrl = urlParams.get('escuela_id');
cargarEscuelaSeleccionada(escuelaIdFromUrl);
</script>
</body>
</html>
