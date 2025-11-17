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

  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

  <!-- Modal para ver ficha del alumno -->
  <div class="modal fade" id="modalFicha" tabindex="-1" aria-labelledby="modalFichaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-info text-white border-0">
          <h5 class="modal-title fw-semibold" id="modalFichaLabel">
            <i class="fas fa-file-alt me-2"></i>Ficha del Alumno
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body bg-light p-4" id="fichaContent">
          <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>
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
    var currentModalEscuelaId = null;
    var currentModalEscuelaNombre = null;
    var currentModalTipo = null;

    var escuelasTable = $('#escuelasDistritoTable').DataTable({
      ajax: {
        url: 'api/get_escuelas_distrito.php',
        dataSrc: 'data'
      },
      dom: 'Bfrtip',
      buttons: ['copy','csv','excel','pdf','print'],
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

    $(modalElement).on('hidden.bs.modal', function () {
      if (alumnosModalTable) {
        alumnosModalTable.destroy();
        alumnosModalTable = null;
      }
      $('#alumnosModalTable tbody').empty();
      currentModalEscuelaId = null;
      currentModalEscuelaNombre = null;
      currentModalTipo = null;
    });

    $(document).on('click', '.nomina-btn', function() {
      mostrarAlumnos($(this).data('escuela-id'), $(this).data('escuela-nombre'), 'nomina');
    });
    $(document).on('click', '.ingresan-btn', function() {
      mostrarAlumnos($(this).data('escuela-id'), $(this).data('escuela-nombre'), 'ingresan');
    });
    $(document).on('click', '.no-ingresan-btn', function() {
      mostrarAlumnos($(this).data('escuela-id'), $(this).data('escuela-nombre'), 'no_ingresan');
    });

    function mostrarAlumnos(escuelaId, escuelaNombre, tipo) {
      currentModalEscuelaId = escuelaId;
      currentModalEscuelaNombre = escuelaNombre;
      currentModalTipo = tipo;

      var titulo = '';
      var columnas = [];
      var headersHtml = '';

      if (tipo === 'nomina') {
        titulo = 'Nómina Completa de Inscriptos - ' + escuelaNombre;
        columnas = [
          { data: 'dni' },
          { data: 'apellido' },
          { data: 'nombre' },
          { data: 'escuela', render: function(data){ return data||'-'; }, title: 'Primaria' },
          { data: 'vinculo' },
          { data: 'telefono' },
          { data: 'mail' },
          { data: null, orderable: false, render: function(data,type,row){ return `<button class="btn btn-sm btn-info ver-ficha-btn" data-dni="${row.dni}"><i class="fas fa-file-alt me-1"></i>Ver Ficha</button>`; }, title: 'Acciones' }
        ];
        headersHtml = `<tr><th>DNI</th><th>Apellido</th><th>Nombre</th><th>Primaria</th><th>Vínculo</th><th>Teléfono</th><th>Mail</th><th>Acciones</th></tr>`;
      } else if (tipo === 'ingresan') {
        titulo = 'Alumnos que Ingresan - ' + escuelaNombre;
        columnas = [
          { data: 'dni' },
          { data: 'apellido' },
          { data: 'nombre' },
          { data: 'escuela', render: function(data){ return data||'-'; }, title: 'Primaria' },
          { data: 'vinculo' },
          { data: 'telefono' },
          { data: 'mail' },
          { data: null, orderable: false, render: function(data,type,row){ return `<button class="btn btn-sm btn-info ver-ficha-btn" data-dni="${row.dni}"><i class="fas fa-file-alt me-1"></i>Ver Ficha</button>`; }, title: 'Acciones' }
        ];
        headersHtml = `<tr><th>DNI</th><th>Apellido</th><th>Nombre</th><th>Primaria</th><th>Vínculo</th><th>Teléfono</th><th>Mail</th><th>Acciones</th></tr>`;
      } else if (tipo === 'no_ingresan') {
        titulo = 'Alumnos que No Ingresan - ' + escuelaNombre;
        columnas = [
          { data: 'orden_lista_espera' },
          { data: 'dni' },
          { data: 'apellido' },
          { data: 'nombre' },
          { data: 'escuela', render: function(data){ return data||'-'; }, title: 'Primaria' },
          { data: 'vinculo' },
          { data: 'telefono' },
          { data: 'mail' },
          { data: 'escuela_opcion2', render: function(data){ return data||'-'; }, title: 'Opción 2' },
          { data: 'escuela_opcion3', render: function(data){ return data||'-'; }, title: 'Opción 3' },
          { data: null, orderable:false, render: function(data,type,row){
              var btn2 = '', btn3 = '';
              if (row.s2_id) {
                var vac2 = parseInt(row.vacantes_op2 || 0, 10);
                var oc2 = parseInt(row.ocupados_op2 || 0, 10);
                var full2 = (vac2 <= 0) || (oc2 >= vac2);
                btn2 = `<button class="btn btn-sm reasignar-op2-btn" data-dni="${row.dni}" data-target-id="${row.s2_id}" data-target-name="${(row.escuela_opcion2||'')}" ${full2 ? 'disabled title="Opción 2 completa"':''}>Reasignar a Opción 2</button>`;
              } else btn2 = '<span class="text-muted">-</span>';
              if (row.s3_id) {
                var vac3 = parseInt(row.vacantes_op3 || 0, 10);
                var oc3 = parseInt(row.ocupados_op3 || 0, 10);
                var full3 = (vac3 <= 0) || (oc3 >= vac3);
                btn3 = `<button class="btn btn-sm btn-secondary ms-1 reasignar-op3-btn" data-dni="${row.dni}" data-target-id="${row.s3_id}" data-target-name="${(row.escuela_opcion3||'')}" ${full3 ? 'disabled title="Opción 3 completa"':''}>Reasignar a Opción 3</button>`;
              } else btn3 = '<span class="text-muted">-</span>';
              return `<div class="mb-2">${btn2} ${btn3}</div><button class="btn btn-sm btn-info ver-ficha-btn" data-dni="${row.dni}"><i class="fas fa-file-alt me-1"></i>Ver Ficha</button>`;
            }, title: 'Acciones'
          }
        ];
        headersHtml = `<tr><th>Orden Lista Espera</th><th>DNI</th><th>Apellido</th><th>Nombre</th><th>Primaria</th><th>Vínculo</th><th>Teléfono</th><th>Mail</th><th>Opción 2</th><th>Opción 3</th><th>Acciones</th></tr>`;
      }

      $('#modalTitulo').text(titulo);

      if (alumnosModalTable) {
        alumnosModalTable.destroy();
        alumnosModalTable = null;
      }
      $('#modalTableHead').html(headersHtml);
      $('#alumnosModalTable tbody').empty();

      alumnosModalTable = $('#alumnosModalTable').DataTable({
        ajax: {
          url: 'api/get_alumnos_escuela.php?escuela_id=' + escuelaId + '&type=' + tipo,
          dataSrc: 'data'
        },
        dom: 'Bfrtip',
        buttons: ['copy','csv','excel','pdf','print'],
        columns: columnas,
        pageLength: 10,
        lengthChange: true,
        order: tipo === 'no_ingresan' ? [[0,'asc']] : [],
        language: {
          search: "Buscar:",
          lengthMenu: "Mostrar _MENU_ entradas por página",
          info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
          infoEmpty: "Mostrando 0 a 0 de 0 entradas",
          infoFiltered: "(filtrado de _MAX_ entradas totales)",
          zeroRecords: "No se encontraron registros",
          paginate: { previous: "Anterior", next: "Siguiente" }
        }
      });

      var modal = new bootstrap.Modal(modalElement);
      modal.show();
    }

    // Ver ficha
    $(document).on('click', '.ver-ficha-btn', function() {
      var dni = $(this).data('dni');
      $.ajax({
        url: 'api/get_ficha_alumno.php?dni=' + encodeURIComponent(dni),
        dataType: 'json'
      }).done(function(resp){
        if (resp && resp.success) {
          var al = resp.alumno || {};
          var tu = resp.tutor || {};
          var html = `
            <div class="card mb-3 border-0 shadow-sm">
              <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Datos del Alumno</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>DNI:</strong> ${al.dni||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Apellido:</strong> ${al.apellido||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Nombre:</strong> ${al.nombre||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Fecha de Nacimiento:</strong> ${al.fecha||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Primaria:</strong> ${al.escuela||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Dirección:</strong> ${al.direccion||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Localidad:</strong> ${al.localidad||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Vínculo:</strong> ${al.vinculo_nombre||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Turno Preferido:</strong> ${al.turno||'-'}</div>
                </div>
              </div>
            </div>
            <div class="card mb-3 border-0 shadow-sm">
              <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>Datos del Padre/Tutor</h6>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6 mb-2"><strong>Apellido:</strong> ${tu.apellido||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Nombre:</strong> ${tu.nombre||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>DNI:</strong> ${tu.dni||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Teléfono:</strong> ${tu.telefono||'-'}</div>
                  <div class="col-md-6 mb-2"><strong>Mail:</strong> ${tu.mail||'-'}</div>
                </div>
              </div>
            </div>
          `;
          $('#fichaContent').html(html);
          var modal = new bootstrap.Modal(document.getElementById('modalFicha'));
          modal.show();
        } else {
          Swal.fire('Error', 'No se pudo cargar la ficha del alumno.', 'error');
        }
      }).fail(function(){
        Swal.fire('Error', 'Error al cargar la ficha.', 'error');
      });
    });

    // Reasignar a opción 2
    $(document).on('click', '.reasignar-op2-btn', function() {
      var dni = $(this).data('dni');
      var targetId = $(this).data('target-id');
      var targetName = $(this).data('target-name') || 'la escuela';
      Swal.fire({
        title: 'Confirmar',
        text: '¿Confirma reasignar este alumno a su Opción 2 (' + targetName + ')?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reasignar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.ajax({
          url: 'api/reasignar_alumno.php',
          method: 'POST',
          data: { dni: dni, target_id: targetId },
          dataType: 'json'
        }).done(function(resp){
          if (resp && resp.success) {
            Swal.fire({ icon: 'success', title: 'Reasignado', text: 'Reasignado a ' + (resp.school_name||targetName) + ' con éxito', timer: 1500, showConfirmButton: false });
            escuelasTable.ajax.reload(null, false);
            if (currentModalTipo === 'no_ingresan' && currentModalEscuelaId) {
              var inst = bootstrap.Modal.getInstance(modalElement);
              if (inst) inst.hide();
              setTimeout(function(){
                mostrarAlumnos(currentModalEscuelaId, currentModalEscuelaNombre, 'ingresan');
              }, 400);
            } else if (alumnosModalTable) {
              alumnosModalTable.ajax.reload(null, false);
            }
          } else {
            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo reasignar.', 'error');
            if (alumnosModalTable) alumnosModalTable.ajax.reload(null, false);
          }
        }).fail(function(){
          Swal.fire('Error servidor', 'Error al realizar la reasignación.', 'error');
        });
      });
    });

    // Reasignar a opción 3
    $(document).on('click', '.reasignar-op3-btn', function() {
      var dni = $(this).data('dni');
      var targetId = $(this).data('target-id');
      var targetName = $(this).data('target-name') || 'la escuela';
      Swal.fire({
        title: 'Confirmar',
        text: '¿Confirma reasignar este alumno a su Opción 3 (' + targetName + ')?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reasignar',
        cancelButtonText: 'Cancelar'
      }).then((result) => {
        if (!result.isConfirmed) return;
        $.ajax({
          url: 'api/reasignar_alumno.php',
          method: 'POST',
          data: { dni: dni, target_id: targetId },
          dataType: 'json'
        }).done(function(resp){
          if (resp && resp.success) {
            Swal.fire({ icon: 'success', title: 'Reasignado', text: 'Reasignado a ' + (resp.school_name||targetName) + ' con éxito', timer: 1500, showConfirmButton: false });
            escuelasTable.ajax.reload(null, false);
            if (currentModalTipo === 'no_ingresan' && currentModalEscuelaId) {
              var inst = bootstrap.Modal.getInstance(modalElement);
              if (inst) inst.hide();
              setTimeout(function(){
                mostrarAlumnos(currentModalEscuelaId, currentModalEscuelaNombre, 'ingresan');
              }, 400);
            } else if (alumnosModalTable) {
              alumnosModalTable.ajax.reload(null, false);
            }
          } else {
            Swal.fire('Error', (resp && resp.message) ? resp.message : 'No se pudo reasignar.', 'error');
            if (alumnosModalTable) alumnosModalTable.ajax.reload(null, false);
          }
        }).fail(function(){
          Swal.fire('Error servidor', 'Error al realizar la reasignación.', 'error');
        });
      });
    });

  });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

