<?php
/**
 * Módulo de Credenciales - Página Principal
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a este módulo', 'danger');
}

// Obtener ciclo escolar actual (el más reciente)
$query_ciclo = "SELECT ciclo_escolar FROM grupos WHERE activo = 1 ORDER BY ciclo_escolar DESC LIMIT 1";
$result_ciclo = $conexion->query($query_ciclo);
$ciclo_actual = $result_ciclo->num_rows > 0 ? $result_ciclo->fetch_assoc()['ciclo_escolar'] : date('Y') . '-' . (date('Y') + 1);

// Obtener turnos para filtro
$turnos = [];
$query_turnos = "SELECT id_turno, nombre_turno FROM turnos ORDER BY id_turno";
$result_turnos = $conexion->query($query_turnos);
while ($row = $result_turnos->fetch_assoc()) {
    $turnos[$row['id_turno']] = $row['nombre_turno'];
}

// Obtener grados para filtro
$grados = [];
$query_grados = "SELECT id_grado, nombre_grado FROM grados ORDER BY id_grado";
$result_grados = $conexion->query($query_grados);
while ($row = $result_grados->fetch_assoc()) {
    $grados[$row['id_grado']] = $row['nombre_grado'];
}

// Verificar si existe una configuración de credencial predeterminada
$query_config = "SELECT * FROM credenciales_config WHERE es_default = 1 LIMIT 1";
$result_config = $conexion->query($query_config);
$tiene_config_default = $result_config->num_rows > 0;

// Obtener historial de generación de credenciales recientes (últimas 5)
$query_historial = "SELECT g.nombre_grupo, g.ciclo_escolar, gr.nombre_grado, t.nombre_turno, 
                    COUNT(a.id_alumno) as total_alumnos, c.fecha_generacion, c.id_generacion,
                    u.nombre_completo as generado_por
                    FROM credenciales_generadas c
                    JOIN grupos g ON c.id_grupo = g.id_grupo
                    JOIN grados gr ON g.id_grado = gr.id_grado
                    JOIN turnos t ON g.id_turno = t.id_turno
                    JOIN alumnos a ON c.id_grupo = a.id_grupo
                    JOIN usuarios u ON c.generado_por = u.id_usuario
                    WHERE c.tipo = 'grupo'
                    GROUP BY c.id_generacion
                    ORDER BY c.fecha_generacion DESC
                    LIMIT 5";
$result_historial = $conexion->query($query_historial);

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-id-card"></i> Credenciales Escolares</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="plantilla.php" class="btn btn-primary">
                <i class="fas fa-cog"></i> Configurar Plantilla
            </a>
        </div>
    </div>

    <?php if (!$tiene_config_default): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Atención:</strong> No hay una plantilla de credencial configurada. 
        Por favor, <a href="plantilla.php" class="alert-link">configure una plantilla</a> antes de generar credenciales.
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Generación por grupo -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users"></i> Generar Credenciales por Grupo
                    </h5>
                </div>
                <div class="card-body">
                    <form id="form-grupo" action="generar_grupo.php" method="get">
                        <div class="mb-3">
                            <label for="turno" class="form-label">Turno</label>
                            <select class="form-select" id="turno" name="turno" required>
                                <option value="">Seleccione un turno</option>
                                <?php foreach ($turnos as $id => $nombre): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="grado" class="form-label">Grado</label>
                            <select class="form-select" id="grado" name="grado" required>
                                <option value="">Seleccione un grado</option>
                                <?php foreach ($grados as $id => $nombre): ?>
                                <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="grupo" class="form-label">Grupo</label>
                            <select class="form-select" id="grupo" name="id_grupo" required disabled>
                                <option value="">Primero seleccione turno y grado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="ciclo_escolar" class="form-label">Ciclo Escolar</label>
                            <select class="form-select" id="ciclo_escolar" name="ciclo_escolar" required>
                                <?php
                                $query_ciclos = "SELECT DISTINCT ciclo_escolar FROM grupos WHERE activo = 1 ORDER BY ciclo_escolar DESC";
                                $result_ciclos = $conexion->query($query_ciclos);
                                while ($row = $result_ciclos->fetch_assoc()) {
                                    $selected = ($row['ciclo_escolar'] == $ciclo_actual) ? 'selected' : '';
                                    echo "<option value=\"" . htmlspecialchars($row['ciclo_escolar']) . "\" $selected>" . 
                                         htmlspecialchars($row['ciclo_escolar']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success" <?= $tiene_config_default ? '' : 'disabled' ?>>
                            <i class="fas fa-print"></i> Generar Credenciales
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Generación individual -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user"></i> Generar Credencial Individual
                    </h5>
                </div>
                <div class="card-body">
                    <form id="form-individual" action="generar.php" method="get">
                        <div class="mb-3">
                            <label for="buscar_alumno" class="form-label">Buscar Alumno</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="buscar_alumno" 
                                       placeholder="Nombre, apellido o matrícula">
                                <button class="btn btn-outline-secondary" type="button" id="btn-buscar">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="alumno" class="form-label">Seleccionar Alumno</label>
                            <select class="form-select" id="alumno" name="id_alumno" required disabled>
                                <option value="">Busque un alumno primero</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" <?= $tiene_config_default ? '' : 'disabled' ?>>
                            <i class="fas fa-id-card"></i> Generar Credencial
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Vista previa de credencial -->
    <!-- Vista previa de credencial PROFESIONAL -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-eye"></i> Vista Previa Profesional - Diseño Real
        </h5>
    </div>
    <div class="card-body text-center">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="credencial-preview mb-3">
                    <iframe src="vista_previa_pro.php" id="preview-frame" 
                            style="width: 100%; height: 320px; border: 2px solid #3b82f6; 
                            border-radius: 12px; box-shadow: 0 8px 25px rgba(59,130,246,0.2);">
                    </iframe>
                </div>

    <!-- Historial reciente -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-history"></i> Generaciones Recientes
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-primary">
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Grupo</th>
                            <th scope="col">Grado/Turno</th>
                            <th scope="col">Ciclo Escolar</th>
                            <th scope="col">Total</th>
                            <th scope="col">Generado por</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result_historial && $result_historial->num_rows > 0): ?>
                            <?php while ($historial = $result_historial->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($historial['fecha_generacion'])) ?></td>
                                <td><?= htmlspecialchars($historial['nombre_grupo']) ?></td>
                                <td><?= htmlspecialchars($historial['nombre_grado'] . ' - ' . $historial['nombre_turno']) ?></td>
                                <td><?= htmlspecialchars($historial['ciclo_escolar']) ?></td>
                                <td><span class="badge bg-info"><?= $historial['total_alumnos'] ?></span></td>
                                <td><?= htmlspecialchars($historial['generado_por']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="reimpresion.php?id=<?= $historial['id_generacion'] ?>" class="btn btn-warning" 
                                        title="Reimprimir">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No hay historial de generación de credenciales.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="reimpresion.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-list"></i> Ver historial completo
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cargar grupos al seleccionar turno y grado
        const turnoSelect = document.getElementById('turno');
        const gradoSelect = document.getElementById('grado');
        const grupoSelect = document.getElementById('grupo');
        
        function cargarGrupos() {
            const turno = turnoSelect.value;
            const grado = gradoSelect.value;
            
            if (turno && grado) {
                grupoSelect.disabled = true;
                grupoSelect.innerHTML = '<option value="">Cargando grupos...</option>';
                
                fetch(`../../includes/ajax/obtener_grupos.php?turno=${turno}&grado=${grado}`)
                    .then(response => response.json())
                    .then(data => {
                        grupoSelect.innerHTML = '<option value="">Seleccione un grupo</option>';
                        
                        if (data.length > 0) {
                            data.forEach(grupo => {
                                const option = document.createElement('option');
                                option.value = grupo.id_grupo;
                                option.textContent = grupo.nombre_grupo;
                                option.dataset.color = grupo.color_credencial;
                                grupoSelect.appendChild(option);
                            });
                            grupoSelect.disabled = false;
                        } else {
                            grupoSelect.innerHTML = '<option value="">No hay grupos disponibles</option>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        grupoSelect.innerHTML = '<option value="">Error al cargar grupos</option>';
                    });
            } else {
                grupoSelect.innerHTML = '<option value="">Primero seleccione turno y grado</option>';
                grupoSelect.disabled = true;
            }
        }
        
        turnoSelect.addEventListener('change', cargarGrupos);
        gradoSelect.addEventListener('change', cargarGrupos);
        
        // Buscar alumnos
        const buscarInput = document.getElementById('buscar_alumno');
        const btnBuscar = document.getElementById('btn-buscar');
        const alumnoSelect = document.getElementById('alumno');
        
        function buscarAlumnos() {
            const busqueda = buscarInput.value.trim();
            
            if (busqueda.length < 3) {
                alert('Por favor, ingrese al menos 3 caracteres para buscar');
                return;
            }
            
            alumnoSelect.disabled = true;
            alumnoSelect.innerHTML = '<option value="">Buscando alumnos...</option>';
            
            fetch(`../../includes/ajax/buscar_alumnos.php?q=${encodeURIComponent(busqueda)}`)
                .then(response => response.json())
                .then(data => {
                    alumnoSelect.innerHTML = '<option value="">Seleccione un alumno</option>';
                    
                    if (data.length > 0) {
                        data.forEach(alumno => {
                            const option = document.createElement('option');
                            option.value = alumno.id_alumno;
                            option.textContent = `${alumno.apellido}, ${alumno.nombre} (${alumno.matricula})`;
                            alumnoSelect.appendChild(option);
                        });
                        alumnoSelect.disabled = false;
                    } else {
                        alumnoSelect.innerHTML = '<option value="">No se encontraron alumnos</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alumnoSelect.innerHTML = '<option value="">Error al buscar alumnos</option>';
                });
        }
        
        btnBuscar.addEventListener('click', buscarAlumnos);
        buscarInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                buscarAlumnos();
            }
        });
        
        // Actualizar vista previa al seleccionar un grupo
        grupoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const color = selectedOption.dataset.color || '#FFFFFF';
                const previewImg = document.getElementById('preview-img');
                previewImg.src = `credencial_preview.php?color=${encodeURIComponent(color)}`;
            }
        });
    });
</script>