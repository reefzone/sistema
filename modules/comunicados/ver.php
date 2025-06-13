<?php
/**
 * Ver Detalle de Comunicado
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

// Obtener ID del comunicado
$id_comunicado = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_comunicado <= 0) {
    redireccionar_con_mensaje('index.php', 'Comunicado no válido', 'danger');
}

// Obtener datos del comunicado
$query = "SELECT c.*, 
          CONCAT(u.nombre, ' ', u.apellido_paterno) as enviado_por_nombre,
          CASE 
            WHEN c.id_grupo IS NULL THEN 'Todos los grupos'
            WHEN c.grupo_especifico = 1 THEN 'Alumnos específicos'
            ELSE CONCAT(g.nombre_grupo, ' (', gr.nombre_grado, ' - ', t.nombre_turno, ')')
          END as destinatarios_desc,
          (SELECT COUNT(*) FROM comunicados_destinatarios WHERE id_comunicado = c.id_comunicado) as total_destinatarios,
          (SELECT COUNT(*) FROM comunicados_destinatarios WHERE id_comunicado = c.id_comunicado AND estado = 'leido') as total_leidos,
          (SELECT COUNT(*) FROM comunicados_destinatarios WHERE id_comunicado = c.id_comunicado AND estado = 'enviado') as total_enviados,
          (SELECT COUNT(*) FROM comunicados_destinatarios WHERE id_comunicado = c.id_comunicado AND estado = 'error') as total_errores,
          p.nombre as nombre_plantilla
          FROM comunicados c
          LEFT JOIN usuarios u ON c.enviado_por = u.id_usuario
          LEFT JOIN grupos g ON c.id_grupo = g.id_grupo
          LEFT JOIN grados gr ON g.id_grado = gr.id_grado
          LEFT JOIN turnos t ON g.id_turno = t.id_turno
          LEFT JOIN comunicados_plantillas p ON c.id_plantilla = p.id_plantilla
          WHERE c.id_comunicado = ? AND c.eliminado = 0";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_comunicado);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El comunicado no existe o ha sido eliminado', 'danger');
}

$comunicado = $result->fetch_assoc();

// Verificar permisos (solo el creador o superadmin pueden ver los detalles)
if ($_SESSION['tipo_usuario'] != 'superadmin' && $comunicado['enviado_por'] != $_SESSION['id_usuario']) {
    redireccionar_con_mensaje('index.php', 'No tienes permisos para ver este comunicado', 'danger');
}

// Obtener adjuntos
$adjuntos = [];
if ($comunicado['tiene_adjuntos']) {
    $query_adjuntos = "SELECT * FROM comunicados_adjuntos WHERE id_comunicado = ? ORDER BY fecha_subida";
    $stmt_adjuntos = $conexion->prepare($query_adjuntos);
    $stmt_adjuntos->bind_param("i", $id_comunicado);
    $stmt_adjuntos->execute();
    $result_adjuntos = $stmt_adjuntos->get_result();
    
    while ($row = $result_adjuntos->fetch_assoc()) {
        $adjuntos[] = $row;
    }
}

// Obtener destinatarios
$destinatarios = [];
$query_dest = "SELECT cd.*, a.nombre, a.apellido, a.matricula, g.nombre_grupo,
               DATE_FORMAT(cd.fecha_envio, '%d/%m/%Y %H:%i') as fecha_envio_fmt,
               DATE_FORMAT(cd.fecha_lectura, '%d/%m/%Y %H:%i') as fecha_lectura_fmt
               FROM comunicados_destinatarios cd
               JOIN alumnos a ON cd.id_alumno = a.id_alumno
               JOIN grupos g ON a.id_grupo = g.id_grupo
               WHERE cd.id_comunicado = ?
               ORDER BY a.apellido, a.nombre";
$stmt_dest = $conexion->prepare($query_dest);
$stmt_dest->bind_param("i", $id_comunicado);
$stmt_dest->execute();
$result_dest = $stmt_dest->get_result();

while ($row = $result_dest->fetch_assoc()) {
    $destinatarios[] = $row;
}

// Si el comunicado es para un grupo específico y no hay destinatarios registrados,
// obtener la lista de alumnos de ese grupo
if (!$comunicado['grupo_especifico'] && $comunicado['id_grupo'] !== null && empty($destinatarios)) {
    $query_alumnos = "SELECT a.id_alumno, a.nombre, a.apellido, a.matricula, g.nombre_grupo
                      FROM alumnos a
                      JOIN grupos g ON a.id_grupo = g.id_grupo
                      WHERE a.id_grupo = ? AND a.activo = 1
                      ORDER BY a.apellido, a.nombre";
    $stmt_alumnos = $conexion->prepare($query_alumnos);
    $stmt_alumnos->bind_param("i", $comunicado['id_grupo']);
    $stmt_alumnos->execute();
    $result_alumnos = $stmt_alumnos->get_result();
    
    while ($row = $result_alumnos->fetch_assoc()) {
        $destinatarios[] = array_merge($row, [
            'estado' => $comunicado['estado'] === 'enviado' ? 'pendiente' : '-',
            'fecha_envio_fmt' => null,
            'fecha_lectura_fmt' => null
        ]);
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-envelope-open-text"></i> Detalle del Comunicado</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
            
            <?php if ($comunicado['estado'] === 'borrador'): ?>
            <a href="crear.php?id=<?= $id_comunicado ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <?php endif; ?>
            
            <?php if ($comunicado['estado'] === 'enviado'): ?>
            <a href="enviar.php?id=<?= $id_comunicado ?>" class="btn btn-warning">
                <i class="fas fa-share"></i> Reenviar
            </a>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipo_usuario'] === 'superadmin'): ?>
            <button type="button" class="btn btn-danger btn-eliminar" 
                    data-id="<?= $id_comunicado ?>" 
                    data-titulo="<?= htmlspecialchars($comunicado['titulo']) ?>">
                <i class="fas fa-trash"></i> Eliminar
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <!-- Columna izquierda: Detalles del comunicado -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Información del Comunicado
                            </h5>
                        </div>
                        <div class="col-auto">
                            <?php
                            $estado_clase = '';
                            switch ($comunicado['estado']) {
                                case 'borrador': $estado_clase = 'bg-secondary'; break;
                                case 'enviado': $estado_clase = 'bg-success'; break;
                                case 'programado': $estado_clase = 'bg-info'; break;
                                case 'cancelado': $estado_clase = 'bg-danger'; break;
                            }
                            
                            $prioridad_clase = '';
                            switch ($comunicado['prioridad']) {
                                case 'baja': $prioridad_clase = 'bg-success'; break;
                                case 'normal': $prioridad_clase = 'bg-primary'; break;
                                case 'alta': $prioridad_clase = 'bg-danger'; break;
                            }
                            ?>
                            <span class="badge <?= $estado_clase ?>">
                                <?= ucfirst($comunicado['estado']) ?>
                            </span>
                            <span class="badge <?= $prioridad_clase ?>">
                                Prioridad: <?= ucfirst($comunicado['prioridad']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h3><?= htmlspecialchars($comunicado['titulo']) ?></h3>
                        <hr>
                    </div>
                    
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Creado por:</strong> <?= htmlspecialchars($comunicado['enviado_por_nombre']) ?></p>
                                <p><strong>Fecha de creación:</strong> <?= date('d/m/Y H:i', strtotime($comunicado['fecha_creacion'])) ?></p>
                                <?php if ($comunicado['fecha_envio']): ?>
                                <p><strong>Fecha de envío:</strong> <?= date('d/m/Y H:i', strtotime($comunicado['fecha_envio'])) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Destinatarios:</strong> <?= htmlspecialchars($comunicado['destinatarios_desc']) ?></p>
                                <?php if ($comunicado['id_plantilla']): ?>
                                <p><strong>Plantilla utilizada:</strong> <?= htmlspecialchars($comunicado['nombre_plantilla']) ?></p>
                                <?php endif; ?>
                                <?php if ($comunicado['id_comunicado_original']): ?>
                                <p><strong>Reenvío de:</strong> <a href="ver.php?id=<?= $comunicado['id_comunicado_original'] ?>">Comunicado #<?= $comunicado['id_comunicado_original'] ?></a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-align-left"></i> Contenido del Comunicado</h6>
                            </div>
                            <div class="card-body">
                                <?= $comunicado['contenido'] ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($adjuntos)): ?>
                    <div class="mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="fas fa-paperclip"></i> Archivos Adjuntos</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($adjuntos as $adjunto): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <i class="fas fa-file me-2"></i>
                                            <?= htmlspecialchars($adjunto['nombre_original']) ?>
                                            <small class="text-muted">(<?= formatear_tamano($adjunto['tamano']) ?>)</small>
                                        </span>
                                        <a href="<?= $adjunto['ruta'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> Descargar
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Columna derecha: Estadísticas y destinatarios -->
        <div class="col-md-4">
            <?php if ($comunicado['estado'] === 'enviado'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-chart-pie"></i> Estadísticas de Envío</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title"><?= $comunicado['total_destinatarios'] ?></h3>
                                    <p class="card-text text-muted">Total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title text-success"><?= $comunicado['total_enviados'] ?></h3>
                                    <p class="card-text text-muted">Enviados</p>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="card">
                                <div class="card-body">
                                    <h3 class="card-title text-primary"><?= $comunicado['total_leidos'] ?></h3>
                                    <p class="card-text text-muted">Leídos</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Tasa de Lectura</h6>
                        <?php
                        $porcentaje_lectura = $comunicado['total_destinatarios'] > 0 ? 
                            round(($comunicado['total_leidos'] / $comunicado['total_destinatarios']) * 100) : 0;
                        ?>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentaje_lectura ?>%;" 
                                 aria-valuenow="<?= $porcentaje_lectura ?>" aria-valuemin="0" aria-valuemax="100">
                                <?= $porcentaje_lectura ?>%
                            </div>
                        </div>
                        <small class="text-muted">
                            <?= $comunicado['total_leidos'] ?> de <?= $comunicado['total_destinatarios'] ?> destinatarios han leído el comunicado
                        </small>
                    </div>
                    
                    <div>
                        <canvas id="estadisticasChart" width="100%" height="200"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-users"></i> Destinatarios
                            </h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-primary"><?= count($destinatarios) ?> destinatarios</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($destinatarios)): ?>
                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Alumno</th>
                                    <th>Grupo</th>
                                    <th>Estado</th>
                                    <th>Detalles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($destinatarios as $destinatario): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($destinatario['apellido'] . ' ' . $destinatario['nombre']) ?>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($destinatario['matricula']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($destinatario['nombre_grupo']) ?></td>
                                    <td>
                                        <?php
                                        $estado_clase = '';
                                        $estado_texto = '';
                                        
                                        switch ($destinatario['estado']) {
                                            case 'pendiente':
                                                $estado_clase = 'bg-warning text-dark';
                                                $estado_texto = 'Pendiente';
                                                break;
                                            case 'enviado':
                                                $estado_clase = 'bg-success';
                                                $estado_texto = 'Enviado';
                                                break;
                                            case 'error':
                                                $estado_clase = 'bg-danger';
                                                $estado_texto = 'Error';
                                                break;
                                            case 'leido':
                                                $estado_clase = 'bg-info';
                                                $estado_texto = 'Leído';
                                                break;
                                            default:
                                                $estado_clase = 'bg-secondary';
                                                $estado_texto = $destinatario['estado'];
                                        }
                                        ?>
                                        <span class="badge <?= $estado_clase ?>"><?= $estado_texto ?></span>
                                    </td>
                                    <td>
                                        <?php if ($destinatario['fecha_envio_fmt']): ?>
                                        <small>Enviado: <?= $destinatario['fecha_envio_fmt'] ?></small><br>
                                        <?php endif; ?>
                                        
                                        <?php if ($destinatario['fecha_lectura_fmt']): ?>
                                        <small>Leído: <?= $destinatario['fecha_lectura_fmt'] ?></small>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($destinatario['error_mensaje']) && !empty($destinatario['error_mensaje'])): ?>
                                        <small class="text-danger">Error: <?= htmlspecialchars($destinatario['error_mensaje']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i> No hay destinatarios registrados para este comunicado.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($comunicado['estado'] === 'enviado'): ?>
    // Estadísticas Chart
    const ctx = document.getElementById('estadisticasChart').getContext('2d');
    
    const pendientes = <?= $comunicado['total_destinatarios'] - $comunicado['total_enviados'] ?>;
    const enviados = <?= $comunicado['total_enviados'] - $comunicado['total_leidos'] ?>;
    const leidos = <?= $comunicado['total_leidos'] ?>;
    const errores = <?= $comunicado['total_errores'] ?>;
    
    const estadisticasChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pendientes', 'Enviados', 'Leídos', 'Errores'],
            datasets: [{
                data: [pendientes, enviados, leidos, errores],
                backgroundColor: [
                    '#6c757d',
                    '#20c997',
                    '#0dcaf0',
                    '#dc3545'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
    <?php endif; ?>
    
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