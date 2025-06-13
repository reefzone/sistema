<?php
/**
 * Funciones para envío de correos y comunicados
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Busca la ruta de PHPMailer en rutas predefinidas.
 *
 * @return string|false Ruta completa si se encuentra, false si no.
 */
function findPHPMailerPath() {
    $root_dir = $_SERVER['DOCUMENT_ROOT'];
    $possible_paths = [
        '/lib/PHPMailer-master/src/',  // Si los archivos están en src
        '/lib/PHPMailer-master/',      // Si los archivos están en la raíz de PHPMailer-master
        '/libs/phpmailer/src/',        // Ruta original que intentamos
        '/PHPMailer-master/src/',      // Otra posible ubicación
        '/PHPMailer/src/',             // Otra posible ubicación
        '/vendor/phpmailer/phpmailer/src/' // Ubicación cuando se instala via Composer
    ];
    
    foreach ($possible_paths as $path) {
        $full_path = $root_dir . $path;
        if (file_exists($full_path . 'PHPMailer.php')) {
            return $full_path;
        }
    }
    
    error_log("No se pudo encontrar PHPMailer en ninguna ubicación conocida");
    return false;
}

// Obtener la ruta de PHPMailer
$phpmailer_path = findPHPMailerPath();

// Solo cargar PHPMailer si se ha encontrado la ruta
if ($phpmailer_path) {
    require_once $phpmailer_path . 'Exception.php';
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
}

/**
 * Configurar y enviar un correo electrónico
 * 
 * @param string $destinatario Correo del destinatario
 * @param string $asunto Asunto del correo
 * @param string $contenido Contenido HTML del correo
 * @param array $adjuntos Array de rutas de archivos adjuntos (opcional)
 * @param array $cc Array de correos para CC (opcional)
 * @param array $bcc Array de correos para BCC (opcional)
 * @return array Resultado del envío ['success' => bool, 'error' => string]
 */
function sendEmail($destinatario, $asunto, $contenido, $adjuntos = [], $cc = [], $bcc = []) {
    global $phpmailer_path;
    
    // Verificar si PHPMailer está disponible
    if (!$phpmailer_path) {
        return ['success' => false, 'error' => 'PHPMailer no está disponible en el sistema'];
    }
    
    // Crear instancia de PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        // Destinatarios
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($destinatario);
        
        // CC
        if (!empty($cc)) {
            foreach ($cc as $email) {
                $mail->addCC($email);
            }
        }
        
        // BCC
        if (!empty($bcc)) {
            foreach ($bcc as $email) {
                $mail->addBCC($email);
            }
        }
        
        // Adjuntos
        if (!empty($adjuntos)) {
            foreach ($adjuntos as $adjunto) {
                if (file_exists($adjunto['ruta'])) {
                    $mail->addAttachment($adjunto['ruta'], $adjunto['nombre_original']);
                }
            }
        }
        
        // Configuración del correo
        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $contenido;
        
        // Generar versión de texto plano
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $contenido));
        
        // Enviar correo
        $mail->send();
        
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Enviar un comunicado a todos sus destinatarios
 * 
 * @param int $id_comunicado ID del comunicado a enviar
 * @return array Resultado del envío ['success' => bool, 'error' => string, 'enviados' => int, 'errores' => int]
 */
