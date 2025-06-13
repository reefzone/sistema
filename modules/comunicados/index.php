<?php
/**
 * Listado de Comunicados Enviados
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';
require_once '../../includes/mail_functions.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Parámetros de paginación y filtrado
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 15;
$inicio = ($pagina - 1) * $registros_por_pagina;

// Filtros
$filtro_estado = isset($_GET['estado']) ? sanitizar_texto($_GET['estado']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitizar_texto($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitizar_texto($_GET['fecha_hasta']) : '';
$filtro_grupo = isset($_GET['id_grupo']) ? intval($_GET['id_grupo']) : 0;
$filtro_prioridad = isset($_GET['prioridad']) ? sanitizar_texto($_GET['prioridad']) : '';
$busqueda = isset($_GET['busqueda']) ? sanitizar_texto($_GET['busqueda']) : '';

// Construir consulta base
$query_base = "FROM comunicados c 
               LEFT JOIN grupos g ON c.id_grupo = g.id_grupo 
               LEFT JOIN usuarios u ON c.enviado_por = u.id_usuario
               WHERE c.eliminado = 0";

// Aplicar filtros
$params = [];
$tipos = '';

if (!empty($filtro_estado)) {
    $query_base .= " AND c.estado = ?";
    $params[] = $filtro_estado;
    $tipos .= 's';
}

if (!empty($filtro_fecha_desde)) {
    $query_base .= " AND DATE(c.fecha_envio) >= ?";
    $params[] = $filtro_fecha_desde;
    $tipos .= 's';
}

if (!empty($filtro_fecha_hasta)) {
    $query_base .= " AND DATE(c.fecha_envio) <= ?";
    $params[] = $filtro_fecha_hasta;
    $tipos .= 's';
}

if ($filtro_grupo > 0) {
    $query_base .= " AND c.id_grupo = ?";
    $params[] = $filtro_grupo;
    $tipos .= 'i';
}

if (!empty($filtro_prioridad) && property_exists('comunicados', 'prioridad')) {
    $query_base .= " AND c.prioridad = ?";
    $params[] = $filtro_prioridad;
    $tipos .= 's';
}

if (!empty($busqueda)) {
    $busqueda_param = "%$busqueda%";
    $query_base .= " AND (c.titulo LIKE ? OR c.contenido LIKE ?)";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $tipos .= 'ss';
}

// Si no es superadmin, solo puede ver sus propios comunicados
if ($_SESSION['tipo_usuario'] != 'superadmin') {
    $query_base .= " AND c.enviado_por = ?";
    $params[] = $_SESSION['id_usuario'];
    $tipos .= 'i';
}

// Obtener total de registros (para paginación)
$query_count = "SELECT COUNT(*) as total " . $query_base;
$stmt_count = $conexion->prepare($query_count);
if (!empty($params)) {
    $stmt_count->bind_param($tipos, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$row_count = $result_count->fetch_assoc();
$total_registros = $row_count['total'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

// Verificar si existe la columna 'estado' en la tabla comunicados
$check_estado_column = "SHOW COLUMNS FROM comunicados LIKE 'estado'";
$result_estado_column = $conexion->query($check_estado_column);
$has_estado_column = $result_estado_column->num_rows > 0;

// Verificar si existe la columna 'prioridad' en la tabla comunicados
$check_prioridad_column = "SHOW COLUMNS FROM comunicados LIKE 'prioridad'";
$result_prioridad_column = $conexion->query($check_prioridad_column);
$has_prioridad_column = $result_prioridad_column->num_rows > 0;

// Verificar si existe la columna 'grupo_especifico' en la tabla comunicados
$check_grupo_especifico_column = "SHOW COLUMNS FROM comunicados LIKE 'grupo_especifico'";
$result_grupo_especifico_column = $conexion->query($check_grupo_especifico_column);
$has_grupo_especifico_column = $result_grupo_especifico_column->num_rows > 0;

// Verificar si existe la columna 'tiene_adjuntos' en la tabla comunicados
$check_tiene_adjuntos_column = "SHOW COLUMNS FROM comunicados LIKE 'tiene_adjuntos'";
$result_tiene_adjuntos_column = $conexion->query($check_tiene_adjuntos_column);
$has_tiene_adjuntos_column = $result_tiene_adjuntos_column->num_rows > 0;

// Obtener comunicados - Modificamos la consulta para adaptarla a la estructura actual
$query = "SELECT c.id_comunicado, c.titulo, c.fecha_envio, 
          " . ($has_estado_column ? "c.estado" : "'enviado' as estado") . ", 
          " . ($has_prioridad_column ? "c.prioridad" : "'normal' as prioridad") . ", 
          " . ($has_tiene_adjuntos_column ? "c.tiene_adjuntos" : "(c.archivo_adjunto IS NOT NULL) as tiene_adjuntos") . ", 
          " . ($has_grupo_especifico_column ? "c.grupo_especifico" : "0 as grupo_especifico") . ",
          u.nombre_completo as enviado_por_nombre,
          CASE 
            WHEN c.id_grupo IS NULL THEN 'Todos los grupos'
            ELSE CONCAT(g.nombre_grupo, ' (', 
                (SELECT COUNT(*) FROM alumnos WHERE id_grupo = g.id_grupo AND activo = 1), 
                ' alumnos)')
          END as destinatarios,
          0 as total_destinatarios,
          0 as total_leidos,
          0 as total_enviados,
          0 as total_errores
          " . $query_base . "
          ORDER BY c.fecha_envio DESC
          LIMIT ?, ?";

$params[] = $inicio;
$params[] = $registros_por_pagina;
$tipos .= 'ii';

$stmt = $conexion->prepare($query);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Obtener grupos para filtro
$query_grupos = "SELECT g.id_grupo, CONCAT(g.nombre_grupo, ' - ', gr.nombre_grado, ' - ', t.nombre_turno) as nombre_completo 
                FROM grupos g 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE g.activo = 1
                ORDER BY t.id_turno, gr.id_grado, g.nombre_grupo";
$result_grupos = $conexion->query($query_grupos);
$grupos = [];
while ($row = $result_grupos->fetch_assoc()) {
    $grupos[$row['id_grupo']] = $row['nombre_completo'];
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-envelope"></i> Comunicados</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="crear.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Nuevo Comunicado
            </a>
            <a href="plantillas.php" class="btn btn-info">
                <i class="fas fa-file-alt"></i> Plantillas
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <?php if ($has_estado_column): ?>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos</option>
                        <option value="borrador" <?= $filtro_estado == 'borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="enviado" <?= $filtro_estado == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                        <option value="programado" <?= $filtro_estado == 'programado' ? 'selected' : '' ?>>Programado</option>
                        <option value="cancelado" <?= $filtro_estado == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="fecha_desde" class="form-label">Fecha desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= $filtro_fecha_desde ?>">
                </div>
                <div class="col-md-3">
                    <label for="fecha_hasta" class="form-label">Fecha hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?= $filtro_fecha_hasta ?>">
                </div>
                <div class="col-md-3">
                    <label for="id_grupo" class="form-label">Grupo</label>
                    <select class="form-select" id="id_grupo" name="id_grupo">
                        <option value="0">Todos los grupos</option>
                        <?php foreach ($grupos as $id => $nombre): ?>
                        <option value="<?= $id ?>" <?= $filtro_grupo == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($nombre) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($has_prioridad_column): ?>
                <div class="col-md-3">
                    <label for="prioridad" class="form-label">Prioridad</label>
                    <select class="form-select" id="prioridad" name="prioridad">
                        <option value="">Todas</option>
                        <option value="baja" <?= $filtro_prioridad == 'baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="normal" <?= $filtro_prioridad == 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="alta" <?= $filtro_prioridad == 'alta' ? 'selected' : '' ?>>Alta</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label for="busqueda" class="form-label">Búsqueda por título o contenido</label>
                    <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Escriba para buscar...">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de comunicados -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> Listado de Comunicados
                    </h5>
                </div>
                <div class="col-auto">
                    <span class="badge bg-primary"><?= $total_registros ?> comunicados</span>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-primary">
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Destinatarios</th>
                            <th>Enviado</th>
                            <th>Estado</th>
                            <?php if ($has_prioridad_column): ?>
                            <th>Prioridad</th>
                            <?php endif; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($comunicado = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $comunicado['id_comunicado'] ?></td>
                            <td>
                                <?= htmlspecialchars($comunicado['titulo']) ?>
                                <?php if ($comunicado['tiene_adjuntos']): ?>
                                <i class="fas fa-paperclip text-muted" title="Tiene archivos adjuntos"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($comunicado['destinatarios']) ?></td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($comunicado['fecha_envio'])) ?>
                            </td>
                            <td>
                                <?php
                                $estado_clase = '';
                                switch ($comunicado['estado']) {
                                    case 'borrador': $estado_clase = 'bg-secondary'; break;
                                    case 'enviado': $estado_clase = 'bg-success'; break;
                                    case 'programado': $estado_clase = 'bg-info'; break;
                                    case 'cancelado': $estado_clase = 'bg-danger'; break;
                                }
                                ?>
                                <span class="badge <?= $estado_clase ?>">
                                    <?= ucfirst($comunicado['estado']) ?>
                                </span>
                            </td>
                            <?php if ($has_prioridad_column): ?>
                            <td>
                                <?php
                                $prioridad_clase = '';
                                switch ($comunicado['prioridad']) {
                                    case 'baja': $prioridad_clase = 'bg-success'; break;
                                    case 'normal': $prioridad_clase = 'bg-primary'; break;
                                    case 'alta': $prioridad_clase = 'bg-danger'; break;
                                }
                                ?>
                                <span class="badge <?= $prioridad_clase ?>">
                                    <?= ucfirst($comunicado['prioridad']) ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td>
                                <div class="btn-group">
                                    <a href="ver.php?id=<?= $comunicado['id_comunicado'] ?>" class="btn btn-sm btn-info" title="Ver detalle">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($comunicado['estado'] == 'borrador'): ?>
                                    <a href="crear.php?id=<?= $comunicado['id_comunicado'] ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($comunicado['estado'] == 'enviado'): ?>
                                    <a href="enviar.php?id=<?= $comunicado['id_comunicado'] ?>" class="btn btn-sm btn-warning" title="Reenviar">
                                        <i class="fas fa-share"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($_SESSION['tipo_usuario'] == 'superadmin'): ?>
                                    <button type="button" class="btn btn-sm btn-danger btn-eliminar" 
                                            data-id="<?= $comunicado['id_comunicado'] ?>" 
                                            data-titulo="<?= htmlspecialchars($comunicado['titulo']) ?>"
                                            title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de comunicados">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=1<?= 
                            (!empty($filtro_estado) ? '&estado='.$filtro_estado : '') . 
                            (!empty($filtro_fecha_desde) ? '&fecha_desde='.$filtro_fecha_desde : '') .
                            (!empty($filtro_fecha_hasta) ? '&fecha_hasta='.$filtro_fecha_hasta : '') .
                            ($filtro_grupo > 0 ? '&id_grupo='.$filtro_grupo : '') .
                            (!empty($filtro_prioridad) ? '&prioridad='.$filtro_prioridad : '') .
                            (!empty($busqueda) ? '&busqueda='.urlencode($busqueda) : '')
                        ?>">Primera</a>
                    </li>
                    <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= ($pagina - 1) ?><?= 
                            (!empty($filtro_estado) ? '&estado='.$filtro_estado : '') . 
                            (!empty($filtro_fecha_desde) ? '&fecha_desde='.$filtro_fecha_desde : '') .
                            (!empty($filtro_fecha_hasta) ? '&fecha_hasta='.$filtro_fecha_hasta : '') .
                            ($filtro_grupo > 0 ? '&id_grupo='.$filtro_grupo : '') .
                            (!empty($filtro_prioridad) ? '&prioridad='.$filtro_prioridad : '') .
                            (!empty($busqueda) ? '&busqueda='.urlencode($busqueda) : '')
                        ?>">Anterior</a>
                    </li>
                    
                    <?php 
                    $inicio_paginacion = max(1, $pagina - 2);
                    $fin_paginacion = min($total_paginas, $pagina + 2);
                    
                    for ($i = $inicio_paginacion; $i <= $fin_paginacion; $i++): 
                    ?>
                    <li class="page-item <?= ($pagina == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?><?= 
                            (!empty($filtro_estado) ? '&estado='.$filtro_estado : '') . 
                            (!empty($filtro_fecha_desde) ? '&fecha_desde='.$filtro_fecha_desde : '') .
                            (!empty($filtro_fecha_hasta) ? '&fecha_hasta='.$filtro_fecha_hasta : '') .
                            ($filtro_grupo > 0 ? '&id_grupo='.$filtro_grupo : '') .
                            (!empty($filtro_prioridad) ? '&prioridad='.$filtro_prioridad : '') .
                            (!empty($busqueda) ? '&busqueda='.urlencode($busqueda) : '')
                        ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= ($pagina + 1) ?><?= 
                            (!empty($filtro_estado) ? '&estado='.$filtro_estado : '') . 
                            (!empty($filtro_fecha_desde) ? '&fecha_desde='.$filtro_fecha_desde : '') .
                            (!empty($filtro_fecha_hasta) ? '&fecha_hasta='.$filtro_fecha_hasta : '') .
                            ($filtro_grupo > 0 ? '&id_grupo='.$filtro_grupo : '') .
                            (!empty($filtro_prioridad) ? '&prioridad='.$filtro_prioridad : '') .
                            (!empty($busqueda) ? '&busqueda='.urlencode($busqueda) : '')
                        ?>">Siguiente</a>
                    </li>
                    <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $total_paginas ?><?= 
                            (!empty($filtro_estado) ? '&estado='.$filtro_estado : '') . 
                            (!empty($filtro_fecha_desde) ? '&fecha_desde='.$filtro_fecha_desde : '') .
                            (!empty($filtro_fecha_hasta) ? '&fecha_hasta='.$filtro_fecha_hasta : '') .
                            ($filtro_grupo > 0 ? '&id_grupo='.$filtro_grupo : '') .
                            (!empty($filtro_prioridad) ? '&prioridad='.$filtro_prioridad : '') .
                            (!empty($busqueda) ? '&busqueda='.urlencode($busqueda) : '')
                        ?>">Última</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i> No se encontraron comunicados con los filtros seleccionados.
            </div>
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
                <p>¿Está seguro que desea eliminar el comunicado <strong id="titulo-comunicado"></strong>?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar" action="eliminar.php" method="post">
                    <input type="hidden" name="id_comunicado" id="id-comunicado">
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
document.addEventListener('DOMContentLoaded', function() {
    // Configurar modal de eliminación
    const btnsEliminar = document.querySelectorAll('.btn-eliminar');
    const modalEliminar = document.getElementById('eliminarModal');
    const idComunicado = document.getElementById('id-comunicado');
    const tituloComunicado = document.getElementById('titulo-comunicado');
    
    btnsEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const titulo = this.getAttribute('data-titulo');
            
            idComunicado.value = id;
            tituloComunicado.textContent = titulo;
            
            const modal = new bootstrap.Modal(modalEliminar);
            modal.show();
        });
    });
});
</script>