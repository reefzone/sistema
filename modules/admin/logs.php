<?php
/**
 * Archivo: logs.php
 * Ubicación: modules/admin/logs.php
 * Propósito: Visualización de registros y logs del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a este módulo', 'danger');
}

// Inicializar variables para filtros
$filtros = [];
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 50;
$where = ["1=1"];
$params = [];
$tipos = "";

// Procesar filtros
if (isset($_GET['tipo']) && !empty($_GET['tipo'])) {
    $where[] = "tipo = ?";
    $params[] = $_GET['tipo'];
    $tipos .= "s";
    $filtros['tipo'] = $_GET['tipo'];
}

if (isset($_GET['fecha_desde']) && !empty($_GET['fecha_desde'])) {
    $where[] = "fecha >= ?";
    $params[] = $_GET['fecha_desde'] . ' 00:00:00';
    $tipos .= "s";
    $filtros['fecha_desde'] = $_GET['fecha_desde'];
}

if (isset($_GET['fecha_hasta']) && !empty($_GET['fecha_hasta'])) {
    $where[] = "fecha <= ?";
    $params[] = $_GET['fecha_hasta'] . ' 23:59:59';
    $tipos .= "s";
    $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
}

if (isset($_GET['usuario']) && !empty($_GET['usuario'])) {
    $where[] = "usuario LIKE ?";
    $params[] = '%' . $_GET['usuario'] . '%';
    $tipos .= "s";
    $filtros['usuario'] = $_GET['usuario'];
}

if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $where[] = "(descripcion LIKE ? OR ip LIKE ? OR usuario LIKE ?)";
    $busqueda = '%' . $_GET['busqueda'] . '%';
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
    $tipos .= "sss";
    $filtros['busqueda'] = $_GET['busqueda'];
}

// Construir consulta
$where_str = implode(" AND ", $where);

// Contar total de registros
$query_count = "SELECT COUNT(*) as total FROM logs_sistema WHERE $where_str";
$stmt_count = $conexion->prepare($query_count);

if (!empty($params)) {
    $stmt_count->bind_param($tipos, ...$params);
}

$stmt_count->execute();
$total = $stmt_count->get_result()->fetch_assoc()['total'];

// Calcular paginación
$total_paginas = ceil($total / $por_pagina);
$inicio = ($pagina_actual - 1) * $por_pagina;

// Obtener logs paginados
$query = "SELECT * FROM logs_sistema 
         WHERE $where_str 
         ORDER BY fecha DESC 
         LIMIT ?, ?";

if (!empty($params)) {
    $params[] = $inicio;
    $params[] = $por_pagina;
    $tipos .= "ii";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param($tipos, ...$params);
} else {
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ii", $inicio, $por_pagina);
}

$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Incluir encabezado
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-list me-2"></i>Registros del Sistema</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 me-1"></i> Volver al Panel
        </a>
    </div>
    
    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="form">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo de Registro:</label>
                            <select class="form-select" id="tipo" name="tipo">
                                <option value="">Todos los tipos</option>
                                <option value="acceso" <?php echo (isset($filtros['tipo']) && $filtros['tipo'] == 'acceso') ? 'selected' : ''; ?>>Acceso</option>
                                <option value="operacion" <?php echo (isset($filtros['tipo']) && $filtros['tipo'] == 'operacion') ? 'selected' : ''; ?>>Operación</option>
                                <option value="error" <?php echo (isset($filtros['tipo']) && $filtros['tipo'] == 'error') ? 'selected' : ''; ?>>Error</option>
                                <option value="seguridad" <?php echo (isset($filtros['tipo']) && $filtros['tipo'] == 'seguridad') ? 'selected' : ''; ?>>Seguridad</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="fecha_desde" class="form-label">Fecha Desde:</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo $filtros['fecha_desde'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="fecha_hasta" class="form-label">Fecha Hasta:</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo $filtros['fecha_hasta'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="usuario" class="form-label">Usuario:</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Nombre de usuario" 
                                   value="<?php echo htmlspecialchars($filtros['usuario'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="busqueda" class="form-label">Búsqueda en Descripción/IP:</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Texto a buscar" 
                                   value="<?php echo htmlspecialchars($filtros['busqueda'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Filtrar
                    </button>
                    <a href="logs.php" class="btn btn-secondary ms-2">
                        <i class="fas fa-sync-alt me-1"></i> Limpiar Filtros
                    </a>
                    <button type="submit" class="btn btn-success ms-2" name="exportar" value="1">
                        <i class="fas fa-file-excel me-1"></i> Exportar a Excel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Tabla de logs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 fw-bold text-primary">Registros</h6>
            <span class="badge bg-info"><?php echo $total; ?> registros encontrados</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tablaLogs" width="100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Tipo</th>
                            <th>Usuario</th>
                            <th>Descripción</th>
                            <th>IP</th>
                            <th>Módulo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No se encontraron registros</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo ($log['tipo'] == 'acceso') ? 'primary' : 
                                            (($log['tipo'] == 'operacion') ? 'success' : 
                                                (($log['tipo'] == 'error') ? 'danger' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst($log['tipo']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['usuario']); ?></td>
                                <td><?php echo htmlspecialchars($log['descripcion']); ?></td>
                                <td><?php echo htmlspecialchars($log['ip']); ?></td>
                                <td><?php echo isset($log['modulo']) ? htmlspecialchars($log['modulo']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de logs">
                <ul class="pagination justify-content-center mt-4">
                    <?php if ($pagina_actual > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=1<?php echo !empty($filtros) ? '&' . http_build_query($filtros) : ''; ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo !empty($filtros) ? '&' . http_build_query($filtros) : ''; ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $inicio = max(1, $pagina_actual - 2);
                    $fin = min($total_paginas, $pagina_actual + 2);
                    
                    for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                        <a class="page-link" href="?pagina=<?php echo $i; ?><?php echo !empty($filtros) ? '&' . http_build_query($filtros) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_actual < $total_paginas): ?>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo !empty($filtros) ? '&' . http_build_query($filtros) : ''; ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($filtros) ? '&' . http_build_query($filtros) : ''; ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estadísticas -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary">Estadísticas de Actividad</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php
                // Obtener estadísticas por tipo
                $query_stats = "SELECT tipo, COUNT(*) as total FROM logs_sistema GROUP BY tipo";
                $stats_tipo = $conexion->query($query_stats)->fetch_all(MYSQLI_ASSOC);
                
                // Obtener estadísticas de los últimos días
                $query_dias = "SELECT DATE(fecha) as dia, COUNT(*) as total FROM logs_sistema 
                             WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                             GROUP BY DATE(fecha) 
                             ORDER BY dia DESC";
                $stats_dias = $conexion->query($query_dias)->fetch_all(MYSQLI_ASSOC);
                
                // Obtener usuarios más activos
                $query_usuarios = "SELECT usuario, COUNT(*) as total FROM logs_sistema 
                                 GROUP BY usuario 
                                 ORDER BY total DESC 
                                 LIMIT 5";
                $stats_usuarios = $conexion->query($query_usuarios)->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <!-- Estadísticas por tipo -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Por Tipo</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats_tipo as $stat): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo ($stat['tipo'] == 'acceso') ? 'primary' : 
                                                        (($stat['tipo'] == 'operacion') ? 'success' : 
                                                            (($stat['tipo'] == 'error') ? 'danger' : 'warning')); 
                                                ?>">
                                                    <?php echo ucfirst($stat['tipo']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $stat['total']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estadísticas por día -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Últimos 7 Días</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Registros</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats_dias as $stat): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($stat['dia'])); ?></td>
                                            <td><?php echo $stat['total']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usuarios más activos -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Usuarios Más Activos</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Actividades</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats_usuarios as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['usuario']); ?></td>
                                            <td><?php echo $stat['total']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad para exportar a Excel
    document.querySelector('form').addEventListener('submit', function(e) {
        if (document.querySelector('button[name="exportar"]:focus')) {
            e.preventDefault();
            
            // Obtener parámetros de filtro
            var params = new URLSearchParams(new FormData(this)).toString();
            
            // Redirigir a la página de exportación
            window.location.href = 'exportar_logs.php?' + params;
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>