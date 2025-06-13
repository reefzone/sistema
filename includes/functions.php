<?php
/**
 * Funciones globales del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

/**
 * Registra una entrada en el log del sistema
 *
 * @param string $tipo Tipo de log (acceso, error, operacion)
 * @param int $id_usuario ID del usuario (null si no aplica)
 * @param string $ip Dirección IP (se detecta automáticamente si es null)
 * @param string $descripcion Descripción detallada del evento
 * @return bool Resultado de la operación
 */
function registrarLog($tipo, $id_usuario = null, $ip = null, $descripcion = '') {
    global $conexion;

    // Asegurarse que tenemos conexión a la BD
    if (!isset($conexion)) {
        require_once __DIR__ . '/../config/database.php';
    }

    // Obtener IP si no se proporciona (con mejor detección)
    if ($ip === null) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Si hay múltiples IPs, tomar la primera
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
    }

    // Obtener usuario actual si está en sesión
    $usuario = 'sistema';
    if (isset($_SESSION['user'])) {
        $usuario = $_SESSION['user'];
    } elseif ($id_usuario !== null) {
        // Intenta obtener nombre de usuario desde la BD
        try {
            $query = "SELECT username FROM usuarios WHERE id_usuario = ?";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param('i', $id_usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $usuario = $row['username'];
            }
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Error al obtener usuario para log: " . $e->getMessage());
            }
        }
    }

    // Validar tipo de log
    $tipos_validos = ['acceso', 'error', 'operacion'];
    if (!in_array($tipo, $tipos_validos)) {
        $tipo = 'error';
    }

    try {
        // Insertar en la base de datos
        $query = "INSERT INTO logs_sistema (tipo, usuario, ip, descripcion) VALUES (?, ?, ?, ?)";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param('ssss', $tipo, $usuario, $ip, $descripcion);
        $result = $stmt->execute();

        // También escribir en archivo de log (solo si el directorio existe)
        if (defined('LOGS_DIR') && is_dir(LOGS_DIR)) {
            $fecha = date('Y-m-d H:i:s');
            $log_entry = "[$fecha][$tipo][$usuario][$ip] $descripcion\n";
            $log_file = LOGS_DIR . '/' . date('Y-m-d') . '_sistema.log';
            file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        } elseif (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Directorio de logs no existe: " . (defined('LOGS_DIR') ? LOGS_DIR : 'LOGS_DIR no definido'));
        }

        return $result;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Error al registrar log: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Sanitiza entrada de texto
 *
 * @param string $texto Texto a sanitizar
 * @return string Texto sanitizado
 */
function sanitizar_texto($texto) {
    if (empty($texto)) {
        return '';
    }
    return htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera un token CSRF
 *
 * @return string Token generado
 */
function generar_token_csrf() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 *
 * @param string $token Token a verificar
 * @return bool True si es válido, False si no
 */
function verificar_token_csrf($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Redirige con mensaje de error
 *
 * @param string $url URL de destino
 * @param string $mensaje Mensaje de error
 * @param string $tipo Tipo de mensaje (success, danger, warning, info)
 */
function redireccionar_con_mensaje($url, $mensaje, $tipo = 'danger') {
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = $tipo;
    header('Location: ' . $url);
    exit;
}

/**
 * Muestra mensaje flash (una sola vez)
 *
 * @return string HTML del mensaje o cadena vacía
 */
function mostrar_mensaje() {
    if (isset($_SESSION['mensaje']) && isset($_SESSION['tipo_mensaje'])) {
        $mensaje = sanitizar_texto($_SESSION['mensaje']);
        $tipo = sanitizar_texto($_SESSION['tipo_mensaje']);
        
        // Validar tipo de mensaje
        $tipos_validos = ['success', 'danger', 'warning', 'info'];
        if (!in_array($tipo, $tipos_validos)) {
            $tipo = 'info';
        }
        
        // Limpiar mensaje para que no se muestre nuevamente
        unset($_SESSION['mensaje']);
        unset($_SESSION['tipo_mensaje']);
        
        return '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">
                    ' . $mensaje . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>';
    }
    return '';
}

/**
 * Verifica si una fecha es válida
 *
 * @param string $fecha Fecha en formato YYYY-MM-DD
 * @return bool True si es válida, False si no
 */
function es_fecha_valida($fecha) {
    if (empty($fecha)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('Y-m-d') === $fecha;
}

/**
 * Verifica si un CURP es válido
 *
 * @param string $curp CURP a validar
 * @return bool True si es válido, False si no
 */
function es_curp_valido($curp) {
    if (empty($curp) || strlen($curp) !== 18) {
        return false;
    }
    
    // Convertir a mayúsculas
    $curp = strtoupper($curp);
    
    // Patrón para validar el CURP
    $patron = '/^([A-Z][AEIOUX][A-Z]{2})(\d{2})(\d{2})(\d{2})([HM])([A-Z]{2})([BCDFGHJKLMNPQRSTVWXYZ]{3})([0-9A-Z])(\d)$/';
    return preg_match($patron, $curp);
}

/**
 * Formatea una fecha de MySQL a formato legible
 *
 * @param string $fecha Fecha en formato YYYY-MM-DD o YYYY-MM-DD HH:MM:SS
 * @param bool $incluir_hora Si debe incluir la hora
 * @return string Fecha formateada
 */
function formatear_fecha($fecha, $incluir_hora = false) {
    if (empty($fecha) || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
        return '';
    }
    
    try {
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return '';
        }
        
        if ($incluir_hora) {
            return date('d/m/Y H:i', $timestamp);
        } else {
            return date('d/m/Y', $timestamp);
        }
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Error al formatear fecha '$fecha': " . $e->getMessage());
        }
        return '';
    }
}

/**
 * Valida un email
 *
 * @param string $email Email a validar
 * @return bool True si es válido, False si no
 */
function es_email_valido($email) {
    return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida un número de teléfono mexicano
 *
 * @param string $telefono Teléfono a validar
 * @return bool True si es válido, False si no
 */
function es_telefono_valido($telefono) {
    if (empty($telefono)) {
        return false;
    }
    
    // Limpiar el teléfono de espacios, guiones y paréntesis
    $telefono_limpio = preg_replace('/[\s\-\(\)]/', '', $telefono);
    
    // Verificar que solo contenga números
    if (!ctype_digit($telefono_limpio)) {
        return false;
    }
    
    // Verificar longitud (10 dígitos para México)
    return strlen($telefono_limpio) === 10;
}

/**
 * Genera una contraseña aleatoria
 *
 * @param int $longitud Longitud de la contraseña
 * @return string Contraseña generada
 */
function generar_password($longitud = 8) {
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%&*';
    $password = '';
    $max = strlen($caracteres) - 1;
    
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $caracteres[random_int(0, $max)];
    }
    
    return $password;
}

/**
 * Encripta una contraseña
 *
 * @param string $password Contraseña a encriptar
 * @return string Contraseña encriptada
 */
function encriptar_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verifica una contraseña
 *
 * @param string $password Contraseña en texto plano
 * @param string $hash Hash almacenado
 * @return bool True si coincide, False si no
 */
function verificar_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Convierte texto a formato de nombre propio
 *
 * @param string $texto Texto a convertir
 * @return string Texto en formato nombre propio
 */
function formato_nombre_propio($texto) {
    if (empty($texto)) {
        return '';
    }
    
    return mb_convert_case(trim($texto), MB_CASE_TITLE, 'UTF-8');
}

/**
 * Calcula la edad basada en la fecha de nacimiento
 *
 * @param string $fecha_nacimiento Fecha en formato YYYY-MM-DD
 * @return int|false Edad en años o false si hay error
 */
function calcular_edad($fecha_nacimiento) {
    if (!es_fecha_valida($fecha_nacimiento)) {
        return false;
    }
    
    try {
        $nacimiento = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($nacimiento);
        return $edad->y;
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Error al calcular edad: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Limpia y valida un número entero
 *
 * @param mixed $valor Valor a limpiar
 * @param int $min Valor mínimo permitido
 * @param int $max Valor máximo permitido
 * @return int|false Número limpio o false si no es válido
 */
function limpiar_numero_entero($valor, $min = null, $max = null) {
    $numero = filter_var($valor, FILTER_VALIDATE_INT);
    
    if ($numero === false) {
        return false;
    }
    
    if ($min !== null && $numero < $min) {
        return false;
    }
    
    if ($max !== null && $numero > $max) {
        return false;
    }
    
    return $numero;
}

/**
 * Formatea un número con separadores de miles
 *
 * @param float $numero Número a formatear
 * @param int $decimales Número de decimales
 * @return string Número formateado
 */
function formatear_numero($numero, $decimales = 2) {
    if (!is_numeric($numero)) {
        return '0';
    }
    
    return number_format($numero, $decimales, '.', ',');
}

/**
 * Obtiene el año escolar actual basado en la fecha
 *
 * @return string Año escolar en formato "2024-2025"
 */
function obtener_año_escolar() {
    $año_actual = date('Y');
    $mes_actual = date('n');
    
    // El año escolar inicia en agosto (mes 8)
    if ($mes_actual >= 8) {
        return $año_actual . '-' . ($año_actual + 1);
    } else {
        return ($año_actual - 1) . '-' . $año_actual;
    }
}

/**
 * Crea los directorios necesarios si no existen
 *
 * @param string $directorio Ruta del directorio
 * @param int $permisos Permisos del directorio
 * @return bool True si se creó o ya existe, False si hay error
 */
function crear_directorio_si_no_existe($directorio, $permisos = 0755) {
    if (is_dir($directorio)) {
        return true;
    }
    
    try {
        return mkdir($directorio, $permisos, true);
    } catch (Exception $e) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Error al crear directorio '$directorio': " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Obtiene el tamaño de un archivo en formato legible
 *
 * @param int $bytes Tamaño en bytes
 * @return string Tamaño formateado
 */
function formatear_tamaño_archivo($bytes) {
    if ($bytes == 0) return '0 B';
    
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $exponente = floor(log($bytes, 1024));
    $exponente = min($exponente, count($unidades) - 1);
    
    return round($bytes / (1024 ** $exponente), 2) . ' ' . $unidades[$exponente];
}