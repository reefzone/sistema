<?php
/**
 * Descarga de archivos de importación
 * Módulo de Registro Masivo - Sistema Escolar ESCUELA SECUNDARIA TECNICA #82
 * Ubicación: modules/registro_masivo/descargar.php
 */

// Incluir archivos requeridos
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Verificar sesión de usuario
verificarSesion();

// Verificar permisos (solo superadmin y organizador)
if (!tienePermiso('admin_alumnos')) {
    header('Location: ../../index.php?mensaje=No tiene permisos para acceder a esta sección');
    exit();
}

// Verificar parámetros
if (!isset($_GET['tipo']) || !isset($_GET['id'])) {
    header('Location: historial.php?error=Parámetros incorrectos');
    exit();
}

$tipo = $_GET['tipo'];
$id_importacion = intval($_GET['id']);

// Verificar que la importación existe
$query = "SELECT * FROM importaciones WHERE id_importacion = ? AND tipo = 'alumnos'";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_importacion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header('Location: historial.php?error=Importación no encontrada');
    exit();
}

$importacion = $resultado->fetch_assoc();

// Determinar qué archivo descargar según el tipo
$archivo = '';
$nombre_descarga = '';

switch($tipo) {
    case 'original':
        $archivo = $importacion['ruta_original'];
        $nombre_descarga = $importacion['nombre_archivo'];
        break;
        
    case 'procesado':
        $archivo = $importacion['ruta_procesado'];
        $nombre_descarga = 'procesado_' . $importacion['nombre_archivo'];
        break;
        
    case 'log':
        // Construir ruta del archivo de log
        $ruta_procesado = $importacion['ruta_procesado'];
        if (!empty($ruta_procesado)) {
            $archivo = str_replace(
                ['/procesados/', '_procesado_'], 
                ['/logs/', '_log_'], 
                $ruta_procesado
            );
            $archivo = substr($archivo, 0, strrpos($archivo, '.')) . '.json';
            $nombre_descarga = 'log_importacion_' . $id_importacion . '.json';
        }
        break;
        
    default:
        header('Location: historial.php?error=Tipo de archivo no válido');
        exit();
}

// Verificar que el archivo existe
if (empty($archivo) || !file_exists($archivo)) {
    header('Location: historial.php?error=El archivo solicitado no existe');
    exit();
}

// Registrar la descarga en el log
registrarLog(
    'operacion',
    $_SESSION['id_usuario'],
    null,
    "Descarga de archivo de importación: $tipo (ID: $id_importacion)"
);

// Determinar el tipo MIME del archivo
$extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
switch($extension) {
    case 'csv':
        $mime_type = 'text/csv';
        break;
    case 'xlsx':
        $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        break;
    case 'xls':
        $mime_type = 'application/vnd.ms-excel';
        break;
    case 'json':
        $mime_type = 'application/json';
        break;
    default:
        $mime_type = 'application/octet-stream';
}

// Entregar el archivo para descarga
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
header('Content-Length: ' . filesize($archivo));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

readfile($archivo);
exit();