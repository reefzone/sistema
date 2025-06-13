<?php
/**
 * Editor Visual de Plantillas de Credenciales - ULTRA PROFESSIONAL EDITION
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_config.php';

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    header('Location: ../login/index.php?error=acceso_denegado');
    exit;
}

// Crear directorios de upload si no existen
$upload_dirs = [
    '../../uploads/credenciales/plantillas/logos/',
    '../../uploads/credenciales/plantillas/firmas/',
    '../../uploads/credenciales/plantillas/fondos/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Generar token CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Procesamiento del formulario
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = "Token de seguridad inválido";
        $tipo_mensaje = 'danger';
    } else {
        $nombre_plantilla = isset($_POST['nombre_plantilla']) ? trim($_POST['nombre_plantilla']) : '';
        $es_default = isset($_POST['es_default']) ? 1 : 0;
        $texto_inferior = isset($_POST['texto_inferior']) ? trim($_POST['texto_inferior']) : '';
        $vigencia = isset($_POST['vigencia']) ? trim($_POST['vigencia']) : '';
        $mostrar_foto = isset($_POST['mostrar_foto']) ? 1 : 0;
        
        // Configuración visual
        $color_fondo = isset($_POST['color_fondo']) ? $_POST['color_fondo'] : '#FFFFFF';
        $color_borde = isset($_POST['color_borde']) ? $_POST['color_borde'] : '#1a1a2e';
        $grosor_borde = isset($_POST['grosor_borde']) ? intval($_POST['grosor_borde']) : 1;
        $esquinas_redondeadas = isset($_POST['esquinas_redondeadas']) ? intval($_POST['esquinas_redondeadas']) : 12;
        $fuente_titulo = isset($_POST['fuente_titulo']) ? $_POST['fuente_titulo'] : 'Inter';
        $tamano_titulo = isset($_POST['tamano_titulo']) ? intval($_POST['tamano_titulo']) : 12;
        $color_titulo = isset($_POST['color_titulo']) ? $_POST['color_titulo'] : '#ffffff';
        $tamano_texto = isset($_POST['tamano_texto']) ? intval($_POST['tamano_texto']) : 9;
        $color_texto = isset($_POST['color_texto']) ? $_POST['color_texto'] : '#ffffff';
        
        // Textos personalizables
        $tipo_credencial = isset($_POST['tipo_credencial']) ? trim($_POST['tipo_credencial']) : 'CREDENCIAL DE ESTUDIANTE';
        $nombre_escuela = isset($_POST['nombre_escuela']) ? trim($_POST['nombre_escuela']) : 'ESCUELA SECUNDARIA TÉCNICA #82';
        $telefono_contacto = isset($_POST['telefono_contacto']) ? trim($_POST['telefono_contacto']) : '(55) 1234-5678';
        $direccion_contacto = isset($_POST['direccion_contacto']) ? trim($_POST['direccion_contacto']) : 'Calle Principal s/n, Col. Centro';
        $website_contacto = isset($_POST['website_contacto']) ? trim($_POST['website_contacto']) : 'www.est82.edu.mx';
        
        // Sanitizar datos
        $nombre_plantilla = htmlspecialchars($nombre_plantilla, ENT_QUOTES, 'UTF-8');
        $texto_inferior = htmlspecialchars($texto_inferior, ENT_QUOTES, 'UTF-8');
        $vigencia = htmlspecialchars($vigencia, ENT_QUOTES, 'UTF-8');
        $tipo_credencial = htmlspecialchars($tipo_credencial, ENT_QUOTES, 'UTF-8');
        $nombre_escuela = htmlspecialchars($nombre_escuela, ENT_QUOTES, 'UTF-8');
        $telefono_contacto = htmlspecialchars($telefono_contacto, ENT_QUOTES, 'UTF-8');
        $direccion_contacto = htmlspecialchars($direccion_contacto, ENT_QUOTES, 'UTF-8');
        $website_contacto = htmlspecialchars($website_contacto, ENT_QUOTES, 'UTF-8');
        
        // Validar campos obligatorios
        $errores = [];
        
        if (empty($nombre_plantilla)) {
            $errores[] = "El nombre de la plantilla es obligatorio";
        }
        
        // Procesar uploads
        $logo_path = null;
        $firma_path = null;
        
        // Upload de logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $result = procesar_upload_imagen('logo', $upload_dirs[0]);
            if ($result['exito']) {
                $logo_path = $result['ruta_relativa'];
            } else {
                $errores[] = "Logo: " . $result['mensaje'];
            }
        }
        
        // Upload de firma
        if (isset($_FILES['firma']) && $_FILES['firma']['error'] == 0) {
            $result = procesar_upload_imagen('firma', $upload_dirs[1]);
            if ($result['exito']) {
                $firma_path = $result['ruta_relativa'];
            } else {
                $errores[] = "Firma: " . $result['mensaje'];
            }
        }
        
        // Si hay errores, mostrar mensaje
        if (!empty($errores)) {
            $mensaje = implode('. ', $errores);
            $tipo_mensaje = 'danger';
        } else {
            // Iniciar transacción
            $conexion->begin_transaction();
            
            try {
                // Si es plantilla predeterminada, desmarcar las demás
                if ($es_default) {
                    $query_update = "UPDATE credenciales_config SET es_default = 0";
                    $conexion->query($query_update);
                }
                
                // Verificar si estamos editando una plantilla existente
                $id_config = isset($_POST['id_config']) ? intval($_POST['id_config']) : 0;
                
                // Preparar configuración visual como JSON
                $config_visual = json_encode([
                    'color_fondo' => $color_fondo,
                    'color_borde' => $color_borde,
                    'grosor_borde' => $grosor_borde,
                    'esquinas_redondeadas' => $esquinas_redondeadas,
                    'fuente_titulo' => $fuente_titulo,
                    'tamano_titulo' => $tamano_titulo,
                    'color_titulo' => $color_titulo,
                    'tamano_texto' => $tamano_texto,
                    'color_texto' => $color_texto,
                    'tipo_credencial' => $tipo_credencial,
                    'nombre_escuela' => $nombre_escuela,
                    'telefono_contacto' => $telefono_contacto,
                    'direccion_contacto' => $direccion_contacto,
                    'website_contacto' => $website_contacto
                ]);
                
                if ($id_config > 0) {
                    // Obtener datos actuales para mantener archivos si no se suben nuevos
                    $query_actual = "SELECT logo_path, firma_path FROM credenciales_config WHERE id_config = ?";
                    $stmt_actual = $conexion->prepare($query_actual);
                    $stmt_actual->bind_param("i", $id_config);
                    $stmt_actual->execute();
                    $result_actual = $stmt_actual->get_result();
                    
                    if ($result_actual->num_rows > 0) {
                        $data_actual = $result_actual->fetch_assoc();
                        // Mantener rutas si no se subieron nuevos archivos
                        $logo_path = $logo_path ?? $data_actual['logo_path'];
                        $firma_path = $firma_path ?? $data_actual['firma_path'];
                    }
                    
                    // Actualizar plantilla existente
                   $query = "UPDATE credenciales_config SET 
          nombre_plantilla = ?, 
          es_default = ?, 
          logo_path = ?, 
          firma_path = ?, 
          texto_inferior = ?, 
          vigencia = ?, 
          mostrar_foto = ?, 
          mostrar_qr = 0,
          config_visual = ?
          WHERE id_config = ?";

$stmt = $conexion->prepare($query);
$stmt->bind_param("sissssiisi", 
                 $nombre_plantilla, 
                 $es_default, 
                 $logo_path, 
                 $firma_path, 
                 $texto_inferior, 
                 $vigencia, 
                 $mostrar_foto, 
                 $config_visual,
                 $id_config);
                    
                    $stmt->execute();
                    $mensaje = "¡Plantilla actualizada con éxito! 🎉";
                    
                } else {
                    // Insertar nueva plantilla
                    $query = "INSERT INTO credenciales_config 
          (nombre_plantilla, es_default, logo_path, firma_path, texto_inferior, 
           vigencia, mostrar_foto, mostrar_qr, config_visual, creado_por) 
          VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";

$stmt = $conexion->prepare($query);
$stmt->bind_param("sissssisi", 
                 $nombre_plantilla, 
                 $es_default, 
                 $logo_path, 
                 $firma_path, 
                 $texto_inferior, 
                 $vigencia, 
                 $mostrar_foto, 
                 $config_visual,
                 $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        $id_config = $conexion->insert_id;
                        $mensaje = "¡Plantilla creada exitosamente! 🎨";
                    } else {
                        throw new Exception("Error al insertar la plantilla: " . $stmt->error);
                    }
                }
                
                // Confirmar transacción
                $conexion->commit();
                $tipo_mensaje = 'success';
                
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $conexion->rollback();
                
                // Registrar el error
                error_log("Error al guardar plantilla: " . $e->getMessage());
                
                $mensaje = "Error al guardar la plantilla: " . $e->getMessage();
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Cargar configuración existente si se está editando
$id_config = isset($_GET['id']) ? intval($_GET['id']) : 0;
$config = null;

if ($id_config > 0) {
    $query = "SELECT * FROM credenciales_config WHERE id_config = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_config);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $config = $result->fetch_assoc();
    } else {
        header('Location: index.php?error=plantilla_no_existe');
        exit;
    }
}

// Incluir header
include '../../includes/header.php';
?>
<style>
/* Importar fuente profesional */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap');

