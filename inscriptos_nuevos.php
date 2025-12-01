<?php
require_once __DIR__ . '/api/config.php';
init_session();
if (!isset($_SESSION['escuela_id']) || !isset($_SESSION['es_jefatura']) || !$_SESSION['es_jefatura']) {
    header('Location: login.php');
    exit;
}

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
  <title>Todos los Inscriptos - <?= htmlspecialchars($esc_nombre) ?></title>

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
        <h1 class="h4 mb-0 fw-semibold">Todos los Inscriptos</h1>
        <small class="text-muted"><?= htmlspecialchars($esc_nombre) ?></small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="dashboard_jefatura.php" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-arrow-left me-1"></i>Volver al Dashboard
        </a>
        <a href="api/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesion
        </a>
      </div>
    </div>
  </header>

  <main class="container mb-5">
    <div class="card p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Todos los inscriptos</h5>
      </div>
      <div class="table-responsive">
        <table id="inscriptosTable" class="display table table-striped" style="width:100%">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellido</th>
              <th>Nombre</th>
              <th>Vínculo</th>
              <th>Sorteo</th>
              <th>Secundaria</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          </tbody>
        </table>
      </div>
    </div>
  </main>

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
    var inscriptosTable = $('#inscriptosTable').DataTable({
      ajax: {
        url: 'api/get_inscriptos_distrito.php',
        dataSrc: 'data'
      },
      dom: 'Bfrtip',
      buttons: ['copy','csv','excel','pdf','print'],
      columns: [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { 
          data: 'vinculo',
          render: function(data) {
            if (data === 'Si' || data === 'si' || data === '1') {
              return '<span class="badge bg-success">Si</span>';
            } else if (data === 'No' || data === 'no' || data === '0') {
              return '<span class="badge bg-danger">No</span>';
            }
            return '<span class="badge bg-secondary">-</span>';
          }
        },
        { 
          data: 'orden_sorteo',
          render: function(data) {
            if (data && data !== 0 && data !== '0') {
              return '<span class="badge bg-warning text-dark">' + data + '</span>';
            }
            return '<span class="badge bg-secondary">-</span>';
          }
        },
        { data: 'secundaria' },
        { 
          data: null, 
          orderable: false, 
          render: function(data, type, row) {
            return `<button class="btn btn-sm btn-info ver-ficha-btn" data-dni="${row.dni}"><i class="fas fa-file-alt me-1"></i>Ver ficha</button>`;
          }
        }
      ],
      pageLength: 15,
      lengthChange: true,
      order: [[1, 'asc']],
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
  });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
