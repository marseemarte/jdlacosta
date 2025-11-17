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

// ========== FUNCIONALIDAD DE OCR Y ESCANEO DE DNI ==========

// Función para mostrar preview de imagen
function showImagePreview(inputId, previewId, buttonId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const button = document.getElementById(buttonId);
    
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.innerHTML = `<img src="${e.target.result}" class="preview-image" alt="Preview">`;
                if (button) {
                    button.style.display = 'inline-block';
                }
            };
            reader.readAsDataURL(file);
        } else {
            preview.innerHTML = '';
            if (button) {
                button.style.display = 'none';
            }
        }
    });
}

// Función para extraer datos del texto escaneado del DNI
function extractDNIData(text, tipo = 'frente') {
    const data = {
        dni: '',
        nombre: '',
        apellido: '',
        fechaNacimiento: '',
        domicilio: ''
    };
    
    // Normalizar texto: eliminar espacios extra y convertir a mayúsculas
    const normalizedText = text.toUpperCase().replace(/\s+/g, ' ').trim();
    const lines = normalizedText.split('\n').map(l => l.trim()).filter(l => l.length > 0);
    
    if (tipo === 'frente') {
        // Buscar DNI: números de 7-8 dígitos
        const dniMatch = normalizedText.match(/\b(\d{7,8})\b/);
        if (dniMatch) {
            data.dni = dniMatch[1];
        }
        
        // Buscar fecha de nacimiento: formato DD/MM/YYYY o DD-MM-YYYY
        const fechaMatch = normalizedText.match(/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/);
        if (fechaMatch) {
            const day = fechaMatch[1].padStart(2, '0');
            const month = fechaMatch[2].padStart(2, '0');
            const year = fechaMatch[3];
            data.fechaNacimiento = `${year}-${month}-${day}`;
        }
        
        // Buscar nombre y apellido
        // En DNI argentino, generalmente el formato es: APELLIDO, NOMBRE
        // O puede estar en líneas separadas
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            
            // Buscar patrón "APELLIDO, NOMBRE" o "APELLIDO NOMBRE"
            const nombreCompletoMatch = line.match(/([A-ZÁÉÍÓÚÑ\s]+)[,\s]+([A-ZÁÉÍÓÚÑ\s]+)/);
            if (nombreCompletoMatch && !data.apellido) {
                data.apellido = nombreCompletoMatch[1].trim();
                data.nombre = nombreCompletoMatch[2].trim();
            }
            
            // Si encontramos palabras que parecen nombres (sin números, sin caracteres especiales)
            if (!data.apellido && /^[A-ZÁÉÍÓÚÑ\s]+$/.test(line) && line.length > 3 && !line.includes('DNI') && !line.includes('ARGENTINA')) {
                // Intentar separar apellido y nombre
                const palabras = line.split(/\s+/);
                if (palabras.length >= 2) {
                    data.apellido = palabras[0];
                    data.nombre = palabras.slice(1).join(' ');
                }
            }
        }
        
        // Buscar nombres comunes en el texto
        const nombresComunes = ['JUAN', 'MARIA', 'JOSE', 'CARLOS', 'ANA', 'LUIS', 'PEDRO', 'JOAQUIN'];
        for (const nombreComun of nombresComunes) {
            if (normalizedText.includes(nombreComun) && !data.nombre) {
                const index = normalizedText.indexOf(nombreComun);
                const contexto = normalizedText.substring(Math.max(0, index - 30), index + 30);
                const regex = new RegExp(`([A-ZÁÉÍÓÚÑ\\s]+)\\s*${nombreComun}\\s+([A-ZÁÉÍÓÚÑ\\s]+)`);
                const match = contexto.match(regex);
                if (match) {
                    data.apellido = match[1].trim();
                    data.nombre = nombreComun + ' ' + match[2].trim();
                }
            }
        }
    } else if (tipo === 'reverso') {
        // En el reverso buscamos principalmente el domicilio
        // Buscar direcciones: generalmente tienen números y palabras
        for (const line of lines) {
            // Buscar líneas que parecen direcciones (tienen números y letras)
            if (/\d+/.test(line) && /[A-ZÁÉÍÓÚÑ]/.test(line) && line.length > 10) {
                // Filtrar líneas que no son fechas ni DNI
                if (!/\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}/.test(line) && !/\b\d{7,8}\b/.test(line)) {
                    data.domicilio = line;
                    break;
                }
            }
        }
    }
    
    return data;
}

