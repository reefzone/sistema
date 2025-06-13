<?php
/**
 * Obtener Alumnos de un Grupo (AJAX)
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Configurar cabeceras para devolver JSON
header('Content-Type: application/json');

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar ID del grupo
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'ID de grupo no válido']);
    exit;
}

$id_grupo = intval($_GET['id']);

// Obtener todos los alumnos del grupo
$query = "SELECT id_alumno, apellido_paterno, apellido_materno, nombres, curp 
         FROM alumnos 
         WHERE id_grupo = ? AND activo = 1 
         ORDER BY apellido_paterno, apellido_materno, nombres";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_grupo);
$stmt->execute();
$result = $stmt->get_result();

$alumnos = [];
while ($row = $result->fetch_assoc()) {
    $alumnos[] = [
        'id_alumno' => $row['id_alumno'],
        'apellido_paterno' => htmlspecialchars($row['apellido_paterno']),
        'apellido_materno' => htmlspecialchars($row['apellido_materno']),
        'nombres' => htmlspecialchars($row['nombres']),
        'curp' => htmlspecialchars($row['curp'])
    ];
}

// Devolver datos en formato JSON
echo json_encode($alumnos);