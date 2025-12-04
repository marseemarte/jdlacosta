// Variables globales
let allSchools = [];
let filteredSchools = [];

// Elementos del DOM
// const searchInput = document.getElementById('searchInput');
// const searchBtn = document.getElementById('searchBtn');
const gridViewBtn = document.getElementById('gridViewBtn');
const listViewBtn = document.getElementById('listViewBtn');
const schoolsContainer = document.getElementById('schoolsContainer');

// Cargar escuelas desde la base de datos
async function loadSchools() {
    // Mostrar indicador de carga
    showLoading();
    
    try {
        const response = await fetch('api/get_escuelas.php');
        const result = await response.json();
        
        console.log('Respuesta de API:', result);
        
        if (result.success) {
            allSchools = result.data;
            console.log('Escuelas cargadas:', allSchools);
            filteredSchools = [...allSchools];
            renderSchools();
        } else {
            console.error('Error al cargar escuelas:', result.error);
            showError('Error al cargar las escuelas. Por favor, intente nuevamente.');
        }
    } catch (error) {
        console.error('Error de conexión:', error);
        showError('Error de conexión. Verifique que el servidor esté funcionando.');
    }
}

// Mostrar indicador de carga
function showLoading() {
    schoolsContainer.innerHTML = `
        <div class="loading">
            <div class="spinner"></div>
        </div>
    `;
}

// Mostrar error al usuario
function showError(message) {
    schoolsContainer.innerHTML = `
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle"></i>
            ${message}
        </div>
    `;
}

// Renderizar escuelas en el contenedor
function renderSchools() {
    if (filteredSchools.length === 0) {
        schoolsContainer.innerHTML = `
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i>
                No se encontraron escuelas.
            </div>
        `;
        return;
    }

    schoolsContainer.innerHTML = filteredSchools.map(school => `
        <div class="school-card" data-school-id="${school.id}">
            <div class="school-card-header">
                <h3>${school.nombre}</h3>
            </div>
            <div class="school-card-body">
                <p><strong>Localidad:</strong> ${school.localidad || 'N/A'}</p>
                <p><strong>Distrito:</strong> ${school.distrito || 'N/A'}</p>
            </div>
            <div class="school-card-footer">
                <button class="btn btn-primary">Inscribirse</button>
            </div>
        </div>
    `).join('');

    addSchoolCardListeners();
}

// Función de búsqueda
function performSearch() {
    const query = searchInput.value.toLowerCase().trim();
    
    if (query === '') {
        filteredSchools = [...allSchools];
    } else {
        filteredSchools = allSchools.filter(school => 
            school.nombre.toLowerCase().includes(query) ||
            (school.localidad && school.localidad.toLowerCase().includes(query)) ||
            (school.distrito && school.distrito.toLowerCase().includes(query))
        );
    }
    
    renderSchools();
}

// Agregar event listeners a las cards de escuelas
function addSchoolCardListeners() {
    const schoolCards = document.querySelectorAll('.school-card');
    schoolCards.forEach(card => {
        card.addEventListener('click', () => {
            const schoolId = card.getAttribute('data-school-id');
            const school = allSchools.find(s => s.id == schoolId);
            console.log('Escuela seleccionada:', school);
            // Redirigir al login de inscripción con el ID de la escuela
            window.location.href = `login_inscripcion.php?escuela_id=${schoolId}`;
        });
    });
}



// Event listeners
// searchBtn.addEventListener('click', performSearch);
// searchInput.addEventListener('keypress', (e) => {
//     if (e.key === 'Enter') {
//         performSearch();
//     }
// });

// View toggle functionality
if (gridViewBtn) {
    gridViewBtn.addEventListener('click', () => {
        gridViewBtn.classList.add('active');
        if (listViewBtn) listViewBtn.classList.remove('active');
        schoolsContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(250px, 1fr))';
    });
} else {
    console.warn('gridViewBtn no encontrado en el DOM - vista de grilla deshabilitada');
}

if (listViewBtn) {
    listViewBtn.addEventListener('click', () => {
        listViewBtn.classList.add('active');
        if (gridViewBtn) gridViewBtn.classList.remove('active');
        schoolsContainer.style.gridTemplateColumns = '1fr';
    });
} else {
    console.warn('listViewBtn no encontrado en el DOM - vista de lista deshabilitada');
}

// Cargar escuelas cuando se carga la página
document.addEventListener('DOMContentLoaded', loadSchools);