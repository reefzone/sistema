<?php
/**
 * Vista Previa de Credencial
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once './pdf.php';

// Cabecera para imagen
header('Content-Type: image/png');

// Obtener ID de configuración si se proporciona
$id_config = isset($_GET['id']) ? intval($_GET['id']) : 0;
$color = isset($_GET['color']) ? $_GET['color'] : '#0066CC';

// Sanitizar color
if (!preg_match('/#[a-fA-F0-9]{6}/', $color)) {
    $color = '#0066CC'; // Color predeterminado si no es válido
}

// Obtener configuración específica o predeterminada
if ($id_config > 0) {
    $query = "SELECT * FROM credenciales_config WHERE id_config = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_config);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $config = $result->fetch_assoc();
    } else {
        // Si no existe la configuración específica, usar la predeterminada
        $query = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
        $result = $conexion->query($query);
        $config = $result->num_rows > 0 ? $result->fetch_assoc() : [];
    }
} else {
    // Usar configuración predeterminada
    $query = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
    $result = $conexion->query($query);
    $config = $result->num_rows > 0 ? $result->fetch_assoc() : [];
}

// Si no hay configuración, usar valores predeterminados
if (empty($config)) {
    $config = [
        'logo_path' => '',
        'firma_path' => '',
        'texto_inferior' => 'Esta credencial acredita al portador como alumno regular de la Escuela Secundaria Técnica #82.',
        'vigencia' => 'Válido durante el ciclo escolar actual',
        'mostrar_foto' => 1,
        'mostrar_qr' => 0
    ];
}

// Generar vista previa
$credencial = new CredencialPDF($conexion, $config);
$imagen = $credencial->generarVistaPrevia($color);

// Mostrar imagen
readfile($imagen);

// Eliminar archivo temporal
@unlink($imagen);