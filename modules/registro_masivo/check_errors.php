<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 VERIFICANDO ERRORES</h2>";

// 1. Verificar conexión a base de datos
echo "<h3>1. Conexión a BD:</h3>";
try {
    require_once '../../config/database.php';
    echo "✅ Conexión a BD: OK<br>";
} catch (Exception $e) {
    echo "❌ Error en BD: " . $e->getMessage() . "<br>";
}

// 2. Verificar si existe el archivo de funciones
echo "<h3>2. Archivo de funciones:</h3>";
$archivo_funciones = 'importacion_functions.php';
if (file_exists($archivo_funciones)) {
    echo "✅ Archivo existe: $archivo_funciones<br>";
    
    // Verificar si se puede incluir
    try {
        require_once $archivo_funciones;
        echo "✅ Archivo incluido: OK<br>";
    } catch (Exception $e) {
        echo "❌ Error al incluir: " . $e->getMessage() . "<br>";
    }
    
    // Verificar si las funciones existen
    echo "<h3>3. Funciones disponibles:</h3>";
    $funciones = ['normalizar_grado', 'buscar_id_grupo', 'procesar_archivo_csv', 'validar_datos_alumno'];
    foreach ($funciones as $funcion) {
        if (function_exists($funcion)) {
            echo "✅ $funcion: EXISTE<br>";
        } else {
            echo "❌ $funcion: NO EXISTE<br>";
        }
    }
    
} else {
    echo "❌ Archivo NO existe: $archivo_funciones<br>";
}

echo "<h3>4. Prueba de normalización:</h3>";
if (function_exists('normalizar_grado')) {
    $test = normalizar_grado('1');
    echo "normalizar_grado('1') = '$test'<br>";
} else {
    echo "❌ Función normalizar_grado no disponible<br>";
}
?>