// Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchBtn = document.getElementById('searchBtn');

        function performSearch() {
            const query = searchInput.value.toLowerCase();
            console.log('Buscando:', query);
            // Aquí se implementará la lógica de búsqueda con la base de datos
        }

        searchBtn.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        // View toggle functionality
        const gridViewBtn = document.getElementById('gridViewBtn');
        const listViewBtn = document.getElementById('listViewBtn');
        const schoolsContainer = document.getElementById('schoolsContainer');

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

        // School card click functionality
        const schoolCards = document.querySelectorAll('.school-card');
        schoolCards.forEach(card => {
            card.addEventListener('click', () => {
                console.log('Escuela seleccionada');
                // Aquí se implementará la navegación a la página de inscripción
            });
        });