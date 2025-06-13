<?php
/**
 * Archivo: configuracion.php
 * Ubicación: modules/admin/configuracion.php
 * Propósito: Gestión de configuración global del sistema
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a este módulo', 'danger');
}

// Funciones de configuración
/**
 * Obtiene todos los valores de configuración de la BD
 * @return array Arreglo asociativo con clave => valor
 */
function obtener_configuracion() {
    global $conexion;
    
    $config = [];
    $query = "SELECT clave, valor FROM configuracion";
    $result = $conexion->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config[$row['clave']] = $row['valor'];
        }
    }
    
    return $config;
}

/**
 * Actualiza un valor de configuración en la BD
 * @param string $clave Clave de configuración
 * @param string $valor Valor de configuración
 * @return bool Resultado de la operación
 */
function actualizar_configuracion($clave, $valor) {
    global $conexion;
    
    // Verificar si la clave ya existe
    $query = "SELECT id_configuracion FROM configuracion WHERE clave = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("s", $clave);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar valor existente
        $query = "UPDATE configuracion SET valor = ?, fecha_actualizacion = NOW() WHERE clave = ?";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("ss", $valor, $clave);
    } else {
        // Insertar nuevo valor
        $query = "INSERT INTO configuracion (clave, valor, descripcion) VALUES (?, ?, ?)";
        $stmt = $conexion->prepare($query);
        $descripcion = "Configuración: " . $clave;
        $stmt->bind_param("sss", $clave, $valor, $descripcion);
    }
    
    return $stmt->execute();
}

