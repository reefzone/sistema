<?php
/**
 * Generador de PDFs para Credenciales
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir librería TCPDF
require_once '../../lib/TCPDF-main/tcpdf.php';

/**
 * Clase para generar credenciales en PDF
 */
class CredencialPDF {
    private $conexion;
    private $config;
    private $visual_config;
    
    /**
     * Constructor
     * 
     * @param mysqli $conexion Conexión a la base de datos
     * @param array $config Configuración de la plantilla
     */
    public function __construct($conexion, $config) {
        $this->conexion = $conexion;
        $this->config = $config;
        
        // Decodificar configuración visual
        $this->visual_config = !empty($config['config_visual']) ? 
            json_decode($config['config_visual'], true) : [];
        
        // Valores por defecto si no hay configuración visual
        $defaults = [
            'color_fondo' => '#FFFFFF',
            'color_borde' => '#0f172a',
            'grosor_borde' => 1,
            'esquinas_redondeadas' => 16,
            'fuente_titulo' => 'helvetica',
            'tamano_titulo' => 10,
            'color_titulo' => '#000000',
            'tamano_texto' => 8,
            'color_texto' => '#000000',
            'tipo_credencial' => 'CREDENCIAL DE ESTUDIANTE',
            'nombre_escuela' => 'ESCUELA SECUNDARIA TÉCNICA #82',
            'telefono_contacto' => '(55) 1234-5678',
            'direccion_contacto' => 'Calle Principal s/n, Col. Centro',
            'website_contacto' => 'www.est82.edu.mx'
        ];
        
        // Combinar valores por defecto con configuración visual
        $this->visual_config = array_merge($defaults, $this->visual_config);
    }
    
