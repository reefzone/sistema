<?php
/**
 * Crear Grupo
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador pueden crear grupos)
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a esta sección', 'danger');
}

// Generar token CSRF
$csrf_token = generar_token_csrf();

// Obtener catálogos
// Turnos
$turnos = [];
$query = "SELECT id_turno, nombre_turno FROM turnos ORDER BY id_turno";
$result = $conexion->query($query);
while ($row = $result->fetch_assoc()) {
    $turnos[$row['id_turno']] = $row['nombre_turno'];
}

// Grados
$grados = [];
$query = "SELECT id_grado, nombre_grado FROM grados ORDER BY id_grado";
$result = $conexion->query($query);
while ($row = $result->fetch_assoc()) {
    $grados[$row['id_grado']] = $row['nombre_grado'];
}

// Obtener ciclo escolar actual (para sugerencia)
$ciclo_actual = date('Y') . '-' . (date('Y') + 1);

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-users-class"></i> Agregar Grupo</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-edit"></i> Formulario de Registro
            </h5>
        </div>
        <div class="card-body">
            <form id="form-grupo" action="guardar.php" method="post" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="row">
                    <!-- Datos del grupo -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-users"></i> Datos del Grupo</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="turno" class="form-label required">Turno</label>
                                    <select class="form-select" id="turno" name="turno" required>
                                        <option value="">Seleccione un turno</option>
                                        <?php foreach ($turnos as $id => $nombre): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un turno
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="grado" class="form-label required">Grado</label>
                                    <select class="form-select" id="grado" name="grado" required>
                                        <option value="">Seleccione un grado</option>
                                        <?php foreach ($grados as $id => $nombre): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un grado
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nombre_grupo" class="form-label required">Nombre del Grupo</label>
                                    <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo" required maxlength="10">
                                    <div class="invalid-feedback">
                                        Por favor ingrese el nombre del grupo
                                    </div>
                                    <div class="form-text">
                                        Ejemplo: A, B, C, etc. (máximo 10 caracteres)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="ciclo_escolar" class="form-label required">Ciclo Escolar</label>
                                    <input type="text" class="form-control" id="ciclo_escolar" name="ciclo_escolar" required maxlength="20" value="<?= $ciclo_actual ?>">
                                    <div class="invalid-feedback">
                                        Por favor ingrese el ciclo escolar
                                    </div>
                                    <div class="form-text">
                                        Formato: AAAA-AAAA (ejemplo: <?= $ciclo_actual ?>)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configuración adicional -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-cog"></i> Configuración Adicional</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="color_credencial" class="form-label required">Color para Credenciales</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="color_credencial" name="color_credencial" value="#FFFFFF" required>
                                        <input type="text" class="form-control" id="color_hex" value="#FFFFFF" readonly>
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un color
                                    </div>
                                    <div class="form-text">
                                        Este color se utilizará para las credenciales de los alumnos de este grupo
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0">Vista previa</h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="preview-credencial" class="border p-3" style="background-color: #FFFFFF; border-radius: 8px;">
                                                <h5 class="text-center mb-3">ESCUELA SECUNDARIA TÉCNICA #82</h5>
                                                <div class="text-center mb-2">
                                                    <i class="fas fa-user-circle fa-5x"></i>
                                                </div>
                                                <p class="text-center">Nombre del Alumno</p>
                                                <p class="text-center mb-0">
                                                    <span class="badge" id="preview-badge" style="background-color: #FFFFFF; color: #000; border: 1px solid #ccc;">
                                                        <span id="preview-grado">-</span>° <span id="preview-grupo">-</span>
                                                        <span id="preview-turno" class="ms-1">-</span>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Guardar Grupo
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        const form = document.getElementById('form-grupo');
        const colorInput = document.getElementById('color_credencial');
        const colorHex = document.getElementById('color_hex');
        const previewCredencial = document.getElementById('preview-credencial');
        const previewBadge = document.getElementById('preview-badge');
        const previewGrado = document.getElementById('preview-grado');
        const previewGrupo = document.getElementById('preview-grupo');
        const previewTurno = document.getElementById('preview-turno');
        const gradoSelect = document.getElementById('grado');
        const turnoSelect = document.getElementById('turno');
        const nombreGrupoInput = document.getElementById('nombre_grupo');
        
        // Validación del formulario
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
        
        // Actualizar color en tiempo real
        colorInput.addEventListener('input', function() {
            const color = this.value;
            colorHex.value = color;
            
            // Actualizar vista previa
            previewCredencial.style.backgroundColor = color;
            previewBadge.style.backgroundColor = color;
            
            // Ajustar color de texto según el color de fondo
            const r = parseInt(color.substr(1, 2), 16);
            const g = parseInt(color.substr(3, 2), 16);
            const b = parseInt(color.substr(5, 2), 16);
            const brightness = (r * 299 + g * 587 + b * 114) / 1000;
            
            if (brightness < 128) {
                previewBadge.style.color = '#FFF';
                previewBadge.style.border = 'none';
            } else {
                previewBadge.style.color = '#000';
                previewBadge.style.border = '1px solid #ccc';
            }
        });
        
        // Actualizar vista previa cuando cambian los selectores
        gradoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            previewGrado.textContent = selectedOption.text.replace('Grado ', '').replace('°', '');
        });
        
        turnoSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            previewTurno.textContent = selectedOption.text;
        });
        
        nombreGrupoInput.addEventListener('input', function() {
            previewGrupo.textContent = this.value;
        });
        
        // Validar que el nombre del grupo sea solo letras A-Z y números
        nombreGrupoInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            
            // Permitir solo letras y números
            if (!/^[A-Z0-9]*$/.test(this.value)) {
                this.setCustomValidity('Solo se permiten letras (A-Z) y números');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Validar formato del ciclo escolar
        const cicloEscolar = document.getElementById('ciclo_escolar');
        cicloEscolar.addEventListener('input', function() {
            const cicloRegex = /^\d{4}-\d{4}$/;
            if (!cicloRegex.test(this.value)) {
                this.setCustomValidity('El formato debe ser AAAA-AAAA (ej: 2024-2025)');
            } else {
                const años = this.value.split('-');
                if (parseInt(años[1]) !== parseInt(años[0]) + 1) {
                    this.setCustomValidity('El segundo año debe ser consecutivo al primero');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
    });
</script>