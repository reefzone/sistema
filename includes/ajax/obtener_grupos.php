<?php
/**
 * Obtener Grupos vía AJAX
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

// Obtener parámetros
$id_turno = isset($_GET['turno']) ? intval($_GET['turno']) : 0;
$id_grado = isset($_GET['grado']) ? intval($_GET['grado']) : 0;
$ciclo_escolar = isset($_GET['ciclo']) ? trim($_GET['ciclo']) : '';

// Validar parámetros
if ($id_turno <= 0 || $id_grado <= 0) {
    echo json_encode([]);
    exit;
}

// Preparar consulta
$query = "SELECT id_grupo, nombre_grupo, color_credencial 
          FROM grupos 
          WHERE id_turno = ? AND id_grado = ? AND activo = 1";
$params = [$id_turno, $id_grado];
$tipos = "ii";

// Si se especificó ciclo escolar
if (!empty($ciclo_escolar)) {
    $query .= " AND ciclo_escolar = ?";
    $params[] = sanitizar_texto($ciclo_escolar);
    $tipos .= "s";
}

$query .= " ORDER BY nombre_grupo";

// Ejecutar consulta
$stmt = $conexion->prepare($query);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$grupos = [];
while ($row = $result->fetch_assoc()) {
    $grupos[] = $row;
}

// Devolver resultados en formato JSON
header('Content-Type: application/json');
echo json_encode($grupos);