.designer-container {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    min-height: 100vh;
    padding: 20px 0;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.designer-card {
    background: rgba(255, 255, 255, 0.98);
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.2);
}

.tools-panel {
    background: linear-gradient(145deg, #f8fafc, #e2e8f0);
    border-radius: 15px;
    padding: 20px;
    height: 85vh;
    overflow-y: auto;
    box-shadow: inset 0 2px 10px rgba(0,0,0,0.05);
}

.canvas-area {
    background: linear-gradient(145deg, #f1f5f9, #e2e8f0);
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    position: relative;
    min-height: 85vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* CREDENCIAL ULTRA PROFESIONAL */
.credencial-licencia {
    width: 216px;
    height: 340px;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    box-shadow: 
        0 20px 40px rgba(0,0,0,0.12),
        0 8px 16px rgba(0,0,0,0.08),
        inset 0 1px 0 rgba(255,255,255,0.9);
    position: relative;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    margin: 10px;
    overflow: hidden;
    transform-style: preserve-3d;
    border: 1px solid rgba(0,0,0,0.08);
}

.credencial-licencia:hover {
    transform: scale(1.02) rotateY(1deg);
    box-shadow: 
        0 30px 60px rgba(0,0,0,0.18),
        0 12px 24px rgba(0,0,0,0.12),
        inset 0 1px 0 rgba(255,255,255,0.95);
}

.credencial-frente, .credencial-reverso {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    padding: 0;
    font-family: 'Inter', sans-serif;
    backface-visibility: hidden;
    transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: flex;
    flex-direction: column;
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 16px;
    overflow: hidden;
}

.credencial-reverso {
    transform: rotateY(180deg);
}

.credencial-container.flipped .credencial-frente {
    transform: rotateY(-180deg);
}

.credencial-container.flipped .credencial-reverso {
    transform: rotateY(0deg);
}

.credencial-container {
    perspective: 1200px;
    cursor: pointer;
}

/* HEADER CORPORATIVO ULTRA PROFESIONAL */
.credencial-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    padding: 16px 16px 12px 16px;
    position: relative;
    border-radius: 16px 16px 0 0;
    margin-bottom: 0;
    min-height: 80px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.3);
    overflow: hidden;
}

.credencial-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%, rgba(255,255,255,0.05) 100%);
    border-radius: 16px 16px 0 0;
}

.credencial-header::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 50%, #8b5cf6 100%);
    box-shadow: 0 0 15px rgba(59, 130, 246, 0.6);
}

.logo-container {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 64px;
    height: 36px;
    z-index: 10;
    background: rgba(255,255,255,0.96);
    border-radius: 10px;
    padding: 4px;
    box-shadow: 
        0 4px 15px rgba(0,0,0,0.15),
        0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid rgba(255,255,255,0.4);
}

.logo-preview {
    width: 100%;
    height: 100%;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 7px;
    color: #0f172a;
    font-weight: 700;
    overflow: hidden;
    background: linear-gradient(145deg, #f8fafc, #ffffff);
    letter-spacing: 0.4px;
    text-transform: uppercase;
}

.logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* TEXTOS CORPORATIVOS PROFESIONALES */
.escuela-nombre {
    font-size: var(--tamano-titulo, 11px);
    font-weight: 800;
    color: var(--color-titulo, #ffffff);
    line-height: 1.1;
    margin-bottom: 6px;
    padding-right: 70px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.4);
    letter-spacing: 0.5px;
    text-align: center;
    font-family: var(--fuente-titulo, 'Inter');
    position: relative;
    z-index: 1;
    text-transform: uppercase;
}

.credencial-tipo {
    font-size: var(--tamano-texto, 8px);
    font-weight: 600;
    color: var(--color-texto, rgba(255,255,255,0.9));
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    text-align: center;
    text-shadow: 0 1px 3px rgba(0,0,0,0.4);
    font-family: var(--fuente-titulo, 'Inter');
    position: relative;
    z-index: 1;
}

.ciclo-escolar {
    font-size: 7px;
    color: rgba(255,255,255,0.8);
    text-align: center;
    font-weight: 500;
    letter-spacing: 0.8px;
    position: relative;
    z-index: 1;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
}

/* BODY REDISEÑADO CON ESPACIADO PROFESIONAL */
.credencial-body {
    flex: 1;
    padding: 18px 16px 0 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
}

.foto-seccion {
    text-align: center;
    margin-bottom: 8px;
}

.foto-alumno {
    width: 70px;
    height: 85px;
    background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
    border: 3px solid #ffffff;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    color: #0f172a;
    font-weight: 700;
    margin: 0 auto;
    box-shadow: 
        0 8px 20px rgba(0,0,0,0.12),
        0 3px 6px rgba(0,0,0,0.08);
    position: relative;
    overflow: hidden;
    letter-spacing: 0.5px;
}

.foto-alumno::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(59,130,246,0.06) 0%, transparent 100%);
    border-radius: 11px;
}

