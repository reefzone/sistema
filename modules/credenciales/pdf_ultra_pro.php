<?php
/**
 * GENERADOR DE CREDENCIALES ULTRA PROFESIONAL
 * Diseño idéntico al editor visual - GARANTIZADO
 * Sistema para 1200+ alumnos - Calidad de producción
 */

require_once '../../lib/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

class CredencialUltraProfesional {
    private $conexion;
    private $config;
    private $visual_config;
    
    public function __construct($conexion, $config) {
        $this->conexion = $conexion;
        $this->config = $config;
        
        $this->visual_config = !empty($config['config_visual']) ? 
            json_decode($config['config_visual'], true) : [];
            
        $defaults = [
            'color_fondo' => '#FFFFFF',
            'color_borde' => '#0f172a',
            'grosor_borde' => 1,
            'esquinas_redondeadas' => 16,
            'fuente_titulo' => 'Arial',
            'tamano_titulo' => 11,
            'color_titulo' => '#ffffff',
            'tamano_texto' => 8,
            'color_texto' => '#ffffff',
            'tipo_credencial' => 'CREDENCIAL DE ESTUDIANTE',
            'nombre_escuela' => 'ESCUELA SECUNDARIA TÉCNICA #82',
            'telefono_contacto' => '(55) 1234-5678',
            'direccion_contacto' => 'Calle Principal s/n, Col. Centro',
            'website_contacto' => 'www.est82.edu.mx'
        ];
        
        $this->visual_config = array_merge($defaults, $this->visual_config);
    }
    
    /**
     * GENERA CREDENCIAL CON DISEÑO PERFECTO - IDÉNTICO AL EDITOR
     */
    public function generarCredencialPerfecta($alumno, $ruta_pdf) {
        // Configurar DomPDF para máxima calidad
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('debugCss', false);
        $options->set('debugKeepTemp', false);
        $options->set('chroot', $_SERVER['DOCUMENT_ROOT']);
        
        $dompdf = new Dompdf($options);
        
        // Generar HTML EXACTO del editor
        $html = $this->generarHTMLProfesional($alumno);
        
        // Cargar HTML
        $dompdf->loadHtml($html);
        
        // Tamaño EXACTO de credencial profesional (86x54mm)
        $dompdf->setPaper([0, 0, 244.09, 152.76], 'landscape');
        
        // Renderizar con máxima calidad
        $dompdf->render();
        
        // Guardar archivo
        file_put_contents($ruta_pdf, $dompdf->output());
        
        return true;
    }
    
    /**
     * GENERA HTML EXACTO DEL EDITOR VISUAL - DISEÑO PERFECTO
     */
    public function generarHTMLProfesional($alumno) {
        $config = $this->visual_config;
        
        // Convertir rutas a base64 para embed perfecto
        $logo_base64 = $this->convertirImagenBase64($this->config['logo_path'] ?? '');
        $firma_base64 = $this->convertirImagenBase64($this->config['firma_path'] ?? '');
        $foto_base64 = $this->convertirImagenBase64($alumno['ruta_foto'] ?? '');
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* RESET COMPLETO PARA PDF */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            color-adjust: exact;
        }
        
