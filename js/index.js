// Variables globales
let allSchools = [];
let filteredSchools = [];

// Elementos del DOM
const searchInput = document.getElementById('searchInput');
const searchBtn = document.getElementById('searchBtn');
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
        
        if (result.success) {
            allSchools = result.data;
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
                No se encontraron escuelas que coincidan con su búsqueda.
            </div>
        `;
        return;
    }

    schoolsContainer.innerHTML = filteredSchools.map(school => `
        <div class="school-card" data-school-id="${school.id}">
            <div class="school-image">
                <img src="img/logo.png" alt="escuela logo" class="logo">
            </div>
            <h3 class="school-name">${school.nombre}</h3>
            <p class="school-info">
                <i class="fas fa-map-marker-alt"></i>
                <span>${school.direccion}</span>
            </p>
            <p class="school-info">
                <i class="fas fa-phone"></i>
                <span>${school.telefono}</span>
            </p>
            <p class="school-info">
                <i class="fas fa-city"></i>
                <span>${school.localidad}</span>
            </p>
        </div>
    `).join('');

    // Agregar event listeners a las nuevas cards
    addSchoolCardListeners();
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

// Función de búsqueda
function performSearch() {
    const query = searchInput.value.toLowerCase().trim();
    
    if (query === '') {
        filteredSchools = [...allSchools];
    } else {
        filteredSchools = allSchools.filter(school => 
            school.nombre.toLowerCase().includes(query) ||
            school.localidad.toLowerCase().includes(query) ||
            school.direccion.toLowerCase().includes(query)
        );
    }
    
    renderSchools();
}

// Event listeners
searchBtn.addEventListener('click', performSearch);
searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        performSearch();
    }
});

// Búsqueda en tiempo real
searchInput.addEventListener('input', performSearch);

// View toggle functionality
gridViewBtn.addEventListener('click', () => {
    gridViewBtn.classList.add('active');
    listViewBtn.classList.remove('active');
    schoolsContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(250px, 1fr))';
});

listViewBtn.addEventListener('click', () => {
    listViewBtn.classList.add('active');
    gridViewBtn.classList.remove('active');
    schoolsContainer.style.gridTemplateColumns = '1fr';
});

// Cargar escuelas cuando se carga la página
document.addEventListener('DOMContentLoaded', loadSchools);