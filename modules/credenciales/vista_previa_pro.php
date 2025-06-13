<?php
/**
 * VISTA PREVIA PROFESIONAL - EXACTA AL PDF
 */
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once './pdf_ultra_pro.php';

// Obtener configuración
$query_config = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
$result_config = $conexion->query($query_config);
$config = $result_config->fetch_assoc();

// Datos de ejemplo
$alumno_ejemplo = [
    'nombre' => 'EJEMPLO',
    'apellido' => 'ESTUDIANTE',
    'matricula' => '2024001',
    'nombre_grado' => '1°',
    'nombre_grupo' => 'A',
    'nombre_turno' => 'MATUTINO',
    'ciclo_escolar' => '2024-2025',
    'ruta_foto' => ''
];

$credencial = new CredencialUltraProfesional($conexion, $config);
$html = $credencial->generarHTMLProfesional($alumno_ejemplo);

// Mostrar HTML directo para vista previa
header('Content-Type: text/html; charset=UTF-8');
echo $html;
?>