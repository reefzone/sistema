<?php
/**
 * Exportar Reporte de Asistencia
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
$id_grupo = isset($_GET['id_grupo']) ? intval($_GET['id_grupo']) : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-30 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$formato = isset($_GET['formato']) ? strtolower($_GET['formato']) : 'excel';

// Validar grupo
if ($id_grupo <= 0) {
    redireccionar_con_mensaje('reporte.php', 'ID de grupo no válido', 'danger');
}

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    redireccionar_con_mensaje('reporte.php', 'Formato de fechas no válido', 'danger');
}

// Validar formato
if (!in_array($formato, ['excel', 'pdf'])) {
    $formato = 'excel';
}

// Obtener información del grupo
$query_grupo = "SELECT g.nombre_grupo, gr.nombre_grado, t.nombre_turno, g.ciclo_escolar
                FROM grupos g
                JOIN grados gr ON g.id_grado = gr.id_grado
                JOIN turnos t ON g.id_turno = t.id_turno
                WHERE g.id_grupo = ?";
$stmt_grupo = $conexion->prepare($query_grupo);
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$result_grupo = $stmt_grupo->get_result();

if ($result_grupo->num_rows == 0) {
    redireccionar_con_mensaje('reporte.php', 'El grupo no existe', 'danger');
}

$info_grupo = $result_grupo->fetch_assoc();

// Obtener la lista de alumnos del grupo
$query_alumnos = "SELECT id_alumno, matricula, CONCAT(apellido_paterno, ' ', apellido_materno, ' ', nombre) as nombre_completo
                 FROM alumnos
                 WHERE id_grupo = ? AND activo = 1
                 ORDER BY apellido_paterno, apellido_materno, nombre";
$stmt_alumnos = $conexion->prepare($query_alumnos);
$stmt_alumnos->bind_param("i", $id_grupo);
$stmt_alumnos->execute();
$result_alumnos = $stmt_alumnos->get_result();

$alumnos = [];
while ($row = $result_alumnos->fetch_assoc()) {
    $alumnos[$row['id_alumno']] = $row;
}

// Obtener las fechas en las que hubo asistencia para este grupo
$query_fechas = "SELECT DISTINCT a.fecha
                FROM asistencia a
                JOIN alumnos al ON a.id_alumno = al.id_alumno
                WHERE al.id_grupo = ? AND a.fecha BETWEEN ? AND ?
                ORDER BY a.fecha";
$stmt_fechas = $conexion->prepare($query_fechas);
$stmt_fechas->bind_param("iss", $id_grupo, $fecha_inicio, $fecha_fin);
$stmt_fechas->execute();
$result_fechas = $stmt_fechas->get_result();

$fechas = [];
while ($row = $result_fechas->fetch_assoc()) {
    $fechas[] = $row['fecha'];
}

// Obtener todas las asistencias para el período
$query_asistencias = "SELECT a.id_alumno, a.fecha, a.asistio, a.justificada
                     FROM asistencia a
                     JOIN alumnos al ON a.id_alumno = al.id_alumno
                     WHERE al.id_grupo = ? AND a.fecha BETWEEN ? AND ?";
$stmt_asistencias = $conexion->prepare($query_asistencias);
$stmt_asistencias->bind_param("iss", $id_grupo, $fecha_inicio, $fecha_fin);
$stmt_asistencias->execute();
$result_asistencias = $stmt_asistencias->get_result();

$asistencias = [];
while ($row = $result_asistencias->fetch_assoc()) {
    $asistencias[$row['id_alumno']][$row['fecha']] = [
        'asistio' => $row['asistio'],
        'justificada' => $row['justificada']
    ];
}

// Calcular estadísticas por alumno
$estadisticas_alumnos = [];
foreach ($alumnos as $id_alumno => $alumno) {
    $total_dias = count($fechas);
    $presentes = 0;
    $ausentes = 0;
    $justificadas = 0;
    
    foreach ($fechas as $fecha) {
        if (isset($asistencias[$id_alumno][$fecha])) {
            if ($asistencias[$id_alumno][$fecha]['asistio']) {
                $presentes++;
            } else {
                $ausentes++;
                if ($asistencias[$id_alumno][$fecha]['justificada']) {
                    $justificadas++;
                }
            }
        }
    }
    
    $porcentaje = ($total_dias > 0) ? round(($presentes / $total_dias) * 100, 2) : 0;
    
    $estadisticas_alumnos[$id_alumno] = [
        'total_dias' => $total_dias,
        'presentes' => $presentes,
        'ausentes' => $ausentes,
        'justificadas' => $justificadas,
        'porcentaje' => $porcentaje
    ];
}

// Exportar según el formato seleccionado
if ($formato === 'excel') {
    // Configurar para descargar como Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="reporte_asistencia_grupo_' . $id_grupo . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Iniciar salida Excel
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Reporte de Asistencia</title>';
    echo '<style>
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid #000; padding: 5px; }
            th { background-color: #f2f2f2; }
            .presente { background-color: #d4edda; }
            .ausente { background-color: #f8d7da; }
            .justificada { background-color: #fff3cd; }
          </style>';
    echo '</head>';
    echo '<body>';
    
    // Título del reporte
    echo '<h1>Reporte de Asistencia</h1>';
    echo '<p><strong>Grupo:</strong> ' . htmlspecialchars($info_grupo['nombre_grupo']) . ' - ' . 
         htmlspecialchars($info_grupo['nombre_grado']) . ' - ' . 
         htmlspecialchars($info_grupo['nombre_turno']) . '</p>';
    echo '<p><strong>Ciclo Escolar:</strong> ' . htmlspecialchars($info_grupo['ciclo_escolar']) . '</p>';
    echo '<p><strong>Período:</strong> ' . date('d/m/Y', strtotime($fecha_inicio)) . ' al ' . date('d/m/Y', strtotime($fecha_fin)) . '</p>';
    
    // Tabla de asistencia
    echo '<table>';
    
    // Encabezados con fechas
    echo '<tr>';
    echo '<th rowspan="2">N°</th>';
    echo '<th rowspan="2">Matrícula</th>';
    echo '<th rowspan="2">Nombre</th>';
    
    foreach ($fechas as $fecha) {
        echo '<th>' . date('d/m/Y', strtotime($fecha)) . '</th>';
    }
    
    echo '<th rowspan="2">Asistencias</th>';
    echo '<th rowspan="2">Faltas</th>';
    echo '<th rowspan="2">Justificadas</th>';
    echo '<th rowspan="2">% Asistencia</th>';
    echo '</tr>';
    
    // Segunda fila de encabezados (días de la semana)
    echo '<tr>';
    foreach ($fechas as $fecha) {
        echo '<th>' . date('D', strtotime($fecha)) . '</th>';
    }
    echo '</tr>';
    
    // Datos de alumnos
    $contador = 1;
    foreach ($alumnos as $id_alumno => $alumno) {
        echo '<tr>';
        echo '<td>' . $contador++ . '</td>';
        echo '<td>' . htmlspecialchars($alumno['matricula']) . '</td>';
        echo '<td>' . htmlspecialchars($alumno['nombre_completo']) . '</td>';
        
        // Asistencias por fecha
        foreach ($fechas as $fecha) {
            $asistio = isset($asistencias[$id_alumno][$fecha]) ? $asistencias[$id_alumno][$fecha]['asistio'] : 1;
            $justificada = isset($asistencias[$id_alumno][$fecha]) ? $asistencias[$id_alumno][$fecha]['justificada'] : 0;
            
            $clase = $asistio ? 'presente' : ($justificada ? 'justificada' : 'ausente');
            $texto = $asistio ? 'P' : ($justificada ? 'J' : 'F');
            
            echo '<td class="' . $clase . '">' . $texto . '</td>';
        }
        
        // Estadísticas del alumno
        echo '<td>' . $estadisticas_alumnos[$id_alumno]['presentes'] . '</td>';
        echo '<td>' . $estadisticas_alumnos[$id_alumno]['ausentes'] . '</td>';
        echo '<td>' . $estadisticas_alumnos[$id_alumno]['justificadas'] . '</td>';
        echo '<td>' . $estadisticas_alumnos[$id_alumno]['porcentaje'] . '%</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    // Leyenda
    echo '<p><strong>Leyenda:</strong> P = Presente, F = Falta, J = Justificada</p>';
    
    // Fecha de generación
    echo '<p><small>Reporte generado el: ' . date('d/m/Y H:i:s') . '</small></p>';
    
    echo '</body>';
    echo '</html>';
    
} else if ($formato === 'pdf') {
    // Aquí iría el código para generar PDF usando una biblioteca como FPDF o TCPDF
    // Para este ejemplo, simplemente redirigimos con un mensaje
    redireccionar_con_mensaje(
        "reporte.php?id_grupo=$id_grupo&fecha_inicio=$fecha_inicio&fecha_fin=$fecha_fin", 
        "La exportación a PDF requiere la instalación de una biblioteca adicional. Por favor, use el formato Excel por ahora.", 
        'info'
    );
}

// Registrar la exportación en el log
$detalle_log = "Se exportó el reporte de asistencia del grupo {$info_grupo['nombre_grupo']} de {$info_grupo['nombre_grado']} ".
              "turno {$info_grupo['nombre_turno']} para el período del " . date('d/m/Y', strtotime($fecha_inicio)) . 
              " al " . date('d/m/Y', strtotime($fecha_fin)) . " en formato $formato";

registrar_log($conexion, 'exportar_reporte_asistencia', $detalle_log, $_SESSION['id_usuario']);

// Función para registrar acción en el log del sistema
function registrar_log($conexion, $accion, $detalle, $id_usuario) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fecha = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO logs_sistema (fecha, accion, detalle, ip, id_usuario) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ssssi", $fecha, $accion, $detalle, $ip, $id_usuario);
    $stmt->execute();
}