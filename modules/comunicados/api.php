<?php
/**
 * API para Seguimiento de Lectura de Comunicados
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}

// Obtener parámetros
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validar token
if (empty($token)) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Decodificar token (formato: hash_id_destinatario)
$partes = explode('_', $token);
if (count($partes) != 2) {
    // Generar imagen de 1x1 transparente (pixel de seguimiento)
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$hash = $partes[0];
$id_destinatario = intval($partes[1]);

// Verificar si el destinatario existe y no ha sido marcado como leído aún
$query = "SELECT cd.id_comunicado, cd.id_alumno, cd.estado, cd.fecha_lectura,
          c.eliminado
          FROM comunicados_destinatarios cd
          JOIN comunicados c ON cd.id_comunicado = c.id_comunicado
          WHERE cd.id_destinatario = ? AND c.eliminado = 0";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_destinatario);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $destinatario = $result->fetch_assoc();
    
    // Verificar si aún no ha sido marcado como leído
    if ($destinatario['estado'] != 'leido') {
        // Actualizar estado a leído
        $query_update = "UPDATE comunicados_destinatarios 
                         SET estado = 'leido', fecha_lectura = NOW() 
                         WHERE id_destinatario = ?";
        $stmt_update = $conexion->prepare($query_update);
        $stmt_update->bind_param("i", $id_destinatario);
        $stmt_update->execute();
    }
}

// Generar imagen de 1x1 transparente (pixel de seguimiento)
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');