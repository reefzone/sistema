<?php
/**
 * Sistema de caché para optimizar consultas frecuentes
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

class Cache {
    private static $instance;
    private $cache_dir;
    private $enabled = true;
    private $default_ttl = 3600; // 1 hora por defecto
    
    /**
     * Constructor (singleton)
     */
    private function __construct() {
        $this->cache_dir = __DIR__ . '/cache/';
        
        // Crear directorio si no existe
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
        
        // Desactivar caché en modo de desarrollo si está configurado
        if (defined('ENTORNO') && ENTORNO == 'desarrollo' && defined('DISABLE_CACHE') && DISABLE_CACHE) {
            $this->enabled = false;
        }
    }
    
    /**
     * Obtener instancia única
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener elemento de caché
     */
    public function get($key) {
        if (!$this->enabled) {
            return false;
        }
        
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = file_get_contents($file);
        $cache = unserialize($data);
        
        // Verificar si expiró
        if (time() > $cache['expires']) {
            $this->delete($key);
            return false;
        }
        
        return $cache['data'];
    }
    
    /**
     * Guardar elemento en caché
     */
    public function set($key, $data, $ttl = null) {
        if (!$this->enabled) {
            return false;
        }
        
        $ttl = $ttl ?: $this->default_ttl;
        
        $cache = [
            'expires' => time() + $ttl,
            'data' => $data
        ];
        
        $file = $this->getCacheFile($key);
        return file_put_contents($file, serialize($cache)) !== false;
    }
    
    /**
     * Eliminar elemento de caché
     */
    public function delete($key) {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    /**
     * Limpiar toda la caché
     */
    public function flush() {
        $files = glob($this->cache_dir . '*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Obtener nombre de archivo de caché
     */
    private function getCacheFile($key) {
        $safe_key = md5($key);
        return $this->cache_dir . $safe_key . '.cache';
    }
    
    /**
     * Verificar si un elemento existe y no ha expirado
     */
    public function exists($key) {
        if (!$this->enabled) {
            return false;
        }
        
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = file_get_contents($file);
        $cache = unserialize($data);
        
        return time() <= $cache['expires'];
    }
}