// Función para escanear DNI con OCR
async function scanDNI(inputId, tipo, campos) {
    const input = document.getElementById(inputId);
    const file = input.files[0];
    
    if (!file) {
        alert('Por favor, seleccione una imagen primero.');
        return;
    }
    
    const statusDiv = document.getElementById(campos.statusId);
    const button = document.getElementById(campos.buttonId);
    
    // Mostrar estado de carga
    if (statusDiv) {
        statusDiv.innerHTML = '<span class="ocr-loading"><i class="fas fa-spinner fa-spin"></i> Escaneando DNI...</span>';
    }
    if (button) {
        button.disabled = true;
    }
    
    try {
        // Usar Tesseract.js para OCR
        const { data: { text } } = await Tesseract.recognize(file, 'spa', {
            logger: m => {
                if (m.status === 'recognizing text') {
                    if (statusDiv) {
                        statusDiv.innerHTML = `<span class="ocr-loading"><i class="fas fa-spinner fa-spin"></i> Escaneando... ${Math.round(m.progress * 100)}%</span>`;
                    }
                }
            }
        });
        
        // Extraer datos del texto
        const extractedData = extractDNIData(text, tipo);
        
        // Autocompletar campos del formulario
        let camposCompletados = 0;
        
        if (extractedData.dni && campos.dniId) {
            const dniField = document.getElementById(campos.dniId);
            if (dniField && !dniField.value) {
                dniField.value = extractedData.dni;
                camposCompletados++;
            }
        }
        
        if (extractedData.nombre && campos.nombreId) {
            const nombreField = document.getElementById(campos.nombreId);
            if (nombreField && !nombreField.value) {
                nombreField.value = extractedData.nombre;
                camposCompletados++;
            }
        }
        
        if (extractedData.apellido && campos.apellidoId) {
            const apellidoField = document.getElementById(campos.apellidoId);
            if (apellidoField && !apellidoField.value) {
                apellidoField.value = extractedData.apellido;
                camposCompletados++;
            }
        }
        
        if (extractedData.fechaNacimiento && campos.fechaId) {
            const fechaField = document.getElementById(campos.fechaId);
            if (fechaField && !fechaField.value) {
                fechaField.value = extractedData.fechaNacimiento;
                camposCompletados++;
            }
        }
        
        if (extractedData.domicilio && campos.domicilioId) {
            const domicilioField = document.getElementById(campos.domicilioId);
            if (domicilioField && !domicilioField.value) {
                domicilioField.value = extractedData.domicilio;
                camposCompletados++;
            }
        }
        
        // Mostrar resultado
        if (statusDiv) {
            if (camposCompletados > 0) {
                statusDiv.innerHTML = `<span class="ocr-success"><i class="fas fa-check-circle"></i> ${camposCompletados} campo(s) completado(s) automáticamente</span>`;
            } else {
                statusDiv.innerHTML = `<span class="ocr-error"><i class="fas fa-exclamation-triangle"></i> No se pudieron extraer datos. Por favor, complete manualmente.</span>`;
            }
        }
        
        // Mostrar texto extraído en consola para debugging
        console.log('Texto extraído del DNI:', text);
        console.log('Datos extraídos:', extractedData);
        
    } catch (error) {
        console.error('Error al escanear DNI:', error);
        if (statusDiv) {
            statusDiv.innerHTML = `<span class="ocr-error"><i class="fas fa-times-circle"></i> Error al escanear. Por favor, intente nuevamente.</span>`;
        }
        alert('Error al escanear el DNI. Por favor, intente nuevamente o complete los datos manualmente.');
    } finally {
        if (button) {
            button.disabled = false;
        }
    }
}

// Configurar previews y botones de escaneo
document.addEventListener('DOMContentLoaded', function() {
    // Preview de imágenes del estudiante
    showImagePreview('dni_frente_estudiante', 'preview_frente_estudiante', 'btn_scan_frente_estudiante');
    showImagePreview('dni_reverso_estudiante', 'preview_reverso_estudiante', 'btn_scan_reverso_estudiante');
    
    // Preview de imágenes del tutor
    showImagePreview('dni_frente_tutor', 'preview_frente_tutor', 'btn_scan_frente_tutor');
    showImagePreview('dni_reverso_tutor', 'preview_reverso_tutor', 'btn_scan_reverso_tutor');
    
    // Botones de escaneo del estudiante
    const btnScanFrenteEstudiante = document.getElementById('btn_scan_frente_estudiante');
    if (btnScanFrenteEstudiante) {
        btnScanFrenteEstudiante.addEventListener('click', function() {
            scanDNI('dni_frente_estudiante', 'frente', {
                statusId: 'status_ocr_estudiante',
                buttonId: 'btn_scan_frente_estudiante',
                dniId: 'dni_estudiante',
                nombreId: 'nombre_estudiante',
                apellidoId: 'apellido_estudiante',
                fechaId: 'fecha_nacimiento_estudiante',
                domicilioId: null
            });
        });
    }
    
    const btnScanReversoEstudiante = document.getElementById('btn_scan_reverso_estudiante');
    if (btnScanReversoEstudiante) {
        btnScanReversoEstudiante.addEventListener('click', function() {
            scanDNI('dni_reverso_estudiante', 'reverso', {
                statusId: 'status_ocr_estudiante',
                buttonId: 'btn_scan_reverso_estudiante',
                dniId: null,
                nombreId: null,
                apellidoId: null,
                fechaId: null,
                domicilioId: 'domicilio_estudiante'
            });
        });
    }
    
    // Botones de escaneo del tutor
    const btnScanFrenteTutor = document.getElementById('btn_scan_frente_tutor');
    if (btnScanFrenteTutor) {
        btnScanFrenteTutor.addEventListener('click', function() {
            scanDNI('dni_frente_tutor', 'frente', {
                statusId: 'status_ocr_tutor',
                buttonId: 'btn_scan_frente_tutor',
                dniId: 'dni_tutor',
                nombreId: 'nombre_tutor',
                apellidoId: 'apellido_tutor',
                fechaId: 'fecha_nacimiento_tutor',
                domicilioId: null
            });
        });
    }
    
    const btnScanReversoTutor = document.getElementById('btn_scan_reverso_tutor');
    if (btnScanReversoTutor) {
        btnScanReversoTutor.addEventListener('click', function() {
            scanDNI('dni_reverso_tutor', 'reverso', {
                statusId: 'status_ocr_tutor',
                buttonId: 'btn_scan_reverso_tutor',
                dniId: null,
                nombreId: null,
                apellidoId: null,
                fechaId: null,
                domicilioId: null
            });
        });
    }
});

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

// Cargar datos cuando se carga la página
document.addEventListener('DOMContentLoaded', function() {
    loadFormData();
    
    // Agregar event listener para el cambio de vínculo
    const vinculoSelect = document.getElementById('vinculo');
    if (vinculoSelect) {
        vinculoSelect.addEventListener('change', handleVinculoChange);
    }
});