.datos-alumno {
    flex: 1;
    background: rgba(255,255,255,0.9);
    padding: 12px;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 
        0 4px 12px rgba(0,0,0,0.06),
        inset 0 1px 0 rgba(255,255,255,0.8);
    min-height: 120px;
    display: flex;
    flex-direction: column;
}

.nombre-alumno {
    font-size: 11px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
    text-align: center;
    line-height: 1.2;
    letter-spacing: 0.4px;
    font-family: 'Inter', sans-serif;
    text-transform: uppercase;
}

.dato-alumno {
    font-size: 8px;
    margin-bottom: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 8px;
    background: rgba(15,23,42,0.04);
    border-radius: 6px;
    border-left: 2px solid #3b82f6;
}

.dato-alumno .label {
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    font-size: 7px;
}

.dato-alumno .valor {
    font-weight: 700;
    color: #0f172a;
    font-size: 8px;
    letter-spacing: 0.2px;
}

/* FOOTER REDISEÑADO PARA EL FRENTE */
.credencial-footer {
    margin-top: auto;
    padding: 10px;
    background: rgba(248,250,252,0.9);
    border-top: 1px solid rgba(0,0,0,0.06);
}

.texto-inferior {
    font-size: 6px;
    text-align: center;
    color: #64748b;
    line-height: 1.3;
    background: rgba(255,255,255,0.8);
    padding: 6px;
    border-radius: 6px;
    border: 1px solid rgba(0,0,0,0.06);
    font-weight: 500;
    font-family: 'Inter', sans-serif;
}

/* REVERSO CORPORATIVO ULTRA PROFESIONAL - OPTIMIZADO */
.reverso-content {
    display: flex;
    flex-direction: column;
    height: 100%;
    padding: 16px;
    background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
}

.vigencia-section {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    padding: 12px;
    border-radius: 12px;
    text-align: center;
    margin-bottom: 14px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}

.vigencia-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255,255,255,0.08) 0%, transparent 100%);
    border-radius: 12px;
}

.vigencia-section::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 50%, #8b5cf6 100%);
    box-shadow: 0 0 8px rgba(59, 130, 246, 0.4);
}

