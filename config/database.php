<?php
// Configuración de la base de datos
// Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82

// Detectar entorno automáticamente
$servidor_actual = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

if (strpos($servidor_actual, 'est82.org') !== false) {
    // Configuración para PRODUCCIÓN
    $db_host = 'localhost';
    $db_user = 'estorg_MegaAdmin2025';
    $db_pass = 'Mexico2025@#';
    $db_name = 'estorg_sistema_escolar';
    $db_charset = 'utf8mb4';
} else {
    // Configuración para DESARROLLO (local)
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'sistema_escolar';
    $db_charset = 'latin1';
}

// Crear conexión con manejo de errores
try {
    $conexion = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    
    if (!$conexion) {
        throw new Exception("Error de conexión: " . mysqli_connect_error());
    }
    
    // Establecer charset
    if (!mysqli_set_charset($conexion, $db_charset)) {
        throw new Exception("Error al establecer charset: " . mysqli_error($conexion));
    }
    
    // Configuración para prevenir inyección SQL
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    
} catch (Exception $e) {
    // Log del error
    error_log("Error crítico de base de datos: " . $e->getMessage());
    
    // En desarrollo, mostrar error detallado
    if (strpos($servidor_actual, 'localhost') !== false) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    } else {
        // En producción, mensaje genérico
        die("Error de conexión a la base de datos. Por favor contacte al administrador.");
    }
}
?>