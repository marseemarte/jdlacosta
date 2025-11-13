<?php
session_start();
if (!isset($_SESSION['escuela_id'])) {
    header('Location: login.html');
    exit;
}
$es_jefatura = isset($_SESSION['es_jefatura']) ? (bool)$_SESSION['es_jefatura'] : false;
$escuela_id = (int) $_SESSION['escuela_id'];
require_once __DIR__ . '/api/config.php';
$pdo = getDBConnection();

// obtener datos de la escuela y conteos
$escuela = $pdo->prepare("SELECT nombre, vacantes FROM secundarias WHERE id = :id LIMIT 1");
$escuela->execute([':id' => $escuela_id]);
$esc = $escuela->fetch();

$esc_nombre = $esc['nombre'] ?? 'Escuela';
$vacantes = $esc['vacantes'] ?? '-';

// obtener número de sorteo: contar alumnos en el sorteo para esta escuela
$numero_sorteo_stmt = $pdo->prepare("SELECT COUNT(DISTINCT dni) as total FROM sorteo WHERE id_secundaria = :id");
$numero_sorteo_stmt->execute([':id' => $escuela_id]);
$numero_sorteo_result = $numero_sorteo_stmt->fetchColumn();
$numero_sorteo = $numero_sorteo_result !== null && $numero_sorteo_result > 0 ? (int)$numero_sorteo_result : '-';

// contar anotados e ingresan/no ingresan usando tabla alumnos
// IMPORTANTE: entro puede tener valores: 0 (no ingresan), 1 (ingresan), 2 (lista de espera)
$total_anotados_stmt = $pdo->prepare("SELECT COUNT(*) as c FROM alumnos WHERE id_secundaria = :id");
$total_anotados_stmt->execute([':id' => $escuela_id]);
$total_anotados = $total_anotados_stmt->fetchColumn();

$ingresan_count_stmt = $pdo->prepare("SELECT COUNT(*) as c FROM alumnos WHERE id_secundaria = :id AND entro = 1");
$ingresan_count_stmt->execute([':id' => $escuela_id]);
$ingresan_count = $ingresan_count_stmt->fetchColumn();

$no_ingresan_count_stmt = $pdo->prepare("SELECT COUNT(*) as c FROM alumnos WHERE id_secundaria = :id AND entro = 0");
$no_ingresan_count_stmt->execute([':id' => $escuela_id]);
$no_ingresan_count = $no_ingresan_count_stmt->fetchColumn();

