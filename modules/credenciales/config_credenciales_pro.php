<?php
/**
 * CONFIGURACIÓN PROFESIONAL PARA CREDENCIALES
 * Optimizaciones para producción masiva
 */

// Configuración de memoria para manejar 1200+ alumnos
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Configuración de calidad de imagen
define('CREDENCIAL_CALIDAD_IMAGEN', 95);
define('CREDENCIAL_DPI', 300);
define('CREDENCIAL_ANCHO_MM', 86);
define('CREDENCIAL_ALTO_MM', 54);

// Rutas optimizadas
define('CREDENCIAL_TEMP_DIR', $_SERVER['DOCUMENT_ROOT'] . '/temp/credenciales/');
define('CREDENCIAL_CACHE_DIR', $_SERVER['DOCUMENT_ROOT'] . '/cache/credenciales/');

// Crear directorios si no existen
$dirs = [CREDENCIAL_TEMP_DIR, CREDENCIAL_CACHE_DIR];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

/**
 * Función para optimizar rendimiento en lotes grandes
 */
function optimizarParaProduccion() {
    // Aumentar límites
    ini_set('memory_limit', '1G');
    ini_set('max_execution_time', 600);
    
    // Limpiar cache
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Garbage collection
    gc_collect_cycles();
}

/**
 * Log profesional para debugging
 */
function logCredencial($mensaje, $tipo = 'INFO') {
    $fecha = date('Y-m-d H:i:s');
    $log = "[{$fecha}] [{$tipo}] {$mensaje}" . PHP_EOL;
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/credenciales.log', $log, FILE_APPEND | LOCK_EX);
}
?>