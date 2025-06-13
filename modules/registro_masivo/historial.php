<?php
/**
 * Historial de importaciones
 * Módulo de Registro Masivo - Sistema Escolar ESCUELA SECUNDARIA TECNICA #82
 * Ubicación: modules/registro_masivo/historial.php
 */

// Incluir archivos requeridos
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador)
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a este módulo', 'danger');
}

// Función para formatear fecha
function formatearFecha($fecha) {
    return date('d/m/Y H:i', strtotime($fecha));
}

// Parámetros de paginación y filtrado
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$limite = 20;
$offset = ($pagina - 1) * $limite;

// Filtros
$filtro_usuario = isset($_GET['usuario']) ? intval($_GET['usuario']) : 0;
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Construir consulta SQL con filtros
$sql_filtros = "WHERE i.tipo = 'alumnos'";
$params = [];
$tipos = "";

if ($filtro_usuario > 0) {
    $sql_filtros .= " AND i.importado_por = ?";
    $params[] = $filtro_usuario;
    $tipos .= "i";
}

if (!empty($filtro_fecha_desde)) {
    $sql_filtros .= " AND i.fecha_importacion >= ?";
    $params[] = $filtro_fecha_desde . " 00:00:00";
    $tipos .= "s";
}

if (!empty($filtro_fecha_hasta)) {
    $sql_filtros .= " AND i.fecha_importacion <= ?";
    $params[] = $filtro_fecha_hasta . " 23:59:59";
    $tipos .= "s";
}

// Consulta para obtener el total de registros con filtros
$sql_total = "SELECT COUNT(*) as total FROM importaciones i $sql_filtros";
$stmt_total = $conexion->prepare($sql_total);

if (!empty($params)) {
    $stmt_total->bind_param($tipos, ...$params);
}

$stmt_total->execute();
$resultado_total = $stmt_total->get_result();
$fila_total = $resultado_total->fetch_assoc();
$total_registros = $fila_total['total'];
$total_paginas = ceil($total_registros / $limite);

// Consulta para obtener los registros con filtros y paginación
$sql = "SELECT i.*, u.nombre_completo as nombre_usuario 
       FROM importaciones i
       JOIN usuarios u ON i.importado_por = u.id_usuario
       $sql_filtros
       ORDER BY i.fecha_importacion DESC
       LIMIT ?, ?";

$stmt = $conexion->prepare($sql);
$params[] = $offset;
$params[] = $limite;
$tipos .= "ii";
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$importaciones = $stmt->get_result();