// Procesar formulario si se envió
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seccion'])) {
    $seccion = $_POST['seccion'];
    $cambios_realizados = false;
    
    // Procesar según la sección
    switch ($seccion) {
        case 'institucion':
            $campos = ['nombre_institucion', 'cct', 'director', 'direccion', 'telefono', 'email_contacto'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, $_POST[$campo]);
                    $cambios_realizados = true;
                }
            }
            
            // Procesar logo si se subió
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                $directorio = '../../uploads/sistema/';
                if (!file_exists($directorio)) {
                    mkdir($directorio, 0755, true);
                }
                
                $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $nombre_archivo = 'logo_institucion.' . $extension;
                $ruta_archivo = $directorio . $nombre_archivo;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta_archivo)) {
                    actualizar_configuracion('logo_institucion', 'uploads/sistema/' . $nombre_archivo);
                    $cambios_realizados = true;
                }
            }
            break;
            
        case 'correo':
            $campos = ['smtp_host', 'smtp_puerto', 'smtp_usuario', 'smtp_password', 'smtp_seguridad', 
                      'correo_remitente', 'nombre_remitente', 'firma_correo'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, $_POST[$campo]);
                    $cambios_realizados = true;
                }
            }
            break;
            
        case 'ciclo_escolar':
            $campos = ['ciclo_actual', 'fecha_inicio_ciclo', 'fecha_fin_ciclo', 
                      'periodo_evaluacion_actual', 'total_periodos_evaluacion'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, $_POST[$campo]);
                    $cambios_realizados = true;
                }
            }
            break;
            
        case 'seguridad':
            $campos = ['longitud_minima_password', 'dias_caducidad_password', 'max_intentos_login', 
                      'tiempo_bloqueo_minutos', 'tiempo_sesion_minutos'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, intval($_POST[$campo]));
                    $cambios_realizados = true;
                }
            }
            
            if (isset($_POST['politica_password_especial'])) {
                actualizar_configuracion('politica_password_especial', '1');
            } else {
                actualizar_configuracion('politica_password_especial', '0');
            }
            
            if (isset($_POST['politica_password_mayusculas'])) {
                actualizar_configuracion('politica_password_mayusculas', '1');
            } else {
                actualizar_configuracion('politica_password_mayusculas', '0');
            }
            
            if (isset($_POST['politica_password_numeros'])) {
                actualizar_configuracion('politica_password_numeros', '1');
            } else {
                actualizar_configuracion('politica_password_numeros', '0');
            }
            
            $cambios_realizados = true;
            break;
            
        case 'respaldos':
            $campos = ['backup_auto_habilitado', 'backup_auto_frecuencia', 'backup_auto_hora', 
                      'backup_auto_retener', 'backup_ruta_externa'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, $_POST[$campo]);
                    $cambios_realizados = true;
                }
            }
            
            if (isset($_POST['backup_incluir_archivos'])) {
                actualizar_configuracion('backup_incluir_archivos', '1');
            } else {
                actualizar_configuracion('backup_incluir_archivos', '0');
            }
            
            if (isset($_POST['backup_notificar_email'])) {
                actualizar_configuracion('backup_notificar_email', '1');
            } else {
                actualizar_configuracion('backup_notificar_email', '0');
            }
            
            $cambios_realizados = true;
            break;
            
        case 'pdf':
            $campos = ['pdf_orientacion_default', 'pdf_mostrar_logo', 'pdf_mostrar_direccion', 
                      'pdf_mostrar_telefonos', 'pdf_color_primario', 'pdf_fuente'];
            foreach ($campos as $campo) {
                if (isset($_POST[$campo])) {
                    actualizar_configuracion($campo, $_POST[$campo]);
                    $cambios_realizados = true;
                }
            }
            
            // Procesar membrete si se subió
            if (isset($_FILES['pdf_membrete']) && $_FILES['pdf_membrete']['error'] === 0) {
                $directorio = '../../uploads/sistema/';
                if (!file_exists($directorio)) {
                    mkdir($directorio, 0755, true);
                }
                
                $extension = pathinfo($_FILES['pdf_membrete']['name'], PATHINFO_EXTENSION);
                $nombre_archivo = 'membrete.' . $extension;
                $ruta_archivo = $directorio . $nombre_archivo;
                
                if (move_uploaded_file($_FILES['pdf_membrete']['tmp_name'], $ruta_archivo)) {
                    actualizar_configuracion('pdf_membrete', 'uploads/sistema/' . $nombre_archivo);
                    $cambios_realizados = true;
                }
            }
            break;
    }
    
    if ($cambios_realizados) {
        $mensaje = "La configuración de " . ucfirst($seccion) . " ha sido actualizada correctamente.";
        $tipo_mensaje = "success";
        
        // Registrar en log - usando la función que ya existe en functions.php
        registrarLog(
            'operacion',
            $_SESSION['id_usuario'] ?? null,
            null,
            "Configuración actualizada: $seccion"
        );
        
        // Guardar mensaje en sesión para mostrarlo después de redirección
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['tipo_mensaje'] = $tipo_mensaje;
        
        // Redireccionar para evitar reenvío del formulario
        header("Location: configuracion.php");
        exit;
    } else {
        $mensaje = "No se realizaron cambios en la configuración.";
        $tipo_mensaje = "info";
        
        // Guardar mensaje en sesión
        $_SESSION['mensaje'] = $mensaje;
        $_SESSION['tipo_mensaje'] = $tipo_mensaje;
    }
}

// Obtener configuración actual
$config = obtener_configuracion();

