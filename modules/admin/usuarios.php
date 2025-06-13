<?php
/**
 * Archivo: usuarios.php
 * Ubicación: modules/admin/usuarios.php
 * Propósito: Gestión de usuarios del sistema escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
// Cambiado: config.php por constants.php y database.php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php'; // Añadido en lugar de verificarSesion()

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a este módulo', 'danger');
}

// Inicializar variables
$filtros = [];
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$por_pagina = 20;

// Procesar filtros
if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
    $filtros['busqueda'] = $_GET['busqueda'];
}

if (isset($_GET['tipo_usuario']) && !empty($_GET['tipo_usuario'])) {
    $filtros['tipo_usuario'] = $_GET['tipo_usuario'];
}

if (isset($_GET['activo']) && ($_GET['activo'] == '0' || $_GET['activo'] == '1')) {
    $filtros['activo'] = (int)$_GET['activo'];
}

// Función para obtener usuarios
function obtener_usuarios($filtros = [], $pagina = 1, $por_pagina = 20) {
    global $conexion;
    
    // Construir consulta base
    $sql_base = "FROM usuarios u WHERE 1=1 ";
    $params = [];
    $tipos = "";
    
    // Aplicar filtros
    if (isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
        $busqueda = "%{$filtros['busqueda']}%";
        $sql_base .= "AND (u.nombre_completo LIKE ? OR u.username LIKE ? OR u.email LIKE ?) ";
        $params[] = $busqueda;
        $params[] = $busqueda;
        $params[] = $busqueda;
        $tipos .= "sss";
    }
    
    if (isset($filtros['tipo_usuario']) && !empty($filtros['tipo_usuario'])) {
        $sql_base .= "AND u.tipo_usuario = ? ";
        $params[] = $filtros['tipo_usuario'];
        $tipos .= "s";
    }
    
    if (isset($filtros['activo'])) {
        $sql_base .= "AND u.activo = ? ";
        $params[] = $filtros['activo'];
        $tipos .= "i";
    }
    
    // Contar total de registros
    $sql_count = "SELECT COUNT(*) as total " . $sql_base;
    $stmt = $conexion->prepare($sql_count);
    
    if (!empty($params)) {
        $stmt->bind_param($tipos, ...$params);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    $total = $resultado->fetch_assoc()['total'];
    
    // Calcular paginación
    $total_paginas = ceil($total / $por_pagina);
    $inicio = ($pagina - 1) * $por_pagina;
    
    // Obtener usuarios paginados
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM logs_sistema 
             WHERE tipo = 'acceso' AND usuario = u.username 
             AND fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS logins_ultimo_mes 
           " . $sql_base . " 
           ORDER BY u.nombre_completo ASC 
           LIMIT ?, ?";
    
    $stmt = $conexion->prepare($sql);
    
    if (!empty($params)) {
        $params[] = $inicio;
        $params[] = $por_pagina;
        $tipos .= "ii";
        $stmt->bind_param($tipos, ...$params);
    } else {
        $stmt->bind_param("ii", $inicio, $por_pagina);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuarios = [];
    
    while ($usuario = $resultado->fetch_assoc()) {
        $usuarios[] = $usuario;
    }
    
    return [
        'usuarios' => $usuarios,
        'total' => $total,
        'paginas' => $total_paginas
    ];
}

// Obtener lista de usuarios con filtros
$resultado = obtener_usuarios($filtros, $pagina_actual, $por_pagina);
$usuarios = $resultado['usuarios'];
$total_usuarios = $resultado['total'];
$total_paginas = $resultado['paginas'];

// Incluir encabezado
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <h1 class="mb-4"><i class="fas fa-users mr-2"></i>Gestión de Usuarios</h1>
    
    <?php echo mostrar_mensaje(); ?>
    
    <!-- Filtros -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtros</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="form-inline">
                <div class="form-group mb-2 mr-2">
                    <input type="text" class="form-control" id="busqueda" name="busqueda" placeholder="Buscar..." value="<?php echo htmlspecialchars($filtros['busqueda'] ?? ''); ?>">
                </div>
                
                <div class="form-group mb-2 mr-2">
                    <select class="form-control" id="tipo_usuario" name="tipo_usuario">
                        <option value="">Todos los roles</option>
                        <option value="superadmin" <?php echo (isset($filtros['tipo_usuario']) && $filtros['tipo_usuario'] == 'superadmin') ? 'selected' : ''; ?>>Superadmin</option>
                        <option value="organizador" <?php echo (isset($filtros['tipo_usuario']) && $filtros['tipo_usuario'] == 'organizador') ? 'selected' : ''; ?>>Organizador</option>
                        <option value="consulta" <?php echo (isset($filtros['tipo_usuario']) && $filtros['tipo_usuario'] == 'consulta') ? 'selected' : ''; ?>>Consulta</option>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-2">
                    <select class="form-control" id="activo" name="activo">
                        <option value="">Todos los estados</option>
                        <option value="1" <?php echo (isset($filtros['activo']) && $filtros['activo'] == 1) ? 'selected' : ''; ?>>Activos</option>
                        <option value="0" <?php echo (isset($filtros['activo']) && $filtros['activo'] == 0) ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary mb-2">Filtrar</button>
                <a href="usuarios.php" class="btn btn-secondary mb-2 ml-2">Limpiar</a>
            </form>
        </div>
    </div>
    
    <!-- Botón de agregar -->
    <div class="mb-3">
        <a href="crear_usuario.php" class="btn btn-success">
            <i class="fas fa-user-plus mr-1"></i> Nuevo Usuario
        </a>
    </div>
    
    <!-- Tabla de usuarios -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Usuarios del Sistema</h6>
            <span class="badge badge-info"><?php echo $total_usuarios; ?> usuarios</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="tablaUsuarios" width="100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Último Acceso</th>
                            <th>Actividad</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron usuarios</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo ($usuario['tipo_usuario'] == 'superadmin') ? 'bg-danger' : 
                                            (($usuario['tipo_usuario'] == 'organizador') ? 'bg-primary' : 'bg-secondary'); 
                                    ?>">
                                        <?php echo htmlspecialchars($usuario['tipo_usuario']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($usuario['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    echo ($usuario['ultimo_acceso']) 
                                        ? date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])) 
                                        : 'Nunca'; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo $usuario['logins_ultimo_mes']; ?> accesos
                                    </span>
                                </td>
                                <td>
                                    <a href="editar_usuario.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($usuario['id_usuario'] != $_SESSION['id_usuario']): ?>
                                        <?php if ($usuario['activo']): ?>
                                            <button type="button" class="btn btn-sm btn-warning cambiar-estado" 
                                                    data-id="<?php echo $usuario['id_usuario']; ?>" 
                                                    data-estado="0" 
                                                    data-nombre="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>"
                                                    title="Desactivar">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-success cambiar-estado" 
                                                    data-id="<?php echo $usuario['id_usuario']; ?>" 
                                                    data-estado="1"
                                                    data-nombre="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>"
                                                    title="Activar">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
            <?php if ($total_paginas > 1): ?>
            <nav aria-label="Paginación de usuarios">
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
</div>

<!-- Modal para confirmar cambio de estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" role="dialog" aria-labelledby="modalCambiarEstadoLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCambiarEstadoLabel">Confirmar Acción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalTexto">¿Estás seguro que deseas cambiar el estado de este usuario?</p>
            </div>
            <div class="modal-footer">
                <form id="formCambiarEstado" method="POST" action="procesar_usuario.php">
                    <input type="hidden" id="usuario_id" name="usuario_id" value="">
                    <input type="hidden" id="nuevo_estado" name="nuevo_estado" value="">
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar botones de cambio de estado
    const botonesEstado = document.querySelectorAll('.cambiar-estado');
    
    botonesEstado.forEach(function(boton) {
        boton.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const estado = this.getAttribute('data-estado');
            const nombre = this.getAttribute('data-nombre');
            
            document.getElementById('usuario_id').value = id;
            document.getElementById('nuevo_estado').value = estado;
            
            if (estado == 1) {
                document.getElementById('modalTexto').textContent = '¿Estás seguro que deseas activar al usuario "' + nombre + '"?';
            } else {
                document.getElementById('modalTexto').textContent = '¿Estás seguro que deseas desactivar al usuario "' + nombre + '"?';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
            modal.show();
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>