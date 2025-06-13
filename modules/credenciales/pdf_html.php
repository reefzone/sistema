<?php
/**
 * Generador de Credenciales HTML-to-PDF con DomPDF - DISEÑO PERFECTO
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir DomPDF
require_once '../../lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class CredencialHTML {
    private $conexion;
    private $config;
    private $visual_config;
    
    public function __construct($conexion, $config) {
        $this->conexion = $conexion;
        $this->config = $config;
        
        // Decodificar configuración visual
        $this->visual_config = !empty($config['config_visual']) ? 
            json_decode($config['config_visual'], true) : [];
            
        // Valores por defecto
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
     * Genera credencial con diseño PERFECTO usando DomPDF
     */
    public function generarCredencialPerfecta($alumno, $ruta_pdf) {
        // Configurar DomPDF
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // Generar HTML con diseño perfecto
        $html = $this->generarHTMLCredencial($alumno);
        
        // Cargar HTML
        $dompdf->loadHtml($html);
        
        // Establecer tamaño de papel (86x54mm convertido a puntos)
        $dompdf->setPaper([0, 0, 244, 153], 'landscape');
        
        // Renderizar PDF
        $dompdf->render();
        
        // Guardar archivo
        file_put_contents($ruta_pdf, $dompdf->output());
        
        return true;
    }
    
    /**
     * Genera el HTML con diseño ULTRA PROFESIONAL
     */
    private function generarHTMLCredencial($alumno) {
        $config = $this->visual_config;
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
            size: 86mm 54mm landscape;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: transparent;
            width: 86mm;
            height: 54mm;
            overflow: hidden;
        }
        
        .credencial-container {
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, ' . $config['color_fondo'] . ' 0%, #f8fafc 100%);
            border: ' . $config['grosor_borde'] . 'px solid ' . $config['color_borde'] . ';
            border-radius: ' . ($config['esquinas_redondeadas'] / 4) . 'px;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: linear-gradient(135deg, ' . $config['color_borde'] . ' 0%, #1e293b 100%);
            padding: 8px 10px;
            border-radius: ' . ($config['esquinas_redondeadas'] / 4) . 'px ' . ($config['esquinas_redondeadas'] / 4) . 'px 0 0;
            position: relative;
            height: 25mm;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .header::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6 0%, #06b6d4 50%, #8b5cf6 100%);
        }
        
        .logo-container {
            position: absolute;
            top: 8px;
            right: 10px;
            width: 24mm;
            height: 12mm;
            background: rgba(255,255,255,0.95);
            border-radius: 4px;
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6px;
            font-weight: bold;
            color: #0f172a;
        }
        
        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .escuela-nombre {
            font-size: ' . $config['tamano_titulo'] . 'px;
            font-weight: bold;
            color: ' . $config['color_titulo'] . ';
            text-align: center;
            margin-bottom: 2px;
            padding-right: 26mm;
            line-height: 1.1;
            text-transform: uppercase;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .credencial-tipo {
            font-size: ' . ($config['tamano_texto'] - 1) . 'px;
            font-weight: bold;
            color: ' . $config['color_texto'] . ';
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .ciclo {
            font-size: ' . ($config['tamano_texto'] - 2) . 'px;
            color: rgba(255,255,255,0.9);
            text-align: center;
            margin-top: 1px;
        }
        
        .body {
            flex: 1;
            padding: 6px 8px;
            display: flex;
            gap: 6px;
            background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        }
        
        .foto-section {
            width: 22mm;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .foto {
            width: 20mm;
            height: 16mm;
            background: linear-gradient(145deg, #f1f5f9 0%, #e2e8f0 100%);
            border: 2px solid #ffffff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6px;
            color: #64748b;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .foto img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .datos {
            flex: 1;
            background: rgba(255,255,255,0.9);
            padding: 6px;
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.06);
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        
        .nombre {
            font-size: ' . ($config['tamano_titulo'] - 1) . 'px;
            font-weight: bold;
            color: #0f172a;
            text-align: center;
            margin-bottom: 4px;
            text-transform: uppercase;
            line-height: 1.1;
        }
        
        .dato {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            padding: 2px 4px;
            background: rgba(15,23,42,0.04);
            border-radius: 3px;
            border-left: 2px solid #3b82f6;
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
        }
        
        .footer {
            padding: 4px 8px;
            background: rgba(248,250,252,0.9);
            border-top: 1px solid rgba(0,0,0,0.06);
        }
        
        .texto-inferior {
            font-size: ' . ($config['tamano_texto'] - 3) . 'px;
            text-align: center;
            color: #64748b;
            line-height: 1.2;
            background: rgba(255,255,255,0.8);
            padding: 3px;
            border-radius: 3px;
        }
        
        .firma-section {
            position: absolute;
            bottom: 8px;
            right: 8px;
            text-align: center;
            font-size: ' . ($config['tamano_texto'] - 2) . 'px;
            color: #64748b;
        }
        
        .firma {
            width: 20mm;
            height: 6mm;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5px;
            color: #64748b;
            margin-bottom: 1px;
        }
        
        .firma img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="credencial-container">
        <div class="header">
            <div class="logo-container">
                ' . (!empty($this->config['logo_path']) && file_exists($this->config['logo_path']) ? 
                    '<img src="' . $this->config['logo_path'] . '">' : 'LOGO') . '
            </div>
            <div class="escuela-nombre">' . htmlspecialchars($config['nombre_escuela']) . '</div>
            <div class="credencial-tipo">' . htmlspecialchars($config['tipo_credencial']) . '</div>
            <div class="ciclo">CICLO ESCOLAR ' . htmlspecialchars($alumno['ciclo_escolar']) . '</div>
        </div>
        
        <div class="body">
            <div class="foto-section">
                <div class="foto">
                    ' . (!empty($alumno['ruta_foto']) && file_exists($alumno['ruta_foto']) ? 
                        '<img src="' . $alumno['ruta_foto'] . '">' : 'FOTO') . '
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
        
        <div class="footer">
            <div class="texto-inferior">
                ' . htmlspecialchars($this->config['texto_inferior']) . '
            </div>
        </div>
        
        <div class="firma-section">
            <div class="firma">
                ' . (!empty($this->config['firma_path']) && file_exists($this->config['firma_path']) ? 
                    '<img src="' . $this->config['firma_path'] . '">' : 'FIRMA') . '
            </div>
            <div>DIRECTOR</div>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
?>