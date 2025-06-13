<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    $busqueda = $_GET['q'] ?? '';
    
    if (strlen($busqueda) < 3) {
        echo json_encode([]);
        exit;
    }
    
    $busqueda = "%" . $busqueda . "%";
    
    $query = "SELECT id_alumno, nombres, apellido_paterno, apellido_materno, curp 
              FROM alumnos 
              WHERE activo = 1 
              AND (nombres LIKE ? OR apellido_paterno LIKE ? OR apellido_materno LIKE ?)
              LIMIT 10";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alumnos = [];
    while ($row = $result->fetch_assoc()) {
        $alumnos[] = [
            'id_alumno' => $row['id_alumno'],
            'nombre' => $row['nombres'],
            'apellido' => $row['apellido_paterno'] . ' ' . $row['apellido_materno'],
            'matricula' => $row['curp']
        ];
    }
    
    echo json_encode($alumnos);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>