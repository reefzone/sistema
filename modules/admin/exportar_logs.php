<?php
/**
 * Archivo: exportar_logs.php
 * Ubicación: modules/admin/exportar_logs.php
 * Propósito: Exportar logs del sistema a formato Excel
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_logs')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Inicializar variables para filtros
$filtros = [];
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

// Obtener todos los logs que coinciden con el filtro
$query = "SELECT * FROM logs_sistema 
         WHERE $where_str 
         ORDER BY fecha DESC";

if (!empty($params)) {
    $stmt = $conexion->prepare($query);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $logs = $conexion->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Configurar cabeceras para descarga
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="logs_sistema_' . date('Ymd_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Crear el archivo Excel
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Worksheet ss:Name="Logs del Sistema">
  <Table>
   <Row>
    <Cell><Data ss:Type="String">ID</Data></Cell>
    <Cell><Data ss:Type="String">Fecha y Hora</Data></Cell>
    <Cell><Data ss:Type="String">Tipo</Data></Cell>
    <Cell><Data ss:Type="String">Usuario</Data></Cell>
    <Cell><Data ss:Type="String">IP</Data></Cell>
    <Cell><Data ss:Type="String">Módulo</Data></Cell>
    <Cell><Data ss:Type="String">Descripción</Data></Cell>
   </Row>
   <?php foreach ($logs as $log): ?>
   <Row>
    <Cell><Data ss:Type="Number"><?php echo $log['id_log']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo date('d/m/Y H:i:s', strtotime($log['fecha'])); ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo ucfirst($log['tipo']); ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $log['usuario']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $log['ip']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $log['modulo'] ?: '-'; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $log['descripcion']; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
 </Worksheet>
</Workbook>
<?php
// Registrar en log
registrarLog(
    'operacion',
    $_SESSION['user_id'],
    null,
    "Exportación de logs a Excel. " . count($logs) . " registros exportados."
);