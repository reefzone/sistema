<?php
/**
 * Ver Detalles de Grupo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar ID del grupo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redireccionar_con_mensaje('index.php', 'ID de grupo no válido', 'danger');
}

$id_grupo = intval($_GET['id']);

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

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El grupo no existe o ha sido eliminado', 'danger');
}

$grupo = $result->fetch_assoc();

// Contar alumnos en el grupo
$query_count = "SELECT COUNT(*) as total FROM alumnos WHERE id_grupo = ? AND activo = 1";
$stmt_count = $conexion->prepare($query_count);
$stmt_count->bind_param("i", $id_grupo);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$row_count = $result_count->fetch_assoc();
$total_alumnos = $row_count['total'];

// Paginación para alumnos
$pagina_actual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$registros_por_pagina = 15;
$inicio = ($pagina_actual - 1) * $registros_por_pagina;
$total_paginas = ceil($total_alumnos / $registros_por_pagina);

// Obtener alumnos paginados
$query_alumnos = "SELECT id_alumno, apellido_paterno, apellido_materno, nombres, curp 
                 FROM alumnos 
                 WHERE id_grupo = ? AND activo = 1 
                 ORDER BY apellido_paterno, apellido_materno, nombres 
                 LIMIT ?, ?";

$stmt_alumnos = $conexion->prepare($query_alumnos);
$stmt_alumnos->bind_param("iii", $id_grupo, $inicio, $registros_por_pagina);
$stmt_alumnos->execute();
$result_alumnos = $stmt_alumnos->get_result();

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-users-class"></i> Detalle del Grupo</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
            <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
            <a href="editar.php?id=<?= $id_grupo ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Editar Grupo
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Información del grupo -->
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Información del Grupo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Grupo:</h6>
                            <p class="lead"><?= htmlspecialchars($grupo['nombre_grupo']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Grado:</h6>
                            <p class="lead"><?= htmlspecialchars($grupo['nombre_grado']) ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Turno:</h6>
                            <p class="lead"><?= htmlspecialchars($grupo['nombre_turno']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Ciclo Escolar:</h6>
                            <p class="lead"><?= htmlspecialchars($grupo['ciclo_escolar']) ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="text-muted">Color para Credenciales:</h6>
                            <div class="d-flex align-items-center">
                                <div class="color-sample me-2" style="width: 30px; height: 30px; background-color: <?= htmlspecialchars($grupo['color_credencial']) ?>; border: 1px solid #ccc; border-radius: 3px;"></div>
                                <span class="lead"><?= htmlspecialchars($grupo['color_credencial']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6 class="text-muted">Total de Alumnos:</h6>
                            <p class="lead">
                                <span class="badge bg-info"><?= $total_alumnos ?> alumnos</span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-grid gap-2">
                        <a href="../credenciales/generar_grupo.php?id=<?= $id_grupo ?>" class="btn btn-success">
                            <i class="fas fa-id-card"></i> Generar Credenciales
                        </a>
                        <a href="javascript:void(0);" onclick="imprimirLista()" class="btn btn-info">
                            <i class="fas fa-print"></i> Imprimir Lista
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vista previa de credencial -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-id-card"></i> Vista Previa de Credencial
                    </h5>
                </div>
                <div class="card-body">
                    <div class="credencial-preview" style="background-color: <?= htmlspecialchars($grupo['color_credencial']) ?>; border-radius: 8px; padding: 1rem; text-align: center;">
                        <h5 class="mb-3">ESCUELA SECUNDARIA TÉCNICA #82</h5>
                        <div class="mb-2">
                            <i class="fas fa-user-circle fa-5x"></i>
                        </div>
                        <p>Nombre del Alumno</p>
                        <?php 
                        // Determinar el color del texto según el color de fondo
                        $color = $grupo['color_credencial'];
                        $r = hexdec(substr($color, 1, 2));
                        $g = hexdec(substr($color, 3, 2));
                        $b = hexdec(substr($color, 5, 2));
                        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                        $text_color = ($brightness > 128) ? '#000000' : '#FFFFFF';
                        ?>
                        <p class="mb-0">
                            <span class="badge" style="background-color: <?= htmlspecialchars($grupo['color_credencial']) ?>; color: <?= $text_color ?>; border: <?= $brightness > 128 ? '1px solid #ccc' : 'none' ?>;">
                                <?= htmlspecialchars($grupo['nombre_grado']) ?> <?= htmlspecialchars($grupo['nombre_grupo']) ?>
                                <span class="ms-1"><?= htmlspecialchars($grupo['nombre_turno']) ?></span>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Listado de alumnos -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-graduate"></i> Alumnos del Grupo
                            </h5>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-light text-dark"><?= $total_alumnos ?> alumnos</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0" id="tabla-alumnos">
                            <thead class="table-info">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Apellido Paterno</th>
                                    <th scope="col">Apellido Materno</th>
                                    <th scope="col">Nombre(s)</th>
                                    <th scope="col">CURP</th>
                                    <th scope="col">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = $inicio + 1;
                                if ($result_alumnos->num_rows > 0) {
                                    while ($alumno = $result_alumnos->fetch_assoc()) {
                                ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($alumno['apellido_paterno']) ?></td>
                                    <td><?= htmlspecialchars($alumno['apellido_materno']) ?></td>
                                    <td><?= htmlspecialchars($alumno['nombres']) ?></td>
                                    <td><?= htmlspecialchars($alumno['curp']) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="../alumnos/ver.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-info" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                                            <a href="../alumnos/editar.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-warning" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../credenciales/generar.php?id=<?= $alumno['id_alumno'] ?>" class="btn btn-success" title="Generar credencial">
                                                <i class="fas fa-id-card"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php
                                    }
                                } else {
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No hay alumnos asignados a este grupo.
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
                    <nav aria-label="Paginación de alumnos">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $id_grupo ?>&pagina=1">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $id_grupo ?>&pagina=<?= $pagina_actual - 1 ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $inicio_paginas = max(1, $pagina_actual - 2);
                            $fin_paginas = min($total_paginas, $pagina_actual + 2);
                            
                            for ($i = $inicio_paginas; $i <= $fin_paginas; $i++): 
                            ?>
                            <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                                <a class="page-link" href="?id=<?= $id_grupo ?>&pagina=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $id_grupo ?>&pagina=<?= $pagina_actual + 1 ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?id=<?= $id_grupo ?>&pagina=<?= $total_paginas ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Area oculta para impresión -->
<div id="print-area" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px;">
        <h2>ESCUELA SECUNDARIA TÉCNICA #82</h2>
        <h3>Lista de Alumnos - Grupo <?= htmlspecialchars($grupo['nombre_grado']) ?> <?= htmlspecialchars($grupo['nombre_grupo']) ?> <?= htmlspecialchars($grupo['nombre_turno']) ?></h3>
        <h4>Ciclo Escolar: <?= htmlspecialchars($grupo['ciclo_escolar']) ?></h4>
        <p>Fecha de impresión: <?= date('d/m/Y H:i:s') ?></p>
    </div>
    <table id="tabla-impresion" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 8px; text-align: center;">#</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: left;">Nombre Completo</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center;">CURP</th>
                <th style="border: 1px solid #000; padding: 8px; text-align: center;">Asistencia</th>
            </tr>
        </thead>
        <tbody>
            <!-- Se llenará con JavaScript -->
        </tbody>
    </table>
    <div style="margin-top: 50px; display: flex; justify-content: space-between;">
        <div style="width: 45%; text-align: center;">
            <p>___________________________</p>
            <p>Profesor/a</p>
        </div>
        <div style="width: 45%; text-align: center;">
            <p>___________________________</p>
            <p>Director/a</p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cargar los alumnos para imprimir (todos, no solo la página actual)
        cargarAlumnosParaImprimir();
    });
    
    // Función para cargar todos los alumnos para impresión
    function cargarAlumnosParaImprimir() {
        fetch('get_alumnos.php?id=<?= $id_grupo ?>')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('#tabla-impresion tbody');
                tbody.innerHTML = '';
                
                data.forEach((alumno, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="border: 1px solid #000; padding: 8px; text-align: center;">${index + 1}</td>
                        <td style="border: 1px solid #000; padding: 8px; text-align: left;">${alumno.apellido_paterno} ${alumno.apellido_materno} ${alumno.nombres}</td>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center;">${alumno.curp}</td>
                        <td style="border: 1px solid #000; padding: 8px; text-align: center;"></td>
                    `;
                    tbody.appendChild(tr);
                });
            })
            .catch(error => {
                console.error('Error al cargar alumnos:', error);
                alert('Error al cargar la lista de alumnos para impresión');
            });
    }
    
    // Función para imprimir la lista
    function imprimirLista() {
        const printArea = document.getElementById('print-area');
        const windowPrint = window.open('', '', 'height=800,width=800');
        
        windowPrint.document.write('<html><head><title>Lista de Alumnos</title>');
        windowPrint.document.write('<style>');
        windowPrint.document.write('body { font-family: Arial, sans-serif; }');
        windowPrint.document.write('table { width: 100%; border-collapse: collapse; }');
        windowPrint.document.write('th, td { border: 1px solid #000; padding: 8px; }');
        windowPrint.document.write('th { background-color: #f2f2f2; }');
        windowPrint.document.write('</style>');
        windowPrint.document.write('</head><body>');
        windowPrint.document.write(printArea.innerHTML);
        windowPrint.document.write('</body></html>');
        
        windowPrint.document.close();
        windowPrint.focus();
        
        setTimeout(function() {
            windowPrint.print();
            windowPrint.close();
        }, 1000);
    }
</script>