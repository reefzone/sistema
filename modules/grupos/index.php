<?php
/**
 * Listado de Grupos
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Filtros de búsqueda
$filtro_nombre = isset($_GET['nombre']) ? sanitizar_texto($_GET['nombre']) : '';
$filtro_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;
$filtro_turno = isset($_GET['turno']) ? intval($_GET['turno']) : 0;
$filtro_ciclo = isset($_GET['ciclo']) ? sanitizar_texto($_GET['ciclo']) : '';

// Paginación
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 20;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;

// Consulta base para obtener grupos
$query_base = "FROM grupos g 
               JOIN grados gr ON g.id_grado = gr.id_grado 
               JOIN turnos t ON g.id_turno = t.id_turno 
               WHERE g.activo = 1";

// Agregar condiciones según filtros
$params = [];
$tipos = "";

if (!empty($filtro_nombre)) {
    $query_base .= " AND g.nombre_grupo LIKE ?";
    $busqueda = "%$filtro_nombre%";
    $params[] = $busqueda;
    $tipos .= "s";
}

if ($filtro_grado > 0) {
    $query_base .= " AND g.id_grado = ?";
    $params[] = $filtro_grado;
    $tipos .= "i";
}

if ($filtro_turno > 0) {
    $query_base .= " AND g.id_turno = ?";
    $params[] = $filtro_turno;
    $tipos .= "i";
}

if (!empty($filtro_ciclo)) {
    $query_base .= " AND g.ciclo_escolar LIKE ?";
    $busqueda = "%$filtro_ciclo%";
    $params[] = $busqueda;
    $tipos .= "s";
}

// Consulta para contar total de registros
$query_count = "SELECT COUNT(g.id_grupo) as total $query_base";
$stmt_count = $conexion->prepare($query_count);

if (!empty($params)) {
    $stmt_count->bind_param($tipos, ...$params);
}

$stmt_count->execute();
$result_count = $stmt_count->get_result();
$row_count = $result_count->fetch_assoc();
$total_registros = $row_count['total'];

// Calcular total de páginas
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Consulta para obtener grupos paginados
$query = "SELECT g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno, 
          g.ciclo_escolar, g.color_credencial,
          (SELECT COUNT(*) FROM alumnos a WHERE a.id_grupo = g.id_grupo AND a.activo = 1) as total_alumnos
          $query_base 
          ORDER BY t.id_turno, gr.id_grado, g.nombre_grupo 
          LIMIT ?, ?";

$stmt = $conexion->prepare($query);

if (!empty($params)) {
    $params[] = $inicio;
    $params[] = $registros_por_pagina;
    $stmt->bind_param($tipos . "ii", ...$params);
} else {
    $stmt->bind_param("ii", $inicio, $registros_por_pagina);
}

$stmt->execute();
$result = $stmt->get_result();

// Obtener grados y turnos para filtros
$grados = [];
$query_grados = "SELECT id_grado, nombre_grado FROM grados ORDER BY id_grado";
$result_grados = $conexion->query($query_grados);
while ($row = $result_grados->fetch_assoc()) {
    $grados[$row['id_grado']] = $row['nombre_grado'];
}

$turnos = [];
$query_turnos = "SELECT id_turno, nombre_turno FROM turnos ORDER BY id_turno";
$result_turnos = $conexion->query($query_turnos);
while ($row = $result_turnos->fetch_assoc()) {
    $turnos[$row['id_turno']] = $row['nombre_turno'];
}

// Obtener ciclos escolares únicos
$ciclos = [];
$query_ciclos = "SELECT DISTINCT ciclo_escolar FROM grupos WHERE activo = 1 ORDER BY ciclo_escolar DESC";
$result_ciclos = $conexion->query($query_ciclos);
while ($row = $result_ciclos->fetch_assoc()) {
    $ciclos[] = $row['ciclo_escolar'];
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-users-class"></i> Grupos</h1>
        </div>
        <div class="col-md-6 text-end">
            <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
            <a href="crear.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Agregar Grupo
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="nombre" class="form-label">Nombre del Grupo</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" 
                           value="<?= htmlspecialchars($filtro_nombre) ?>">
                </div>
                <div class="col-md-3">
                    <label for="grado" class="form-label">Grado</label>
                    <select class="form-select" id="grado" name="grado">
                        <option value="0">Todos</option>
                        <?php foreach ($grados as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_grado == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="turno" class="form-label">Turno</label>
                    <select class="form-select" id="turno" name="turno">
                        <option value="0">Todos</option>
                        <?php foreach ($turnos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_turno == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="ciclo" class="form-label">Ciclo Escolar</label>
                    <select class="form-select" id="ciclo" name="ciclo">
                        <option value="">Todos</option>
                        <?php foreach ($ciclos as $ciclo): ?>
                        <option value="<?= $ciclo ?>" <?= $filtro_ciclo == $ciclo ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ciclo) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-broom"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de grupos -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> Listado de Grupos
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_registros ?> grupos encontrados</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Grupo</th>
                            <th scope="col">Grado</th>
                            <th scope="col">Turno</th>
                            <th scope="col">Ciclo Escolar</th>
                            <th scope="col">Color</th>
                            <th scope="col">Alumnos</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = $inicio + 1;
                        if ($result->num_rows > 0) {
                            while ($grupo = $result->fetch_assoc()) {
                        ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><?= htmlspecialchars($grupo['nombre_grupo']) ?></td>
                            <td><?= htmlspecialchars($grupo['nombre_grado']) ?></td>
                            <td><?= htmlspecialchars($grupo['nombre_turno']) ?></td>
                            <td><?= htmlspecialchars($grupo['ciclo_escolar']) ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="color-sample me-2" style="width: 20px; height: 20px; background-color: <?= htmlspecialchars($grupo['color_credencial']) ?>; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <span><?= htmlspecialchars($grupo['color_credencial']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?= $grupo['total_alumnos'] ?></span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="ver.php?id=<?= $grupo['id_grupo'] ?>" class="btn btn-info" 
                                       title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                                    <a href="editar.php?id=<?= $grupo['id_grupo'] ?>" class="btn btn-warning" 
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                    <button type="button" class="btn btn-danger btn-eliminar" 
                                            data-id="<?= $grupo['id_grupo'] ?>" 
                                            data-nombre="<?= htmlspecialchars($grupo['nombre_grupo'] . ' - ' . 
                                            $grupo['nombre_grado'] . ' - ' . $grupo['nombre_turno']) ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php
                            }
                        } else {
                        ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-info-circle me-2"></i>
                                No se encontraron grupos con los criterios de búsqueda.
                            </td>
                        </tr>
                        <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de grupos">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=1&nombre=<?= urlencode($filtro_nombre) ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&ciclo=<?= urlencode($filtro_ciclo) ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?>&nombre=<?= urlencode($filtro_nombre) ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&ciclo=<?= urlencode($filtro_ciclo) ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $inicio_paginas = max(1, $pagina_actual - 2);
                    $fin_paginas = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): 
                    ?>
                    <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&nombre=<?= urlencode($filtro_nombre) ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&ciclo=<?= urlencode($filtro_ciclo) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?>&nombre=<?= urlencode($filtro_nombre) ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&ciclo=<?= urlencode($filtro_ciclo) ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $total_paginas ?>&nombre=<?= urlencode($filtro_nombre) ?>&grado=<?= $filtro_grado ?>&turno=<?= $filtro_turno ?>&ciclo=<?= urlencode($filtro_ciclo) ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="eliminarModal" tabindex="-1" aria-labelledby="eliminarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="eliminarModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar el grupo <strong id="nombre-grupo"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer y podría afectar a los alumnos asignados a este grupo.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar" action="eliminar.php" method="post">
                    <input type="hidden" name="id_grupo" id="id-grupo">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    // Configurar modal de eliminación
    document.addEventListener('DOMContentLoaded', function() {
        const btnsEliminar = document.querySelectorAll('.btn-eliminar');
        const modalEliminar = document.getElementById('eliminarModal');
        const nombreGrupo = document.getElementById('nombre-grupo');
        const idGrupo = document.getElementById('id-grupo');
        
        btnsEliminar.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const nombre = this.getAttribute('data-nombre');
                
                idGrupo.value = id;
                nombreGrupo.textContent = nombre;
                
                const modal = new bootstrap.Modal(modalEliminar);
                modal.show();
            });
        });
    });
</script>