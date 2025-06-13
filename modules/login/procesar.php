<?php
/**
 * Procesamiento de login
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../includes/session_config.php';
require_once '../../includes/functions.php';
require_once '../../config/database.php';

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || !verificar_token_csrf($_POST['csrf_token'])) {
    registrarLog('error', null, null, "Intento de login con token CSRF inválido");
    header('Location: index.php?error=token_invalido');
    exit;
}

// Verificar campos requeridos
if (empty($_POST['username']) || empty($_POST['password'])) {
    header('Location: index.php?error=credenciales');
    exit;
}

// Sanitizar entrada
$username = sanitizar_texto($_POST['username']);
$password = $_POST['password'];

// Consultar usuario en la base de datos
$sql = "SELECT id_usuario, username, password, tipo_usuario, nombre_completo, activo, intentos_fallidos 
        FROM usuarios WHERE username = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

// Verificar si existe el usuario
if ($result->num_rows === 0) {
    registrarLog('acceso', null, null, "Intento de login con usuario inexistente: $username");
    // Retraso para evitar ataques de fuerza bruta
    sleep(1);
    header('Location: index.php?error=credenciales');
    exit;
}

// Obtener datos del usuario
$usuario = $result->fetch_assoc();

// Verificar si la cuenta está activa
if ($usuario['activo'] != 1) {
    registrarLog('acceso', $usuario['id_usuario'], null, "Intento de login en cuenta inactiva: $username");
    header('Location: index.php?error=cuenta_inactiva');
    exit;
}

// Verificar si la cuenta está bloqueada por intentos fallidos
if ($usuario['intentos_fallidos'] >= 5) {
    registrarLog('acceso', $usuario['id_usuario'], null, "Intento de login en cuenta bloqueada: $username");
    header('Location: index.php?error=cuenta_bloqueada');
    exit;
}

// Verificar contraseña
if (!password_verify($password, $usuario['password'])) {
    // Incrementar intentos fallidos
    $intentos = $usuario['intentos_fallidos'] + 1;
    $update = "UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?";
    $stmt_update = $conexion->prepare($update);
    $stmt_update->bind_param('ii', $intentos, $usuario['id_usuario']);
    $stmt_update->execute();
    
    registrarLog('acceso', $usuario['id_usuario'], null, "Contraseña incorrecta para: $username");
    header('Location: index.php?error=credenciales');
    exit;
}

// Restablecer intentos fallidos y actualizar último acceso
$update = "UPDATE usuarios SET intentos_fallidos = 0, ultimo_acceso = NOW() WHERE id_usuario = ?";
$stmt_update = $conexion->prepare($update);
$stmt_update->bind_param('i', $usuario['id_usuario']);
$stmt_update->execute();

// Crear la sesión
$_SESSION['user_id'] = $usuario['id_usuario'];
$_SESSION['user'] = $usuario['username'];
$_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
$_SESSION['nombre_completo'] = $usuario['nombre_completo'];
$_SESSION['tiempo_inicio'] = time();

// Registrar login exitoso
registrarLog('acceso', $usuario['id_usuario'], null, "Login exitoso: $username");

// Redireccionar según el tipo de usuario
switch ($usuario['tipo_usuario']) {
    case 'superadmin':
    case 'organizador':
    case 'consulta':
        header('Location: ../panel_inicio/index.php');
        break;
    default:
        header('Location: index.php');
}
exit;