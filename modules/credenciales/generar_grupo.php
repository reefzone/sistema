<?php
/**
 * Generación de Credenciales por Grupo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';
require_once './pdf.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Verificar existencia de ID de grupo
if (!isset($_GET['id_grupo']) || !is_numeric($_GET['id_grupo'])) {
    redireccionar_con_mensaje('index.php', 'ID de grupo no válido', 'danger');
}

$id_grupo = intval($_GET['id_grupo']);
$exclusiones = isset($_GET['excluir']) ? explode(',', $_GET['excluir']) : [];

// Obtener datos del grupo
$query = "SELECT g.*, gr.nombre_grado, t.nombre_turno 
          FROM grupos g
          JOIN grados gr ON g.id_grado = gr.id_grado
          JOIN turnos t ON g.id_turno = t.id_turno
          WHERE g.id_grupo = ? AND g.activo = 1";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_grupo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El grupo no existe o no está activo', 'danger');
}

$grupo = $result->fetch_assoc();

// Obtener alumnos del grupo
$query_alumnos = "SELECT a.* 
                  FROM alumnos a
                  WHERE a.id_grupo = ? AND a.activo = 1
                  ORDER BY a.apellido, a.nombre";

$stmt_alumnos = $conexion->prepare($query_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$result_alumnos = $stmt_alumnos->get_result();

if ($result_alumnos->num_rows == 0) {
    redireccionar_con_mensaje('index.php', 'El grupo no tiene alumnos activos', 'warning');
}

$alumnos = [];
while ($alumno = $result_alumnos->fetch_assoc()) {
    // Verificar si el alumno está en la lista de exclusiones
    if (!in_array($alumno['id_alumno'], $exclusiones)) {
        $alumnos[] = $alumno;
    }
}

if (empty($alumnos)) {
    redireccionar_con_mensaje('index.php', 'No hay alumnos seleccionados para generar credenciales', 'warning');
}

// Obtener configuración de plantilla predeterminada
$query_config = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
$result_config = $conexion->query($query_config);

if ($result_config->num_rows == 0) {
    redireccionar_con_mensaje('plantilla.php', 'No hay una plantilla predeterminada configurada', 'warning');
}

$config = $result_config->fetch_assoc();

// Preparar directorio para guardar las credenciales
$year = date('Y');
$dir_credenciales = "../../uploads/credenciales/grupos/{$year}/";

if (!file_exists($dir_credenciales)) {
    mkdir($dir_credenciales, 0755, true);
}

// Nombre del archivo PDF
$nombre_archivo = "grupo_{$grupo['nombre_grupo']}_{$year}.pdf";
$ruta_pdf = $dir_credenciales . $nombre_archivo;

// Crear PDF
$credencial = new CredencialPDF($conexion, $config);

// Si la solicitud es para procesar, generamos el PDF
if (isset($_GET['procesar']) && $_GET['procesar'] == 1) {
    // Generar credenciales
    $credencial->generarCredencialesGrupo($grupo, $alumnos, $ruta_pdf);
    
    // Registrar en la base de datos
    $query_insert = "INSERT INTO credenciales_generadas 
                    (id_grupo, tipo, ruta_archivo, fecha_generacion, generado_por) 
                    VALUES (?, 'grupo', ?, NOW(), ?)";
    $stmt_insert = $conexion->prepare($query_insert);
    $stmt_insert->bind_param("isi", $id_grupo, $ruta_pdf, $_SESSION['id_usuario']);
    $stmt_insert->execute();
    $id_generacion = $conexion->insert_id;
    
    // Registrar en el log
    $detalle_log = "Se generaron credenciales para el grupo {$grupo['nombre_grupo']} ({$grupo['nombre_grado']} - {$grupo['nombre_turno']}) con {$result_alumnos->num_rows} alumnos";
    registrar_log($conexion, 'generar_credenciales_grupo', $detalle_log, $_SESSION['id_usuario']);
    
    // Redirigir a la página de resultado
    header("Location: generar_grupo.php?id_grupo={$id_grupo}&resultado=1&id_generacion={$id_generacion}");
    exit;
}

// Si llegamos aquí con un ID de generación, mostramos el resultado
$mostrar_resultado = isset($_GET['resultado']) && $_GET['resultado'] == 1 && isset($_GET['id_generacion']);
$id_generacion = $mostrar_resultado ? intval($_GET['id_generacion']) : 0;

// Si no estamos mostrando resultados ni procesando, mostramos el formulario de confirmación
$mostrar_formulario = !$mostrar_resultado && (!isset($_GET['procesar']) || $_GET['procesar'] != 1);

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-id-card"></i> Credenciales por Grupo</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($mostrar_formulario): ?>
    <!-- Formulario de confirmación -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-users"></i> Generar Credenciales para Grupo
            </h5>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Información del Grupo</h5>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grupo:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_grupo']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grado:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_grado']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Turno:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_turno']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Ciclo Escolar:</strong></span>
                            <span><?= htmlspecialchars($grupo['ciclo_escolar']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Total Alumnos:</strong></span>
                            <span class="badge bg-primary rounded-pill"><?= count($alumnos) ?></span>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5>Vista Previa</h5>
                    <div class="credencial-preview text-center">
                        <img src="credencial_preview.php?color=<?= urlencode($grupo['color_credencial']) ?>" 
                             class="img-fluid" alt="Vista previa de credencial" 
                             style="border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    </div>
                    <p class="text-muted mt-2 text-center">
                        <small><i class="fas fa-info-circle"></i> 
                        Esta es una vista previa de cómo se verán las credenciales.</small>
                    </p>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <h5>Alumnos que recibirán credencial</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Matrícula</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($alumnos as $index => $alumno): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']) ?></td>
                                    <td><?= htmlspecialchars($alumno['matricula']) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-excluir" 
                                                data-id="<?= $alumno['id_alumno'] ?>"
                                                data-nombre="<?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?>">
                                            <i class="fas fa-times"></i> Excluir
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <form id="form-generar" action="generar_grupo.php" method="get">
                <input type="hidden" name="id_grupo" value="<?= $id_grupo ?>">
                <input type="hidden" name="procesar" value="1">
                <input type="hidden" id="excluir" name="excluir" value="">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Información:</strong> Se generará un archivo PDF con las credenciales de todos los alumnos del grupo.
                    Cada estudiante tendrá su credencial personalizada con los colores del grupo.
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-print"></i> Generar Credenciales
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mostrar_resultado): ?>
    <!-- Resultado de la generación -->
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <strong>¡Éxito!</strong> Se han generado correctamente las credenciales para el grupo <?= htmlspecialchars($grupo['nombre_grupo']) ?>.
    </div>
    
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-file-pdf"></i> Credenciales Generadas
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <h5>Información del Grupo</h5>
                    <ul class="list-group mb-4">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grupo:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_grupo']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Grado:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_grado']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Turno:</strong></span>
                            <span><?= htmlspecialchars($grupo['nombre_turno']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Ciclo Escolar:</strong></span>
                            <span><?= htmlspecialchars($grupo['ciclo_escolar']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Total Credenciales:</strong></span>
                            <span class="badge bg-success rounded-pill"><?= count($alumnos) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><strong>Fecha de Generación:</strong></span>
                            <span><?= date('d/m/Y H:i') ?></span>
                        </li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-print me-2"></i> Instrucciones de Impresión:</h6>
                        <ul class="mb-0">
                            <li>Utilice papel grueso o cartulina</li>
                            <li>Seleccione impresión a doble cara</li>
                            <li>Opción: "Voltear por el lado corto"</li>
                            <li>Escala de impresión: 100%</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="credencial-preview text-center mb-4">
                        <iframe src="<?= $ruta_pdf ?>" width="100%" height="500" style="border: 1px solid #ddd; border-radius: 8px;"></iframe>
                    </div>
                    
                    <div class="d-flex justify-content-center">
                        <div class="btn-group">
                            <a href="<?= $ruta_pdf ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-eye"></i> Ver PDF
                            </a>
                            <a href="<?= $ruta_pdf ?>" class="btn btn-success" download>
                                <i class="fas fa-download"></i> Descargar
                            </a>
                            <button class="btn btn-info" onclick="window.print()">
                                <i class="fas fa-print"></i> Imprimir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <a href="reimpresion.php?id=<?= $id_generacion ?>" class="btn btn-outline-primary">
                    <i class="fas fa-history"></i> Ver en Historial
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Manejar exclusión de alumnos
        const botonesExcluir = document.querySelectorAll('.btn-excluir');
        const inputExcluir = document.getElementById('excluir');
        let alumnos_excluidos = [];
        
        botonesExcluir.forEach(function(boton) {
            boton.addEventListener('click', function() {
                const id_alumno = this.getAttribute('data-id');
                const nombre_alumno = this.getAttribute('data-nombre');
                
                // Confirmar exclusión
                if (confirm(`¿Está seguro que desea excluir a ${nombre_alumno} de la generación de credenciales?`)) {
                    // Agregar a lista de excluidos
                    alumnos_excluidos.push(id_alumno);
                    inputExcluir.value = alumnos_excluidos.join(',');
                    
                    // Deshabilitar botón y visualmente marcar como excluido
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-ban"></i> Excluido';
                    this.classList.remove('btn-outline-danger');
                    this.classList.add('btn-secondary');
                    
                    // Marcar fila como excluida
                    this.closest('tr').classList.add('table-secondary');
                    this.closest('tr').style.textDecoration = 'line-through';
                }
            });
        });
    });
</script>

<?php
// Función para registrar acción en el log del sistema
function registrar_log($conexion, $accion, $detalle, $id_usuario) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO logs_sistema (fecha, accion, detalle, ip, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ssssi", $fecha, $accion, $detalle, $ip, $id_usuario);
    $stmt->execute();
}
?>