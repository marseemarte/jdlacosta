# Sistema de Inscripción Secundaria La Costa 2025

Este es un sistema web para la inscripción de estudiantes en escuelas secundarias de La Costa.

## Características

- **Carga dinámica de escuelas**: Las escuelas se cargan desde la base de datos MySQL
- **Búsqueda en tiempo real**: Filtra escuelas por nombre, localidad o dirección
- **Vista adaptable**: Cambio entre vista de grilla y lista
- **Interfaz responsive**: Diseño que se adapta a diferentes dispositivos
- **Manejo de errores**: Mensajes informativos para el usuario

## Requisitos del Sistema

- PHP 7.4 o superior
## Notas de seguridad importantes

- Las contraseñas ahora se almacenan de forma segura usando password_hash() de PHP. Las contraseñas existentes en texto plano se migrarán automáticamente la primera vez que el usuario inicie sesión.
- Todas las sesiones usan cookies con flags HttpOnly y SameSite=Lax; en producción asegúrate de servir el sitio vía HTTPS para forzar el flag secure.
- Agregado comprobación CSRF: el frontend pide un token a `api/get_csrf.php` y lo incluye en los formularios sensibles (login/reset, inscripciones).
- Limites y validación de archivos subidos: sólo imágenes (jpg/png) y PDF, tamaño máximo 3MB; los archivos se renombran de forma aleatoria y se guardan en `uploads/dni/`.
- Logging básico disponible en `api/logs/app.log` (eventos importantes como login, reset de contraseña e inscripciones exitosas).

- MySQL 5.7 o superior
- Servidor web (Apache/Nginx) o WAMP/XAMPP
- Navegador web moderno

## Instalación

1. **Configurar la base de datos**:
   - Importar el archivo `jdlacosta.sql` en MySQL
   - Verificar que la base de datos se haya creado correctamente

2. **Configurar la conexión**:
   - Editar el archivo `api/config.php`
   - Ajustar los parámetros de conexión según tu configuración:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'jdlacosta');
     define('DB_USER', 'tu_usuario');
     define('DB_PASS', 'tu_contraseña');
     ```

3. **Configurar el servidor web**:
   - Colocar los archivos en el directorio del servidor web
   - Asegurarse de que PHP esté habilitado
   - Verificar que las extensiones PDO y PDO_MySQL estén activas

## Estructura del Proyecto

```
jdlacosta/
├── api/
│   ├── config.php          # Configuración de la base de datos
│   └── get_escuelas.php    # API para obtener escuelas
├── css/
│   └── index.css           # Estilos del sistema
├── img/
│   └── logo.png           # Logo de las escuelas
├── js/
│   └── index.js           # Funcionalidad JavaScript
├── index.html             # Página principal
├── jdlacosta.sql          # Base de datos
└── README.md             # Este archivo
```

## Uso

1. **Acceder al sistema**: Abrir `index.html` en el navegador
2. **Buscar escuelas**: Usar el campo de búsqueda para filtrar escuelas
3. **Cambiar vista**: Usar los botones de grilla/lista
4. **Seleccionar escuela**: Hacer clic en una card para seleccionar la escuela

## Funcionalidades Implementadas

### ✅ Carga Dinámica de Escuelas
- Las escuelas se cargan automáticamente desde la base de datos
- Muestra nombre, localidad, dirección y teléfono
- Indicador de carga mientras se obtienen los datos

### ✅ Búsqueda en Tiempo Real
- Filtra por nombre de escuela
- Filtra por localidad
- Filtra por dirección
- Búsqueda instantánea mientras se escribe

### ✅ Vista Adaptable
- Vista de grilla para mostrar múltiples escuelas
- Vista de lista para mostrar más detalles
- Cambio dinámico entre vistas

### ✅ Interfaz Responsive
- Diseño que se adapta a móviles y tablets
- Botones y campos optimizados para touch
- Grid que se ajusta al tamaño de pantalla

### ✅ Manejo de Errores
- Mensajes informativos para errores de conexión
- Indicadores de estado (cargando, error, sin resultados)
- Validación de datos del servidor

## Próximas Funcionalidades

- [ ] Página de inscripción individual
- [ ] Formulario de datos del estudiante
- [ ] Validación de documentos
- [ ] Sistema de turnos
- [ ] Panel de administración
- [ ] Reportes y estadísticas

## Soporte

Para soporte técnico o consultas sobre el sistema, contactar al administrador del sistema.

## Licencia

Este proyecto es de uso interno para el sistema de inscripciones de La Costa.
