<?php
/**
 * Obtener Plantilla de Comunicado vía AJAX
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador', 'profesor'])) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Obtener ID de plantilla
$id_plantilla = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_plantilla <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de plantilla no válido']);
    exit;
}

// Obtener contenido de la plantilla
$query = "SELECT contenido FROM comunicados_plantillas WHERE id_plantilla = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_plantilla);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Plantilla no encontrada']);
    exit;
}

$plantilla = $result->fetch_assoc();

// Devolver contenido en formato JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'contenido' => $plantilla['contenido']]);