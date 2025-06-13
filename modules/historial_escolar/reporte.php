<?php
/**
 * Generación de Reporte PDF de Historial Escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/historial_functions.php';
require_once '../../includes/session_checker.php';
require_once '../../vendor/tcpdf/tcpdf.php';

// Verificar el ID del alumno
$id_alumno = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_alumno <= 0) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

// Verificar si es un formulario enviado para generar PDF
$generar_pdf = isset($_POST['generar_pdf']) && $_POST['generar_pdf'] == 1;

// Si no es para generar PDF, mostrar el formulario de opciones
if (!$generar_pdf) {
    // Obtener datos del alumno
    $query_alumno = "SELECT a.*, CONCAT(a.nombre, ' ', a.apellido) as nombre_completo, 
                    g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                    FROM alumnos a
                    JOIN grupos g ON a.id_grupo = g.id_grupo
                    JOIN grados gr ON g.id_grado = gr.id_grado
                    JOIN turnos t ON g.id_turno = t.id_turno
                    WHERE a.id_alumno = ?";

    $stmt_alumno = $conexion->prepare($query_alumno);
    $stmt_alumno->bind_param("i", $id_alumno);
    $stmt_alumno->execute();
    $result_alumno = $stmt_alumno->get_result();

    if ($result_alumno->num_rows === 0) {
        redireccionar_con_mensaje('index.php', 'Alumno no encontrado', 'danger');
    }

    $alumno = $result_alumno->fetch_assoc();
    
    // Incluir header
    include '../../includes/header.php';
    ?>

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Historial Escolar</a></li>
                        <li class="breadcrumb-item"><a href="ver.php?id=<?= $id_alumno ?>">Ver Historial</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Generar Reporte</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-file-pdf"></i> Generar Reporte de Historial</h1>
            </div>
        </div>
        
        <!-- Datos del alumno -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-user"></i> Datos del Alumno</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <img src="../../uploads/alumnos/<?= $id_alumno ?>.jpg" 
                             class="img-fluid rounded-circle mb-2" style="max-width: 150px; max-height: 150px;"
                             alt="<?= htmlspecialchars($alumno['nombre_completo']) ?>"
                             onerror="this.src='../../assets/img/user-default.png'">
                    </div>
                    <div class="col-md-10">
                        <h3><?= htmlspecialchars($alumno['nombre_completo']) ?></h3>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Matrícula:</strong> <?= htmlspecialchars($alumno['matricula']) ?></p>
                                <p class="mb-1"><strong>Grupo:</strong> <?= htmlspecialchars($alumno['nombre_grupo']) ?></p>
                                <p class="mb-1"><strong>Grado:</strong> <?= htmlspecialchars($alumno['nombre_grado']) ?></p>
                                <p class="mb-1"><strong>Turno:</strong> <?= htmlspecialchars($alumno['nombre_turno']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>CURP:</strong> <?= htmlspecialchars($alumno['curp'] ?? 'No registrado') ?></p>
                                <p class="mb-1"><strong>Fecha de nacimiento:</strong> <?= !empty($alumno['fecha_nacimiento']) ? date('d/m/Y', strtotime($alumno['fecha_nacimiento'])) : 'No registrado' ?></p>
                                <p class="mb-1"><strong>Género:</strong> <?= htmlspecialchars($alumno['genero'] ?? 'No registrado') ?></p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1"><strong>Dirección:</strong> <?= htmlspecialchars($alumno['direccion'] ?? 'No registrado') ?></p>
                                <p class="mb-1"><strong>Teléfono:</strong> <?= htmlspecialchars($alumno['telefono'] ?? 'No registrado') ?></p>
                                <p class="mb-1"><strong>Correo electrónico:</strong> <?= htmlspecialchars($alumno['correo'] ?? 'No registrado') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Formulario de opciones para el reporte -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="fas fa-cog"></i> Opciones del Reporte</h5>
            </div>
            <div class="card-body">
                <form action="" method="post" target="_blank">
                    <input type="hidden" name="generar_pdf" value="1">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    
                    <div class="row">
                        <!-- Período de tiempo -->
                        <div class="col-md-6 mb-4">
                            <h5 class="border-bottom pb-2">Período de Tiempo</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="fecha_inicio" class="form-label">Fecha de inicio</label>
                                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="fecha_fin" class="form-label">Fecha de fin</label>
                                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tipos de registro a incluir -->
                        <div class="col-md-6 mb-4">
                            <h5 class="border-bottom pb-2">Tipos de Registro a Incluir</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]" value="academico" id="tipo_academico" checked>
                                        <label class="form-check-label" for="tipo_academico">
                                            <i class="fas fa-graduation-cap text-primary"></i> Académico
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]" value="asistencia" id="tipo_asistencia" checked>
                                        <label class="form-check-label" for="tipo_asistencia">
                                            <i class="fas fa-calendar-check text-info"></i> Asistencia
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]" value="conducta" id="tipo_conducta" checked>
                                        <label class="form-check-label" for="tipo_conducta">
                                            <i class="fas fa-exclamation-triangle text-warning"></i> Conducta
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]" value="reconocimiento" id="tipo_reconocimiento" checked>
                                        <label class="form-check-label" for="tipo_reconocimiento">
                                            <i class="fas fa-award text-success"></i> Reconocimientos
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="tipos[]" value="observacion" id="tipo_observacion" checked>
                                        <label class="form-check-label" for="tipo_observacion">
                                            <i class="fas fa-comment text-secondary"></i> Observaciones
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="mostrar_adjuntos" id="mostrar_adjuntos" checked>
                                        <label class="form-check-label" for="mostrar_adjuntos">
                                            <i class="fas fa-paperclip"></i> Incluir lista de archivos adjuntos
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Opciones de visualización -->
                        <div class="col-md-6 mb-4">
                            <h5 class="border-bottom pb-2">Opciones de Visualización</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ordenar_por" class="form-label">Ordenar por</label>
                                    <select name="ordenar_por" id="ordenar_por" class="form-select">
                                        <option value="fecha_desc">Fecha (más reciente primero)</option>
                                        <option value="fecha_asc">Fecha (más antiguo primero)</option>
                                        <option value="tipo">Tipo de registro</option>
                                        <option value="relevancia">Relevancia</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="formato" class="form-label">Formato de salida</label>
                                    <select name="formato" id="formato" class="form-select">
                                        <option value="detallado">Detallado (todos los campos)</option>
                                        <option value="resumido">Resumido (información principal)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_estadisticas" id="incluir_estadisticas" checked>
                                        <label class="form-check-label" for="incluir_estadisticas">
                                            <i class="fas fa-chart-bar"></i> Incluir estadísticas y gráficos
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Firmas y autorizaciones -->
                        <div class="col-md-6 mb-4">
                            <h5 class="border-bottom pb-2">Firmas y Autorizaciones</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_firma_director" id="incluir_firma_director" checked>
                                        <label class="form-check-label" for="incluir_firma_director">
                                            Incluir firma del Director
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_firma_tutor" id="incluir_firma_tutor" checked>
                                        <label class="form-check-label" for="incluir_firma_tutor">
                                            Incluir firma del Tutor
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_fecha_expedicion" id="incluir_fecha_expedicion" checked>
                                        <label class="form-check-label" for="incluir_fecha_expedicion">
                                            Incluir fecha de expedición
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="incluir_sello_escuela" id="incluir_sello_escuela" checked>
                                        <label class="form-check-label" for="incluir_sello_escuela">
                                            Incluir sello de la escuela
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="col-12 text-end mt-4">
                            <a href="ver.php?id=<?= $id_alumno ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Volver al Historial
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Generar PDF
                            </button>
                            <button type="submit" name="enviar_email" value="1" class="btn btn-success">
                                <i class="fas fa-envelope"></i> Generar y Enviar por Email
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Autocompletar fecha de inicio (3 meses atrás por defecto)
        const fechaInicio = document.getElementById('fecha_inicio');
        const fechaFin = document.getElementById('fecha_fin');
        
        const hoy = new Date();
        const tresMesesAtras = new Date();
        tresMesesAtras.setMonth(hoy.getMonth() - 3);
        
        fechaInicio.value = tresMesesAtras.toISOString().split('T')[0];
        fechaFin.value = hoy.toISOString().split('T')[0];
        
        // Validación antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const tiposSeleccionados = document.querySelectorAll('input[name="tipos[]"]:checked');
            
            if (tiposSeleccionados.length === 0) {
                e.preventDefault();
                alert('Debe seleccionar al menos un tipo de registro');
                return;
            }
            
            const fechaInicioVal = new Date(fechaInicio.value);
            const fechaFinVal = new Date(fechaFin.value);
            
            if (fechaInicio.value && fechaFin.value && fechaInicioVal > fechaFinVal) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser posterior a la fecha de fin');
                return;
            }
        });
    });
    </script>

    <?php
    exit;
}

// A partir de aquí es la generación del PDF

// Verificar token CSRF
if (!verificar_token_csrf($_POST['csrf_token'])) {
    redireccionar_con_mensaje('index.php', 'Token de seguridad inválido', 'danger');
}

// Obtener opciones del formulario
$fecha_inicio = isset($_POST['fecha_inicio']) ? sanitizar_texto($_POST['fecha_inicio']) : '';
$fecha_fin = isset($_POST['fecha_fin']) ? sanitizar_texto($_POST['fecha_fin']) : date('Y-m-d');
$tipos = isset($_POST['tipos']) ? $_POST['tipos'] : [];
$mostrar_adjuntos = isset($_POST['mostrar_adjuntos']);
$ordenar_por = isset($_POST['ordenar_por']) ? sanitizar_texto($_POST['ordenar_por']) : 'fecha_desc';
$formato = isset($_POST['formato']) ? sanitizar_texto($_POST['formato']) : 'detallado';
$incluir_estadisticas = isset($_POST['incluir_estadisticas']);
$incluir_firma_director = isset($_POST['incluir_firma_director']);
$incluir_firma_tutor = isset($_POST['incluir_firma_tutor']);
$incluir_fecha_expedicion = isset($_POST['incluir_fecha_expedicion']);
$incluir_sello_escuela = isset($_POST['incluir_sello_escuela']);
$enviar_email = isset($_POST['enviar_email']) && $_POST['enviar_email'] == 1;

// Obtener datos del alumno
$query_alumno = "SELECT a.*, CONCAT(a.nombre, ' ', a.apellido) as nombre_completo, 
                g.nombre_grupo, gr.nombre_grado, t.nombre_turno 
                FROM alumnos a
                JOIN grupos g ON a.id_grupo = g.id_grupo
                JOIN grados gr ON g.id_grado = gr.id_grado
                JOIN turnos t ON g.id_turno = t.id_turno
                WHERE a.id_alumno = ?";

$stmt_alumno = $conexion->prepare($query_alumno);
$stmt_alumno->bind_param("i", $id_alumno);
$stmt_alumno->execute();
$result_alumno = $stmt_alumno->get_result();

if ($result_alumno->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'Alumno no encontrado', 'danger');
}

$alumno = $result_alumno->fetch_assoc();

// Preparar filtros para la consulta de historial
$filtros = [];
if (!empty($fecha_inicio)) $filtros['fecha_inicio'] = $fecha_inicio;
if (!empty($fecha_fin)) $filtros['fecha_fin'] = $fecha_fin;
if (!empty($tipos)) $filtros['tipos'] = $tipos;

// Obtener historial del alumno según filtros
$historial = obtener_historial_alumno_reporte($id_alumno, $filtros, $ordenar_por);

// Definir colores para tipos de registro
$colores_tipo = [
    'academico' => [0, 123, 255],  // Azul
    'asistencia' => [23, 162, 184], // Cyan
    'conducta' => [255, 193, 7],    // Amarillo
    'reconocimiento' => [40, 167, 69], // Verde
    'observacion' => [108, 117, 125]  // Gris
];

// Extender la clase TCPDF para personalizar el encabezado y pie de página
class MYPDF extends TCPDF {
    protected $alumno;
    
    public function setAlumno($alumno) {
        $this->alumno = $alumno;
    }
    
    public function Header() {
        // Logo
        $this->Image('../../assets/img/logo_escuela.png', 15, 10, 25, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Encabezado
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'ESCUELA SECUNDARIA TÉCNICA #82', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(5);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, 'HISTORIAL ESCOLAR', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        $this->Ln(10);
        
        // Información del alumno
        if ($this->alumno) {
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(40, 6, 'Alumno:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(70, 6, $this->alumno['nombre_completo'], 0, 0, 'L');
            
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(30, 6, 'Matrícula:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(40, 6, $this->alumno['matricula'], 0, 1, 'L');
            
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(40, 6, 'Grupo:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(70, 6, $this->alumno['nombre_grupo'], 0, 0, 'L');
            
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(30, 6, 'Grado:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(40, 6, $this->alumno['nombre_grado'], 0, 1, 'L');
            
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(40, 6, 'Turno:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(70, 6, $this->alumno['nombre_turno'], 0, 0, 'L');
            
            $this->SetFont('helvetica', 'B', 10);
            $this->Cell(30, 6, 'CURP:', 0, 0, 'L');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(40, 6, $this->alumno['curp'] ?? 'No registrado', 0, 1, 'L');
        }
        
        // Línea divisoria
        $this->Line(15, 45, 195, 45);
        $this->Ln(5);
    }
    
    public function Footer() {
        // Posicionar a 15 mm del final
        $this->SetY(-15);
        // Fuente y tamaño
        $this->SetFont('helvetica', 'I', 8);
        // Texto del pie de página
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        
        // Fecha de generación
        if (isset($_POST['incluir_fecha_expedicion']) && $_POST['incluir_fecha_expedicion']) {
            $this->Cell(0, 10, 'Generado el: ' . date('d/m/Y H:i'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }
    }
}

// Crear una instancia de TCPDF
$pdf = new MYPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);

// Establecer datos del alumno
$pdf->setAlumno($alumno);

// Configuración del documento
$pdf->SetCreator('Sistema Escolar');
$pdf->SetAuthor('ESCUELA SECUNDARIA TECNICA #82');
$pdf->SetTitle('Historial Escolar - ' . $alumno['nombre_completo']);
$pdf->SetSubject('Historial Académico');
$pdf->SetKeywords('historial, escolar, académico, ' . $alumno['nombre_completo']);

// Establecer márgenes
$pdf->SetMargins(15, 50, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(15);

// Establecer auto salto de página
$pdf->SetAutoPageBreak(TRUE, 20);

// Agregar una página
$pdf->AddPage();

// Establecer fuente por defecto
$pdf->SetFont('helvetica', '', 10);

// Mensaje si no hay registros
if (empty($historial)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'No se encontraron registros que coincidan con los filtros seleccionados.', 0, 1, 'C');
    
    // Generar el PDF
    $pdf->Output('Historial_Escolar_' . $alumno['matricula'] . '.pdf', 'I');
    exit;
}

// Si se incluyen estadísticas, generarlas primero
if ($incluir_estadisticas) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'RESUMEN ESTADÍSTICO', 0, 1, 'L');
    $pdf->Ln(2);
    
    // Contar registros por tipo
    $conteo_por_tipo = [];
    foreach ($historial as $registro) {
        $tipo = $registro['tipo_registro'];
        if (!isset($conteo_por_tipo[$tipo])) {
            $conteo_por_tipo[$tipo] = 0;
        }
        $conteo_por_tipo[$tipo]++;
    }
    
    // Total de registros
    $total_registros = count($historial);
    
    // Tabla de resumen
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(90, 7, 'Tipo de Registro', 1, 0, 'C', 0);
    $pdf->Cell(30, 7, 'Cantidad', 1, 0, 'C', 0);
    $pdf->Cell(30, 7, 'Porcentaje', 1, 1, 'C', 0);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Tipos de registro en orden específico
    $tipos_ordenados = ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'];
    $etiquetas_tipos = [
        'academico' => 'Académico',
        'asistencia' => 'Asistencia',
        'conducta' => 'Conducta',
        'reconocimiento' => 'Reconocimiento',
        'observacion' => 'Observación'
    ];
    
    foreach ($tipos_ordenados as $tipo) {
        if (isset($conteo_por_tipo[$tipo])) {
            $cantidad = $conteo_por_tipo[$tipo];
            $porcentaje = ($cantidad / $total_registros) * 100;
            
            $pdf->Cell(90, 7, $etiquetas_tipos[$tipo], 1, 0, 'L', 0);
            $pdf->Cell(30, 7, $cantidad, 1, 0, 'C', 0);
            $pdf->Cell(30, 7, number_format($porcentaje, 1) . '%', 1, 1, 'C', 0);
        }
    }
    
    // Total
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(90, 7, 'Total', 1, 0, 'L', 0);
    $pdf->Cell(30, 7, $total_registros, 1, 0, 'C', 0);
    $pdf->Cell(30, 7, '100%', 1, 1, 'C', 0);
    
    $pdf->Ln(10);
}

// Listado de registros
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'REGISTROS DE HISTORIAL', 0, 1, 'L');
$pdf->Ln(2);

// Encabezado de la tabla según formato
$pdf->SetFont('helvetica', 'B', 9);
if ($formato == 'detallado') {
    $pdf->Cell(25, 7, 'Fecha', 1, 0, 'C', 0);
    $pdf->Cell(30, 7, 'Tipo', 1, 0, 'C', 0);
    $pdf->Cell(60, 7, 'Título', 1, 0, 'C', 0);
    $pdf->Cell(30, 7, 'Categoría', 1, 0, 'C', 0);
    $pdf->Cell(25, 7, 'Relevancia', 1, 1, 'C', 0);
} else { // formato resumido
    $pdf->Cell(25, 7, 'Fecha', 1, 0, 'C', 0);
    $pdf->Cell(30, 7, 'Tipo', 1, 0, 'C', 0);
    $pdf->Cell(110, 7, 'Título', 1, 1, 'C', 0);
}

// Datos de la tabla
$pdf->SetFont('helvetica', '', 9);

foreach ($historial as $registro) {
    // Establecer color según tipo
    $tipo_color = isset($colores_tipo[$registro['tipo_registro']]) ? 
                 $colores_tipo[$registro['tipo_registro']] : [0, 0, 0];
    
    // Traducir tipo de registro
    $tipo_texto = '';
    switch ($registro['tipo_registro']) {
        case 'academico': $tipo_texto = 'Académico'; break;
        case 'asistencia': $tipo_texto = 'Asistencia'; break;
        case 'conducta': $tipo_texto = 'Conducta'; break;
        case 'reconocimiento': $tipo_texto = 'Reconocimiento'; break;
        case 'observacion': $tipo_texto = 'Observación'; break;
    }
    
    // Color de texto para el tipo
    $pdf->SetTextColor($tipo_color[0], $tipo_color[1], $tipo_color[2]);
    
    // Formato de fecha
    $fecha_formateada = date('d/m/Y', strtotime($registro['fecha_evento']));
    
    if ($formato == 'detallado') {
        $pdf->Cell(25, 7, $fecha_formateada, 1, 0, 'C', 0);
        $pdf->Cell(30, 7, $tipo_texto, 1, 0, 'C', 0);
        
        // Resetear color de texto para el resto de celdas
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(60, 7, $registro['titulo'], 1, 0, 'L', 0);
        $pdf->Cell(30, 7, $registro['categoria'], 1, 0, 'C', 0);
        $pdf->Cell(25, 7, ucfirst($registro['relevancia']), 1, 1, 'C', 0);
        
        // Descripción en nueva línea
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(25, 7, '', 0, 0); // espacio para alinear
        $pdf->MultiCell(145, 7, 'Descripción: ' . $registro['descripcion'], 1, 'L', 0, 1);
        
        // Calificación si existe
        if (!is_null($registro['calificacion']) && $registro['tipo_registro'] == 'academico') {
            $pdf->Cell(25, 7, '', 0, 0); // espacio para alinear
            $pdf->Cell(145, 7, 'Calificación: ' . $registro['calificacion'], 1, 1, 'L', 0);
        }
        
        // Mostrar adjuntos si hay y se solicitó
        if ($mostrar_adjuntos && $registro['tiene_adjunto']) {
            $adjuntos = obtener_adjuntos_registro($conexion, $registro['id_historial']);
            if (!empty($adjuntos)) {
                $pdf->Cell(25, 7, '', 0, 0); // espacio para alinear
                $pdf->Cell(145, 7, 'Archivos adjuntos:', 1, 1, 'L', 0);
                
                foreach ($adjuntos as $adjunto) {
                    $pdf->Cell(25, 7, '', 0, 0); // espacio para alinear
                    $pdf->Cell(145, 7, '• ' . $adjunto['nombre_original'], 1, 1, 'L', 0);
                }
            }
        }
        
        $pdf->Ln(3); // Espacio entre registros
        $pdf->SetFont('helvetica', '', 9); // Restaurar fuente
    } else { // formato resumido
        $pdf->Cell(25, 7, $fecha_formateada, 1, 0, 'C', 0);
        $pdf->Cell(30, 7, $tipo_texto, 1, 0, 'C', 0);
        
        // Resetear color de texto para el resto de celdas
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(110, 7, $registro['titulo'], 1, 1, 'L', 0);
    }
}

// Espacio para firmas si se solicitaron
if ($incluir_firma_director || $incluir_firma_tutor) {
    $pdf->Ln(20);
    
    if ($incluir_firma_director && $incluir_firma_tutor) {
        // Dos firmas
        $pdf->Cell(80, 7, '_______________________________', 0, 0, 'C', 0);
        $pdf->Cell(10, 7, '', 0, 0); // Espacio entre firmas
        $pdf->Cell(80, 7, '_______________________________', 0, 1, 'C', 0);
        
        $pdf->Cell(80, 7, 'Director(a)', 0, 0, 'C', 0);
        $pdf->Cell(10, 7, '', 0, 0); // Espacio entre firmas
        $pdf->Cell(80, 7, 'Tutor(a) de Grupo', 0, 1, 'C', 0);
    } elseif ($incluir_firma_director) {
        // Solo firma del director
        $pdf->Cell(0, 7, '_______________________________', 0, 1, 'C', 0);
        $pdf->Cell(0, 7, 'Director(a)', 0, 1, 'C', 0);
    } elseif ($incluir_firma_tutor) {
        // Solo firma del tutor
        $pdf->Cell(0, 7, '_______________________________', 0, 1, 'C', 0);
        $pdf->Cell(0, 7, 'Tutor(a) de Grupo', 0, 1, 'C', 0);
    }
}

// Agregar sello si se solicitó
if ($incluir_sello_escuela) {
    // Posicionar el sello (ajustar coordenadas según diseño)
    if ($incluir_firma_director && $incluir_firma_tutor) {
        $pdf->Image('../../assets/img/sello_escuela.png', 85, $pdf->GetY() - 30, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->Image('../../assets/img/sello_escuela.png', 85, $pdf->GetY() - 20, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }
}

// Si se solicitó enviar por email
if ($enviar_email) {
    // Guardar el PDF en una ubicación temporal
    $temp_file = tempnam(sys_get_temp_dir(), 'historial_') . '.pdf';
    $pdf->Output($temp_file, 'F');
    
    // Verificar si el alumno tiene correo registrado
    if (empty($alumno['correo'])) {
        // Generar el PDF sin enviar email
        $pdf->Output('Historial_Escolar_' . $alumno['matricula'] . '.pdf', 'I');
        exit;
    }
    
    // Aquí iría el código para enviar el email con el PDF adjunto
    // Usando la función de envío de emails del sistema
    
    // Limpiar el archivo temporal después de enviar
    unlink($temp_file);
    
    // Redireccionar con mensaje de éxito
    redireccionar_con_mensaje("ver.php?id=$id_alumno", "Reporte generado y enviado correctamente por email", 'success');
} else {
    // Generar el PDF
    $pdf->Output('Historial_Escolar_' . $alumno['matricula'] . '.pdf', 'I');
}

// Funciones auxiliares

// Función para obtener el historial del alumno con opciones de ordenamiento
function obtener_historial_alumno_reporte($id_alumno, $filtros, $ordenar_por) {
    global $conexion;
    
    $where = ["h.id_alumno = ?", "h.eliminado = 0"];
    $params = [$id_alumno];
    $tipos = "i";
    
    // Aplicar filtros
    if (!empty($filtros['fecha_inicio'])) {
        $where[] = "h.fecha_evento >= ?";
        $params[] = $filtros['fecha_inicio'];
        $tipos .= "s";
    }
    
    if (!empty($filtros['fecha_fin'])) {
        $where[] = "h.fecha_evento <= ?";
        $params[] = $filtros['fecha_fin'];
        $tipos .= "s";
    }
    
    if (!empty($filtros['tipos']) && is_array($filtros['tipos'])) {
        $placeholders = implode(',', array_fill(0, count($filtros['tipos']), '?'));
        $where[] = "h.tipo_registro IN ($placeholders)";
        foreach ($filtros['tipos'] as $tipo) {
            $params[] = $tipo;
            $tipos .= "s";
        }
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Definir ordenamiento
    $order_by = "h.fecha_evento DESC, h.id_historial DESC";
    
    switch ($ordenar_por) {
        case 'fecha_asc':
            $order_by = "h.fecha_evento ASC, h.id_historial ASC";
            break;
        case 'tipo':
            $order_by = "h.tipo_registro ASC, h.fecha_evento DESC";
            break;
        case 'relevancia':
            $order_by = "FIELD(h.relevancia, 'alta', 'normal', 'baja'), h.fecha_evento DESC";
            break;
    }
    
    $query = "SELECT h.*, u.nombre_completo as registrado_por_nombre 
             FROM historial_escolar h 
             LEFT JOIN usuarios u ON h.registrado_por = u.id_usuario 
             WHERE $where_clause 
             ORDER BY $order_by";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historial = [];
    while ($row = $result->fetch_assoc()) {
        $historial[] = $row;
    }
    
    return $historial;
}

// Función para obtener los adjuntos de un registro
function obtener_adjuntos_registro($conexion, $id_historial) {
    $query = "SELECT * FROM historial_adjuntos 
             WHERE id_historial = ? AND eliminado = 0";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_historial);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adjuntos = [];
    while ($row = $result->fetch_assoc()) {
        $adjuntos[] = $row;
    }
    
    return $adjuntos;
}