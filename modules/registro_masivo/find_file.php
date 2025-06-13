<?php
echo "<h2>🔍 ENCONTRAR ARCHIVO CORRECTO</h2>";

// 1. Ruta actual donde estamos
echo "<h3>1. Directorio actual:</h3>";
echo "Estamos en: " . __DIR__ . "<br>";
echo "Archivo actual: " . __FILE__ . "<br>";

// 2. Verificar el archivo que debe existir
$archivo_funciones = __DIR__ . '/importacion_functions.php';
echo "<h3>2. Archivo de funciones:</h3>";
echo "Ruta completa: $archivo_funciones<br>";

if (file_exists($archivo_funciones)) {
    echo "✅ Archivo EXISTE<br>";
    echo "📊 Tamaño: " . filesize($archivo_funciones) . " bytes<br>";
    echo "📅 Última modificación: " . date('Y-m-d H:i:s', filemtime($archivo_funciones)) . "<br>";
    
    // Mostrar primeras líneas
    echo "<h3>3. Primeras líneas del archivo:</h3>";
    $handle = fopen($archivo_funciones, 'r');
    if ($handle) {
        echo "<pre>";
        for ($i = 0; $i < 20 && !feof($handle); $i++) {
            echo htmlspecialchars(fgets($handle));
        }
        echo "</pre>";
        fclose($handle);
    }
} else {
    echo "❌ Archivo NO EXISTE<br>";
}

// 3. Verificar permisos
echo "<h3>4. Permisos:</h3>";
if (file_exists($archivo_funciones)) {
    echo "Permisos: " . substr(sprintf('%o', fileperms($archivo_funciones)), -4) . "<br>";
    echo "Escribible: " . (is_writable($archivo_funciones) ? "SÍ" : "NO") . "<br>";
} else {
    echo "Directorio escribible: " . (is_writable(__DIR__) ? "SÍ" : "NO") . "<br>";
}

// 4. Listar todos los archivos .php en el directorio
echo "<h3>5. Archivos PHP en el directorio:</h3>";
$files = glob(__DIR__ . '/*.php');
foreach ($files as $file) {
    echo "- " . basename($file) . " (" . filesize($file) . " bytes)<br>";
}
?>