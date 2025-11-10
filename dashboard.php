<?php
session_start();
if (!isset($_SESSION['escuela_id'])) {
    header('Location: login.html');
    exit;
}
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>
<body>
  <header class="py-3 mb-4 border-bottom" style="background: linear-gradient(90deg, #f6f8ff, #ffffff);">
    <div class="container d-flex align-items-center justify-content-between">
      <div>
        <h1 class="h4 mb-0">Inscripción Secundaria 2025</h1>
        <small class="text-muted">Escuela: <?= htmlspecialchars($esc_nombre) ?></small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a href="api/logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
      </div>
    </div>
    <style>
      :root {
  --accent: #183c8f;
  --accent-light: #4f6fdc;
  --muted: #6b7280;
  --card-bg: #fff;
  --bg-gradient: linear-gradient(120deg, #f6f8ff 0%, #e9eefa 100%);
  --shadow: 0 4px 24px 0 rgba(24,60,143,0.10);
  --radius: 1.2rem;
}

body {
  background: var(--bg-gradient);
  color: #1f2b4d;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  min-height: 100vh;

}

header {
  background: linear-gradient(90deg, #f6f8ff, #ffffff);
  border-bottom: 1px solid #e5e7eb;
  box-shadow: 0 2px 8px rgba(24,60,143,0.04);
}

.info-card {
  border-radius: var(--radius);
  background: var(--card-bg);
  box-shadow: var(--shadow);
  border: none;
  transition: box-shadow 0.2s, transform 0.2s;
  position: relative;
  overflow: hidden;
}
.info-card:hover {
  box-shadow: 0 8px 32px 0 rgba(24,60,143,0.16);
  transform: translateY(-2px) scale(1.01);
}
.info-card .h5 {
  font-weight: 700;
  color: var(--accent);
}
.info-card i {
  opacity: 0.85;
  filter: drop-shadow(0 2px 8px #e9eefa);
}

.card {
  border-radius: var(--radius);
  border: none;
  background: var(--card-bg);
  box-shadow: var(--shadow);
}

.summary-card {
  background: linear-gradient(90deg, #e9eefa 60%, #f6f8ff 100%);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: none;
  margin-top: 2rem;
}

.summary-card h6 {
  color: var(--accent);
  font-weight: 700;
}

.summary-card p {
  font-size: 1.1rem;
  margin-bottom: 0;
}

/* Modern Table Styles */
.table {
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 4px 24px 0 rgba(24,60,143,0.07);
  background: #fff;
  margin-bottom: 0;
}

.table thead th {
  background: linear-gradient(90deg, #e9eefa 60%, #f6f8ff 100%);
  color: #183c8f;
  font-weight: 700;
  font-size: 1.05rem;
  border-top: none;
  border-bottom: 2px solid #e9eefa;
  letter-spacing: 0.02em;
  padding-top: 1rem;
  padding-bottom: 1rem;
}
.table-striped > tbody > tr:nth-of-type(odd) {
  background-color: #f8fafc;
}
.table-hover tbody tr:hover {
  background-color: #e3eafd;
  transition: background 0.18s;
}
.table td, .table th {
  vertical-align: middle;
  padding: 0.85rem 0.75rem;
  font-size: 1.01rem;
}
.table .badge {
  font-size: 0.92em;
  padding: 0.45em 0.8em;
  border-radius: 1rem;
  background: #e9eefa;
  color: #183c8f;
  font-weight: 500;
  letter-spacing: 0.01em;
}

.btn-primary {
  border-radius: 1.5rem;
  font-size: 0.97rem;
  padding: 0.35rem 1.1rem;
  box-shadow: 0 2px 8px 0 rgba(24,60,143,0.07);
}

@media (max-width: 767px) {
  .table thead { display: none; }
  .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
  .table tr { margin-bottom: 1rem; }
  .table td {
    text-align: right;
    padding-left: 50%;
    position: relative;
  }
  .table td::before {
    content: attr(data-label);
    position: absolute;
    left: 1rem;
    top: 0.85rem;
    font-weight: 600;
    color: #183c8f;
    text-align: left;
  }
}
    </style>
  </header>

  <main class="container mb-5">
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <div class="card p-3 info-card">
          <small class="text-muted">Número del sorteo</small>
          <div class="h5 mb-0"><?= htmlspecialchars($numero_sorteo) ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 info-card">
          <small class="text-muted">Vacantes</small>
          <div class="h5 mb-0"><?= htmlspecialchars($vacantes) ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 info-card">
          <small class="text-muted">Anotados</small>
          <div class="h5 mb-0"><?= htmlspecialchars($total_anotados) ?></div>
        </div>
      </div>
    </div>

    <section class="card p-3 mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="mb-0">Ingresan (<?= $ingresan_count ?>)</h5>
          <?php if ($ingresan_count > $vacantes && $vacantes > 0): ?>
            <small class="text-warning"><i class="fas fa-exclamation-triangle"></i> Exceden las <?= $vacantes ?> vacantes disponibles</small>
          <?php endif; ?>
        </div>
        <div class="d-flex align-items-center">
          <input id="tableSearch" class="form-control form-control-sm me-2" style="width:200px;" placeholder="Buscar...">
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
    <div class="card p-3">
      <h6 class="mb-2">Resumen de distribución</h6>
      <div class="row">
        <div class="col-md-3">
          <small class="text-muted">Total anotados:</small>
          <div class="h6 mb-0"><?= $total_anotados ?></div>
        </div>
        <div class="col-md-3">
          <small class="text-muted">Ingresan:</small>
          <div class="h6 mb-0 text-success"><?= $ingresan_count ?></div>
        </div>
        <?php if ($lista_espera_count > 0): ?>
        <div class="col-md-3">
          <small class="text-muted">Lista de espera:</small>
          <div class="h6 mb-0 text-warning"><?= $lista_espera_count ?></div>
        </div>
        <?php endif; ?>
        <div class="col-md-3">
          <small class="text-muted">No ingresan:</small>
          <div class="h6 mb-0 text-danger"><?= $no_ingresan_count ?></div>
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

  </main>

<!-- Modal Ver Ficha Alumno -->
<div class="modal fade" id="modalFichaAlumno" tabindex="-1" aria-labelledby="modalFichaAlumnoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="modalFichaAlumnoLabel"><i class="fas fa-user-graduate me-2"></i>Ficha de Inscripción</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" style="background:#f6f8ff;">
        <div id="fichaAlumnoContent">
          <div class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando datos...</div>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
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
            <div class="mb-2"><strong>Nombre:</strong> <span>${t.nombre || ''}</span></div>
            <div class="mb-2"><strong>Apellido:</strong> <span>${t.apellido || ''}</span></div>
            <div class="mb-2"><strong>Fecha de Nacimiento:</strong> <span>${t.fecha || ''}</span></div>
            <div class="mb-2"><strong>Teléfono:</strong> <span>${t.telefono || ''}</span></div>
            <div class="mb-2"><strong>E-Mail:</strong> <span>${t.mail || ''}</span></div>
          </div>
        </div>
      </div>
    `);
  }).fail(function() {
    $('#fichaAlumnoContent').html('<div class="alert alert-danger">No se pudo cargar la ficha.</div>');
  });
});

});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>