// Consulta para obtener la lista de usuarios para el filtro
$usuarios = $conexion->query("
    SELECT DISTINCT u.id_usuario, u.nombre_completo as nombre_usuario 
    FROM importaciones i 
    JOIN usuarios u ON i.importado_por = u.id_usuario 
    WHERE i.tipo = 'alumnos'
    ORDER BY u.nombre_completo
");

// Incluir header
include '../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>
            <i class="fas fa-history"></i> Historial de Importaciones
        </h1>
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-file-import"></i> Nueva Importación
        </a>
    </div>
    
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="get" action="historial.php" class="row g-3">
                <div class="col-md-4">
                    <label for="usuario" class="form-label">Usuario:</label>
                    <select name="usuario" id="usuario" class="form-select">
                        <option value="0">Todos los usuarios</option>
                        <?php if ($usuarios): while ($user = $usuarios->fetch_assoc()): ?>
                            <option value="<?php echo $user['id_usuario']; ?>" 
                                <?php echo ($filtro_usuario == $user['id_usuario']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nombre_usuario']); ?>
                            </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="fecha_desde" class="form-label">Fecha desde:</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                        value="<?php echo $filtro_fecha_desde; ?>">
                </div>
                <div class="col-md-4">
                    <label for="fecha_hasta" class="form-label">Fecha hasta:</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                        value="<?php echo $filtro_fecha_hasta; ?>">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <a href="historial.php" class="btn btn-secondary me-2">
                        <i class="fas fa-eraser"></i> Limpiar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($total_registros > 0): ?>
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-table"></i> Listado de Importaciones</h5>
                <span class="badge bg-light text-dark">
                    Total: <?php echo $total_registros; ?> importaciones
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Archivo</th>
                                <th>Registros</th>
                                <th>Resultado</th>
                                <th>Usuario</th>
                                <th>Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($imp = $importaciones->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo formatearFecha($imp['fecha_importacion']); ?></td>
                                    <td><?php echo htmlspecialchars($imp['nombre_archivo']); ?></td>
                                    <td><?php echo $imp['total_registros']; ?></td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="badge bg-success mb-1">
                                                <?php echo $imp['registros_exitosos']; ?> exitosos
                                            </span>
                                            
                                            <?php if ($imp['registros_error'] > 0): ?>
                                                <span class="badge bg-danger mb-1">
                                                    <?php echo $imp['registros_error']; ?> errores
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($imp['registros_omitidos'] > 0): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <?php echo $imp['registros_omitidos']; ?> omitidos
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($imp['nombre_usuario']); ?></td>
                                    <td>
                                        <?php 
                                        if (!empty($imp['notas'])) {
                                            if (strlen($imp['notas']) > 30) {
                                                echo '<span data-bs-toggle="tooltip" title="' . 
                                                    htmlspecialchars($imp['notas']) . '">' . 
                                                    htmlspecialchars(substr($imp['notas'], 0, 30)) . '...</span>';
                                            } else {
                                                echo htmlspecialchars($imp['notas']);
                                            }
                                        } else {
                                            echo '<span class="text-muted">Sin notas</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Verificar si el archivo original existe
                                        $archivo_existe = !empty($imp['ruta_original']) && file_exists($imp['ruta_original']);
                                        ?>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                                Acciones
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($archivo_existe): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="descargar.php?tipo=original&id=<?php echo $imp['id_importacion']; ?>" target="_blank">
                                                            <i class="fas fa-download"></i> Descargar Original
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Verificar si existe un log de esta importación
                                                $ruta_procesado = $imp['ruta_procesado'];
                                                if (!empty($ruta_procesado) && file_exists($ruta_procesado)) {
                                                    $posible_log = str_replace(
                                                        ['/procesados/', '_procesado_'], 
                                                        ['/logs/', '_log_'], 
                                                        $ruta_procesado
                                                    );
                                                    $posible_log = substr($posible_log, 0, strrpos($posible_log, '.')) . '.json';
                                                    
                                                    if (file_exists($posible_log)):
                                                ?>
                                                    <li>
                                                        <a class="dropdown-item" href="descargar.php?tipo=log&id=<?php echo $imp['id_importacion']; ?>" target="_blank">
                                                            <i class="fas fa-file-code"></i> Descargar Log
                                                        </a>
                                                    </li>
                                                <?php 
                                                    endif;
                                                }
                                                ?>
                                                
                                                <li>
                                                    <a class="dropdown-item" href="../alumnos/listado.php?id_importacion=<?php echo $imp['id_importacion']; ?>">
                                                        <i class="fas fa-users"></i> Ver Alumnos Importados
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_paginas > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Navegación de páginas">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($pagina > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=1<?php echo $filtro_usuario ? '&usuario=' . $filtro_usuario : ''; ?><?php echo $filtro_fecha_desde ? '&fecha_desde=' . $filtro_fecha_desde : ''; ?><?php echo $filtro_fecha_hasta ? '&fecha_hasta=' . $filtro_fecha_hasta : ''; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $pagina - 1; ?><?php echo $filtro_usuario ? '&usuario=' . $filtro_usuario : ''; ?><?php echo $filtro_fecha_desde ? '&fecha_desde=' . $filtro_fecha_desde : ''; ?><?php echo $filtro_fecha_hasta ? '&fecha_hasta=' . $filtro_fecha_hasta : ''; ?>">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            // Mostrar 5 páginas centradas alrededor de la página actual
                            $inicio = max(1, $pagina - 2);
                            $fin = min($total_paginas, $pagina + 2);
                            
                            for ($i = $inicio; $i <= $fin; $i++):
                            ?>
                                <li class="page-item <?php echo ($i == $pagina) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo $filtro_usuario ? '&usuario=' . $filtro_usuario : ''; ?><?php echo $filtro_fecha_desde ? '&fecha_desde=' . $filtro_fecha_desde : ''; ?><?php echo $filtro_fecha_hasta ? '&fecha_hasta=' . $filtro_fecha_hasta : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($pagina < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $pagina + 1; ?><?php echo $filtro_usuario ? '&usuario=' . $filtro_usuario : ''; ?><?php echo $filtro_fecha_desde ? '&fecha_desde=' . $filtro_fecha_desde : ''; ?><?php echo $filtro_fecha_hasta ? '&fecha_hasta=' . $filtro_fecha_hasta : ''; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo $filtro_usuario ? '&usuario=' . $filtro_usuario : ''; ?><?php echo $filtro_fecha_desde ? '&fecha_desde=' . $filtro_fecha_desde : ''; ?><?php echo $filtro_fecha_hasta ? '&fecha_hasta=' . $filtro_fecha_hasta : ''; ?>">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No se encontraron importaciones con los filtros seleccionados.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>