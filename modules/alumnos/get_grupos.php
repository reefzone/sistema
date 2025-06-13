<?php
/**
 * Obtener Grupos (AJAX)
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_config.php';

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar parámetros
if (!isset($_GET['turno']) || !isset($_GET['grado'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros incompletos']);
    exit;
}

// Sanitizar entrada
$turno = intval($_GET['turno']);
$grado = intval($_GET['grado']);

// Consultar grupos
$query = "SELECT id_grupo, nombre_grupo 
          FROM grupos 
          WHERE id_turno = ? AND id_grado = ? AND activo = 1 
          ORDER BY nombre_grupo";

$stmt = $conexion->prepare($query);
$stmt->bind_param("ii", $turno, $grado);
$stmt->execute();
$result = $stmt->get_result();

$grupos = [];
while ($row = $result->fetch_assoc()) {
    $grupos[] = $row;
}

// Devolver resultado en formato JSON
header('Content-Type: application/json');
echo json_encode($grupos);
exit;