.vigencia-title {
    font-size: 10px;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

.vigencia-texto {
    font-size: 8px;
    color: rgba(255,255,255,0.95);
    font-weight: 600;
    line-height: 1.3;
    position: relative;
    z-index: 1;
    font-family: 'Inter', sans-serif;
}

.contacto-section {
    flex: 1;
    background: rgba(255,255,255,0.95);
    padding: 14px;
    border-radius: 12px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 
        0 3px 10px rgba(0,0,0,0.04),
        inset 0 1px 0 rgba(255,255,255,0.9);
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100px;
}

.contacto-title {
    font-size: 7px;
    font-weight: 800;
    color: #475569;
    margin-bottom: 10px;
    line-height: 1.3;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #3b82f6;
    padding-bottom: 6px;
    font-family: 'Inter', sans-serif;
}

.contacto-info {
    font-size: 7px;
    color: #64748b;
    line-height: 1.5;
    text-align: center;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
}

.contacto-info div {
    margin-bottom: 3px;
}

.contacto-info strong {
    color: #0f172a;
    font-weight: 800;
}

/* FIRMA DEL DIRECTOR EN EL REVERSO - OPTIMIZADA */
.firma-director-reverso {
    text-align: center;
    margin-top: 12px;
    background: rgba(255,255,255,0.9);
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}

.firma-img {
    width: 65px;
    height: 26px;
    background: linear-gradient(145deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 6px;
    color: #0f172a;
    font-weight: 700;
    margin-bottom: 4px;
    overflow: hidden;
    border: 1px solid rgba(0,0,0,0.08);
    letter-spacing: 0.3px;
}

.firma-img img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.director-texto {
    font-size: 7px;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-family: 'Inter', sans-serif;
}

/* HERRAMIENTAS */
.tool-group {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border-left: 4px solid #3b82f6;
}

.tool-group h6 {
    color: #0f172a;
    font-weight: 700;
    margin-bottom: 12px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-family: 'Inter', sans-serif;
}

.color-picker {
    width: 50px;
    height: 32px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.slider-control {
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: linear-gradient(90deg, #3b82f6, #8b5cf6);
    outline: none;
    cursor: pointer;
    -webkit-appearance: none;
    appearance: none;
}

.slider-control::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.slider-control::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.save-btn {
    background: linear-gradient(45deg, #3b82f6, #8b5cf6);
    border: none;
    color: white;
    padding: 14px 32px;
    border-radius: 12px;
    font-weight: 700;
    font-family: 'Inter', sans-serif;
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.save-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
    color: white;
}

.flip-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.95);
    border: none;
    border-radius: 12px;
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    color: #0f172a;
    font-size: 16px;
}

.flip-btn:hover {
    background: white;
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}

.form-control, .form-select {
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}

.form-label {
    font-weight: 600;
    color: #374151;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    margin-bottom: 6px;
}

/* Plantillas rápidas */
.plantilla-btn {
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 10px;
    padding: 8px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    font-size: 12px;
}

.plantilla-btn:hover {
    border-color: #3b82f6;
    background: #f8fafc;
    transform: translateY(-1px);
}

/* Efectos y animaciones adicionales */
.credencial-licencia {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .credencial-licencia {
        transform: scale(0.85);
    }
    
    .tools-panel {
        height: auto;
        max-height: 70vh;
    }
    
    .canvas-area {
        min-height: auto;
        padding: 20px;
    }
}

/* Estados de los elementos */
.form-check-input:checked {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.btn-outline-primary {
    border-color: #3b82f6;
    color: #3b82f6;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
}

.btn-outline-primary:hover {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: white;
}

/* Mejoras adicionales para elementos específicos */
.alert {
    border-radius: 12px;
    border: none;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(145deg, #ecfdf5, #d1fae5);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(145deg, #fef2f2, #fecaca);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}

/* Efectos de sombra mejorados */
.designer-card {
    box-shadow: 
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Mejoras en la tipografía */
h1, h2, h3, h4, h5, h6 {
    font-family: 'Inter', sans-serif;
    font-weight: 700;
}

body {
    font-family: 'Inter', sans-serif;
}

/* Scrollbar personalizado para el panel de herramientas */
.tools-panel::-webkit-scrollbar {
    width: 6px;
}

.tools-panel::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.tools-panel::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.tools-panel::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>
<div class="designer-container">
   <div class="container-fluid">
       <div class="row mb-3">
           <div class="col-12 text-center">
               <h1 class="text-white mb-0">
                   <i class="fas fa-id-card-alt"></i> 
                   Editor Profesional de Credenciales
                   <small class="d-block mt-2 text-white-50">Diseño corporativo ultra profesional e impactante</small>
               </h1>
           </div>
       </div>

       <?php if (!empty($mensaje)): ?>
       <div class="row mb-3">
           <div class="col-12">
               <div class="alert alert-<?= $tipo_mensaje ?>" role="alert">
                   <i class="fas fa-<?= $tipo_mensaje == 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                   <?= $mensaje ?>
               </div>
           </div>
       </div>
       <?php endif; ?>

       <div class="designer-card">
           <form action="" method="post" enctype="multipart/form-data" id="designerForm">
               <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
               <?php if ($config): ?>
               <input type="hidden" name="id_config" value="<?= $config['id_config'] ?>">
               <?php endif; ?>
               
               <div class="row g-0">
                   <!-- Panel de Herramientas -->
                   <div class="col-md-4">
                       <div class="tools-panel">
                           <div class="d-flex justify-content-between align-items-center mb-4">
                               <h5 class="mb-0">🎨 Panel de Diseño</h5>
                               <a href="index.php" class="btn btn-sm btn-outline-primary rounded-pill">
                                   <i class="fas fa-arrow-left"></i> Volver
                               </a>
                           </div>

                           <!-- Información Básica -->
                           <div class="tool-group">
                               <h6><i class="fas fa-info-circle"></i> Información Básica</h6>
                               <div class="mb-3">
                                   <label class="form-label">Nombre de la Plantilla</label>
                                   <input type="text" class="form-control" name="nombre_plantilla" 
                                          value="<?= $config ? htmlspecialchars($config['nombre_plantilla']) : '' ?>" 
                                          placeholder="Ej: Plantilla Corporativa 2025" required>
                               </div>
                               <div class="form-check">
                                   <input class="form-check-input" type="checkbox" name="es_default" value="1"
                                          <?= ($config && $config['es_default']) ? 'checked' : '' ?>>
                                   <label class="form-check-label">
                                       ⭐ Plantilla predeterminada
                                   </label>
                               </div>
                           </div>

                           <!-- Colores y Estilos -->
                           <div class="tool-group">
                               <h6><i class="fas fa-fill-drip"></i> Colores Corporativos</h6>
                               <div class="row g-2 mb-3">
                                   <div class="col-6">
                                       <label class="form-label">Color de Fondo</label>
                                       <input type="color" class="color-picker" name="color_fondo" 
                                              value="#FFFFFF" onchange="updatePreview()">
                                   </div>
                                   <div class="col-6">
                                       <label class="form-label">Color de Borde</label>
                                       <input type="color" class="color-picker" name="color_borde" 
                                              value="#0f172a" onchange="updatePreview()">
                                   </div>
                               </div>
                               <div class="mb-2">
                                   <label class="form-label">Grosor del Borde: <span id="grosor-value">1px</span></label>
                                   <input type="range" class="slider-control" name="grosor_borde" 
                                          min="0" max="5" value="1" 
                                          oninput="updateGrosor(this.value); updatePreview()">
                               </div>
                               <div class="mb-2">
                                   <label class="form-label">Esquinas Redondeadas: <span id="esquinas-value">16px</span></label>
                                   <input type="range" class="slider-control" name="esquinas_redondeadas" 
                                          min="0" max="25" value="16" 
                                          oninput="updateEsquinas(this.value); updatePreview()">
                               </div>
                           </div>

                           <!-- Tipografía -->
                           <div class="tool-group">
                               <h6><i class="fas fa-font"></i> Tipografía Profesional</h6>
                               <div class="mb-3">
                                   <label class="form-label">Fuente Principal</label>
                                   <select class="form-select" name="fuente_titulo" onchange="updatePreview()">
                                       <option value="Inter">Inter (Recomendada)</option>
                                       <option value="Arial">Arial</option>
                                       <option value="Helvetica">Helvetica</option>
                                       <option value="Segoe UI">Segoe UI</option>
                                       <option value="Roboto">Roboto</option>
                                       <option value="Open Sans">Open Sans</option>
                                   </select>
                               </div>
                               <div class="row g-2 mb-3">
                                   <div class="col-6">
                                       <label class="form-label">Tamaño Título: <span id="titulo-size">13px</span></label>
                                       <input type="range" class="slider-control" name="tamano_titulo" 
                                              min="10" max="18" value="13" 
                                              oninput="updateTituloSize(this.value); updatePreview()">
                                   </div>
                                   <div class="col-6">
                                       <label class="form-label">Color Título</label>
                                       <input type="color" class="color-picker" name="color_titulo" 
                                              value="#ffffff" onchange="updatePreview()">
                                   </div>
                               </div>
                               <div class="row g-2">
                                   <div class="col-6">
                                       <label class="form-label">Tamaño Texto: <span id="texto-size">9px</span></label>
                                       <input type="range" class="slider-control" name="tamano_texto" 
                                              min="8" max="12" value="9" 
                                              oninput="updateTextoSize(this.value); updatePreview()">
                                   </div>
                                   <div class="col-6">
                                       <label class="form-label">Color Texto</label>
                                       <input type="color" class="color-picker" name="color_texto" 
                                              value="#ffffff" onchange="updatePreview()">
                                   </div>
                               </div>
                           </div>

                           <!-- Elementos -->
                           <div class="tool-group">
                               <h6><i class="fas fa-images"></i> Elementos Gráficos</h6>
                               <div class="mb-3">
                                   <label class="form-label">Logo de la Institución</label>
                                   <input type="file" class="form-control" name="logo" accept="image/*" onchange="previewLogo(this)">
                                   <small class="text-muted">Recomendado: PNG 200x80px</small>
                                   <?php if ($config && !empty($config['logo_path'])): ?>
                                   <div class="mt-2">
                                       <img src="<?= $config['logo_path'] ?>" alt="Logo actual" class="img-thumbnail" style="max-height: 60px;">
                                   </div>
                                   <?php endif; ?>
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">Firma del Director</label>
                                   <input type="file" class="form-control" name="firma" accept="image/*" onchange="previewFirma(this)">
                                   <small class="text-muted">Recomendado: PNG 120x50px</small>
                                   <?php if ($config && !empty($config['firma_path'])): ?>
                                   <div class="mt-2">
                                       <img src="<?= $config['firma_path'] ?>" alt="Firma actual" class="img-thumbnail" style="max-height: 40px;">
                                   </div>
                                   <?php endif; ?>
                               </div>
                               
                               <div class="form-check">
                                   <input class="form-check-input" type="checkbox" name="mostrar_foto" value="1"
                                          <?= (!$config || $config['mostrar_foto']) ? 'checked' : '' ?> onchange="updatePreview()">
                                   <label class="form-check-label">📸 Mostrar Fotografía del Estudiante</label>
                               </div>
                           </div>

                           <!-- Textos -->
                           <div class="tool-group">
                               <h6><i class="fas fa-align-left"></i> Contenido Personalizable</h6>
                               
                               <div class="mb-3">
                                   <label class="form-label">Tipo de Credencial</label>
                                   <input type="text" class="form-control" name="tipo_credencial" 
                                          value="CREDENCIAL DE ESTUDIANTE" onchange="updatePreview()" 
                                          placeholder="Ej: CREDENCIAL DE ESTUDIANTE">
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">Texto Inferior</label>
                                   <textarea class="form-control" name="texto_inferior" rows="3" 
                                             onchange="updatePreview()" 
                                             placeholder="Texto que aparece en la parte inferior"><?= $config ? htmlspecialchars($config['texto_inferior']) : 'Esta credencial acredita al portador como alumno regular de la institución educativa.' ?></textarea>
                               </div>
                               
                               <div class="mb-3">
                                   <label class="form-label">Vigencia</label>
                                   <input type="text" class="form-control" name="vigencia" 
                                          value="<?= $config ? htmlspecialchars($config['vigencia']) : 'Válido durante el ciclo escolar 2024-2025' ?>"
                                          onchange="updatePreview()" placeholder="Vigencia de la credencial">
                               </div>
                               
                               <div class="row g-2">
                                   <div class="col-6">
                                       <label class="form-label">Nombre de Institución</label>
                                       <input type="text" class="form-control" name="nombre_escuela" 
                                              value="ESCUELA SECUNDARIA TÉCNICA #82" onchange="updatePreview()">
                                   </div>
                                   <div class="col-6">
                                       <label class="form-label">Teléfono</label>
                                       <input type="text" class="form-control" name="telefono_contacto" 
                                              value="(55) 1234-5678" onchange="updatePreview()">
                                   </div>
                               </div>
                               
                               <div class="row g-2 mt-2">
                                   <div class="col-6">
                                       <label class="form-label">Dirección</label>
                                       <input type="text" class="form-control" name="direccion_contacto" 
                                              value="Calle Principal s/n, Col. Centro" onchange="updatePreview()">
                                   </div>
                                   <div class="col-6">
                                       <label class="form-label">Sitio Web</label>
                                       <input type="text" class="form-control" name="website_contacto" 
                                              value="www.est82.edu.mx" onchange="updatePreview()">
                                   </div>
                               </div>
                           </div>

                           <!-- Plantillas Rápidas -->
                           <div class="tool-group">
                               <h6><i class="fas fa-palette"></i> Plantillas Profesionales</h6>
                               <div class="row g-2">
                                   <div class="col-6">
                                       <button type="button" class="btn plantilla-btn w-100" onclick="aplicarPlantilla('corporativa')">
                                           🏢 Corporativa
                                       </button>
                                   </div>
                                   <div class="col-6">
                                       <button type="button" class="btn plantilla-btn w-100" onclick="aplicarPlantilla('ejecutiva')">
                                           💼 Ejecutiva
                                       </button>
                                   </div>
                                   <div class="col-6">
                                       <button type="button" class="btn plantilla-btn w-100" onclick="aplicarPlantilla('minimalista')">
                                           ✨ Minimal
                                       </button>
                                   </div>
                                   <div class="col-6">
                                       <button type="button" class="btn plantilla-btn w-100" onclick="aplicarPlantilla('tecnologica')">
                                           🔬 Tech
                                       </button>
                                   </div>
                               </div>
                           </div>
                       </div>
                   </div>

                   <!-- Área de Canvas -->
                   <div class="col-md-8">
                       <div class="canvas-area">
                           <div class="text-center mb-4">
                               <h4 class="text-dark mb-2">
                                   <i class="fas fa-eye"></i> Vista Previa Profesional
                               </h4>
                               <p class="text-muted">Haz clic en la credencial para ver el reverso • Cambios en tiempo real</p>
                           </div>
<!-- Vista previa HTML REAL -->
<div class="mb-4">
    <div class="card shadow-lg border-0">
        <div class="card-header bg-gradient text-white text-center">
            <h6 class="mb-0">🎯 VISTA PREVIA REAL - Como se verá en el PDF</h6>
        </div>
        <div class="card-body p-2">
            <iframe id="preview-real" src="vista_previa_pro.php" 
                    style="width: 100%; height: 280px; border: none; border-radius: 8px;">
            </iframe>
        </div>
    </div>
</div>
                           <!-- Credencial con ambas caras -->
                           <div class="credencial-container" id="credencial-container" onclick="flipCard()">
                               <div class="credencial-licencia" id="credencial-preview">
                                   <!-- FRENTE -->
                                   <div class="credencial-frente" id="credencial-frente">
                                       <!-- Header -->
                                       <div class="credencial-header">
                                           <div class="logo-container">
                                               <div id="logo-preview" class="logo-preview">LOGO</div>
                                           </div>
                                           <div class="escuela-nombre">ESCUELA SECUNDARIA TÉCNICA #82</div>
                                           <div class="credencial-tipo">CREDENCIAL DE ESTUDIANTE</div>
                                           <div class="ciclo-escolar">CICLO ESCOLAR 2024-2025</div>
                                       </div>

                                       <!-- Body -->
                                       <div class="credencial-body">
                                           <div class="foto-seccion">
                                               <div id="foto-alumno" class="foto-alumno">FOTO</div>
                                           </div>
                                           
                                           <div class="datos-alumno">
                                               <div class="nombre-alumno">NOMBRE DEL ESTUDIANTE</div>
                                               
                                               <div class="dato-alumno">
                                                   <span class="label">MATRÍCULA:</span> 
                                                   <span class="valor">2024001</span>
                                               </div>
                                               
                                               <div class="dato-alumno">
                                                   <span class="label">GRADO Y GRUPO:</span> 
                                                   <span class="valor">1° - A</span>
                                               </div>
                                               
                                               <div class="dato-alumno">
                                                   <span class="label">TURNO:</span> 
                                                   <span class="valor">MATUTINO</span>
                                               </div>
                                           </div>
                                       </div>

                                       <!-- Footer -->
                                       <div class="credencial-footer">
                                           <div id="texto-inferior-preview" class="texto-inferior">
                                               Esta credencial acredita al portador como alumno regular de la institución educativa.
                                           </div>
                                       </div>
                                   </div>

                                   <!-- REVERSO -->
                                   <div class="credencial-reverso" id="credencial-reverso">
                                       <div class="reverso-content">
                                           <!-- Vigencia -->
                                           <div class="vigencia-section">
                                               <div class="vigencia-title">VIGENCIA</div>
                                               <div id="vigencia-preview" class="vigencia-texto">Válido durante el ciclo escolar 2024-2025</div>
                                           </div>

                                           <!-- Información de contacto -->
                                           <div class="contacto-section">
                                               <div class="contacto-title">EN CASO DE ENCONTRAR ESTA CREDENCIAL, FAVOR DE ENTREGARLA EN:</div>
                                               <div class="contacto-info">
                                                   <div id="escuela-contacto">ESCUELA SECUNDARIA TÉCNICA #82</div>
                                                   <div id="direccion-contacto">Calle Principal s/n, Col. Centro</div>
                                                   <div id="telefono-contacto">Tel: (55) 1234-5678</div>
                                                   <div><strong id="website-contacto">www.est82.edu.mx</strong></div>
                                               </div>
                                           </div>

                                           <!-- Firma del Director en el Reverso -->
                                           <div class="firma-director-reverso">
                                               <div id="firma-preview" class="firma-img">FIRMA</div>
                                               <div class="director-texto">DIRECTOR(A)</div>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <!-- Botón para voltear -->
                           <button type="button" class="flip-btn" onclick="flipCard()" title="Voltear credencial">
                               <i class="fas fa-sync-alt"></i>
                           </button>

                           <!-- Controles -->
                           <div class="mt-4 text-center">
                               <button type="submit" class="save-btn me-3">
                                   <i class="fas fa-save"></i> Guardar Plantilla
                               </button>
                               <button type="button" class="btn btn-outline-primary rounded-pill" onclick="resetCanvas()">
                                   <i class="fas fa-undo"></i> Resetear
                               </button>
                           </div>

                           <!-- Info adicional -->
                           <div class="mt-4 row g-3">
                               <div class="col-md-3 text-center">
                                   <div class="p-3 bg-white rounded-3 shadow-sm border">
                                       <i class="fas fa-id-card fa-2x text-primary mb-2"></i>
                                       <h6>Tamaño Estándar</h6>
                                       <small class="text-muted">86mm x 54mm</small>
                                   </div>
                               </div>
                               <div class="col-md-3 text-center">
                                   <div class="p-3 bg-white rounded-3 shadow-sm border">
                                       <i class="fas fa-building fa-2x text-success mb-2"></i>
                                       <h6>Diseño Corporativo</h6>
                                       <small class="text-muted">Ultra profesional</small>
                                   </div>
                               </div>
                               <div class="col-md-3 text-center">
                                   <div class="p-3 bg-white rounded-3 shadow-sm border">
                                       <i class="fas fa-sync-alt fa-2x text-info mb-2"></i>
                                       <h6>Doble Cara</h6>
                                       <small class="text-muted">Frente y reverso</small>
                                   </div>
                               </div>
                               <div class="col-md-3 text-center">
                                   <div class="p-3 bg-white rounded-3 shadow-sm border">
                                       <i class="fas fa-bolt fa-2x text-warning mb-2"></i>
                                       <h6>Tiempo Real</h6>
                                       <small class="text-muted">Vista previa instantánea</small>
                                   </div>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
           </form>
       </div>
   </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
let isFlipped = false;

// Actualizar valores de sliders en tiempo real
function updateGrosor(value) {
   document.getElementById('grosor-value').textContent = value + 'px';
}

function updateEsquinas(value) {
   document.getElementById('esquinas-value').textContent = value + 'px';
}

function updateTituloSize(value) {
   document.getElementById('titulo-size').textContent = value + 'px';
}

function updateTextoSize(value) {
   document.getElementById('texto-size').textContent = value + 'px';
}

// Voltear credencial
function flipCard() {
   const container = document.getElementById('credencial-container');
   isFlipped = !isFlipped;
   
   if (isFlipped) {
       container.classList.add('flipped');
   } else {
       container.classList.remove('flipped');
   }
}

// Actualizar vista previa en tiempo real
function updatePreview() {
   const credencial = document.getElementById('credencial-preview');
   if (!credencial) return;
   
   const colorFondo = document.querySelector('input[name="color_fondo"]')?.value || '#FFFFFF';
   const colorBorde = document.querySelector('input[name="color_borde"]')?.value || '#0f172a';
   const grosorBorde = document.querySelector('input[name="grosor_borde"]')?.value || 1;
   const esquinas = document.querySelector('input[name="esquinas_redondeadas"]')?.value || 16;
   const fuenteTitulo = document.querySelector('select[name="fuente_titulo"]')?.value || 'Inter';
   const tamanoTitulo = document.querySelector('input[name="tamano_titulo"]')?.value || 13;
   const colorTitulo = document.querySelector('input[name="color_titulo"]')?.value || '#ffffff';
   const tamanoTexto = document.querySelector('input[name="tamano_texto"]')?.value || 9;
   const colorTexto = document.querySelector('input[name="color_texto"]')?.value || '#ffffff';
   const textoInferior = document.querySelector('textarea[name="texto_inferior"]')?.value || '';
   const vigencia = document.querySelector('input[name="vigencia"]')?.value || '';
   const mostrarFoto = document.querySelector('input[name="mostrar_foto"]')?.checked || false;

   // Textos editables
 // Textos editables
   const tipoCredencial = document.querySelector('input[name="tipo_credencial"]')?.value || 'CREDENCIAL DE ESTUDIANTE';
   const nombreEscuela = document.querySelector('input[name="nombre_escuela"]')?.value || 'ESCUELA SECUNDARIA TÉCNICA #82';
   const telefonoContacto = document.querySelector('input[name="telefono_contacto"]')?.value || '(55) 1234-5678';
   const direccionContacto = document.querySelector('input[name="direccion_contacto"]')?.value || 'Calle Principal s/n, Col. Centro';
   const websiteContacto = document.querySelector('input[name="website_contacto"]')?.value || 'www.est82.edu.mx';

   // Aplicar estilos al contenedor de la credencial
   credencial.style.backgroundColor = colorFondo;
   credencial.style.border = `${grosorBorde}px solid ${colorBorde}`;
   credencial.style.borderRadius = `${esquinas}px`;
   credencial.style.boxShadow = `0 10px 30px ${hexToRgba(colorBorde, 0.2)}`;

   // Aplicar variables CSS
   credencial.style.setProperty('--tamano-titulo', tamanoTitulo + 'px');
   credencial.style.setProperty('--color-titulo', colorTitulo);
   credencial.style.setProperty('--tamano-texto', tamanoTexto + 'px');
   credencial.style.setProperty('--color-texto', colorTexto);
   credencial.style.setProperty('--fuente-titulo', fuenteTitulo);

   // Actualizar textos editables
   const credencialTipoEl = document.querySelector('.credencial-tipo');
   if (credencialTipoEl) {
       credencialTipoEl.textContent = tipoCredencial;
   }

   const escuelaNombreEl = document.querySelector('.escuela-nombre');
   if (escuelaNombreEl) {
       escuelaNombreEl.textContent = nombreEscuela;
   }

   // Actualizar datos del cuerpo
   const nombreAlumno = document.querySelector('.nombre-alumno');
   const datosAlumno = document.querySelectorAll('.dato-alumno');
   const textoInferiorPreview = document.getElementById('texto-inferior-preview');

   if (nombreAlumno) {
       nombreAlumno.style.fontFamily = fuenteTitulo;
       nombreAlumno.style.fontSize = tamanoTitulo + 'px';
   }

   datosAlumno.forEach(dato => {
       dato.style.fontFamily = fuenteTitulo;
       dato.style.fontSize = tamanoTexto + 'px';
       const valor = dato.querySelector('.valor');
       if (valor) valor.style.color = colorBorde;
   });

   if (textoInferiorPreview) {
       textoInferiorPreview.style.fontFamily = fuenteTitulo;
       textoInferiorPreview.style.fontSize = (tamanoTexto - 2) + 'px';
       textoInferiorPreview.textContent = textoInferior;
   }

   // Actualizar textos del reverso
   const vigenciaPreview = document.getElementById('vigencia-preview');
   if (vigenciaPreview) {
       vigenciaPreview.style.fontFamily = fuenteTitulo;
       vigenciaPreview.style.fontSize = tamanoTexto + 'px';
       vigenciaPreview.textContent = vigencia;
   }

   // Actualizar datos de contacto en el reverso
   const escuelaContacto = document.getElementById('escuela-contacto');
   if (escuelaContacto) escuelaContacto.textContent = nombreEscuela;

   const direccionContactoEl = document.getElementById('direccion-contacto');
   if (direccionContactoEl) direccionContactoEl.textContent = direccionContacto;

   const telefonoContactoEl = document.getElementById('telefono-contacto');
   if (telefonoContactoEl) telefonoContactoEl.textContent = `Tel: ${telefonoContacto}`;

   const websiteContactoEl = document.getElementById('website-contacto');
   if (websiteContactoEl) websiteContactoEl.textContent = websiteContacto;

   // Mostrar/ocultar elementos
   const fotoAlumno = document.getElementById('foto-alumno');
   if (fotoAlumno) {
       fotoAlumno.style.display = mostrarFoto ? 'flex' : 'none';
   }
   // Actualizar también la vista previa REAL
   const previewReal = document.getElementById('preview-real');
   if (previewReal) {
       // Recargar iframe para mostrar cambios
       previewReal.src = previewReal.src;
   }
}

// Plantillas predefinidas profesionales
function aplicarPlantilla(tipo) {
   const templates = {
       corporativa: {
           color_fondo: '#FFFFFF',
           color_borde: '#0f172a',
           grosor_borde: 1,
           esquinas_redondeadas: 16,
           fuente_titulo: 'Inter',
           tamano_titulo: 13,
           color_titulo: '#ffffff',
           tamano_texto: 9,
           color_texto: '#ffffff'
       },
       ejecutiva: {
           color_fondo: '#FAFBFC',
           color_borde: '#1e293b',
           grosor_borde: 2,
           esquinas_redondeadas: 12,
           fuente_titulo: 'Inter',
           tamano_titulo: 12,
           color_titulo: '#ffffff',
           tamano_texto: 9,
           color_texto: '#ffffff'
       },
       minimalista: {
           color_fondo: '#FFFFFF',
           color_borde: '#334155',
           grosor_borde: 1,
           esquinas_redondeadas: 20,
           fuente_titulo: 'Inter',
           tamano_titulo: 12,
           color_titulo: '#ffffff',
           tamano_texto: 8,
           color_texto: '#ffffff'
       },
       tecnologica: {
           color_fondo: '#F8FAFC',
           color_borde: '#3b82f6',
           grosor_borde: 2,
           esquinas_redondeadas: 14,
           fuente_titulo: 'Inter',
           tamano_titulo: 13,
           color_titulo: '#ffffff',
           tamano_texto: 9,
           color_texto: '#ffffff'
       }
   };

   if (templates[tipo]) {
       const template = templates[tipo];
       
       // Aplicar valores al formulario
       Object.keys(template).forEach(key => {
           const input = document.querySelector(`[name="${key}"]`);
           if (input) {
               if (input.type === 'range') {
                   input.value = template[key];
                   // Actualizar displays
                   if (key === 'grosor_borde') updateGrosor(template[key]);
                   if (key === 'esquinas_redondeadas') updateEsquinas(template[key]);
                   if (key === 'tamano_titulo') updateTituloSize(template[key]);
                   if (key === 'tamano_texto') updateTextoSize(template[key]);
               } else {
                   input.value = template[key];
               }
           }
       });

       // Actualizar vista previa
       setTimeout(updatePreview, 100);
   }
}

// Preview de archivos
function previewLogo(input) {
   if (input.files && input.files[0]) {
       const reader = new FileReader();
       reader.onload = function(e) {
           const logoPreview = document.getElementById('logo-preview');
           if (logoPreview) {
               logoPreview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px;">`;
           }
       };
       reader.readAsDataURL(input.files[0]);
   }
}

function previewFirma(input) {
   if (input.files && input.files[0]) {
       const reader = new FileReader();
       reader.onload = function(e) {
           const firmaPreview = document.getElementById('firma-preview');
           if (firmaPreview) {
               firmaPreview.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px;">`;
           }
       };
       reader.readAsDataURL(input.files[0]);
   }
}

// Resetear canvas
function resetCanvas() {
   if (confirm('¿Estás seguro de que quieres resetear todos los cambios?')) {
       location.reload();
   }
}

// Función auxiliar para convertir hex a RGBA
function hexToRgba(hex, alpha) {
   const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
   if (result) {
       const r = parseInt(result[1], 16);
       const g = parseInt(result[2], 16);
       const b = parseInt(result[3], 16);
       return `rgba(${r}, ${g}, ${b}, ${alpha})`;
   }
   return `rgba(0, 0, 0, ${alpha})`;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
   // Aplicar vista previa inicial
   updatePreview();

   // Event listeners para cambios en tiempo real
   document.querySelectorAll('input, select, textarea').forEach(element => {
       element.addEventListener('input', updatePreview);
       element.addEventListener('change', updatePreview);
   });

   // Animación de entrada
   setTimeout(() => {
       const designerCard = document.querySelector('.designer-card');
       if (designerCard) {
           designerCard.style.opacity = '1';
           designerCard.style.transform = 'translateY(0)';
       }
   }, 100);

   // Aplicar plantilla corporativa por defecto
   setTimeout(() => {
       aplicarPlantilla('corporativa');
   }, 200);
});

// Estilos iniciales para animación
const designerCard = document.querySelector('.designer-card');
if (designerCard) {
   designerCard.style.opacity = '0';
   designerCard.style.transform = 'translateY(20px)';
   designerCard.style.transition = 'all 0.5s ease';
}

// Validación de formulario
document.getElementById('designerForm').addEventListener('submit', function(e) {
   const nombrePlantilla = document.querySelector('input[name="nombre_plantilla"]').value;
   
   if (!nombrePlantilla.trim()) {
       e.preventDefault();
       alert('Por favor, ingresa un nombre para la plantilla.');
       return false;
   }
   
   // Mostrar indicador de carga
   const saveBtn = document.querySelector('.save-btn');
   if (saveBtn) {
       saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
       saveBtn.disabled = true;
   }
});

// Mejorar la experiencia del usuario con tooltips
document.querySelectorAll('[title]').forEach(element => {
   element.addEventListener('mouseenter', function() {
       this.style.position = 'relative';
   });
});

// Optimizar rendimiento en dispositivos móviles
if (window.innerWidth <= 768) {
   // Reducir la frecuencia de actualizaciones en móviles
   let updateTimeout;
   const originalUpdatePreview = updatePreview;
   
   updatePreview = function() {
       clearTimeout(updateTimeout);
       updateTimeout = setTimeout(originalUpdatePreview, 150);
   };
}
</script>

<?php
// Función para procesar uploads de imágenes - MEJORADA
function procesar_upload_imagen($campo, $directorio) {
   if (!isset($_FILES[$campo])) {
       return [
           'exito' => false,
           'mensaje' => 'No se seleccionó archivo',
           'ruta_relativa' => ''
       ];
   }
   
   $archivo = $_FILES[$campo];
   $resultado = [
       'exito' => false,
       'mensaje' => '',
       'ruta_relativa' => ''
   ];
   
   // Verificar si hubo error en la subida
   if ($archivo['error'] !== UPLOAD_ERR_OK) {
       $resultado['mensaje'] = "Error en la subida del archivo. Código: " . $archivo['error'];
       return $resultado;
   }
   
   // Validar tipo de archivo
   $tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
   $finfo = finfo_open(FILEINFO_MIME_TYPE);
   $mime_type = finfo_file($finfo, $archivo['tmp_name']);
   finfo_close($finfo);
   
   if (!in_array($mime_type, $tipos_permitidos)) {
       $resultado['mensaje'] = "El archivo debe ser una imagen (JPG, PNG, GIF o WebP)";
       return $resultado;
   }
   
   // Validar tamaño (max 5MB para mejor calidad)
   if ($archivo['size'] > 5 * 1024 * 1024) {
       $resultado['mensaje'] = "El tamaño máximo permitido es 5MB";
       return $resultado;
   }
   
   // Crear directorio si no existe
   if (!file_exists($directorio)) {
       mkdir($directorio, 0755, true);
   }
   
   // Generar nombre único
   $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
   $nombre_archivo = uniqid('prof_') . '.' . strtolower($extension);
   $ruta_completa = $directorio . $nombre_archivo;
   
   // Mover archivo
   if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
       $resultado['exito'] = true;
       $resultado['ruta_relativa'] = $ruta_completa;
       
       // Optimizar imagen para mejor rendimiento
       try {
           optimizarImagenProfesional($ruta_completa, $campo);
       } catch (Exception $e) {
           // Si hay error en optimización, continuar sin optimizar
           error_log("Error optimizando imagen: " . $e->getMessage());
       }
   } else {
       $resultado['mensaje'] = "Error al mover el archivo al directorio de destino";
   }
   
   return $resultado;
}

// Función para optimizar imágenes con calidad profesional
function optimizarImagenProfesional($ruta, $tipo) {
   if (!extension_loaded('gd')) {
       return; // Si no hay GD, no optimizar
   }
   
   $info = getimagesize($ruta);
   if (!$info) return;
   
   $mime = $info['mime'];
   
   // Crear imagen desde archivo
   switch ($mime) {
       case 'image/jpeg':
           $imagen = imagecreatefromjpeg($ruta);
           break;
       case 'image/png':
           $imagen = imagecreatefrompng($ruta);
           break;
       case 'image/gif':
           $imagen = imagecreatefromgif($ruta);
           break;
       case 'image/webp':
           $imagen = imagecreatefromwebp($ruta);
           break;
       default:
           return;
   }
   
   if (!$imagen) return;
   
   // Definir tamaños máximos según el tipo con mejor calidad
   $max_width = ($tipo === 'logo') ? 300 : 200;
   $max_height = ($tipo === 'logo') ? 120 : 80;
   
   $width = imagesx($imagen);
   $height = imagesy($imagen);
   
   // Calcular nuevas dimensiones manteniendo proporción
   $ratio = min($max_width / $width, $max_height / $height);
   
   if ($ratio < 1) {
       $new_width = (int)($width * $ratio);
       $new_height = (int)($height * $ratio);
       
       // Crear nueva imagen redimensionada con mejor calidad
       $nueva_imagen = imagecreatetruecolor($new_width, $new_height);
       
       // Habilitar antialiasing para mejor calidad
       imageantialias($nueva_imagen, true);
       
       // Preservar transparencia para PNG
       if ($mime === 'image/png') {
           imagealphablending($nueva_imagen, false);
           imagesavealpha($nueva_imagen, true);
           $transparent = imagecolorallocatealpha($nueva_imagen, 255, 255, 255, 127);
           imagefill($nueva_imagen, 0, 0, $transparent);
       }
       
       // Redimensionar con mejor algoritmo
       imagecopyresampled($nueva_imagen, $imagen, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
       
       // Guardar imagen optimizada con alta calidad
       switch ($mime) {
           case 'image/jpeg':
               imagejpeg($nueva_imagen, $ruta, 95); // Alta calidad
               break;
           case 'image/png':
               imagepng($nueva_imagen, $ruta, 3); // Compresión ligera
               break;
           case 'image/gif':
               imagegif($nueva_imagen, $ruta);
               break;
           case 'image/webp':
               imagewebp($nueva_imagen, $ruta, 95); // Alta calidad
               break;
       }
       
       imagedestroy($nueva_imagen);
   }
   
   imagedestroy($imagen);
}
?>