function enviar_comunicado($id_comunicado) {
    global $conexion, $phpmailer_path;
    
    // Verificar si PHPMailer está disponible
    if (!$phpmailer_path) {
        return ['success' => false, 'error' => 'PHPMailer no está disponible en el sistema', 'enviados' => 0, 'errores' => 0];
    }
    
    // Validar parámetro
    $id_comunicado = intval($id_comunicado);
    if ($id_comunicado <= 0) {
        return ['success' => false, 'error' => 'ID de comunicado no válido', 'enviados' => 0, 'errores' => 0];
    }
    
    // Iniciar transacción
    $conexion->begin_transaction();
    
    try {
        // Obtener datos del comunicado
        $query = "SELECT c.*, u.nombre as enviado_por_nombre, u.apellido_paterno as enviado_por_apellido
                  FROM comunicados c
                  JOIN usuarios u ON c.enviado_por = u.id_usuario
                  WHERE c.id_comunicado = ? AND c.eliminado = 0";
        $stmt = $conexion->prepare($query);
        $stmt->bind_param("i", $id_comunicado);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("El comunicado no existe o ha sido eliminado");
        }
        
        $comunicado = $result->fetch_assoc();
        
        // Obtener adjuntos
        $adjuntos = [];
        if ($comunicado['tiene_adjuntos']) {
            $query_adjuntos = "SELECT * FROM comunicados_adjuntos WHERE id_comunicado = ?";
            $stmt_adjuntos = $conexion->prepare($query_adjuntos);
            $stmt_adjuntos->bind_param("i", $id_comunicado);
            $stmt_adjuntos->execute();
            $result_adjuntos = $stmt_adjuntos->get_result();
            
            while ($row = $result_adjuntos->fetch_assoc()) {
                $adjuntos[] = $row;
            }
        }
        
        // Determinar destinatarios
        $destinatarios = [];
        
        if ($comunicado['grupo_especifico'] == 1) {
            // Alumnos específicos (ya registrados en comunicados_destinatarios)
            $query_dest = "SELECT cd.id_destinatario, a.id_alumno, a.nombre, a.apellido, 
                          g.nombre_grupo, gr.nombre_grado,
                          ce.id_contacto, ce.nombre_completo as contacto_nombre, ce.email as contacto_email
                          FROM comunicados_destinatarios cd
                          JOIN alumnos a ON cd.id_alumno = a.id_alumno
                          JOIN grupos g ON a.id_grupo = g.id_grupo
                          JOIN grados gr ON g.id_grado = gr.id_grado
                          LEFT JOIN contactos_emergencia ce ON a.id_alumno = ce.id_alumno AND ce.es_tutor = 1
                          WHERE cd.id_comunicado = ?";
            $stmt_dest = $conexion->prepare($query_dest);
            $stmt_dest->bind_param("i", $id_comunicado);
            $stmt_dest->execute();
            $result_dest = $stmt_dest->get_result();
            
            while ($row = $result_dest->fetch_assoc()) {
                $destinatarios[] = $row;
            }
        } else if ($comunicado['id_grupo'] !== null) {
            // Alumnos de un grupo específico
            $query_alumnos = "SELECT a.id_alumno, a.nombre, a.apellido, 
                             g.nombre_grupo, gr.nombre_grado,
                             ce.id_contacto, ce.nombre_completo as contacto_nombre, ce.email as contacto_email
                             FROM alumnos a
                             JOIN grupos g ON a.id_grupo = g.id_grupo
                             JOIN grados gr ON g.id_grado = gr.id_grado
                             LEFT JOIN contactos_emergencia ce ON a.id_alumno = ce.id_alumno AND ce.es_tutor = 1
                             WHERE a.id_grupo = ? AND a.activo = 1";
            $stmt_alumnos = $conexion->prepare($query_alumnos);
            $stmt_alumnos->bind_param("i", $comunicado['id_grupo']);
            $stmt_alumnos->execute();
            $result_alumnos = $stmt_alumnos->get_result();
            
            // Registrar destinatarios en la tabla comunicados_destinatarios
            $query_insert = "INSERT INTO comunicados_destinatarios (id_comunicado, id_alumno, id_contacto, email, estado)
                            VALUES (?, ?, ?, ?, 'pendiente')";
            $stmt_insert = $conexion->prepare($query_insert);
            
            while ($row = $result_alumnos->fetch_assoc()) {
                // Insertar en la tabla
                $email = $row['contacto_email'] ?? null;
                $stmt_insert->bind_param("iiis", $id_comunicado, $row['id_alumno'], $row['id_contacto'], $email);
                $stmt_insert->execute();
                
                // Añadir a la lista de destinatarios
                $row['id_destinatario'] = $stmt_insert->insert_id;
                $destinatarios[] = $row;
            }
        } else {
            // Todos los alumnos
            $query_alumnos = "SELECT a.id_alumno, a.nombre, a.apellido, 
                             g.nombre_grupo, gr.nombre_grado,
                             ce.id_contacto, ce.nombre_completo as contacto_nombre, ce.email as contacto_email
                             FROM alumnos a
                             JOIN grupos g ON a.id_grupo = g.id_grupo
                             JOIN grados gr ON g.id_grado = gr.id_grado
                             LEFT JOIN contactos_emergencia ce ON a.id_alumno = ce.id_alumno AND ce.es_tutor = 1
                             WHERE a.activo = 1";
            $result_alumnos = $conexion->query($query_alumnos);
            
            // Registrar destinatarios en la tabla comunicados_destinatarios
            $query_insert = "INSERT INTO comunicados_destinatarios (id_comunicado, id_alumno, id_contacto, email, estado)
                            VALUES (?, ?, ?, ?, 'pendiente')";
            $stmt_insert = $conexion->prepare($query_insert);
            
            while ($row = $result_alumnos->fetch_assoc()) {
                // Insertar en la tabla
                $email = $row['contacto_email'] ?? null;
                $stmt_insert->bind_param("iiis", $id_comunicado, $row['id_alumno'], $row['id_contacto'], $email);
                $stmt_insert->execute();
                
                // Añadir a la lista de destinatarios
                $row['id_destinatario'] = $stmt_insert->insert_id;
                $destinatarios[] = $row;
            }
        }
        
        // Actualizar estado del comunicado
        $query_update = "UPDATE comunicados SET estado = 'enviado', fecha_envio = NOW() WHERE id_comunicado = ?";
        $stmt_update = $conexion->prepare($query_update);
        $stmt_update->bind_param("i", $id_comunicado);
        $stmt_update->execute();
        
        // Confirmar transacción
        $conexion->commit();
        
        // Enviar correos (después de confirmar transacción para evitar problemas si falla el envío)
        $enviados = 0;
        $errores = 0;
        
        foreach ($destinatarios as $destinatario) {
            // Solo procesar si hay email
            if (!empty($destinatario['contacto_email'])) {
                // Personalizar contenido
                $contenido_personalizado = $comunicado['contenido'];
                $contenido_personalizado = str_replace('{{NOMBRE_ALUMNO}}', $destinatario['nombre'] . ' ' . $destinatario['apellido'], $contenido_personalizado);
                $contenido_personalizado = str_replace('{{NOMBRE_CONTACTO}}', $destinatario['contacto_nombre'] ?? 'Estimado(a)', $contenido_personalizado);
                $contenido_personalizado = str_replace('{{GRADO}}', $destinatario['nombre_grado'] ?? '', $contenido_personalizado);
                $contenido_personalizado = str_replace('{{GRUPO}}', $destinatario['nombre_grupo'] ?? '', $contenido_personalizado);
                
                // Añadir pixel de seguimiento
                $token = md5($destinatario['id_destinatario'] . $id_comunicado . time()) . '_' . $destinatario['id_destinatario'];
                $pixel = '<img src="' . SITE_URL . '/modules/comunicados/api.php?token=' . $token . '" width="1" height="1" alt="" style="display:none">';
                $contenido_personalizado .= $pixel;
                
                // Añadir firma
                $firma = '<br><br><hr><p style="font-size: 14px; color: #666;">Este comunicado fue enviado por: ' . 
                         $comunicado['enviado_por_nombre'] . ' ' . $comunicado['enviado_por_apellido'] . '<br>' .
                         'ESCUELA SECUNDARIA TECNICA #82<br>' .
                         'Por favor no responda a este correo.</p>';
                $contenido_personalizado .= $firma;
                
                // Enviar correo
                $resultado = sendEmail(
                    $destinatario['contacto_email'],
                    $comunicado['titulo'],
                    $contenido_personalizado,
                    $adjuntos
                );
                
                // Actualizar estado del envío
                $query_update_estado = "UPDATE comunicados_destinatarios SET 
                                        estado = ?, 
                                        fecha_envio = NOW(), 
                                        error_mensaje = ?, 
                                        intentos = intentos + 1
                                        WHERE id_destinatario = ?";
                $stmt_update_estado = $conexion->prepare($query_update_estado);
                
                if ($resultado['success']) {
                    $estado = 'enviado';
                    $error_mensaje = '';
                    $enviados++;
                } else {
                    $estado = 'error';
                    $error_mensaje = $resultado['error'];
                    $errores++;
                }
                
                $stmt_update_estado->bind_param("ssi", $estado, $error_mensaje, $destinatario['id_destinatario']);
                $stmt_update_estado->execute();
            }
        }
        
        return [
            'success' => true,
            'error' => '',
            'enviados' => $enviados,
            'errores' => $errores,
            'total' => count($destinatarios)
        ];
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $conexion->rollback();
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'enviados' => 0,
            'errores' => 0
        ];
    }
}

/**
 * Formatear tamaño de archivo en formato legible
 * 
 * @param int $tamano Tamaño en bytes
 * @return string Tamaño formateado (KB, MB, etc.)
 */
function formatear_tamano($tamano) {
    if ($tamano < 1024) {
        return $tamano . ' bytes';
    } elseif ($tamano < 1048576) {
        return round($tamano / 1024, 2) . ' KB';
    } elseif ($tamano < 1073741824) {
        return round($tamano / 1048576, 2) . ' MB';
    } else {
        return round($tamano / 1073741824, 2) . ' GB';
    }
}