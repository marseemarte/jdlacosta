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
          <table id="noIngresanTable" class="display table table-sm" style="width:100%">
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
          return '<button class="btn btn-sm btn-primary">Ver ficha</button>';
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
  if ($('#noIngresanTable').length) {
    $('#noIngresanTable').DataTable({
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
});
</script>
</body>
</html>