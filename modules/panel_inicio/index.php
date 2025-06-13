<?php
/**
 * Panel de Inicio
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

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/index.php?error=sesion_expirada');
    exit;
}

// Verificar permisos (todos los usuarios pueden acceder)
$roles_permitidos = ['superadmin', 'organizador', 'consulta'];
if (!in_array($_SESSION['tipo_usuario'], $roles_permitidos)) {
    header('Location: ../login/index.php?error=acceso_denegado');
    exit;
}

// Obtener estadísticas
$total_alumnos = 0;
$total_grupos = 0;
$alumnos_por_grado = [];
$inasistencias_recientes = [];

// Total de alumnos
$query = "SELECT COUNT(*) as total FROM alumnos WHERE activo = 1";
$result = $conexion->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $total_alumnos = $row['total'];
}

// Total de grupos
$query = "SELECT COUNT(*) as total FROM grupos WHERE activo = 1";
$result = $conexion->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $total_grupos = $row['total'];
}

// Alumnos por grado
$query = "SELECT g.nombre_grado, COUNT(a.id_alumno) as total 
          FROM alumnos a 
          JOIN grupos gr ON a.id_grupo = gr.id_grupo 
          JOIN grados g ON gr.id_grado = g.id_grado 
          WHERE a.activo = 1 
          GROUP BY g.nombre_grado 
          ORDER BY g.id_grado";
$result = $conexion->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $alumnos_por_grado[$row['nombre_grado']] = $row['total'];
    }
}

// Inasistencias recientes (últimos 5 días)
$query = "SELECT a.fecha, COUNT(*) as total 
          FROM asistencia a 
          WHERE a.asistio = 0 AND a.fecha >= DATE_SUB(CURDATE(), INTERVAL 5 DAY) 
          GROUP BY a.fecha 
          ORDER BY a.fecha DESC";
$result = $conexion->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inasistencias_recientes[$row['fecha']] = $row['total'];
    }
}

// Incluir header
include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1 class="mb-4">
            <i class="fas fa-tachometer-alt me-2"></i> Panel de Control
        </h1>
        
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Bienvenido(a), <?= $_SESSION['nombre_completo'] ?></h5>
                <p class="card-text">
                    Este es el panel de control del Sistema Escolar de la 
                    <strong>ESCUELA SECUNDARIA TECNICA #82</strong>. 
                    Aquí podrás gestionar información de alumnos, grupos, asistencias y más.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Tarjeta de Total Alumnos -->
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Total de Alumnos</h5>
                        <h2 class="display-4"><?= $total_alumnos ?></h2>
                    </div>
                    <i class="fas fa-user-graduate fa-4x"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="../alumnos/index.php" class="text-white">Ver detalles <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Total Grupos -->
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Total de Grupos</h5>
                        <h2 class="display-4"><?= $total_grupos ?></h2>
                    </div>
                    <i class="fas fa-users fa-4x"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="../grupos/index.php" class="text-white">Ver detalles <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
    
    <!-- Tarjeta de Asistencia Hoy -->
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-info h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title">Inasistencias Hoy</h5>
                        <h2 class="display-4"><?= isset($inasistencias_recientes[date('Y-m-d')]) ? $inasistencias_recientes[date('Y-m-d')] : 0 ?></h2>
                    </div>
                    <i class="fas fa-clipboard-check fa-4x"></i>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0">
                <a href="../asistencia/index.php" class="text-white">Tomar asistencia <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de Alumnos por Grado -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-bar me-2"></i>Alumnos por Grado
                </h5>
            </div>
            <div class="card-body">
                <canvas id="alumnosPorGradoChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Gráfico de Inasistencias Recientes -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>Inasistencias Recientes
                </h5>
            </div>
            <div class="card-body">
                <canvas id="inasistenciasChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Accesos Rápidos -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-bolt me-2"></i>Accesos Rápidos
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="../alumnos/crear.php" class="btn btn-lg btn-outline-primary w-100 h-100 d-flex flex-column justify-content-center align-items-center py-4">
                            <i class="fas fa-user-plus fa-3x mb-2"></i>
                            <span>Agregar Alumno</span>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="../grupos/index.php" class="btn btn-lg btn-outline-success w-100 h-100 d-flex flex-column justify-content-center align-items-center py-4">
                            <i class="fas fa-users fa-3x mb-2"></i>
                            <span>Ver Grupos</span>
                        </a>
                    </div>
                    
                    <?php if (in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="../asistencia/index.php" class="btn btn-lg btn-outline-info w-100 h-100 d-flex flex-column justify-content-center align-items-center py-4">
                            <i class="fas fa-clipboard-list fa-3x mb-2"></i>
                            <span>Pase de Lista</span>
                        </a>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="../comunicados/crear.php" class="btn btn-lg btn-outline-warning w-100 h-100 d-flex flex-column justify-content-center align-items-center py-4">
                            <i class="fas fa-envelope fa-3x mb-2"></i>
                            <span>Nuevo Comunicado</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Datos para gráficos
const gradosLabels = <?= json_encode(array_keys($alumnos_por_grado)) ?>;
const gradosData = <?= json_encode(array_values($alumnos_por_grado)) ?>;

const inasistenciasFechas = <?= json_encode(array_map(function($fecha) {
    return date('d/m', strtotime($fecha));
}, array_keys($inasistencias_recientes))) ?>;
const inasistenciasData = <?= json_encode(array_values($inasistencias_recientes)) ?>;

// Gráfico de Alumnos por Grado
const gradosCtx = document.getElementById('alumnosPorGradoChart').getContext('2d');
const gradosChart = new Chart(gradosCtx, {
    type: 'bar',
    data: {
        labels: gradosLabels,
        datasets: [{
            label: 'Alumnos',
            data: gradosData,
            backgroundColor: 'rgba(54, 162, 235, 0.7)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Gráfico de Inasistencias Recientes
const inasistenciasCtx = document.getElementById('inasistenciasChart').getContext('2d');
const inasistenciasChart = new Chart(inasistenciasCtx, {
    type: 'line',
    data: {
        labels: inasistenciasFechas,
        datasets: [{
            label: 'Inasistencias',
            data: inasistenciasData,
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
</script>