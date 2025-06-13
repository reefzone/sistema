<?php
/**
 * Configuración de sesión segura
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir constantes si no están incluidas
if (!defined('SESSION_NAME')) {
    require_once __DIR__ . '/../config/constants.php';
}

// Detectar si estamos en HTTPS (importante para producción)
$es_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || $_SERVER['SERVER_PORT'] == 443 
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

// Configurar parámetros de sesión para mayor seguridad
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', $es_https ? 1 : 0); // Solo HTTPS si está disponible
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.name', SESSION_NAME);

// Configuraciones adicionales para producción
if (ENTORNO == 'produccion') {
    ini_set('session.entropy_length', 32);
    ini_set('session.hash_bits_per_character', 6);
}

/**
 * Función para verificar roles de acceso
 *
 * @param array $roles_permitidos Array de roles permitidos
 * @return bool True si el usuario tiene acceso, False si no
 */
function verificar_acceso($roles_permitidos) {
    if (!isset($_SESSION['user']) || !isset($_SESSION['tipo_usuario'])) {
        if (DEBUG_MODE) {
            error_log("Acceso denegado: No hay sesión válida");
        }
        return false;
    }

    if (!in_array($_SESSION['tipo_usuario'], $roles_permitidos)) {
        if (DEBUG_MODE) {
            error_log("Acceso denegado: Rol " . $_SESSION['tipo_usuario'] . " sin permiso");
        }
        return false;
    }

    return true;
}

/**
 * Función para verificar tiempo de inactividad
 *
 * @return bool True si la sesión está activa, False si expiró
 */
function verificar_tiempo_sesion() {
    if (!isset($_SESSION['tiempo_inicio'])) {
        return false;
    }

    if (time() - $_SESSION['tiempo_inicio'] > SESSION_LIFETIME) {
        // Sesión expirada
        if (DEBUG_MODE) {
            error_log("Sesión expirada para usuario: " . ($_SESSION['user'] ?? 'desconocido'));
        }
        return false;
    }

    // Actualizar tiempo
    $_SESSION['tiempo_inicio'] = time();
    return true;
}