    /**
     * Genera una credencial individual para un alumno
     * 
     * @param array $alumno Datos del alumno
     * @param string $ruta_pdf Ruta donde guardar el archivo PDF
     * @return bool True si se generó correctamente, False en caso contrario
     */
    public function generarCredencialIndividual($alumno, $ruta_pdf) {
        // Crear instancia de TCPDF
        $pdf = new TCPDF('L', 'mm', array(86, 54), true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('Sistema Escolar EST #82');
        $pdf->SetAuthor('EST #82');
        $pdf->SetTitle('Credencial Escolar - ' . $alumno['nombre'] . ' ' . $alumno['apellido']);
        $pdf->SetSubject('Credencial Escolar');
        
        // Eliminar encabezado y pie de página
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Establecer márgenes
        $pdf->SetMargins(3, 3, 3);
        
        // Desactivar salto de página automático
        $pdf->SetAutoPageBreak(false, 0);
        
        // Obtener colores de la configuración visual
        $color_borde = $this->hexToRgb($this->visual_config['color_borde']);
        $color_fondo = $this->hexToRgb($this->visual_config['color_fondo']);
        $color_titulo = $this->hexToRgb($this->visual_config['color_titulo']);
        $color_texto = $this->hexToRgb($this->visual_config['color_texto']);
        
        // Crear página para frente de la credencial
        $pdf->AddPage();
        
        // Crear fondo con color configurado
        $pdf->SetFillColor($color_fondo[0], $color_fondo[1], $color_fondo[2]);
        $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');
        
        // Dibujar borde de la credencial
        $pdf->SetLineWidth($this->visual_config['grosor_borde']);
        $pdf->SetDrawColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->Rect(0.5, 0.5, $pdf->getPageWidth()-1, $pdf->getPageHeight()-1, 'D');
        
        // Agregar logo de la escuela
        if (!empty($this->config['logo_path']) && file_exists($this->config['logo_path'])) {
            $pdf->Image($this->config['logo_path'], 5, 5, 30, 0, '', '', '', false, 300);
        }
        
        // Nombre de la escuela
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
        $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
        $pdf->SetXY(40, 5);
        $pdf->Cell(43, 5, $this->visual_config['nombre_escuela'], 0, 1, 'R');
        
        // Texto "CREDENCIAL DE ESTUDIANTE"
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(40, 10);
        $pdf->Cell(43, 5, $this->visual_config['tipo_credencial'], 0, 1, 'R');
        
        // Ciclo escolar
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 1);
        $pdf->SetXY(40, 15);
        $pdf->Cell(43, 5, 'CICLO ESCOLAR ' . $alumno['ciclo_escolar'], 0, 1, 'R');
        
        // Fotografía del alumno
        if ($this->config['mostrar_foto'] && !empty($alumno['ruta_foto']) && file_exists($alumno['ruta_foto'])) {
            $pdf->Image($alumno['ruta_foto'], 5, 20, 25, 30, '', '', '', false, 300);
        } else {
            // Silueta en caso de no tener foto
            $pdf->RoundedRect(5, 20, 25, 30, 2, '1111', 'DF', array(), array(230, 230, 230));
            $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetXY(5, 30);
            $pdf->Cell(25, 10, 'SIN FOTO', 0, 1, 'C');
        }
        
        // Datos del alumno
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
        $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->SetXY(33, 20);
        $pdf->Cell(50, 5, $alumno['nombre'] . ' ' . $alumno['apellido'], 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(33, 26);
        $pdf->Cell(25, 4, 'MATRÍCULA:', 0, 0, 'L');
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->Cell(25, 4, $alumno['matricula'], 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(33, 31);
        $pdf->Cell(25, 4, 'GRADO Y GRUPO:', 0, 0, 'L');
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->Cell(25, 4, $alumno['nombre_grado'] . ' - ' . $alumno['nombre_grupo'], 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(33, 36);
        $pdf->Cell(25, 4, 'TURNO:', 0, 0, 'L');
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->Cell(25, 4, $alumno['nombre_turno'], 0, 1, 'L');
        
        // Firma
        if (!empty($this->config['firma_path']) && file_exists($this->config['firma_path'])) {
            $pdf->Image($this->config['firma_path'], 55, 38, 25, 10, '', '', '', false, 300);
        }
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto'] - 1);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(55, 48);
        $pdf->Cell(25, 3, 'DIRECTOR', 0, 1, 'C');
        
        // Texto inferior
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 2);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(5, 50);
        $pdf->MultiCell(76, 3, $this->config['texto_inferior'], 0, 'C');
        
        // Página para el reverso de la credencial
        $pdf->AddPage();
        
        // Crear fondo con color configurado
        $pdf->SetFillColor($color_fondo[0], $color_fondo[1], $color_fondo[2]);
        $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');
        
        // Dibujar borde de la credencial
        $pdf->SetLineWidth($this->visual_config['grosor_borde']);
        $pdf->SetDrawColor($color_borde[0], $color_borde[1], $color_borde[2]);
        $pdf->Rect(0.5, 0.5, $pdf->getPageWidth()-1, $pdf->getPageHeight()-1, 'D');
        
        // Vigencia
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
        $pdf->SetXY(5, 5);
        $pdf->Cell(76, 5, 'VIGENCIA:', 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(5, 10);
        $pdf->Cell(76, 5, $this->config['vigencia'], 0, 1, 'L');
        
        // Información de contacto
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto'] - 1);
        $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
        $pdf->SetXY(5, 18);
        $pdf->Cell(76, 5, 'EN CASO DE ENCONTRAR ESTA CREDENCIAL, FAVOR DE ENTREGARLA EN:', 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 1);
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetXY(5, 23);
        $contacto_info = $this->visual_config['nombre_escuela'] . "\n" . 
                        $this->visual_config['direccion_contacto'] . "\n" .
                        "Tel: " . $this->visual_config['telefono_contacto'];
        $pdf->MultiCell(76, 4, $contacto_info, 0, 'C');
        
        // Código QR si está habilitado
        if ($this->config['mostrar_qr']) {
            // Datos para el QR
            $datos_qr = "EST82:AL:{$alumno['matricula']}:{$alumno['id_alumno']}";
            
            // Generar QR
            $style = array(
                'border' => 2,
                'vpadding' => 'auto',
                'hpadding' => 'auto',
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => array(255, 255, 255),
                'module_width' => 1,
                'module_height' => 1
            );
            
            $pdf->write2DBarcode($datos_qr, 'QRCODE,L', 57, 35, 24, 15, $style, 'N');
            
            $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 2);
            $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
            $pdf->SetXY(57, 50);
            $pdf->Cell(24, 3, 'VERIFICACIÓN', 0, 1, 'C');
        }
        
        // Guardar PDF
        return $pdf->Output($ruta_pdf, 'F');
    }
    
   /**
     * Genera credenciales para todos los alumnos de un grupo
     * 
     * @param array $grupo Datos del grupo
     * @param array $alumnos Lista de alumnos del grupo
     * @param string $ruta_pdf Ruta donde guardar el archivo PDF
     * @return bool True si se generó correctamente, False en caso contrario
     */
    public function generarCredencialesGrupo($grupo, $alumnos, $ruta_pdf) {
        // Crear instancia de TCPDF
        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('Sistema Escolar EST #82');
        $pdf->SetAuthor('EST #82');
        $pdf->SetTitle('Credenciales Escolares - Grupo ' . $grupo['nombre_grupo']);
        $pdf->SetSubject('Credenciales Escolares');
        
        // Eliminar encabezado y pie de página
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Establecer márgenes
        $pdf->SetMargins(10, 10, 10);
        
        // Desactivar salto de página automático
        $pdf->SetAutoPageBreak(true, 10);
        
        // Obtener colores de la configuración visual
        $color_borde = $this->hexToRgb($this->visual_config['color_borde']);
        $color_fondo = $this->hexToRgb($this->visual_config['color_fondo']);
        $color_titulo = $this->hexToRgb($this->visual_config['color_titulo']);
        $color_texto = $this->hexToRgb($this->visual_config['color_texto']);
        
        // Crear primera página con información del grupo
        $pdf->AddPage();
        
        // Título
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', 16);
        $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
        $pdf->Cell(0, 10, 'CREDENCIALES ESCOLARES', 0, 1, 'C');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', 14);
        $pdf->Cell(0, 10, 'Grupo: ' . $grupo['nombre_grupo'], 0, 1, 'C');
        
        $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', 12);
        $pdf->Cell(0, 6, 'Grado: ' . $grupo['nombre_grado'], 0, 1, 'C');
        $pdf->Cell(0, 6, 'Turno: ' . $grupo['nombre_turno'], 0, 1, 'C');
        $pdf->Cell(0, 6, 'Ciclo Escolar: ' . $grupo['ciclo_escolar'], 0, 1, 'C');
        
        // Total de alumnos
        $pdf->Ln(5);
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', 12);
        $pdf->Cell(0, 6, 'Total de alumnos: ' . count($alumnos), 0, 1, 'C');
        
        // Fecha de generación
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', 10);
        $pdf->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1, 'C');
        
        // Instrucciones
        $pdf->Ln(5);
        $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', 10);
        $pdf->Cell(0, 6, 'INSTRUCCIONES:', 0, 1, 'L');
        
        $pdf->SetFont($this->visual_config['fuente_titulo'], '', 10);
        $pdf->MultiCell(0, 5, "- Imprima este documento en papel grueso (cartulina o similar).
- Las credenciales están diseñadas para recortarse con formato de 86 x 54 mm.
- Se incluyen líneas guía para facilitar el corte.
- Cada hoja contiene 4 credenciales (2 alumnos con frente y reverso).
- Para impresión a doble cara, utilice la opción 'Voltear por el lado corto'.
- Se recomienda usar impresión a color para mejor identificación.", 0, 'L');
        
        // Línea horizontal
        $pdf->Ln(5);
        $pdf->Line(10, $pdf->GetY(), $pdf->getPageWidth() - 10, $pdf->GetY());
        $pdf->Ln(5);
        
        // Generar páginas de credenciales (4 credenciales por página, 2 frente y 2 reverso)
        $credenciales_por_pagina = 2;
        $total_alumnos = count($alumnos);
        $total_paginas = ceil($total_alumnos / $credenciales_por_pagina);
        
        // Definir medidas de credencial (86x54mm)
        $ancho_credencial = 86;
        $alto_credencial = 54;
        
        // Márgenes y espaciados
        $margen_x = 15;
        $margen_y = 15;
        $espacio_entre_credenciales = 10;
        
        // Calcular posiciones
        $posiciones = array(
            // Primera fila
            array('x' => $margen_x, 'y' => $margen_y),
            array('x' => $margen_x, 'y' => $margen_y + $alto_credencial + $espacio_entre_credenciales),
        );
        
        for ($pagina = 0; $pagina < $total_paginas; $pagina++) {
            // Añadir nueva página para las credenciales
            $pdf->AddPage();
            
            // Procesar dos alumnos por página
            for ($i = 0; $i < $credenciales_por_pagina; $i++) {
                $indice_alumno = $pagina * $credenciales_por_pagina + $i;
                
                // Verificar si hay más alumnos
                if ($indice_alumno >= $total_alumnos) {
                    break;
                }
                
                $alumno = $alumnos[$indice_alumno];
                $pos = $posiciones[$i];
                
                // FRENTE DE LA CREDENCIAL
                // Crear fondo con color configurado
                $pdf->SetFillColor($color_fondo[0], $color_fondo[1], $color_fondo[2]);
                $pdf->RoundedRect($pos['x'], $pos['y'], $ancho_credencial, $alto_credencial, 2, '1111', 'F');
                
                // Dibujar borde de la credencial
                $pdf->SetLineWidth($this->visual_config['grosor_borde']);
                $pdf->SetDrawColor($color_borde[0], $color_borde[1], $color_borde[2]);
                $pdf->RoundedRect($pos['x'], $pos['y'], $ancho_credencial, $alto_credencial, 2, '1111', 'D');
                
                // Agregar logo de la escuela
                if (!empty($this->config['logo_path']) && file_exists($this->config['logo_path'])) {
                    $pdf->Image($this->config['logo_path'], $pos['x'] + 5, $pos['y'] + 5, 30, 0, '', '', '', false, 300);
                }
                
                // Nombre de la escuela
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
                $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
                $pdf->SetXY($pos['x'] + 40, $pos['y'] + 5);
                $pdf->Cell(43, 5, $this->visual_config['nombre_escuela'], 0, 1, 'R');
                
                // Texto "CREDENCIAL DE ESTUDIANTE"
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 40, $pos['y'] + 10);
                $pdf->Cell(43, 5, $this->visual_config['tipo_credencial'], 0, 1, 'R');
                
                // Ciclo escolar
                $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 1);
                $pdf->SetXY($pos['x'] + 40, $pos['y'] + 15);
                $pdf->Cell(43, 5, 'CICLO ESCOLAR ' . $grupo['ciclo_escolar'], 0, 1, 'R');
                
                // Fotografía del alumno
                if ($this->config['mostrar_foto']) {
                    $ruta_foto = '';
                    
                    // Obtener ruta de la foto
                    $query = "SELECT ruta_foto FROM alumnos_fotos WHERE id_alumno = ? ORDER BY fecha_subida DESC LIMIT 1";
                    $stmt = $this->conexion->prepare($query);
                    $stmt->bind_param("i", $alumno['id_alumno']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $ruta_foto = $result->fetch_assoc()['ruta_foto'];
                    }
                    
                    if (!empty($ruta_foto) && file_exists($ruta_foto)) {
                        $pdf->Image($ruta_foto, $pos['x'] + 5, $pos['y'] + 20, 25, 30, '', '', '', false, 300);
                    } else {
                        // Silueta en caso de no tener foto
                        $pdf->RoundedRect($pos['x'] + 5, $pos['y'] + 20, 25, 30, 2, '1111', 'DF', array(), array(230, 230, 230));
                        $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
                        $pdf->SetTextColor(100, 100, 100);
                        $pdf->SetXY($pos['x'] + 5, $pos['y'] + 30);
                        $pdf->Cell(25, 10, 'SIN FOTO', 0, 1, 'C');
                    }
                }
                
                // Datos del alumno
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
                $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
                $pdf->SetXY($pos['x'] + 33, $pos['y'] + 20);
                $pdf->Cell(50, 5, $alumno['nombre'] . ' ' . $alumno['apellido'], 0, 1, 'L');
                
                $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 33, $pos['y'] + 26);
                $pdf->Cell(25, 4, 'MATRÍCULA:', 0, 0, 'L');
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
                $pdf->Cell(25, 4, $alumno['matricula'], 0, 1, 'L');
                
                $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 33, $pos['y'] + 31);
                $pdf->Cell(25, 4, 'GRADO Y GRUPO:', 0, 0, 'L');
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
                $pdf->Cell(25, 4, $grupo['nombre_grado'] . ' - ' . $grupo['nombre_grupo'], 0, 1, 'L');
                
                $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 33, $pos['y'] + 36);
                $pdf->Cell(25, 4, 'TURNO:', 0, 0, 'L');
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
                $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
                $pdf->Cell(25, 4, $grupo['nombre_turno'], 0, 1, 'L');
                
