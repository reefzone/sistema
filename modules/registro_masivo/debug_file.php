<?php
echo "<h2>🔍 DIAGNÓSTICO DEL ARCHIVO</h2>";

$archivo = 'importacion_functions.php';

// 1. Verificar si existe
echo "<h3>1. Archivo:</h3>";
if (file_exists($archivo)) {
    echo "✅ Existe: $archivo<br>";
    echo "📊 Tamaño: " . filesize($archivo) . " bytes<br>";
} else {
    echo "❌ NO existe: $archivo<br>";
    exit;
}

// 2. Mostrar contenido
echo "<h3>2. Contenido del archivo:</h3>";
echo "<textarea style='width:100%; height:200px;'>";
echo htmlspecialchars(file_get_contents($archivo));
echo "</textarea>";

// 3. Verificar si PHP puede parsearlo
echo "<h3>3. Verificación de sintaxis:</h3>";
$output = [];
$return_var = 0;
exec("php -l $archivo 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "✅ Sintaxis PHP: OK<br>";
} else {
    echo "❌ Error de sintaxis:<br>";
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "<br>";
    }
}

// 4. Intentar incluir y ver errores
echo "<h3>4. Incluir archivo:</h3>";
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include_once $archivo;
    echo "✅ Archivo incluido correctamente<br>";
    
    // Verificar funciones después de incluir
    if (function_exists('normalizar_grado')) {
        echo "✅ normalizar_grado: EXISTE AHORA<br>";
        $test = normalizar_grado('1');
        echo "Test: normalizar_grado('1') = '$test'<br>";
    } else {
        echo "❌ normalizar_grado: SIGUE SIN EXISTIR<br>";
        
        // Mostrar todas las funciones definidas en el archivo
        echo "<br><strong>Funciones definidas:</strong><br>";
        $functions = get_defined_functions()['user'];
        foreach ($functions as $func) {
            echo "- $func<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error al incluir: " . $e->getMessage() . "<br>";
}
?>