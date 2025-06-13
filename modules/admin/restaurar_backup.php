<?php
/**
 * Archivo: restaurar_backup.php
 * Ubicación: modules/admin/restaurar_backup.php
 * Propósito: Restaurar respaldos de la base de datos
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
if (!isset($_POST['id_backup']) || empty($_POST['id_backup'])) {
    header('Location: backup.php?mensaje=Respaldo no especificado&tipo=danger');
    exit;
}

$id_backup = intval($_POST['id_backup']);

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

// Verificar que sea un respaldo de base de datos
if ($backup['tipo'] != 'database') {
    header('Location: backup.php?mensaje=Solo se pueden restaurar respaldos de base de datos&tipo=danger');
    exit;
}

// Verificar que el archivo exista
$ruta_archivo = __DIR__ . '/../../' . $backup['ruta'];

if (!file_exists($ruta_archivo)) {
    header('Location: backup.php?mensaje=El archivo de respaldo no existe en el sistema&tipo=danger');
    exit;
}

// Configuración de base de datos
$db_host = $GLOBALS['db_host'];
$db_user = $GLOBALS['db_user'];
$db_pass = $GLOBALS['db_pass'];
$db_name = $GLOBALS['db_name'];

// Crear directorio temporal
$temp_dir = __DIR__ . '/../../system/temp/' . uniqid('restore_');
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

// Descomprimir archivo
$archivo_sql = $temp_dir . '/' . $backup['nombre_archivo'] . '.sql';
$gz = gzopen($ruta_archivo, 'rb');
$sql = fopen($archivo_sql, 'wb');

while (!gzeof($gz)) {
    fwrite($sql, gzread($gz, 4096));
}

gzclose($gz);
fclose($sql);

// Restaurar base de datos
$comando = sprintf(
    'mysql --host=%s --user=%s --password=%s %s < %s',
    escapeshellarg($db_host),
    escapeshellarg($db_user),
    escapeshellarg($db_pass),
    escapeshellarg($db_name),
    escapeshellarg($archivo_sql)
);

exec($comando, $output, $return_code);

// Eliminar archivos temporales
unlink($archivo_sql);
rmdir($temp_dir);

// Verificar resultado
if ($return_code !== 0) {
    header('Location: backup.php?mensaje=Error al restaurar la base de datos. Código: ' . $return_code . '&tipo=danger');
    exit;
}

// Registrar en log
registrarLog(
    'operacion',
    $_SESSION['user_id'],
    null,
    "Restauración de base de datos desde respaldo: {$backup['nombre_archivo']}"
);

// Redireccionar con mensaje de éxito
header('Location: backup.php?mensaje=Base de datos restaurada correctamente&tipo=success');