                // Firma
                if (!empty($this->config['firma_path']) && file_exists($this->config['firma_path'])) {
                    $pdf->Image($this->config['firma_path'], $pos['x'] + 55, $pos['y'] + 38, 25, 10, '', '', '', false, 300);
                }
                
                $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto'] - 1);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 55, $pos['y'] + 48);
                $pdf->Cell(25, 3, 'DIRECTOR', 0, 1, 'C');
                
                // Texto inferior
                $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 2);
                $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                $pdf->SetXY($pos['x'] + 5, $pos['y'] + 50);
                $pdf->MultiCell(76, 3, $this->config['texto_inferior'], 0, 'C');
            }
            
            // Añadir siguiente página para los reversos
            $pdf->AddPage();
            
			// Procesar los reversos de las credenciales (en orden inverso para impresión a doble cara)
           for ($i = 0; $i < $credenciales_por_pagina; $i++) {
               $indice_alumno = $pagina * $credenciales_por_pagina + $i;
               
               // Verificar si hay más alumnos
               if ($indice_alumno >= $total_alumnos) {
                   break;
               }
               
               // Para impresión a doble cara, invertimos el orden
               $pos = $posiciones[$credenciales_por_pagina - 1 - $i];
               $alumno = $alumnos[$indice_alumno];
               
               // REVERSO DE LA CREDENCIAL
               
               // Crear fondo con color configurado
               $pdf->SetFillColor($color_fondo[0], $color_fondo[1], $color_fondo[2]);
               $pdf->RoundedRect($pos['x'], $pos['y'], $ancho_credencial, $alto_credencial, 2, '1111', 'F');
               
               // Dibujar borde de la credencial
               $pdf->SetLineWidth($this->visual_config['grosor_borde']);
               $pdf->SetDrawColor($color_borde[0], $color_borde[1], $color_borde[2]);
               $pdf->RoundedRect($pos['x'], $pos['y'], $ancho_credencial, $alto_credencial, 2, '1111', 'D');
               
               // Vigencia
               $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
               $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
               $pdf->SetXY($pos['x'] + 5, $pos['y'] + 5);
               $pdf->Cell(76, 5, 'VIGENCIA:', 0, 1, 'L');
               
               $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
               $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
               $pdf->SetXY($pos['x'] + 5, $pos['y'] + 10);
               $pdf->Cell(76, 5, $this->config['vigencia'], 0, 1, 'L');
               
               // Información de contacto
               $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto'] - 1);
               $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
               $pdf->SetXY($pos['x'] + 5, $pos['y'] + 18);
               $pdf->Cell(76, 5, 'EN CASO DE ENCONTRAR ESTA CREDENCIAL, FAVOR DE ENTREGARLA EN:', 0, 1, 'L');
               
               $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 1);
               $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
               $pdf->SetXY($pos['x'] + 5, $pos['y'] + 23);
               $contacto_info = $this->visual_config['nombre_escuela'] . "\n" . 
                               $this->visual_config['direccion_contacto'] . "\n" .
                               "Tel: " . $this->visual_config['telefono_contacto'];
               $pdf->MultiCell(76, 4, $contacto_info, 0, 'C');
               
               // Código QR si está habilitado
               if ($this->config['mostrar_qr']) {
                   // Datos para el QR
                   $datos_qr = "EST82:AL:{$alumno['matricula']}:{$alumno['id_alumno']}";
                   
                   // Generar QR
                   $style = array(
                       'border' => 2,
                       'vpadding' => 'auto',
                       'hpadding' => 'auto',
                       'fgcolor' => array(0, 0, 0),
                       'bgcolor' => array(255, 255, 255),
                       'module_width' => 1,
                       'module_height' => 1
                   );
                   
                   $pdf->write2DBarcode($datos_qr, 'QRCODE,L', $pos['x'] + 57, $pos['y'] + 35, 24, 15, $style, 'N');
                   
                   $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 2);
                   $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
                   $pdf->SetXY($pos['x'] + 57, $pos['y'] + 50);
                   $pdf->Cell(24, 3, 'VERIFICACIÓN', 0, 1, 'C');
               }
           }
       }
       
       // Guardar PDF
       return $pdf->Output($ruta_pdf, 'F');
   }
   
   /**
    * Genera una vista previa de credencial
    * 
    * @param string $color Color en formato hexadecimal
    * @return string Ruta del archivo de imagen generado
    */
   public function generarVistaPrevia($color = '#0066CC') {
       // Crear instancia de TCPDF
       $pdf = new TCPDF('L', 'mm', array(86, 54), true, 'UTF-8', false);
       
       // Configurar documento
       $pdf->SetCreator('Sistema Escolar EST #82');
       $pdf->SetAuthor('EST #82');
       $pdf->SetTitle('Vista Previa de Credencial');
       
       // Eliminar encabezado y pie de página
       $pdf->setPrintHeader(false);
       $pdf->setPrintFooter(false);
       
       // Establecer márgenes
       $pdf->SetMargins(3, 3, 3);
       
       // Desactivar salto de página automático
       $pdf->SetAutoPageBreak(false, 0);
       
       // Obtener colores de la configuración visual
       $color_borde = $this->hexToRgb($this->visual_config['color_borde']);
       $color_fondo = $this->hexToRgb($this->visual_config['color_fondo']);
       $color_titulo = $this->hexToRgb($this->visual_config['color_titulo']);
       $color_texto = $this->hexToRgb($this->visual_config['color_texto']);
       
       // Crear página para frente de la credencial
       $pdf->AddPage();
       
       // Crear fondo con color configurado
       $pdf->SetFillColor($color_fondo[0], $color_fondo[1], $color_fondo[2]);
       $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');
       
       // Dibujar borde de la credencial
       $pdf->SetLineWidth($this->visual_config['grosor_borde']);
       $pdf->SetDrawColor($color_borde[0], $color_borde[1], $color_borde[2]);
       $pdf->Rect(0.5, 0.5, $pdf->getPageWidth()-1, $pdf->getPageHeight()-1, 'D');
       
       // Agregar logo de la escuela
       if (!empty($this->config['logo_path']) && file_exists($this->config['logo_path'])) {
           $pdf->Image($this->config['logo_path'], 5, 5, 30, 0, '', '', '', false, 300);
       }
       
       // Nombre de la escuela
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
       $pdf->SetTextColor($color_titulo[0], $color_titulo[1], $color_titulo[2]);
       $pdf->SetXY(40, 5);
       $pdf->Cell(43, 5, $this->visual_config['nombre_escuela'], 0, 1, 'R');
       
       // Texto "CREDENCIAL DE ESTUDIANTE"
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(40, 10);
       $pdf->Cell(43, 5, $this->visual_config['tipo_credencial'], 0, 1, 'R');
       
       // Ciclo escolar
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 1);
       $pdf->SetXY(40, 15);
       $pdf->Cell(43, 5, 'CICLO ESCOLAR 2024-2025', 0, 1, 'R');
       
       // Silueta en lugar de foto
       $pdf->RoundedRect(5, 20, 25, 30, 2, '1111', 'DF', array(), array(230, 230, 230));
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor(100, 100, 100);
       $pdf->SetXY(5, 30);
       $pdf->Cell(25, 10, 'FOTO', 0, 1, 'C');
       
       // Datos del alumno de ejemplo
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_titulo']);
       $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
       $pdf->SetXY(33, 20);
       $pdf->Cell(50, 5, 'NOMBRE DEL ALUMNO', 0, 1, 'L');
       
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(33, 26);
       $pdf->Cell(25, 4, 'MATRÍCULA:', 0, 0, 'L');
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
       $pdf->Cell(25, 4, '12345678', 0, 1, 'L');
       
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(33, 31);
       $pdf->Cell(25, 4, 'GRADO Y GRUPO:', 0, 0, 'L');
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
       $pdf->Cell(25, 4, '1° - A', 0, 1, 'L');
       
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(33, 36);
       $pdf->Cell(25, 4, 'TURNO:', 0, 0, 'L');
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto']);
       $pdf->SetTextColor($color_borde[0], $color_borde[1], $color_borde[2]);
       $pdf->Cell(25, 4, 'MATUTINO', 0, 1, 'L');
       
       // Firma
       if (!empty($this->config['firma_path']) && file_exists($this->config['firma_path'])) {
           $pdf->Image($this->config['firma_path'], 55, 38, 25, 10, '', '', '', false, 300);
       }
       
       $pdf->SetFont($this->visual_config['fuente_titulo'], 'B', $this->visual_config['tamano_texto'] - 1);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(55, 48);
       $pdf->Cell(25, 3, 'DIRECTOR', 0, 1, 'C');
       
       // Texto inferior
       $pdf->SetFont($this->visual_config['fuente_titulo'], '', $this->visual_config['tamano_texto'] - 2);
       $pdf->SetTextColor($color_texto[0], $color_texto[1], $color_texto[2]);
       $pdf->SetXY(5, 50);
       if (!empty($this->config['texto_inferior'])) {
           $pdf->MultiCell(76, 3, $this->config['texto_inferior'], 0, 'C');
       } else {
           $pdf->MultiCell(76, 3, 'Esta credencial acredita al portador como alumno regular de la Escuela Secundaria Técnica #82.', 0, 'C');
       }
       
       // Guardar como imagen PNG para vista previa
       $temp_dir = sys_get_temp_dir();
       $temp_file = tempnam($temp_dir, 'credencial_preview_');
       $pdf->Output($temp_file, 'F');
       
       // Convertir primera página del PDF a imagen (requiere ImageMagick o similar)
       $output_file = $temp_file . '.png';
       
       // Intentar usar ImageMagick si está disponible
       if (extension_loaded('imagick')) {
           $imagick = new Imagick();
           $imagick->readImage($temp_file . '[0]');
           $imagick->setImageFormat('png');
           $imagick->writeImage($output_file);
           $imagick->clear();
           $imagick->destroy();
       } else {
           // Alternativa usando GD
           // Convertir PDF a imagen con resolución suficiente
           $command = "convert -density 150 {$temp_file}[0] -quality 90 $output_file";
           exec($command, $output, $return_var);
           
           if ($return_var !== 0) {
               // Si no se puede usar ImageMagick, crear una imagen de reemplazo con GD
               $preview = imagecreatetruecolor(430, 270);
               $background = imagecolorallocate($preview, 255, 255, 255);
               $text_color = imagecolorallocate($preview, 0, 0, 0);
               
               imagefill($preview, 0, 0, $background);
               imagestring($preview, 5, 120, 120, 'Vista previa de credencial', $text_color);
               
               imagepng($preview, $output_file);
               imagedestroy($preview);
           }
       }
       
       // Eliminar archivo temporal PDF
       @unlink($temp_file);
       
       return $output_file;
   }
   
   /**
    * Convierte un color hexadecimal a RGB
    * 
    * @param string $hex Color en formato hexadecimal (#RRGGBB)
    * @return array Array con valores R, G, B (0-255)
    */
   private function hexToRgb($hex) {
       // Eliminar # si existe
       $hex = ltrim($hex, '#');
       
       // Valores por defecto si el hex no es válido
       if (!ctype_xdigit($hex) || strlen($hex) != 6) {
           return [0, 102, 204]; // Azul por defecto
       }
       
       // Convertir a RGB
       $r = hexdec(substr($hex, 0, 2));
       $g = hexdec(substr($hex, 2, 2));
       $b = hexdec(substr($hex, 4, 2));
       
       return [$r, $g, $b];
   }
}
?>