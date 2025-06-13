<?php
/**
 * Verificador de sesión para módulos del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/functions.php';

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
    if (DEBUG_MODE) {
        error_log("Sesión iniciada con ID: " . session_id());
    }
}

// Si está en modo depuración, mostrar datos de sesión
if (DEBUG_MODE) {
    error_log("Datos en la sesión: " . print_r($_SESSION, true));
}

// Definir los roles permitidos (puede sobrescribirse en cada módulo)
$roles_permitidos = ['superadmin', 'organizador', 'consulta'];

// Verificar que el usuario tenga permisos
if (!isset($_SESSION['user']) || !isset($_SESSION['tipo_usuario'])) {
    // Redirigir a login
    header('Location: ' . BASE_URL . 'modules/login/index.php?error=sesion_expirada');
    exit;
}

// Verificar tiempo de sesión
if (!verificar_tiempo_sesion()) {
    // Cerrar sesión
    session_destroy();
    header('Location: ' . BASE_URL . 'modules/login/index.php?error=sesion_expirada');
    exit;
}

// Verificar rol de usuario
if (!verificar_acceso($roles_permitidos)) {
    header('Location: ' . BASE_URL . 'modules/login/index.php?error=acceso_denegado');
    exit;
}

// Registro de acceso exitoso
$pagina_actual = basename($_SERVER['PHP_SELF']);
$ip = $_SERVER['REMOTE_ADDR'];
$usuario = $_SESSION['user'];
$rol = $_SESSION['tipo_usuario'];

// Registrar acceso en el log
registrarLog('acceso', $_SESSION['user_id'], $ip, "Acceso a página: $pagina_actual");