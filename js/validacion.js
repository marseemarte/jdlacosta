// DNI: 7-8 dígitos (elimina espacios y puntos)
function validarDNI(dni) {
    const limpio = dni.replace(/[\s.]/g, '');
    if (!/^\d+$/.test(limpio)) return false;
    return limpio.length >= 7 && limpio.length <= 8;
}

// Email: algo@algo.algo
function validarEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Teléfono: mínimo 10 dígitos (elimina espacios, guiones, paréntesis)
function validarTelefono(telefono) {
    const limpio = telefono.replace(/[\s-()]/g, '');
    return /^\d{10,}$/.test(limpio);
}

// Apellido/Nombre: mínimo 2 caracteres
function validarApellidoNombre(texto) {
    return texto.trim().length >= 2;
}

// Muestra/oculta error bajo un campo (busca .error-message en el padre)
function mostrarErrores(fieldId, mensaje) {
    const campo = document.getElementById(fieldId);
    if (!campo) return;
    
    const errorDiv = campo.parentElement.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.textContent = mensaje;
        errorDiv.style.display = mensaje ? 'block' : 'none';
    }
}
