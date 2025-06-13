<?php
/**
 * Cierre de sesión
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Destruir todas las variables de sesión
session_unset();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redireccionar al login con mensaje de logout exitoso
header('Location: index.php?logout=1');
exit();
?>