$lista_espera_count_stmt = $pdo->prepare("SELECT COUNT(*) as c FROM alumnos WHERE id_secundaria = :id AND entro = 2");
$lista_espera_count_stmt->execute([':id' => $escuela_id]);
$lista_espera_count = $lista_espera_count_stmt->fetchColumn();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - <?= htmlspecialchars($esc_nombre) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/dashboard.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
</head>
<body>
  <header class="py-3 mb-4 border-bottom">
    <div class="container d-flex align-items-center justify-content-between">
      <div>
        <h1 class="h4 mb-0 fw-semibold">Inscripción Secundaria 2025</h1>
        <small class="text-muted"><?= htmlspecialchars($esc_nombre) ?></small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if ($es_jefatura): ?>
        <a href="#todosInscriptosCard" id="verTodosBtn" class="btn btn-primary btn-sm me-2">
          <i class="fas fa-list me-1"></i>Ver todos los inscriptos
        </a>
        <?php endif; ?>
        <a href="api/logout.php" class="btn btn-outline-danger btn-sm">
          <i class="fas fa-sign-out-alt me-1"></i>Cerrar Sesion
        </a>
      </div>
    </div>
  </header>

  <main class="container mb-5">
    <?php if ($es_jefatura): ?>
    <!-- Vista JEFATURA: Tabla de todas las escuelas del distrito -->
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
    <!-- Vista JEFATURA: Todos los inscriptos del distrito -->
    <div id="todosInscriptosCard" class="card p-4 mb-4 d-none">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Todos los inscriptos del distrito</h5>
      </div>
      <div class="table-responsive">
        <table id="todosInscriptosTable" class="display table table-striped" style="width:100%">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellido</th>
              <th>Nombre</th>
              <th>Vínculo</th>
              <th>Sorteo</th>
              <th>Secundaria</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <!-- Vista normal de escuela -->
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card p-4 info-card h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted text-uppercase fw-medium">Número del sorteo</small>
              <div class="h5 mb-0 mt-1"><?= htmlspecialchars($numero_sorteo) ?></div>
            </div>
            <div class="text-primary opacity-75">
              <i class="fas fa-dice fs-3"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-4 info-card h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted text-uppercase fw-medium">Vacantes</small>
              <div class="h5 mb-0 mt-1"><?= htmlspecialchars($vacantes) ?></div>
            </div>
            <div class="text-success opacity-75">
              <i class="fas fa-users fs-3"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-4 info-card h-100">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <small class="text-muted text-uppercase fw-medium">Anotados</small>
              <div class="h5 mb-0 mt-1"><?= htmlspecialchars($total_anotados) ?></div>
            </div>
            <div class="text-info opacity-75">
              <i class="fas fa-user-check fs-3"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="card p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h5 class="mb-1 fw-semibold">Ingresan <span class="badge bg-primary ms-1"><?= $ingresan_count ?></span></h5>
          <?php if ($ingresan_count > $vacantes && $vacantes > 0): ?>
            <small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Exceden las <?= $vacantes ?> vacantes disponibles</small>
          <?php endif; ?>
        </div>
        <div class="d-flex align-items-center">
          <div class="input-group input-group-sm" style="width: 250px;">
            <span class="input-group-text bg-transparent border-end-0">
              <i class="fas fa-search text-muted"></i>
            </span>
            <input id="tableSearch" class="form-control border-start-0" placeholder="Buscar alumno...">
          </div>
        </div>
      </div>

      <div class="table-responsive">
        <table id="ingresanTable" class="display table table-striped" style="width:100%">
          <thead>
            <tr>
              <th>DNI</th>
              <th>Apellido</th>
              <th>Nombre</th>
              <th>Vínculo</th>
              <th>Teléfono</th>
              <th>Mail</th>
              <th>Acciones</th>
            </tr>
          </thead>
        </table>
      </div>

      <div class="mt-3 d-flex justify-content-between">
        <small class="text-muted">Mostrando datos de la escuela</small>
        <div></div>
      </div>
    </section>

    <?php if ((int)$lista_espera_count > 0): ?>
    <section class="card p-3 mb-4">
      <h5 class="mb-3">Lista de espera (<?= $lista_espera_count ?>)</h5>
      <div id="listaEsperaList">
        <!-- cargaremos la lista vía AJAX -->
        <div class="table-responsive">
          <table id="listaEsperaTable" class="display table table-sm table-warning" style="width:100%">
            <thead>
              <tr>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Vínculo</th>
                <th>Teléfono</th>
                <th>Mail</th>
                <th>Orden</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ((int)$no_ingresan_count > 0): ?>
    <section class="card p-3 mb-4">
      <h5 class="mb-3">Alumnos que no ingresan (<?= $no_ingresan_count ?>)</h5>
      <div id="noIngresanList">
        <!-- cargaremos la lista vía AJAX -->
        <div class="table-responsive">
          <table id="noIngresarTable" class="display table table-sm" style="width:100%">
            <thead>
              <tr>
                <th>DNI</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Vínculo</th>
                <th>Teléfono</th>
                <th>Mail</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Resumen de distribución -->
    <div class="card p-4">
      <h6 class="mb-3 text-uppercase fw-semibold text-muted small">Resumen de distribución</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <div class="text-center p-3 bg-light rounded">
            <small class="text-muted d-block mb-1">Total anotados</small>
            <div class="h5 mb-0 fw-bold text-primary"><?= $total_anotados ?></div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="text-center p-3 bg-success bg-opacity-10 rounded">
            <small class="text-muted d-block mb-1">Ingresan</small>
            <div class="h5 mb-0 fw-bold text-success"><?= $ingresan_count ?></div>
          </div>
        </div>
        <?php if ($lista_espera_count > 0): ?>
        <div class="col-md-3">
          <div class="text-center p-3 bg-warning bg-opacity-10 rounded">
            <small class="text-muted d-block mb-1">Lista de espera</small>
            <div class="h5 mb-0 fw-bold text-warning"><?= $lista_espera_count ?></div>
          </div>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
          <div class="text-center p-3 bg-danger bg-opacity-10 rounded">
            <small class="text-muted d-block mb-1">No ingresan</small>
            <div class="h5 mb-0 fw-bold text-danger"><?= $no_ingresan_count ?></div>
          </div>
        </div>
      </div>
      <?php 
      $suma_estados = $ingresan_count + $lista_espera_count + $no_ingresan_count;
      if ($suma_estados != $total_anotados): 
      ?>
      <div class="alert alert-warning mt-2 mb-0">
        <small><i class="fas fa-exclamation-triangle"></i> <strong>Nota:</strong> La suma de estados (<?= $suma_estados ?>) no coincide con el total de anotados (<?= $total_anotados ?>). Puede haber registros con valores de 'entro' diferentes a 0, 1 o 2.</small>
      </div>
      <?php endif; ?>
      <?php if ($total_anotados == $vacantes && ($no_ingresan_count > 0 || $lista_espera_count > 0)): ?>
      <div class="alert alert-info mt-2 mb-0">
        <small><i class="fas fa-info-circle"></i> <strong>Nota:</strong> Aunque el número de anotados coincide con las vacantes, hay alumnos que no ingresan o están en lista de espera. Esto puede deberse a que el sorteo o la asignación ya se realizó y algunos alumnos fueron asignados a otras escuelas o rechazados por otros motivos.</small>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </main>

