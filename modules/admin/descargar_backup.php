<?php
/**
 * Archivo: descargar_backup.php
 * Ubicación: modules/admin/descargar_backup.php
 * Propósito: Descargar archivos de respaldo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin_functions.php';

// Verificar sesión activa y permisos
verificarSesion();
if (!tienePermiso('admin_backup')) {
    header('Location: ../../index.php?error=acceso_denegado');
    exit;
}

// Verificar ID de respaldo
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: backup.php?mensaje=Respaldo no especificado&tipo=danger');
    exit;
}

$id_backup = intval($_GET['id']);

// Obtener información del respaldo
$query = "SELECT * FROM backups WHERE id_backup = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_backup);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header('Location: backup.php?mensaje=Respaldo no encontrado&tipo=danger');
    exit;
}

$backup = $resultado->fetch_assoc();

// Verificar que el archivo exista
$ruta_archivo = __DIR__ . '/../../' . $backup['ruta'];

if (!file_exists($ruta_archivo)) {
    header('Location: backup.php?mensaje=El archivo de respaldo no existe en el sistema&tipo=danger');
    exit;
}

// Registrar en log
registrarLog(
    'operacion',
    $_SESSION['user_id'],
    null,
    "Descarga de respaldo: {$backup['nombre_archivo']}"
);

// Configurar cabeceras para la descarga
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($ruta_archivo) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($ruta_archivo));

// Limpiar cualquier salida previa
ob_clean();
flush();

// Enviar el archivo
readfile($ruta_archivo);
exit;