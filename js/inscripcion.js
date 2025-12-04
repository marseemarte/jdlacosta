// Obtener datos del alumno desde sesión
let CSRF_TOKEN = '';
let escuelasSecundarias = []; // Guardar todas las escuelas para filtrado dinámico
let escuelaSeleccionada = 0; // ID de la escuela seleccionada en el index (a excluir de opciones 2 y 3)
let nombreEscuelaSeleccionada = ''; // Guardar el nombre de la escuela seleccionada en el index

// Obtener el ID de la escuela desde los parámetros de URL
function getEscuelaDelURL() {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('escuela_id')) || 0;
}

async function loadCsrfToken(){
    try{
        const r = await fetch('api/get_csrf.php', { credentials: 'same-origin' });
        const j = await r.json();
        if (j && j.csrf_token) CSRF_TOKEN = j.csrf_token;
    }catch(e){ console.warn('No se pudo obtener CSRF token', e); }
}
let alumnoData = null;

async function obtenerDatosAlumno() {
    try {
        const res = await fetch('api/get_alumno_sesion.php', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        
        if (data.success && data.alumno) {
            alumnoData = data.alumno;
            escuelaSeleccionada = getEscuelaDelURL(); // Obtener escuela del URL (la que se seleccionó en index)
            
            // Auto-completar campos del estudiante
            document.getElementById('dni_estudiante').value = alumnoData.dni || '';
            document.getElementById('apellido_estudiante').value = alumnoData.apellido || '';
            document.getElementById('nombre_estudiante').value = alumnoData.nombre || '';
            document.getElementById('escuela_id').value = escuelaSeleccionada || '';
            
            console.log('Escuela seleccionada (del index):', escuelaSeleccionada);
            
            // Ahora cargar los datos del formulario (dropdowns, etc)
            await loadFormData();
            
            return true;
        } else {
            alert('No se encontraron datos del alumno. Vuelve a iniciar sesión.');
            window.location.href = 'login_inscripcion.html';
            return false;
        }
    } catch (e) {
        console.error('Error al obtener datos del alumno:', e);
        alert('Error al cargar datos del alumno');
        return false;
    }
}

// Esperar a que el DOM esté listo antes de cargar datos
// NO usar DOMContentLoaded aquí, se declara al final del archivo

// Cargar datos para los dropdowns
async function loadFormData() {
    try {
        // Cargar localidades
        const localidadesRes = await fetch('api/get_localidades.php');
        const localidadesData = await localidadesRes.json();
        if (localidadesData.success) {
            const selectLocalidad = document.getElementById('localidad_estudiante');
            localidadesData.data.forEach(loc => {
                const option = document.createElement('option');
                option.value = loc.id;
                option.textContent = loc.localidad;
                selectLocalidad.appendChild(option);
            });
        }

        // Cargar escuelas primarias
        const escuelasPrimRes = await fetch('api/get_escuelas_primarias.php');
        const escuelasPrimData = await escuelasPrimRes.json();
        if (escuelasPrimData.success) {
            const selectEscuela = document.getElementById('escuela_procedencia');
            escuelasPrimData.data.forEach(esc => {
                const option = document.createElement('option');
                option.value = esc.id;
                option.textContent = esc.nombre;
                selectEscuela.appendChild(option);
            });
        }

        // Cargar escuelas secundarias para opciones 2 y 3
        const escuelasSecRes = await fetch('api/get_escuelas.php');
        const escuelasSecData = await escuelasSecRes.json();
        if (escuelasSecData.success) {
            escuelasSecundarias = escuelasSecData.data; // Guardar todas las escuelas
            // Encontrar el nombre de la escuela seleccionada en el index
            const escuela = escuelasSecData.data.find(e => parseInt(e.id) === escuelaSeleccionada);
            if (escuela) {
                nombreEscuelaSeleccionada = escuela.nombre;
                console.log('Escuela seleccionada en index:', nombreEscuelaSeleccionada);
            }
            // NO poblar los selects aquí - se llenarán cuando se seleccione procedencia
        }

        // Cargar vínculos
        const vinculosRes = await fetch('api/get_vinculos.php');
        const vinculosData = await vinculosRes.json();
        if (vinculosData.success) {
            const selectVinculo = document.getElementById('vinculo');
            vinculosData.data.forEach(vin => {
                const option = document.createElement('option');
                option.value = vin.id;
                option.textContent = vin.vinculo;
                selectVinculo.appendChild(option);
            });
        }

    } catch (error) {
        console.error('Error al cargar datos del formulario:', error);
        alert('Error al cargar los datos del formulario. Por favor, recargue la página.');
    }
}

// Manejar el envío del formulario - se asignará en DOMContentLoaded
async function handleFormSubmit(e) {
    e.preventDefault();
    console.log('=== INICIO SUBMIT ===');
    
    // Validar DNI del estudiante
    const dniEstudiante = document.getElementById('dni_estudiante').value.trim();
    console.log('DNI Estudiante:', dniEstudiante);
    if (!validarDNI(dniEstudiante)) {
        document.getElementById('error-dni').textContent = 'DNI debe tener 7-8 dígitos';
        document.getElementById('error-dni').style.display = 'block';
        console.log('DNI Estudiante inválido');
        return;
    } else {
        document.getElementById('error-dni').style.display = 'none';
    }
    
    // Validar DNI del tutor
    const dniTutor = document.getElementById('dni_tutor').value.trim();
    console.log('DNI Tutor:', dniTutor);
    if (!validarDNI(dniTutor)) {
        document.getElementById('error-dni-tutor').textContent = 'DNI debe tener 7-8 dígitos';
        document.getElementById('error-dni-tutor').style.display = 'block';
        console.log('DNI Tutor inválido');
        return;
    } else {
        document.getElementById('error-dni-tutor').style.display = 'none';
    }
    
    const formData = new FormData(document.getElementById('inscripcionForm'));
    if (CSRF_TOKEN) {
        formData.append('csrf_token', CSRF_TOKEN);
        console.log('CSRF Token agregado:', CSRF_TOKEN.substring(0, 10) + '...');
    } else {
        console.warn('No hay CSRF_TOKEN disponible');
    }
    
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Deshabilitar botón y mostrar carga
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    console.log('Enviando formulario a api/save_inscripcion.php...');
    
    try {
        const response = await fetch('api/save_inscripcion.php', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        
        const result = await response.json();
        console.log('Response JSON:', result);
        
        if (result.success) {
            alert('Inscripción guardada exitosamente.');
            window.location.href = 'index.html';
        } else {
            const errorMsg = result.message || 'Error desconocido';
            console.error('Error:', errorMsg);
            alert('Error al guardar la inscripción: ' + errorMsg);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error de conexión:', error);
        alert('Error de conexión al guardar la inscripción: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
}

// Función para mostrar preview de imagen
function showImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="preview-image" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
        }
    });
}

// Función para manejar el cambio de vínculo y mostrar campos dinámicos
function handleVinculoChange() {
    const vinculoSelect = document.getElementById('vinculo');
    const camposDinamicos = document.getElementById('campos_vinculo_dinamicos');
    const camposHermano = document.getElementById('campos_hermano');
    const camposProfesor = document.getElementById('campos_profesor');
    const camposPPI = document.getElementById('campos_ppi');
    
    // Ocultar todos los campos dinámicos primero
    camposDinamicos.style.display = 'none';
    camposHermano.style.display = 'none';
    camposProfesor.style.display = 'none';
    camposPPI.style.display = 'none';
    
    // Hacer opcionales todos los campos dinámicos
    const camposDinamicosInputs = camposDinamicos.querySelectorAll('input');
    camposDinamicosInputs.forEach(input => {
        input.removeAttribute('required');
        input.removeAttribute('readonly');
    });
    
    // Mostrar campos según el vínculo seleccionado
    const vinculoId = parseInt(vinculoSelect.value);
    
    switch (vinculoId) {
        case 2: // Hermano/a de alumno/a
            camposDinamicos.style.display = 'block';
            camposHermano.style.display = 'block';
            document.getElementById('dni_hermano').setAttribute('required', 'required');
            document.getElementById('nombre_hermano').setAttribute('required', 'required');
            break;
            
        case 3: // Hijo/a de profesor/a
            camposDinamicos.style.display = 'block';
            camposProfesor.style.display = 'block';
            document.getElementById('dni_profesor').setAttribute('required', 'required');
            document.getElementById('nombre_profesor').setAttribute('required', 'required');
            document.getElementById('escuela_profesor').setAttribute('required', 'required');
            // Auto-completar el campo de escuela del profesor con la escuela seleccionada en index
            const escuelaProfesorInput = document.getElementById('escuela_profesor');
            escuelaProfesorInput.value = nombreEscuelaSeleccionada;
            escuelaProfesorInput.setAttribute('readonly', 'readonly');
            break;
            
        case 4: // PPI
            camposDinamicos.style.display = 'block';
            camposPPI.style.display = 'block';
            document.getElementById('numero_ppi').setAttribute('required', 'required');
            document.getElementById('documentacion_ppi').setAttribute('required', 'required');
            break;
            
        case 1: // Ninguno
        default:
            // No mostrar campos adicionales
            break;
    }
}

// Función para actualizar dinámicamente las opciones de escuelas 2 y 3
function updateEscuelasOpciones() {
    const selectSegunda = document.getElementById('segunda_opcion');
    const selectTercera = document.getElementById('tercera_opcion');
    
    console.log('=== UPDATE ESCUELAS ===');
    console.log('Escuela seleccionada (index):', escuelaSeleccionada);
    
    // Limpiar opciones de 2da (excepto la vacía)
    while (selectSegunda.options.length > 1) {
        selectSegunda.remove(1);
    }
    selectSegunda.value = ''; // Resetear valor
    
    // Poblar 2da opción: todas las escuelas EXCEPTO la seleccionada en index
    escuelasSecundarias.forEach(esc => {
        const escuelaId = parseInt(esc.id);
        if (escuelaId !== escuelaSeleccionada) {
            const option = document.createElement('option');
            option.value = escuelaId;
            option.textContent = `${esc.nombre} (${esc.localidad.substring(0, 10)})`;
            selectSegunda.appendChild(option);
        }
    });
    
    console.log('2da opción poblada con', selectSegunda.options.length - 1, 'escuelas');
    
    // Limpiar 3ra opción
    while (selectTercera.options.length > 1) {
        selectTercera.remove(1);
    }
    selectTercera.value = '';
}

// Función para actualizar SOLO la 3ra opción cuando cambia la 2da
function actualizarTerceraOpcion() {
    const selectSegunda = document.getElementById('segunda_opcion');
    const selectTercera = document.getElementById('tercera_opcion');
    
    const segundaSeleccionada = parseInt(selectSegunda.value) || 0;
    
    console.log('=== ACTUALIZAR TERCERA ===');
    console.log('Escuela seleccionada (index):', escuelaSeleccionada);
    console.log('2da opción:', segundaSeleccionada);
    
    // Limpiar 3ra opción (excepto la vacía)
    while (selectTercera.options.length > 1) {
        selectTercera.remove(1);
    }
    selectTercera.value = '';
    
    // Poblar 3ra opción: todas las escuelas EXCEPTO la seleccionada en index y la 2da opción
    escuelasSecundarias.forEach(esc => {
        const escuelaId = parseInt(esc.id);
        // Excluir si es la escuela seleccionada en index O la 2da opción
        if (escuelaId !== escuelaSeleccionada && escuelaId !== segundaSeleccionada) {
            const option = document.createElement('option');
            option.value = escuelaId;
            option.textContent = `${esc.nombre} (${esc.localidad.substring(0, 10)})`;
            selectTercera.appendChild(option);
        }
    });
    
    console.log('3ra opción poblada con', selectTercera.options.length - 1, 'escuelas');
}

// Cargar datos cuando se carga la página
document.addEventListener('DOMContentLoaded', async function() {
    await loadCsrfToken();
    // 1. Primero obtener datos del alumno desde sesión
    await obtenerDatosAlumno();
    
    // 2. Configurar previews de imágenes
    // Preview de imágenes del estudiante
    showImagePreview('dni_frente_estudiante', 'preview_frente_estudiante');
    showImagePreview('dni_reverso_estudiante', 'preview_reverso_estudiante');
    
    // Preview de imágenes del tutor
    showImagePreview('dni_frente_tutor', 'preview_frente_tutor');
    showImagePreview('dni_reverso_tutor', 'preview_reverso_tutor');
    
    // 3. Asignar evento submit del formulario
    const inscripcionForm = document.getElementById('inscripcionForm');
    if (inscripcionForm) {
        inscripcionForm.addEventListener('submit', handleFormSubmit);
    }
    
    // 4. Agregar event listener para el cambio de vínculo
    const vinculoSelect = document.getElementById('vinculo');
    if (vinculoSelect) {
        vinculoSelect.addEventListener('change', handleVinculoChange);
    }
    
    // 5. Agregar event listeners para actualizar opciones de escuelas dinámicamente
    const escuelaProc = document.getElementById('escuela_procedencia');
    const selectSegunda = document.getElementById('segunda_opcion');
    const selectTercera = document.getElementById('tercera_opcion');
    
    if (escuelaProc) {
        escuelaProc.addEventListener('change', updateEscuelasOpciones);
    }
    if (selectSegunda) {
        // Cuando cambia segunda opción, solo actualizar tercera
        selectSegunda.addEventListener('change', actualizarTerceraOpcion);
    }
    if (selectTercera) {
        // No necesita listener, es solo lectura en función de otras opciones
    }
});

