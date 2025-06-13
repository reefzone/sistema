<?php
/**
 * Búsqueda de Alumnos para Historial vía AJAX
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../../config/constants.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/historial_functions.php';
require_once '../../../includes/session_checker.php';

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

// Obtener parámetros de búsqueda
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$id_grupo = isset($_GET['grupo']) ? intval($_GET['grupo']) : 0;

// Verificar longitud mínima de búsqueda
if (strlen($busqueda) < 3) {
    echo json_encode([]);
    exit;
}

// Sanitizar búsqueda
$busqueda = sanitizar_texto($busqueda);
$busqueda = "%$busqueda%";

// Preparar consulta base
$query = "SELECT a.id_alumno, a.nombre, a.apellido, a.matricula, g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
          FROM alumnos a
          JOIN grupos g ON a.id_grupo = g.id_grupo
          JOIN grados gr ON g.id_grado = gr.id_grado
          JOIN turnos t ON g.id_turno = t.id_turno
          WHERE a.activo = 1 AND (
              a.nombre LIKE ? OR 
              a.apellido LIKE ? OR 
              a.matricula LIKE ? OR
              CONCAT(a.nombre, ' ', a.apellido) LIKE ? OR
              CONCAT(a.apellido, ' ', a.nombre) LIKE ?
          )";

$params = [$busqueda, $busqueda, $busqueda, $busqueda, $busqueda];
$tipos = "sssss";

// Añadir filtro por grupo si se especificó
if ($id_grupo > 0) {
    $query .= " AND a.id_grupo = ?";
    $params[] = $id_grupo;
    $tipos .= "i";
}

// Completar consulta
$query .= " ORDER BY a.apellido, a.nombre LIMIT 20";

// Ejecutar consulta
$stmt = $conexion->prepare($query);
$stmt->bind_param($tipos, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$alumnos = [];
while ($row = $result->fetch_assoc()) {
    // Obtener resumen del historial para este alumno
    $resumen = obtener_resumen_historial($row['id_alumno']);
    $row['resumen'] = $resumen;
    
    $alumnos[] = $row;
}

// Devolver resultados en formato JSON
header('Content-Type: application/json');
echo json_encode($alumnos);