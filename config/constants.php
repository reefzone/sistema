<?php
/**
 * Constantes del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Detectar entorno automáticamente
$servidor_actual = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

if (strpos($servidor_actual, 'est82.org') !== false) {
    define('ENTORNO', 'produccion');
} else {
    define('ENTORNO', 'desarrollo');
}

// Configurar reportes de errores según entorno
if (ENTORNO == 'desarrollo') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

// Rutas base según entorno
if (ENTORNO == 'produccion') {
    define('BASE_URL', '/'); // En producción, asumiendo que está en la raíz del dominio
    define('UPLOADS_DIR', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
    define('LOGS_DIR', $_SERVER['DOCUMENT_ROOT'] . '/logs/');
} else {
    define('BASE_URL', '/'); // Ajusta si tu proyecto local está en una subcarpeta
    define('UPLOADS_DIR', $_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'uploads/');
    define('LOGS_DIR', $_SERVER['DOCUMENT_ROOT'] . BASE_URL . 'logs/');
}

// Información de la escuela
define('NOMBRE_ESCUELA', 'ESCUELA SECUNDARIA TECNICA #82');
define('CCT_ESCUELA', '00AAA0000A'); // Cambiar por la CCT real

// Configuración de email según entorno
if (ENTORNO == 'produccion') {
    define('MAIL_HOST', 'smtp.gmail.com');
    define('MAIL_PORT', 587);
    define('MAIL_USERNAME', 'tu_correo@gmail.com'); // Cambiar con el correo real
    define('MAIL_PASSWORD', 'tu_contraseña'); // Cambiar con la contraseña real
    define('MAIL_FROM_ADDRESS', 'tu_correo@gmail.com');
    define('MAIL_FROM_NAME', 'ESCUELA SECUNDARIA TECNICA #82');
} else {
    // Configuración de email para desarrollo (puede ser la misma o de pruebas)
    define('MAIL_HOST', 'smtp.gmail.com');
    define('MAIL_PORT', 587);
    define('MAIL_USERNAME', 'desarrollo@gmail.com');
    define('MAIL_PASSWORD', 'password_desarrollo');
    define('MAIL_FROM_ADDRESS', 'desarrollo@gmail.com');
    define('MAIL_FROM_NAME', 'ESCUELA SECUNDARIA TECNICA #82 - DEV');
}

// Configuración de sesión
define('SESSION_NAME', 'EST82_SESSION');
define('SESSION_LIFETIME', 1800); // 30 minutos en segundos

// Depuración
define('DEBUG_MODE', ENTORNO == 'desarrollo');