        @page {
            margin: 0;
            size: 86mm 54mm landscape;
            padding: 0;
        }
        
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: transparent;
            width: 86mm;
            height: 54mm;
            overflow: hidden;
            position: relative;
            font-size: 8px;
            line-height: 1.2;
        }
        
        /* CONTENEDOR PRINCIPAL - DISEÑO EXACTO */
        .credencial-container {
            width: 86mm;
            height: 54mm;
            background: linear-gradient(145deg, ' . $config['color_fondo'] . ' 0%, #f8fafc 100%);
            border: ' . $config['grosor_borde'] . 'px solid ' . $config['color_borde'] . ';
            border-radius: ' . ($config['esquinas_redondeadas'] / 4) . 'px;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: inset 0 1px 2px rgba(255,255,255,0.8);
        }
        
        /* HEADER CORPORATIVO ULTRA PROFESIONAL */
        .credencial-header {
            background: linear-gradient(135deg, ' . $config['color_borde'] . ' 0%, #1e293b 100%);
            padding: 6px 8px;
            position: relative;
            height: 20mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-radius: ' . ($config['esquinas_redondeadas'] / 4) . 'px ' . ($config['esquinas_redondeadas'] / 4) . 'px 0 0;
        }
        
        .credencial-header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1.5px;
            background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 50%, #8b5cf6 100%);
        }
        
        .logo-container {
            position: absolute;
            top: 6px;
            right: 8px;
            width: 20mm;
            height: 10mm;
            background: rgba(255,255,255,0.95);
            border-radius: 3px;
            padding: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .logo-placeholder {
            font-size: 5px;
            font-weight: bold;
            color: #0f172a;
            text-align: center;
        }
        
        .escuela-nombre {
            font-size: ' . $config['tamano_titulo'] . 'px;
            font-weight: bold;
            color: ' . $config['color_titulo'] . ';
            text-align: center;
            margin-bottom: 2px;
            padding-right: 22mm;
            line-height: 1.0;
            text-transform: uppercase;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .credencial-tipo {
            font-size: ' . ($config['tamano_texto']) . 'px;
            font-weight: bold;
            color: ' . $config['color_texto'] . ';
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            padding-right: 22mm;
        }
        
        .ciclo {
            font-size: ' . ($config['tamano_texto'] - 1) . 'px;
            color: rgba(255,255,255,0.9);
            text-align: center;
            margin-top: 1px;
            padding-right: 22mm;
        }
        
        /* BODY PROFESIONAL */
        .credencial-body {
            flex: 1;
            padding: 4px 6px;
            display: flex;
            gap: 4px;
            background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        }
        
        .foto-section {
            width: 18mm;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .foto {
            width: 16mm;
            height: 20mm;
            background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 2px solid #ffffff;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5px;
            color: #64748b;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 2px;
        }
        
        .datos {
            flex: 1;
            background: rgba(255,255,255,0.9);
            padding: 4px;
            border-radius: 4px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .nombre {
            font-size: ' . ($config['tamano_titulo'] - 1) . 'px;
            font-weight: bold;
            color: #0f172a;
            text-align: center;
            margin-bottom: 3px;
            text-transform: uppercase;
            line-height: 1.0;
        }
        
        .dato {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5px;
            padding: 1px 3px;
            background: rgba(15,23,42,0.04);
            border-radius: 2px;
            border-left: 1.5px solid #3b82f6;
            font-size: ' . ($config['tamano_texto'] - 1) . 'px;
        }
        
        .dato .label {
            font-weight: bold;
            color: #475569;
            font-size: ' . ($config['tamano_texto'] - 2) . 'px;
        }
        
        .dato .value {
            font-weight: bold;
            color: #0f172a;
            font-size: ' . ($config['tamano_texto'] - 1) . 'px;
        }
        
        /* FOOTER */
        .footer {
            padding: 2px 6px;
            background: rgba(248,250,252,0.9);
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        
        .texto-inferior {
            font-size: ' . ($config['tamano_texto'] - 3) . 'px;
            text-align: center;
            color: #64748b;
            line-height: 1.1;
            background: rgba(255,255,255,0.8);
            padding: 2px;
            border-radius: 2px;
        }
        
        .firma-section {
            position: absolute;
            bottom: 6px;
            right: 6px;
            text-align: center;
            font-size: ' . ($config['tamano_texto'] - 2) . 'px;
            color: #64748b;
        }
        
        .firma {
            width: 16mm;
            height: 5mm;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4px;
            color: #64748b;
            margin-bottom: 1px;
            overflow: hidden;
        }
        
        .firma img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* OPTIMIZACIONES PARA PDF */
        .credencial-container * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        @media print {
            body { 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .credencial-container {
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="credencial-container">
        <!-- HEADER -->
        <div class="credencial-header">
            <div class="logo-container">
                ' . ($logo_base64 ? '<img src="' . $logo_base64 . '">' : '<div class="logo-placeholder">LOGO</div>') . '
            </div>
            <div class="escuela-nombre">' . htmlspecialchars($config['nombre_escuela']) . '</div>
            <div class="credencial-tipo">' . htmlspecialchars($config['tipo_credencial']) . '</div>
            <div class="ciclo">CICLO ESCOLAR ' . htmlspecialchars($alumno['ciclo_escolar']) . '</div>
        </div>
        
        <!-- BODY -->
        <div class="credencial-body">
            <div class="foto-section">
                <div class="foto">
                    ' . ($foto_base64 ? '<img src="' . $foto_base64 . '">' : 'FOTO') . '
                </div>
            </div>
            
            <div class="datos">
                <div class="nombre">' . htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) . '</div>
                
                <div class="dato">
                    <span class="label">MATRÍCULA:</span>
                    <span class="value">' . htmlspecialchars($alumno['matricula']) . '</span>
                </div>
                
                <div class="dato">
                    <span class="label">GRADO:</span>
                    <span class="value">' . htmlspecialchars($alumno['nombre_grado'] . ' - ' . $alumno['nombre_grupo']) . '</span>
                </div>
                
                <div class="dato">
                    <span class="label">TURNO:</span>
                    <span class="value">' . htmlspecialchars($alumno['nombre_turno']) . '</span>
                </div>
            </div>
        </div>
        
        <!-- FOOTER -->
        <div class="footer">
            <div class="texto-inferior">
                ' . htmlspecialchars($this->config['texto_inferior']) . '
            </div>
        </div>
        
        <!-- FIRMA -->
        <div class="firma-section">
            <div class="firma">
                ' . ($firma_base64 ? '<img src="' . $firma_base64 . '">' : 'FIRMA') . '
            </div>
            <div>DIRECTOR</div>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
    
    /**
     * Convierte imágenes a base64 para embed perfecto en PDF
     */
    private function convertirImagenBase64($ruta) {
        if (empty($ruta) || !file_exists($ruta)) {
            return '';
        }
        
        $tipo = mime_content_type($ruta);
        $data = file_get_contents($ruta);
        
        if ($data === false) {
            return '';
        }
        
        return 'data:' . $tipo . ';base64,' . base64_encode($data);
    }
    
    /**
     * GENERA CREDENCIALES MASIVAS CON CALIDAD PROFESIONAL
     */
    public function generarCredencialesGrupo($grupo, $alumnos, $ruta_pdf) {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // HTML para múltiples credenciales
        $html = $this->generarHTMLGrupo($grupo, $alumnos);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        file_put_contents($ruta_pdf, $dompdf->output());
        
        return true;
    }
    
    private function generarHTMLGrupo($grupo, $alumnos) {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 10mm; }
        body { font-family: Arial; margin: 0; padding: 0; }
        .credenciales-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10mm;
            page-break-inside: avoid;
        }
        .credencial-item {
            width: 86mm;
            height: 54mm;
            page-break-inside: avoid;
            transform: scale(0.8);
            transform-origin: top left;
        }
    </style>
</head>
<body>';

        $contador = 0;
        foreach ($alumnos as $alumno) {
            if ($contador % 4 == 0) {
                $html .= '<div class="credenciales-grid">';
            }
            
            $html .= '<div class="credencial-item">';
            $html .= $this->generarHTMLProfesional($alumno);
            $html .= '</div>';
            
            if ($contador % 4 == 3 || $contador == count($alumnos) - 1) {
                $html .= '</div>';
                if ($contador < count($alumnos) - 1) {
                    $html .= '<div style="page-break-after: always;"></div>';
                }
            }
            
            $contador++;
        }

        $html .= '</body></html>';
        return $html;
    }
}
?>