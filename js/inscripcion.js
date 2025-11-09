// Obtener el ID de la escuela desde la URL
const urlParams = new URLSearchParams(window.location.search);
const escuelaId = urlParams.get('escuela_id');

if (!escuelaId) {
    alert('No se ha seleccionado una escuela. Redirigiendo...');
    window.location.href = 'index.html';
}

// Establecer el ID de la escuela en el formulario
document.getElementById('escuela_id').value = escuelaId;

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
            const selectSegunda = document.getElementById('segunda_opcion');
            const selectTercera = document.getElementById('tercera_opcion');
            
            escuelasSecData.data.forEach(esc => {
                // Opción 2
                const option2 = document.createElement('option');
                option2.value = esc.id;
                option2.textContent = `${esc.nombre} (${esc.localidad.substring(0, 10)})`;
                selectSegunda.appendChild(option2.cloneNode(true));
                
                // Opción 3
                const option3 = option2.cloneNode(true);
                selectTercera.appendChild(option3);
            });
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

// Manejar el envío del formulario
document.getElementById('inscripcionForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Deshabilitar botón y mostrar carga
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    
    try {
        const response = await fetch('api/save_inscripcion.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Inscripción guardada exitosamente.');
            window.location.href = 'index.html';
        } else {
            alert('Error al guardar la inscripción: ' + (result.message || 'Error desconocido'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error de conexión al guardar la inscripción.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

// --- OCR y autocompletado de datos del DNI del estudiante ---

let imagenEstudiante = null;

// Cargar y previsualizar imagen del DNI
document.getElementById('dni_frente_estudiante').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('preview_frente_estudiante');
    const btnExtraer = document.getElementById('btn_extraer_estudiante');
    const status = document.getElementById('status_ocr_estudiante');
    preview.innerHTML = '';
    status.textContent = '';
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            imagenEstudiante = ev.target.result;
            preview.innerHTML = `<img src="${imagenEstudiante}" class="preview-image" alt="Preview DNI">`;
            btnExtraer.style.display = 'inline-block';
        };
        reader.readAsDataURL(file);
    } else {
        imagenEstudiante = null;
        btnExtraer.style.display = 'none';
    }
});

// Botón para extraer datos automáticamente
document.getElementById('btn_extraer_estudiante').addEventListener('click', async function() {
    const status = document.getElementById('status_ocr_estudiante');
    const manualDiv = document.getElementById('manual_estudiante');
    if (!imagenEstudiante) {
        status.innerHTML = '<span class="ocr-error">Primero seleccione una imagen del DNI.</span>';
        return;
    }
    status.innerHTML = '<span class="ocr-loading"><i class="fas fa-spinner fa-spin"></i> Procesando imagen...</span>';
    manualDiv.style.display = 'none';

    try {
        const { data: { text } } = await Tesseract.recognize(imagenEstudiante, 'spa', {
            logger: m => {
                if (m.status === 'recognizing text') {
                    status.innerHTML = `<span class="ocr-loading"><i class="fas fa-spinner fa-spin"></i> Reconociendo texto... ${Math.round(m.progress * 100)}%</span>`;
                }
            }
        });

        // --- Extraer datos del texto OCR ---
        const datos = parsearDNI(text);

        // Autocompletar campos en orden: DNI → Apellido → Nombre → Fecha de Nacimiento
        if (datos.dni && /^\d{7,8}$/.test(datos.dni)) document.getElementById('dni_estudiante').value = datos.dni;
        if (datos.apellido && datos.apellido.length > 2) document.getElementById('apellido_estudiante').value = capitalizarTexto(datos.apellido);
        if (datos.nombre && datos.nombre.length > 2) document.getElementById('nombre_estudiante').value = capitalizarTexto(datos.nombre);
        if (datos.fechaNacimiento && /^\d{4}-\d{2}-\d{2}$/.test(datos.fechaNacimiento)) document.getElementById('fecha_nacimiento_estudiante').value = datos.fechaNacimiento;

        // Mensaje de éxito o advertencia
        if (datos.dni || datos.nombre || datos.apellido) {
            status.innerHTML = '<span class="ocr-success"><i class="fas fa-check-circle"></i> Datos extraídos correctamente. Revise y complete los campos faltantes si es necesario.</span>';
        } else {
            status.innerHTML = '<span class="ocr-error"><i class="fas fa-exclamation-triangle"></i> No se pudieron extraer datos automáticamente de la imagen.</span>';
        }
        manualDiv.style.display = 'block';

        // Para depuración: mostrar texto OCR en consola
        console.log('Texto OCR:', text);
        console.log('Datos extraídos:', datos);

    } catch (error) {
        status.innerHTML = '<span class="ocr-error"><i class="fas fa-times-circle"></i> Error al procesar la imagen. Intente nuevamente.</span>';
        manualDiv.style.display = 'block';
        console.error('Error en OCR:', error);
    }
});

// --- Función para extraer datos del DNI argentino desde texto OCR ---
function parsearDNI(texto) {
    const datos = { dni: '', nombre: '', apellido: '', fechaNacimiento: '' };
    texto = texto.toUpperCase();
    const lineas = texto.split(/\n/).map(l => l.trim()).filter(l => l.length > 0);

    // DNI: busca 7-8 dígitos seguidos, que no empiecen con 0
    const dniMatch = texto.match(/\b([1-9]\d{6,7})\b/);
    if (dniMatch) datos.dni = dniMatch[1];

    // Fecha de nacimiento: DD/MM/YYYY o similar
    const fechaMatch = texto.match(/\b(\d{2})[\/\-\.](\d{2})[\/\-\.](\d{4})\b/);
    if (fechaMatch) datos.fechaNacimiento = `${fechaMatch[3]}-${fechaMatch[2]}-${fechaMatch[1]}`;

    // Nombre y apellido: línea con coma o línea larga en mayúsculas
    let nombreLinea = lineas.find(l => l.includes(','));
    if (nombreLinea) {
        const partes = nombreLinea.split(',');
        datos.apellido = limpiarNombre(partes[0]);
        datos.nombre = limpiarNombre(partes[1] || '');
    } else {
        // Buscar línea con dos palabras o más, toda en mayúsculas
        const posibles = lineas.filter(l => /^[A-ZÁÉÍÓÚÑ\s]+$/.test(l) && l.split(' ').length >= 2);
        if (posibles.length) {
            // Si hay más de dos palabras, las primeras suelen ser apellido, el resto nombre
            const palabras = posibles[0].split(' ');
            if (palabras.length >= 3) {
                datos.apellido = limpiarNombre(palabras[0] + ' ' + palabras[1]);
                datos.nombre = limpiarNombre(palabras.slice(2).join(' '));
            } else {
                datos.apellido = limpiarNombre(palabras[0]);
                datos.nombre = limpiarNombre(palabras.slice(1).join(' '));
            }
        }
    }
    return datos;
}

function limpiarNombre(txt) {
    return txt.replace(/[^A-ZÁÉÍÓÚÑ\s]/g, '').replace(/\s+/g, ' ').trim();
}

function capitalizarTexto(txt) {
    return txt ? txt.toLowerCase().replace(/\b\w/g, l => l.toUpperCase()) : '';
}

// Cargar datos cuando se carga la página
document.addEventListener('DOMContentLoaded', loadFormData);