<!-- Modal Ver Ficha Alumno -->
<div class="modal fade" id="modalFichaAlumno" tabindex="-1" aria-labelledby="modalFichaAlumnoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title fw-semibold" id="modalFichaAlumnoLabel">
          <i class="fas fa-user-graduate me-2"></i>Ficha de Inscripción
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body bg-light p-4">
        <div id="fichaAlumnoContent">
          <div class="text-center text-muted">
            <i class="fas fa-spinner fa-spin me-2"></i>Cargando datos...
          </div>
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
  <?php if ($es_jefatura): ?>
  // DataTable para escuelas del distrito (vista JEFATURA)
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
            <button class="btn btn-sm btn-success me-1 nomina-btn" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
              Nomina inscriptos
            </button>
            <button class="btn btn-sm btn-primary me-1 ingresan-btn" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
              Ingresan
            </button>
            <button class="btn btn-sm btn-danger no-ingresan-btn" data-escuela-id="${row.id}" data-escuela-nombre="${row.nombre}">
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

  // Mostrar tabla de todos los inscriptos (distrito)
  var todosInscriptosInit = false;
  $('#verTodosBtn').on('click', function(e){
    e.preventDefault();
    // Mostrar la tabla de inscriptos y ocultar la de escuelas
    $('#todosInscriptosCard').removeClass('d-none');
    $('.card:has(#escuelasDistritoTable)').addClass('d-none');
    // Scroll hacia la tabla
    if ($('#todosInscriptosCard').length) {
      $('html, body').animate({ scrollTop: $('#todosInscriptosCard').offset().top - 80 }, 400);
    }
    // Inicializar DataTable solo una vez
    if (!todosInscriptosInit) {
      $('#todosInscriptosTable').DataTable({
        ajax: {
          url: 'api/get_inscriptos_distrito.php',
          dataSrc: 'data'
        },
        columns: [
          { data: 'dni' },
          { data: 'apellido' },
          { 
            data: 'nombre',
            render: function(data, type, row) {
              return `<a href="#" class="ver-ficha-link" data-dni="${row.dni}">${data}</a>`;
            }
          },
          { data: 'vinculo' },
          { data: 'orden_sorteo', defaultContent: '-' },
          { data: 'secundaria' }
        ],
        pageLength: 25,
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
      todosInscriptosInit = true;
    }
  });

  // Handler para abrir ficha desde el nombre
  $(document).on('click', '.ver-ficha-link', function(e){
    e.preventDefault();
    const dni = $(this).data('dni');
    $('#fichaAlumnoContent').html('<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando datos...</div>');
    var modal = new bootstrap.Modal(document.getElementById('modalFichaAlumno'));
    modal.show();
    $.getJSON('api/get_ficha_alumno.php?dni=' + encodeURIComponent(dni), function(data) {
      if (!data.success) {
        $('#fichaAlumnoContent').html('<div class="alert alert-danger">No se pudo cargar la ficha.</div>');
        return;
      }
      const a = data.alumno || {};
      const t = data.tutor || {};
      const alumnoDniFoto = a.foto_dni || a.dni_foto || '';
      const tutorDniFoto = t.foto_dni || t.dni_foto || '';
      $('#fichaAlumnoContent').html(`
        <div class="container-fluid">
          <div class="row mb-4">
            <div class="col-md-6">
              <h6 class="mb-3 text-primary">Datos del Alumno</h6>
              <div class="mb-2"><strong>DNI:</strong> <span>${a.dni || ''}</span></div>
              <div class="mb-2"><strong>Nombre:</strong> <span>${a.nombre || ''}</span></div>
              <div class="mb-2"><strong>Apellido:</strong> <span>${a.apellido || ''}</span></div>
              <div class="mb-2"><strong>Fecha de Nacimiento:</strong> <span>${a.fecha || ''}</span></div>
              <div class="mb-2"><strong>Dirección:</strong> <span>${a.direccion || ''}</span></div>
              <div class="mb-2"><strong>Localidad:</strong> <span>${a.localidad || ''}</span></div>
              <div class="mb-2"><strong>Escuela de procedencia:</strong> <span>${a.escuela || ''}</span></div>
              <div class="mb-2"><strong>Turno de preferencia:</strong> <span>${a.turno || ''}</span></div>
              <div class="mb-2"><strong>Vínculo con la escuela:</strong> <span>${a.vinculo_nombre || a.vinculo || ''}</span></div>
              <div class="mb-2"><strong>2da opción:</strong> <span>${a.id_sec2 || ''}</span></div>
              <div class="mb-2"><strong>3ra opción:</strong> <span>${a.id_sec3 || ''}</span></div>
              <div class="mb-2"><strong>Fecha inscripción:</strong> <span>${a.fecha_insc || ''}</span></div>
              <div class="mb-2"><strong>Hora inscripción:</strong> <span>${a.hora_insc || ''}</span></div>
              ${alumnoDniFoto ? `<div class="mt-3"><strong>Foto DNI Alumno:</strong><div><img src="${alumnoDniFoto}" alt="DNI Alumno" class="img-fluid rounded border" style="max-height:240px;object-fit:contain;"></div></div>` : ''}
            </div>
            <div class="col-md-6">
              <h6 class="mb-3 text-primary">Datos del padre, madre o tutor</h6>
              <div class="mb-2"><strong>DNI:</strong> <span>${t.dni || ''}</span></div>
              <div class="mb-2"><strong>Nombre:</strong> <span>${t.nombre || ''} ${t.apellido || ''}</span></div>
              <div class="mb-2"><strong>Fecha de Nacimiento:</strong> <span>${t.fecha || ''}</span></div>
              <div class="mb-2"><strong>Teléfono:</strong> <span>${t.telefono || ''}</span></div>
              <div class="mb-2"><strong>E-Mail:</strong> <span>${t.mail || ''}</span></div>
              ${t.dni ? '<div class="mt-3 text-muted"><small><i class="fas fa-user-shield me-1"></i>Responsable legal del alumno</small></div>' : '<div class="mt-3 text-muted"><small><i class="fas fa-exclamation-triangle me-1"></i>No se encontraron datos del tutor</small></div>'}
              ${tutorDniFoto ? `<div class="mt-3"><strong>Foto DNI Tutor:</strong><div><img src="${tutorDniFoto}" alt="DNI Tutor" class="img-fluid rounded border" style="max-height:240px;object-fit:contain;"></div></div>` : ''}
            </div>
          </div>
        </div>
      `);
    }).fail(function() {
      $('#fichaAlumnoContent').html('<div class="alert alert-danger">No se pudo cargar la ficha.</div>');
    });
  });
  // Handlers para los botones de acciones
  $(document).on('click', '.nomina-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    // Aquí puedes implementar la funcionalidad para ver la nómina completa
    alert('Ver nómina de inscriptos de: ' + escuelaNombre + ' (ID: ' + escuelaId + ')');
  });

  $(document).on('click', '.ingresan-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    // Redirigir o mostrar modal con alumnos que ingresan
    window.location.href = 'dashboard_escuela.php?escuela_id=' + escuelaId + '&tipo=ingresan';
  });

  $(document).on('click', '.no-ingresan-btn', function() {
    var escuelaId = $(this).data('escuela-id');
    var escuelaNombre = $(this).data('escuela-nombre');
    // Redirigir o mostrar modal con alumnos que no ingresan
    window.location.href = 'dashboard_escuela.php?escuela_id=' + escuelaId + '&tipo=no_ingresan';
  });
  <?php else: ?>
  // DataTable para "Ingresan" (trae datos desde API)
  var table = $('#ingresanTable').DataTable({
    ajax: {
      url: 'api/get_alumnos.php?type=ingresan',
      dataSrc: 'data'
    },
    columns: [
      { data: 'dni' },
      { data: 'apellido' },
      { data: 'nombre' },
      { data: 'vinculo' },
      { data: 'telefono' },
      { data: 'mail' },
      { data: null, orderable: false, render: function (d) {
          return `<button class="btn btn-sm btn-primary ver-ficha-btn" 
      data-dni="${d.dni}" 
      data-apellido="${d.apellido}" 
      data-nombre="${d.nombre}" 
      data-vinculo="${d.vinculo}" 
      data-telefono="${d.telefono || ''}" 
      data-mail="${d.mail || ''}"
      >Ver ficha</button>`;
        }
      }
    ],
    pageLength: 10,
    lengthChange: false,
    language: {
      search: "Filtro:",
      paginate: {
        previous: "Prev",
        next: "Next"
      },
      info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
      zeroRecords: "No se encontraron registros"
    }
  });

  // búsqueda externa
  $('#tableSearch').on('keyup', function(){
    table.search(this.value).draw();
  });

  // si existe sección "lista de espera" la cargamos en otra datatable
  if ($('#listaEsperaTable').length) {
    $('#listaEsperaTable').DataTable({
      ajax: {
        url: 'api/get_alumnos.php?type=lista_espera',
        dataSrc: 'data'
      },
      columns: [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { data: 'vinculo' },
        { data: 'telefono' },
        { data: 'mail' },
        { data: 'espera', render: function(data) { return data || '-'; } }
      ],
      pageLength: 10,
      lengthChange: false,
      order: [[6, 'asc']], // ordenar por orden de espera
      language: {
        search: "Filtro:",
        paginate: { previous: "Prev", next: "Next" },
        info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
        zeroRecords: "No se encontraron registros"
      }
    });
  }

  // si existe sección "no ingresan" la cargamos en otra datatable
  if ($('#noIngresarTable').length) {
    $('#noIngresarTable').DataTable({
      ajax: {
        url: 'api/get_alumnos.php?type=no_ingresan',
        dataSrc: 'data'
      },
      columns: [
        { data: 'dni' },
        { data: 'apellido' },
        { data: 'nombre' },
        { data: 'vinculo' },
        { data: 'telefono' },
        { data: 'mail' }
      ],
      pageLength: 10,
      lengthChange: false,
      language: {
        search: "Filtro:",
        paginate: { previous: "Prev", next: "Next" },
        info: "Mostrando _START_ a _END_ de _TOTAL_ entradas",
        zeroRecords: "No se encontraron registros"
      }
    });
  }

  // Modal "Ver ficha" - muestra todos los datos del alumno y tutor
  $(document).on('click', '.ver-ficha-btn', function() {
  const dni = $(this).data('dni');
  $('#fichaAlumnoContent').html('<div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando datos...</div>');
  var modal = new bootstrap.Modal(document.getElementById('modalFichaAlumno'));
  modal.show();

  $.getJSON('api/get_ficha_alumno.php?dni=' + encodeURIComponent(dni), function(data) {
    if (!data.success) {
      $('#fichaAlumnoContent').html('<div class="alert alert-danger">No se pudo cargar la ficha.</div>');
      return;
    }
    const a = data.alumno;
    const t = data.tutor;
    $('#fichaAlumnoContent').html(`
      <div class="container-fluid">
        <div class="row mb-4">
          <div class="col-md-6">
            <h6 class="mb-3 text-primary">Datos del Alumno</h6>
            <div class="mb-2"><strong>DNI:</strong> <span>${a.dni || ''}</span></div>
            <div class="mb-2"><strong>Nombre:</strong> <span>${a.nombre || ''}</span></div>
            <div class="mb-2"><strong>Apellido:</strong> <span>${a.apellido || ''}</span></div>
            <div class="mb-2"><strong>Fecha de Nacimiento:</strong> <span>${a.fecha || ''}</span></div>
            <div class="mb-2"><strong>Dirección:</strong> <span>${a.direccion || ''}</span></div>
            <div class="mb-2"><strong>Localidad:</strong> <span>${a.localidad || ''}</span></div>
            <div class="mb-2"><strong>Escuela de procedencia:</strong> <span>${a.escuela || ''}</span></div>
            <div class="mb-2"><strong>Turno de preferencia:</strong> <span>${a.turno || ''}</span></div>
            <div class="mb-2"><strong>Vínculo con la escuela:</strong> <span>${a.vinculo_nombre || a.vinculo || ''}</span></div>
            <div class="mb-2"><strong>2da opción:</strong> <span>${a.id_sec2 || ''}</span></div>
            <div class="mb-2"><strong>3ra opción:</strong> <span>${a.id_sec3 || ''}</span></div>
            <div class="mb-2"><strong>Fecha inscripción:</strong> <span>${a.fecha_insc || ''}</span></div>
            <div class="mb-2"><strong>Hora inscripción:</strong> <span>${a.hora_insc || ''}</span></div>
          </div>
          <div class="col-md-6">
            <h6 class="mb-3 text-primary">Datos del padre, madre o tutor</h6>
            <div class="mb-2"><strong>DNI:</strong> <span>${t.dni || ''}</span></div>
            <div class="mb-2"><strong>Nombre:</strong> <span>${t.nombre || ''} ${t.apellido || ''}</span></div>
            <div class="mb-2"><strong>Fecha de Nacimiento:</strong> <span>${t.fecha || ''}</span></div>
            <div class="mb-2"><strong>Teléfono:</strong> <span>${t.telefono || ''}</span></div>
            <div class="mb-2"><strong>E-Mail:</strong> <span>${t.mail || ''}</span></div>
            ${t.dni ? '<div class="mt-3 text-muted"><small><i class="fas fa-user-shield me-1"></i>Responsable legal del alumno</small></div>' : '<div class="mt-3 text-muted"><small><i class="fas fa-exclamation-triangle me-1"></i>No se encontraron datos del tutor</small></div>'}
          </div>
        </div>
      </div>
    `);
  }).fail(function() {
    $('#fichaAlumnoContent').html('<div class="alert alert-danger">No se pudo cargar la ficha.</div>');
  });
  <?php endif; ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>