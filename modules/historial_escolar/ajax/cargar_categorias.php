<?php
/**
 * Cargar Categorías para Tipo de Registro vía AJAX
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

// Obtener parámetros
$tipo_registro = isset($_GET['tipo']) ? sanitizar_texto($_GET['tipo']) : '';

// Verificar tipo válido
if (!in_array($tipo_registro, ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'])) {
    echo json_encode([]);
    exit;
}

// Obtener categorías para el tipo seleccionado
$categorias = obtener_categorias_historial($tipo_registro);

// Devolver resultados en formato JSON
header('Content-Type: application/json');
echo json_encode($categorias);