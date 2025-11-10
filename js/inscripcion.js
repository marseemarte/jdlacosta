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

// Cargar datos cuando se carga la página
document.addEventListener('DOMContentLoaded', loadFormData);

