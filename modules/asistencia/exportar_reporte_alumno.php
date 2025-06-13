<?php
/**
 * Exportar Reporte de Asistencia por Alumno
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para realizar esta acción', 'danger');
}

// Obtener parámetros
$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-90 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$formato = isset($_GET['formato']) ? strtolower($_GET['formato']) : 'pdf';

// Validar alumno
if ($id_alumno <= 0) {
    redireccionar_con_mensaje('reporte.php', 'ID de alumno no válido', 'danger');
}

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    redireccionar_con_mensaje('reporte.php', 'Formato de fechas no válido', 'danger');
}

// Validar formato
if (!in_array($formato, ['pdf'])) {
    $formato = 'pdf';
}

// Obtener datos del alumno
$query_alumno = "SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, 
                g.id_grupo, g.nombre_grupo, gr.nombre_grado, t.nombre_turno, g.ciclo_escolar
                FROM alumnos a 
                JOIN grupos g ON a.id_grupo = g.id_grupo 
                JOIN grados gr ON g.id_grado = gr.id_grado 
                JOIN turnos t ON g.id_turno = t.id_turno 
                WHERE a.id_alumno = ? AND a.activo = 1";
$stmt_alumno = $conexion->prepare($query_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();

if ($result_alumno->num_rows == 0) {
    redireccionar_con_mensaje('reporte.php', 'El alumno no existe o no está activo', 'danger');
}

$alumno = $result_alumno->fetch_assoc();

// Obtener el historial de asistencia del alumno
$query_asistencia = "SELECT a.id_asistencia, a.fecha, a.asistio, a.justificada, a.observaciones,
                    DATE_FORMAT(a.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro,
                    (SELECT CONCAT(nombre, ' ', apellido_paterno) FROM usuarios WHERE id_usuario = a.registrado_por) as registrado_por
                    FROM asistencia a
                    WHERE a.id_alumno = ? AND a.fecha BETWEEN ? AND ?
                    ORDER BY a.fecha DESC";
$stmt_asistencia = $conexion->prepare($query_asistencia);
$stmt_asistencia->bind_param("iss", $id_alumno, $fecha_inicio, $fecha_fin);
$stmt_asistencia->execute();
$result_asistencia = $stmt_asistencia->get_result();

// Estadísticas
$total_dias = $result_asistencia->num_rows;
$presentes = 0;
$ausentes = 0;
$justificadas = 0;
$porcentaje_asistencia = 0;

$registros = [];

// Solo calculamos estadísticas si hay registros
if ($total_dias > 0) {
    // Resetear el puntero del resultado
    $result_asistencia->data_seek(0);
    
    while ($row = $result_asistencia->fetch_assoc()) {
        if ($row['asistio']) {
            $presentes++;
        } else {
            $ausentes++;
            if ($row['justificada']) {
                $justificadas++;
            }
        }
        
        $registros[] = $row;
    }
    
    // Calcular porcentaje de asistencia
    $porcentaje_asistencia = round(($presentes / $total_dias) * 100, 2);
}

// Generar reporte en PDF simple en HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencia - <?= htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombre']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 12px;
        }
        h1 {
            color: #007bff;
            text-align: center;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .escuela {
            font-weight: bold;
            font-size: 16px;
        }
        .logo {
            max-width: 100px;
            max-height: 100px;
        }
        .datos-alumno {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
        }
        .row {
            display: flex;
            margin-bottom: 10px;
        }
        .col {
            flex: 1;
        }
        .estadisticas {
            display: flex;
            margin-bottom: 20px;
        }
        .stat-box {
            flex: 1;
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 0 5px;
            border-radius: 5px;
        }
        .presente {
            background-color: #d4edda;
        }
        .ausente {
            background-color: #f8d7da;
        }
        .justificada {
            background-color: #fff3cd;
        }
        .porcentaje {
            background-color: #cce5ff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr.presente {
            background-color: #d4edda;
        }
        tr.ausente {
            background-color: #f8d7da;
        }
        tr.justificada {
            background-color: #fff3cd;
        }
        .badge {
            display: inline-block;
            padding: 3px 7px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 10px;
        }
        .bg-success {
            background-color: #28a745;
            color: white;
        }
        .bg-danger {
            background-color: #dc3545;
            color: white;
        }
        .bg-warning {
            background-color: #ffc107;
            color: black;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="escuela">ESCUELA SECUNDARIA TECNICA #82</div>
        <div>SISTEMA ESCOLAR - REPORTE DE ASISTENCIA INDIVIDUAL</div>
    </div>
    
    <h1>Reporte de Asistencia Individual</h1>
    
    <div class="datos-alumno">
        <div class="row">
            <div class="col">
                <p><strong>Matrícula:</strong> <?= htmlspecialchars($alumno['matricula']) ?></p>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno'] . ' ' . $alumno['nombre']) ?></p>
            </div>
            <div class="col">
                <p><strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                <p><strong>Grado/Turno:</strong> <?= htmlspecialchars($alumno['nombre_grado'] . ' - ' . $alumno['nombre_turno']) ?></p>
                <p><strong>Ciclo Escolar:</strong> <?= htmlspecialchars($alumno['ciclo_escolar']) ?></p>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <p><strong>Período del Reporte:</strong> <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
            </div>
        </div>
    </div>
    
    <div class="estadisticas">
        <div class="stat-box presente">
            <strong>Presentes</strong>
            <h2><?= $presentes ?></h2>
        </div>
        <div class="stat-box ausente">
            <strong>Ausencias</strong>
            <h2><?= $ausentes ?></h2>
        </div>
        <div class="stat-box justificada">
            <strong>Justificadas</strong>
            <h2><?= $justificadas ?></h2>
        </div>
        <div class="stat-box porcentaje">
            <strong>% Asistencia</strong>
            <h2><?= $porcentaje_asistencia ?>%</h2>
        </div>
    </div>
    
    <?php if (!empty($registros)): ?>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Día</th>
                <th>Estado</th>
                <th>Observaciones</th>
                <th>Registrado por</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $registro): ?>
            <tr class="<?= !$registro['asistio'] ? ($registro['justificada'] ? 'justificada' : 'ausente') : 'presente' ?>">
                <td><?= date('d/m/Y', strtotime($registro['fecha'])) ?></td>
                <td><?= date('l', strtotime($registro['fecha'])) ?></td>
                <td>
                    <?php if ($registro['asistio']): ?>
                    <span class="badge bg-success">Presente</span>
                    <?php else: ?>
                    <span class="badge <?= $registro['justificada'] ? 'bg-warning' : 'bg-danger' ?>">
                        <?= $registro['justificada'] ? 'Ausente Justificado' : 'Ausente' ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($registro['observaciones']) ?></td>
                <td><?= htmlspecialchars($registro['registrado_por']) ?><br>
                    <small><?= $registro['fecha_registro'] ?></small>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 5px;">
        <p>No hay registros de asistencia para este alumno en el período seleccionado.</p>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <p>Reporte generado el: <?= date('d/m/Y H:i:s') ?></p>
        <p>Este documento es de carácter informativo y forma parte del Sistema Escolar de la ESCUELA SECUNDARIA TECNICA #82.</p>
    </div>
</body>
</html>
<?php
// Registrar la exportación en el log
$detalle_log = "Se exportó el reporte de asistencia del alumno {$alumno['apellido_paterno']} {$alumno['apellido_materno']} {$alumno['nombre']} ".
              "({$alumno['matricula']}) para el período del " . date('d/m/Y', strtotime($fecha_inicio)) . 
              " al " . date('d/m/Y', strtotime($fecha_fin)) . " en formato $formato";

registrar_log($conexion, 'exportar_reporte_asistencia_alumno', $detalle_log, $_SESSION['id_usuario']);

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