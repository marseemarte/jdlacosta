<?php
session_start();
if (!isset($_SESSION['escuela_id']) || !isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/api/config.php';
$pdo = getDBConnection();

// Obtener nombre de la jefatura
$escuela = $pdo->prepare("SELECT nombre FROM secundarias WHERE id = :id LIMIT 1");
$escuela->execute([':id' => $_SESSION['escuela_id']]);
$esc = $escuela->fetch();
$esc_nombre = $esc['nombre'] ?? 'Jefatura Distrital';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Jefatura - <?= htmlspecialchars($esc_nombre) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/dashboard.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
  <header class="py-3 mb-4 border-bottom">
    <div class="container d-flex align-items-center justify-content-between">
      <div>
        <h1 class="h4 mb-0 fw-semibold">Inscripción Secundaria 2025</h1>
        <small class="text-muted"><?= htmlspecialchars($esc_nombre) ?></small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="api/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesion
        </a>
      </div>
    </div>
  </header>

  <main class="container mb-5">
    <!-- Tabla de todas las escuelas del distrito -->
    <div class="card p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Escuelas del Distrito</h5>
      </div>
      <div class="table-responsive">
        <table id="escuelasDistritoTable" class="display table table-striped" style="width:100%">
          <thead>
            <tr>
              <th>Escuela</th>
              <th>Vacantes</th>
              <th>Anotados</th>
              <th>Último Acceso</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div>
  </main>

<!-- Modal para mostrar alumnos -->
<div class="modal fade" id="modalAlumnos" tabindex="-1" aria-labelledby="modalAlumnosLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title fw-semibold" id="modalAlumnosLabel">
          <i class="fas fa-users me-2"></i><span id="modalTitulo">Alumnos</span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body bg-light p-4">
        <div class="table-responsive">
          <table id="alumnosModalTable" class="display table table-striped" style="width:100%">
            <thead id="modalTableHead">
              <tr>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Vínculo</th>
                <th>Teléfono</th>
                <th>Mail</th>
              </tr>
            </thead>
            <tbody>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer bg-white border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="fas fa-times me-1"></i>Cerrar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
  // DataTable para escuelas del distrito
  var escuelasTable = $('#escuelasDistritoTable').DataTable({
    ajax: {
      url: 'api/get_escuelas_distrito.php',
      dataSrc: 'data'
    },
    columns: [
      { data: 'nombre' },
      { data: 'vacantes' },
      { data: 'anotados' },
      { data: 'ultimo_acceso', defaultContent: '-' },
      { 
        data: null, 
        orderable: false, 
        render: function (data, type, row) {
          return `
            <button class="btn btn-sm me-1 nomina-btn" style="background-color: #28a745; color: white;" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
              Nomina inscriptos
            </button>
            <button class="btn btn-sm me-1 ingresan-btn" style="background-color: #007bff; color: white;" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
              Ingresan
            </button>
            <button class="btn btn-sm no-ingresan-btn" style="background-color: #dc3545; color: white;" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
              No ingresan
            </button>
          `;
        }
      }
    ],
    pageLength: 10,
    lengthChange: true,
    language: {
      search: "Buscar:",
      lengthMenu: "Mostrar _MENU_ entradas por página",
      info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
      infoEmpty: "Mostrando 0 a 0 de 0 entradas",
      infoFiltered: "(filtrado de _MAX_ entradas totales)",
      zeroRecords: "No se encontraron registros",
      paginate: {
        previous: "Anterior",
        next: "Siguiente"
      }
    }
  });

  var alumnosModalTable = null;
  var modalElement = document.getElementById('modalAlumnos');

  // Limpiar tabla cuando se cierra el modal
  $(modalElement).on('hidden.bs.modal', function () {
    if (alumnosModalTable) {
      alumnosModalTable.destroy();
      alumnosModalTable = null;
    }
    // Limpiar el tbody
    $('#alumnosModalTable tbody').empty();
  });

  // Handler para botón "Nomina inscriptos"
  $(document).on('click', '.nomina-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    mostrarAlumnos(escuelaId, escuelaNombre, 'nomina');
  });

  // Handler para botón "Ingresan"
  $(document).on('click', '.ingresan-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    mostrarAlumnos(escuelaId, escuelaNombre, 'ingresan');
  });

  // Handler para botón "No ingresan"
  $(document).on('click', '.no-ingresan-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    mostrarAlumnos(escuelaId, escuelaNombre, 'no_ingresan');
  });

  function mostrarAlumnos(escuelaId, escuelaNombre, tipo) {
    var titulo = '';
    var columnas = [];
    var headersHtml = '';
    
    if (tipo === 'nomina') {
      titulo = 'Nómina Completa de Inscriptos - ' + escuelaNombre;
      columnas = [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { data: 'vinculo' },
        { data: 'telefono' },
        { data: 'mail' }
      ];
      headersHtml = `
        <tr>
          <th>DNI</th>
          <th>Apellido</th>
          <th>Nombre</th>
          <th>Vínculo</th>
          <th>Teléfono</th>
          <th>Mail</th>
        </tr>
      `;
    } else if (tipo === 'ingresan') {
      titulo = 'Alumnos que Ingresan - ' + escuelaNombre;
      columnas = [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { data: 'vinculo' },
        { data: 'telefono' },
        { data: 'mail' }
      ];
      headersHtml = `
        <tr>
          <th>DNI</th>
          <th>Apellido</th>
          <th>Nombre</th>
          <th>Vínculo</th>
          <th>Teléfono</th>
          <th>Mail</th>
        </tr>
      `;
    } else if (tipo === 'no_ingresan') {
      titulo = 'Alumnos que No Ingresan - ' + escuelaNombre;
      columnas = [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { data: 'vinculo' },
        { data: 'telefono' },
        { data: 'mail' },
        { 
          data: 'escuela_opcion2', 
          render: function(data) { return data || '-'; },
          title: 'Opción 2'
        },
        { 
          data: 'escuela_opcion3', 
          render: function(data) { return data || '-'; },
          title: 'Opción 3'
        }
      ];
      headersHtml = `
        <tr>
          <th>DNI</th>
          <th>Apellido</th>
          <th>Nombre</th>
          <th>Vínculo</th>
          <th>Teléfono</th>
          <th>Mail</th>
          <th>Opción 2</th>
          <th>Opción 3</th>
        </tr>
      `;
    }

    $('#modalTitulo').text(titulo);
    
    // Destruir tabla anterior si existe
    if (alumnosModalTable) {
      alumnosModalTable.destroy();
      alumnosModalTable = null;
    }

    // Actualizar encabezados de la tabla ANTES de crear la nueva DataTable
    $('#modalTableHead').html(headersHtml);
    
    // Limpiar el tbody
    $('#alumnosModalTable tbody').empty();

    // Crear nueva tabla
    alumnosModalTable = $('#alumnosModalTable').DataTable({
      ajax: {
        url: 'api/get_alumnos_escuela.php?escuela_id=' + escuelaId + '&type=' + tipo,
        dataSrc: 'data'
      },
      columns: columnas,
      pageLength: 10,
      lengthChange: true,
      language: {
        search: "Buscar:",
        lengthMenu: "Mostrar _MENU_ entradas por página",
        info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
        infoEmpty: "Mostrando 0 a 0 de 0 entradas",
        infoFiltered: "(filtrado de _MAX_ entradas totales)",
        zeroRecords: "No se encontraron registros",
        paginate: {
          previous: "Anterior",
          next: "Siguiente"
        }
      }
    });

    // Mostrar modal
    var modal = new bootstrap.Modal(modalElement);
    modal.show();
  }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