// Incluir encabezado
include '../../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cog me-2"></i>Configuración del Sistema</h1>
        <a href="index.php" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50 me-1"></i> Volver al Panel
        </a>
    </div>
    
    <?php echo mostrar_mensaje(); ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="nav flex-column nav-pills mb-4" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                <a class="nav-link active" id="v-pills-institucion-tab" data-bs-toggle="pill" href="#v-pills-institucion" role="tab" aria-controls="v-pills-institucion" aria-selected="true">
                    <i class="fas fa-school me-2"></i> Datos de la Institución
                </a>
                <a class="nav-link" id="v-pills-correo-tab" data-bs-toggle="pill" href="#v-pills-correo" role="tab" aria-controls="v-pills-correo" aria-selected="false">
                    <i class="fas fa-envelope me-2"></i> Configuración de Correo
                </a>
                <a class="nav-link" id="v-pills-ciclo-tab" data-bs-toggle="pill" href="#v-pills-ciclo" role="tab" aria-controls="v-pills-ciclo" aria-selected="false">
                    <i class="fas fa-calendar-alt me-2"></i> Ciclo Escolar
                </a>
                <a class="nav-link" id="v-pills-seguridad-tab" data-bs-toggle="pill" href="#v-pills-seguridad" role="tab" aria-controls="v-pills-seguridad" aria-selected="false">
                    <i class="fas fa-shield-alt me-2"></i> Seguridad
                </a>
                <a class="nav-link" id="v-pills-respaldos-tab" data-bs-toggle="pill" href="#v-pills-respaldos" role="tab" aria-controls="v-pills-respaldos" aria-selected="false">
                    <i class="fas fa-database me-2"></i> Respaldos
                </a>
                <a class="nav-link" id="v-pills-pdf-tab" data-bs-toggle="pill" href="#v-pills-pdf" role="tab" aria-controls="v-pills-pdf" aria-selected="false">
                    <i class="fas fa-file-pdf me-2"></i> Documentos PDF
                </a>
            </div>
        </div>
        
        <div class="col-md-9">
            <div class="tab-content" id="v-pills-tabContent">
                <!-- Datos de la Institución -->
                <div class="tab-pane fade show active" id="v-pills-institucion" role="tabpanel" aria-labelledby="v-pills-institucion-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Datos de la Institución</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="seccion" value="institucion">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="nombre_institucion" class="form-label">Nombre de la Institución:</label>
                                            <input type="text" class="form-control" id="nombre_institucion" name="nombre_institucion" 
                                                   value="<?php echo htmlspecialchars($config['nombre_institucion'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cct" class="form-label">CCT (Clave del Centro de Trabajo):</label>
                                            <input type="text" class="form-control" id="cct" name="cct" 
                                                   value="<?php echo htmlspecialchars($config['cct'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="director" class="form-label">Nombre del Director:</label>
                                            <input type="text" class="form-control" id="director" name="director" 
                                                   value="<?php echo htmlspecialchars($config['director'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telefono" class="form-label">Teléfono:</label>
                                            <input type="text" class="form-control" id="telefono" name="telefono" 
                                                   value="<?php echo htmlspecialchars($config['telefono'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email_contacto" class="form-label">Email de Contacto:</label>
                                            <input type="email" class="form-control" id="email_contacto" name="email_contacto" 
                                                   value="<?php echo htmlspecialchars($config['email_contacto'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="direccion" class="form-label">Dirección:</label>
                                            <textarea class="form-control" id="direccion" name="direccion" rows="2"><?php echo htmlspecialchars($config['direccion'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Logotipo:</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                    <div class="form-text">Formatos recomendados: PNG o JPG. Tamaño máximo: 2MB.</div>
                                    
                                    <?php if (!empty($config['logo_institucion'])): ?>
                                    <div class="mt-2">
                                        <img src="../../<?php echo htmlspecialchars($config['logo_institucion']); ?>" alt="Logo actual" class="img-thumbnail" style="max-height: 100px">
                                        <p class="text-muted">Logo actual</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Configuración de Correo -->
                <div class="tab-pane fade" id="v-pills-correo" role="tabpanel" aria-labelledby="v-pills-correo-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Configuración de Correo Electrónico</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="seccion" value="correo">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_host" class="form-label">Servidor SMTP:</label>
                                            <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($config['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_puerto" class="form-label">Puerto SMTP:</label>
                                            <input type="text" class="form-control" id="smtp_puerto" name="smtp_puerto" 
                                                   value="<?php echo htmlspecialchars($config['smtp_puerto'] ?? '587'); ?>" placeholder="587">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_usuario" class="form-label">Usuario SMTP:</label>
                                            <input type="text" class="form-control" id="smtp_usuario" name="smtp_usuario" 
                                                   value="<?php echo htmlspecialchars($config['smtp_usuario'] ?? ''); ?>" placeholder="usuario@gmail.com">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_password" class="form-label">Contraseña SMTP:</label>
                                            <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($config['smtp_password'] ?? ''); ?>" placeholder="Contraseña">
                                            <div class="form-text">Deje en blanco para mantener la contraseña actual.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="smtp_seguridad" class="form-label">Seguridad SMTP:</label>
                                            <select class="form-select" id="smtp_seguridad" name="smtp_seguridad">
                                                <option value="tls" <?php echo (($config['smtp_seguridad'] ?? '') == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                                <option value="ssl" <?php echo (($config['smtp_seguridad'] ?? '') == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                                <option value="none" <?php echo (($config['smtp_seguridad'] ?? '') == 'none') ? 'selected' : ''; ?>>Ninguna</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="correo_remitente" class="form-label">Correo del Remitente:</label>
                                            <input type="email" class="form-control" id="correo_remitente" name="correo_remitente" 
                                                   value="<?php echo htmlspecialchars($config['correo_remitente'] ?? ''); ?>" placeholder="sistema@est82.edu.mx">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nombre_remitente" class="form-label">Nombre del Remitente:</label>
                                    <input type="text" class="form-control" id="nombre_remitente" name="nombre_remitente" 
                                           value="<?php echo htmlspecialchars($config['nombre_remitente'] ?? ''); ?>" placeholder="Sistema Escolar EST #82">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="firma_correo" class="form-label">Firma para Correos:</label>
                                    <textarea class="form-control" id="firma_correo" name="firma_correo" rows="4"><?php echo htmlspecialchars($config['firma_correo'] ?? ''); ?></textarea>
                                    <div class="form-text">Puede utilizar HTML básico para dar formato.</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                                
                                <button type="button" class="btn btn-info ms-2" id="btnProbarCorreo">
                                    <i class="fas fa-paper-plane me-1"></i> Probar Configuración
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Ciclo Escolar -->
                <div class="tab-pane fade" id="v-pills-ciclo" role="tabpanel" aria-labelledby="v-pills-ciclo-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Configuración de Ciclo Escolar</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="seccion" value="ciclo_escolar">
                                
                                <div class="mb-3">
                                    <label for="ciclo_actual" class="form-label">Ciclo Escolar Actual:</label>
                                    <input type="text" class="form-control" id="ciclo_actual" name="ciclo_actual" 
                                           value="<?php echo htmlspecialchars($config['ciclo_actual'] ?? ''); ?>" placeholder="2024-2025">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="fecha_inicio_ciclo" class="form-label">Fecha de Inicio del Ciclo:</label>
                                            <input type="date" class="form-control" id="fecha_inicio_ciclo" name="fecha_inicio_ciclo" 
                                                   value="<?php echo htmlspecialchars($config['fecha_inicio_ciclo'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="fecha_fin_ciclo" class="form-label">Fecha de Fin del Ciclo:</label>
                                            <input type="date" class="form-control" id="fecha_fin_ciclo" name="fecha_fin_ciclo" 
                                                   value="<?php echo htmlspecialchars($config['fecha_fin_ciclo'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="periodo_evaluacion_actual" class="form-label">Periodo de Evaluación Actual:</label>
                                            <select class="form-select" id="periodo_evaluacion_actual" name="periodo_evaluacion_actual">
                                                <option value="1" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == '1') ? 'selected' : ''; ?>>Primer periodo</option>
                                                <option value="2" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == '2') ? 'selected' : ''; ?>>Segundo periodo</option>
                                                <option value="3" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == '3') ? 'selected' : ''; ?>>Tercer periodo</option>
                                                <option value="4" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == '4') ? 'selected' : ''; ?>>Cuarto periodo</option>
                                                <option value="5" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == '5') ? 'selected' : ''; ?>>Quinto periodo</option>
                                                <option value="final" <?php echo (($config['periodo_evaluacion_actual'] ?? '') == 'final') ? 'selected' : ''; ?>>Evaluación final</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="total_periodos_evaluacion" class="form-label">Total de Periodos de Evaluación:</label>
                                            <select class="form-select" id="total_periodos_evaluacion" name="total_periodos_evaluacion">
                                                <option value="3" <?php echo (($config['total_periodos_evaluacion'] ?? '') == '3') ? 'selected' : ''; ?>>3 periodos</option>
                                                <option value="4" <?php echo (($config['total_periodos_evaluacion'] ?? '') == '4') ? 'selected' : ''; ?>>4 periodos</option>
                                                <option value="5" <?php echo (($config['total_periodos_evaluacion'] ?? '') == '5') ? 'selected' : ''; ?>>5 periodos</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Seguridad -->
                <div class="tab-pane fade" id="v-pills-seguridad" role="tabpanel" aria-labelledby="v-pills-seguridad-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Configuración de Seguridad</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="seccion" value="seguridad">
                                
                                <h5>Política de Contraseñas</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="longitud_minima_password" class="form-label">Longitud Mínima de Contraseña:</label>
                                            <input type="number" class="form-control" id="longitud_minima_password" name="longitud_minima_password" 
                                                   value="<?php echo htmlspecialchars($config['longitud_minima_password'] ?? '8'); ?>" min="6" max="20">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="dias_caducidad_password" class="form-label">Días para Caducidad de Contraseña:</label>
                                            <input type="number" class="form-control" id="dias_caducidad_password" name="dias_caducidad_password" 
                                                   value="<?php echo htmlspecialchars($config['dias_caducidad_password'] ?? '90'); ?>" min="0" max="365">
                                            <div class="form-text">0 = No caduca</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="politica_password_mayusculas" name="politica_password_mayusculas" value="1"
                                           <?php echo (($config['politica_password_mayusculas'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="politica_password_mayusculas">
                                        Requerir al menos una letra mayúscula
                                    </label>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="politica_password_numeros" name="politica_password_numeros" value="1"
                                           <?php echo (($config['politica_password_numeros'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="politica_password_numeros">
                                        Requerir al menos un número
                                    </label>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="politica_password_especial" name="politica_password_especial" value="1"
                                           <?php echo (($config['politica_password_especial'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="politica_password_especial">
                                        Requerir al menos un carácter especial
                                    </label>
                                </div>
                                
                                <hr>
                                
                                <h5>Configuración de Inicio de Sesión</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="max_intentos_login" class="form-label">Intentos Fallidos Permitidos:</label>
                                            <input type="number" class="form-control" id="max_intentos_login" name="max_intentos_login" 
                                                   value="<?php echo htmlspecialchars($config['max_intentos_login'] ?? '5'); ?>" min="1" max="10">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tiempo_bloqueo_minutos" class="form-label">Tiempo de Bloqueo (minutos):</label>
                                            <input type="number" class="form-control" id="tiempo_bloqueo_minutos" name="tiempo_bloqueo_minutos" 
                                                   value="<?php echo htmlspecialchars($config['tiempo_bloqueo_minutos'] ?? '30'); ?>" min="5" max="1440">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="tiempo_sesion_minutos" class="form-label">Tiempo de Sesión (minutos):</label>
                                            <input type="number" class="form-control" id="tiempo_sesion_minutos" name="tiempo_sesion_minutos" 
                                                   value="<?php echo htmlspecialchars($config['tiempo_sesion_minutos'] ?? '30'); ?>" min="5" max="1440">
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Respaldos -->
                <div class="tab-pane fade" id="v-pills-respaldos" role="tabpanel" aria-labelledby="v-pills-respaldos-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Configuración de Respaldos</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="seccion" value="respaldos">
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="backup_auto_habilitado" name="backup_auto_habilitado" value="1"
                                           <?php echo (($config['backup_auto_habilitado'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_auto_habilitado">
                                        Habilitar respaldos automáticos
                                    </label>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="backup_auto_frecuencia" class="form-label">Frecuencia:</label>
                                            <select class="form-select" id="backup_auto_frecuencia" name="backup_auto_frecuencia">
                                                <option value="diario" <?php echo (($config['backup_auto_frecuencia'] ?? '') == 'diario') ? 'selected' : ''; ?>>Diario</option>
                                                <option value="semanal" <?php echo (($config['backup_auto_frecuencia'] ?? '') == 'semanal') ? 'selected' : ''; ?>>Semanal</option>
                                                <option value="mensual" <?php echo (($config['backup_auto_frecuencia'] ?? '') == 'mensual') ? 'selected' : ''; ?>>Mensual</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="backup_auto_hora" class="form-label">Hora de ejecución:</label>
                                            <input type="time" class="form-control" id="backup_auto_hora" name="backup_auto_hora" 
                                                   value="<?php echo htmlspecialchars($config['backup_auto_hora'] ?? '03:00'); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="backup_auto_retener" class="form-label">Respaldos a retener:</label>
                                            <input type="number" class="form-control" id="backup_auto_retener" name="backup_auto_retener" 
                                                   value="<?php echo htmlspecialchars($config['backup_auto_retener'] ?? '10'); ?>" min="1" max="100">
                                            <div class="form-text">Los respaldos más antiguos se eliminarán automáticamente.</div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="backup_ruta_externa" class="form-label">Ruta externa (opcional):</label>
                                            <input type="text" class="form-control" id="backup_ruta_externa" name="backup_ruta_externa" 
                                                   value="<?php echo htmlspecialchars($config['backup_ruta_externa'] ?? ''); ?>" placeholder="/ruta/externa">
                                            <div class="form-text">Ruta adicional donde se copiará el respaldo.</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="backup_incluir_archivos" name="backup_incluir_archivos" value="1"
                                           <?php echo (($config['backup_incluir_archivos'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_incluir_archivos">
                                        Incluir archivos en respaldos automáticos
                                    </label>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="backup_notificar_email" name="backup_notificar_email" value="1"
                                           <?php echo (($config['backup_notificar_email'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_notificar_email">
                                        Enviar notificación por correo al completar respaldo
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                                
                                <a href="backup.php" class="btn btn-info ms-2">
                                    <i class="fas fa-database me-1"></i> Gestionar Respaldos
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Documentos PDF -->
                <div class="tab-pane fade" id="v-pills-pdf" role="tabpanel" aria-labelledby="v-pills-pdf-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 fw-bold text-primary">Configuración de Documentos PDF</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="seccion" value="pdf">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pdf_orientacion_default" class="form-label">Orientación Predeterminada:</label>
                                            <select class="form-select" id="pdf_orientacion_default" name="pdf_orientacion_default">
                                                <option value="P" <?php echo (($config['pdf_orientacion_default'] ?? '') == 'P') ? 'selected' : ''; ?>>Vertical (Portrait)</option>
                                                <option value="L" <?php echo (($config['pdf_orientacion_default'] ?? '') == 'L') ? 'selected' : ''; ?>>Horizontal (Landscape)</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="pdf_fuente" class="form-label">Fuente Predeterminada:</label>
                                            <select class="form-select" id="pdf_fuente" name="pdf_fuente">
                                                <option value="dejavusans" <?php echo (($config['pdf_fuente'] ?? '') == 'dejavusans') ? 'selected' : ''; ?>>DejaVu Sans</option>
                                                <option value="times" <?php echo (($config['pdf_fuente'] ?? '') == 'times') ? 'selected' : ''; ?>>Times New Roman</option>
                                                <option value="helvetica" <?php echo (($config['pdf_fuente'] ?? '') == 'helvetica') ? 'selected' : ''; ?>>Helvetica</option>
                                                <option value="courier" <?php echo (($config['pdf_fuente'] ?? '') == 'courier') ? 'selected' : ''; ?>>Courier</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pdf_color_primario" class="form-label">Color Primario (Hexadecimal):</label>
                                    <input type="text" class="form-control" id="pdf_color_primario" name="pdf_color_primario" 
                                           value="<?php echo htmlspecialchars($config['pdf_color_primario'] ?? '#336699'); ?>" placeholder="#336699">
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="pdf_mostrar_logo" name="pdf_mostrar_logo" value="1"
                                           <?php echo (($config['pdf_mostrar_logo'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="pdf_mostrar_logo">
                                        Mostrar logotipo de la institución
                                    </label>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="pdf_mostrar_direccion" name="pdf_mostrar_direccion" value="1"
                                           <?php echo (($config['pdf_mostrar_direccion'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="pdf_mostrar_direccion">
                                        Mostrar dirección de la institución
                                    </label>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="pdf_mostrar_telefonos" name="pdf_mostrar_telefonos" value="1"
                                           <?php echo (($config['pdf_mostrar_telefonos'] ?? '') == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="pdf_mostrar_telefonos">
                                        Mostrar teléfonos de contacto
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="pdf_membrete" class="form-label">Membrete (opcional):</label>
                                    <input type="file" class="form-control" id="pdf_membrete" name="pdf_membrete" accept="image/*">
                                    <div class="form-text">Imagen para usar como membrete en documentos PDF.</div>
                                    
                                    <?php if (!empty($config['pdf_membrete'])): ?>
                                    <div class="mt-2">
                                        <img src="../../<?php echo htmlspecialchars($config['pdf_membrete']); ?>" alt="Membrete actual" class="img-thumbnail" style="max-height: 100px">
                                        <p class="text-muted">Membrete actual</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Probar Correo -->
<div class="modal fade" id="modalProbarCorreo" tabindex="-1" aria-labelledby="modalProbarCorreoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProbarCorreoLabel">Probar Configuración de Correo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="email_prueba" class="form-label">Correo Electrónico de Prueba:</label>
                    <input type="email" class="form-control" id="email_prueba" name="email_prueba" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                </div>
                
                <div id="resultado_prueba_correo"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnEnviarPrueba">
                    <i class="fas fa-paper-plane me-1"></i> Enviar Correo de Prueba
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar clic en el botón de probar correo
    document.getElementById('btnProbarCorreo').addEventListener('click', function() {
        var modalProbarCorreo = new bootstrap.Modal(document.getElementById('modalProbarCorreo'));
        modalProbarCorreo.show();
    });
    
    // Enviar correo de prueba
    document.getElementById('btnEnviarPrueba').addEventListener('click', function() {
        var email = document.getElementById('email_prueba').value;
        if (!email) {
            alert('Por favor ingrese un correo electrónico válido.');
            return;
        }
        
        var boton = this;
        var resultado = document.getElementById('resultado_prueba_correo');
        
        boton.disabled = true;
        boton.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Enviando...';
        resultado.innerHTML = '<div class="alert alert-info">Enviando correo de prueba...</div>';
        
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'accion=probar_correo&email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                resultado.innerHTML = '<div class="alert alert-success">' + data.mensaje + '</div>';
            } else {
                resultado.innerHTML = '<div class="alert alert-danger">' + data.mensaje + '</div>';
            }
        })
        .catch(error => {
            resultado.innerHTML = '<div class="alert alert-danger">Error en la comunicación con el servidor.</div>';
            console.error('Error:', error);
        })
        .finally(() => {
            boton.disabled = false;
            boton.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Enviar Correo de Prueba';
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>