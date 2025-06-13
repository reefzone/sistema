<?php
/**
 * Editar Alumno
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador pueden editar alumnos)
if (!in_array($_SESSION['tipo_usuario'], ['superadmin', 'organizador'])) {
    redireccionar_con_mensaje('../login/index.php', 'No tienes permisos para acceder a esta sección', 'danger');
}

// Verificar si se proporcionó un ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redireccionar_con_mensaje('index.php', 'ID de alumno no válido', 'danger');
}

$id_alumno = intval($_GET['id']);

// Obtener datos del alumno
$query = "SELECT a.* FROM alumnos a WHERE a.id_alumno = ? AND a.activo = 1";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redireccionar_con_mensaje('index.php', 'El alumno solicitado no existe o no está activo', 'danger');
}

$alumno = $result->fetch_assoc();

// Obtener contactos de emergencia
$query = "SELECT * FROM contactos_emergencia WHERE id_alumno = ? ORDER BY es_principal DESC";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$contactos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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

// Grupos (se cargarán por AJAX dependiendo del grado y turno seleccionado)

// Generar token CSRF
$csrf_token = generar_token_csrf();

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-user-edit"></i> Editar Alumno</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="ver.php?id=<?= $id_alumno ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> Ver Detalles
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-edit"></i> Formulario de Edición
            </h5>
        </div>
        <div class="card-body">
            <form id="form-alumno" action="actualizar.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                
                <div class="row">
                    <!-- Datos personales -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Datos Personales</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <?php
                                    // Verificar si existe la foto
                                    $ruta_foto = UPLOADS_DIR . 'fotos/' . $id_alumno . '.jpg';
                                    $ruta_foto_png = UPLOADS_DIR . 'fotos/' . $id_alumno . '.png';
                                    $tiene_foto = file_exists($ruta_foto) || file_exists($ruta_foto_png);
                                    $url_foto = $tiene_foto ? 
                                        (file_exists($ruta_foto) ? BASE_URL . 'uploads/fotos/' . $id_alumno . '.jpg' : BASE_URL . 'uploads/fotos/' . $id_alumno . '.png') : 
                                        BASE_URL . 'assets/images/user-placeholder.png';
                                    ?>
                                    <img id="vista-previa" src="<?= $url_foto ?>" alt="Foto de <?= htmlspecialchars($alumno['nombres']) ?>" 
                                         class="img-fluid rounded-circle" style="max-width: 150px; height: 150px; object-fit: cover;">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="foto" class="form-label">Cambiar Fotografía</label>
                                    <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg,image/png">
                                    <div class="form-text">
                                        Formatos permitidos: JPG, PNG. Tamaño máximo: 2MB
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="apellido_paterno" class="form-label required">Apellido Paterno</label>
                                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" value="<?= htmlspecialchars($alumno['apellido_paterno']) ?>" required>
                                        <div class="invalid-feedback">
                                            Por favor ingrese el apellido paterno
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido_materno" class="form-label required">Apellido Materno</label>
                                        <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" value="<?= htmlspecialchars($alumno['apellido_materno']) ?>" required>
                                        <div class="invalid-feedback">
                                            Por favor ingrese el apellido materno
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nombres" class="form-label required">Nombre(s)</label>
                                    <input type="text" class="form-control" id="nombres" name="nombres" value="<?= htmlspecialchars($alumno['nombres']) ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el/los nombre(s)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="curp" class="form-label required">CURP</label>
                                    <input type="text" class="form-control" id="curp" name="curp" maxlength="18" value="<?= htmlspecialchars($alumno['curp']) ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese un CURP válido
                                    </div>
                                    <div class="form-text">
                                        El CURP debe tener 18 caracteres (Ej: MAAA000101HDFRRL09)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label required">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" value="<?= $alumno['fecha_nacimiento'] ?>" required>
                                    <div class="invalid-feedback">
                                        Por favor seleccione la fecha de nacimiento
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Datos escolares y médicos -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-school"></i> Datos Escolares</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="turno" class="form-label required">Turno</label>
                                    <select class="form-select" id="turno" name="turno" required>
                                        <option value="">Seleccione un turno</option>
                                        <?php foreach ($turnos as $id => $nombre): ?>
                                        <option value="<?= $id ?>" <?= $alumno['id_turno'] == $id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nombre) ?>
                                        </option>
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
                                        <option value="<?= $id ?>" <?= $alumno['id_grado'] == $id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nombre) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un grado
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="grupo" class="form-label required">Grupo</label>
                                    <select class="form-select" id="grupo" name="grupo" required>
                                        <option value="">Cargando grupos...</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un grupo
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-heartbeat"></i> Datos Médicos</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="tipo_sangre" class="form-label">Tipo de Sangre</label>
                                    <select class="form-select" id="tipo_sangre" name="tipo_sangre">
                                        <option value="">Desconocido</option>
                                        <option value="O+" <?= $alumno['tipo_sangre'] == 'O+' ? 'selected' : '' ?>>O+</option>
                                        <option value="O-" <?= $alumno['tipo_sangre'] == 'O-' ? 'selected' : '' ?>>O-</option>
                                        <option value="A+" <?= $alumno['tipo_sangre'] == 'A+' ? 'selected' : '' ?>>A+</option>
                                        <option value="A-" <?= $alumno['tipo_sangre'] == 'A-' ? 'selected' : '' ?>>A-</option>
                                        <option value="B+" <?= $alumno['tipo_sangre'] == 'B+' ? 'selected' : '' ?>>B+</option>
                                        <option value="B-" <?= $alumno['tipo_sangre'] == 'B-' ? 'selected' : '' ?>>B-</option>
                                        <option value="AB+" <?= $alumno['tipo_sangre'] == 'AB+' ? 'selected' : '' ?>>AB+</option>
                                        <option value="AB-" <?= $alumno['tipo_sangre'] == 'AB-' ? 'selected' : '' ?>>AB-</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="enfermedades" class="form-label">Enfermedades o Condiciones Médicas</label>
                                    <textarea class="form-control" id="enfermedades" name="enfermedades" rows="3"><?= htmlspecialchars($alumno['enfermedades']) ?></textarea>
                                    <div class="form-text">
                                        Indique si el alumno tiene alguna enfermedad crónica, alergia o condición médica especial
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Contactos de emergencia -->
                <div class="row">
                    <div class="col-12">
                        <div class="card mb-3" id="contactos">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fas fa-phone-alt"></i> Contactos de Emergencia</h5>
                            </div>
                            <div class="card-body">
                                <div id="contactos-container">
                                    <?php if (empty($contactos)): ?>
                                    <div class="contacto-item border rounded p-3 mb-3">
                                        <input type="hidden" name="contacto_id[]" value="0">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="contacto_nombre_1" class="form-label required">Nombre Completo</label>
                                                <input type="text" class="form-control" id="contacto_nombre_1" name="contacto_nombre[]" required>
                                                <div class="invalid-feedback">
                                                    Por favor ingrese el nombre del contacto
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="contacto_telefono_1" class="form-label required">Teléfono</label>
                                                <input type="tel" class="form-control" id="contacto_telefono_1" name="contacto_telefono[]" required>
                                                <div class="invalid-feedback">
                                                    Por favor ingrese un número de teléfono
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="contacto_parentesco_1" class="form-label required">Parentesco</label>
                                                <select class="form-select" id="contacto_parentesco_1" name="contacto_parentesco[]" required>
                                                    <option value="">Seleccione un parentesco</option>
                                                    <option value="Madre">Madre</option>
                                                    <option value="Padre">Padre</option>
                                                    <option value="Tutor">Tutor</option>
                                                    <option value="Abuelo/a">Abuelo/a</option>
                                                    <option value="Tío/a">Tío/a</option>
                                                    <option value="Hermano/a">Hermano/a</option>
                                                    <option value="Otro">Otro</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Por favor seleccione un parentesco
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="contacto_email_1" class="form-label">Correo Electrónico</label>
                                                <input type="email" class="form-control" id="contacto_email_1" name="contacto_email[]">
                                            </div>
                                        </div>
                                        <div class="mb-3 mt-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="contacto_principal[]" id="contacto_principal_1" value="1" checked>
                                                <label class="form-check-label" for="contacto_principal_1">
                                                    Contacto principal
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <?php foreach ($contactos as $index => $contacto): ?>
                                    <div class="contacto-item border rounded p-3 mb-3">
                                        <input type="hidden" name="contacto_id[]" value="<?= $contacto['id_contacto'] ?>">
                                        <?php if ($index > 0): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Contacto #<?= ($index + 1) ?></h6>
                                            <button type="button" class="btn btn-sm btn-danger btn-eliminar-contacto" data-id="<?= $contacto['id_contacto'] ?>">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label for="contacto_nombre_<?= ($index + 1) ?>" class="form-label required">Nombre Completo</label>
                                                <input type="text" class="form-control" id="contacto_nombre_<?= ($index + 1) ?>" name="contacto_nombre[]" value="<?= htmlspecialchars($contacto['nombre_completo']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Por favor ingrese el nombre del contacto
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="contacto_telefono_<?= ($index + 1) ?>" class="form-label required">Teléfono</label>
                                                <input type="tel" class="form-control" id="contacto_telefono_<?= ($index + 1) ?>" name="contacto_telefono[]" value="<?= htmlspecialchars($contacto['telefono']) ?>" required>
                                                <div class="invalid-feedback">
                                                    Por favor ingrese un número de teléfono
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label for="contacto_parentesco_<?= ($index + 1) ?>" class="form-label required">Parentesco</label>
                                                <select class="form-select" id="contacto_parentesco_<?= ($index + 1) ?>" name="contacto_parentesco[]" required>
                                                    <option value="">Seleccione un parentesco</option>
                                                    <option value="Madre" <?= $contacto['parentesco'] == 'Madre' ? 'selected' : '' ?>>Madre</option>
                                                    <option value="Padre" <?= $contacto['parentesco'] == 'Padre' ? 'selected' : '' ?>>Padre</option>
                                                    <option value="Tutor" <?= $contacto['parentesco'] == 'Tutor' ? 'selected' : '' ?>>Tutor</option>
                                                    <option value="Abuelo/a" <?= $contacto['parentesco'] == 'Abuelo/a' ? 'selected' : '' ?>>Abuelo/a</option>
                                                    <option value="Tío/a" <?= $contacto['parentesco'] == 'Tío/a' ? 'selected' : '' ?>>Tío/a</option>
                                                    <option value="Hermano/a" <?= $contacto['parentesco'] == 'Hermano/a' ? 'selected' : '' ?>>Hermano/a</option>
                                                    <option value="Otro" <?= $contacto['parentesco'] == 'Otro' ? 'selected' : '' ?>>Otro</option>
                                                </select>
                                                <div class="invalid-feedback">
                                                    Por favor seleccione un parentesco
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="contacto_email_<?= ($index + 1) ?>" class="form-label">Correo Electrónico</label>
                                                <input type="email" class="form-control" id="contacto_email_<?= ($index + 1) ?>" name="contacto_email[]" value="<?= htmlspecialchars($contacto['email']) ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3 mt-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="contacto_principal[]" id="contacto_principal_<?= ($index + 1) ?>" value="<?= ($index + 1) ?>" <?= $contacto['es_principal'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="contacto_principal_<?= ($index + 1) ?>">
                                                    Contacto principal
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" id="agregar-contacto" class="btn btn-outline-primary">
                                    <i class="fas fa-plus"></i> Agregar Otro Contacto
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Actualizar Alumno
                        </button>
                        <a href="ver.php?id=<?= $id_alumno ?>" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación de contacto -->
<div class="modal fade" id="eliminarContactoModal" tabindex="-1" aria-labelledby="eliminarContactoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="eliminarContactoModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar este contacto de emergencia?</p>
                <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form id="form-eliminar-contacto" action="eliminar_contacto.php" method="post">
                    <input type="hidden" name="id_contacto" id="id-contacto">
                    <input type="hidden" name="id_alumno" value="<?= $id_alumno ?>">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables
        const form = document.getElementById('form-alumno');
        const turnoSelect = document.getElementById('turno');
        const gradoSelect = document.getElementById('grado');
        const grupoSelect = document.getElementById('grupo');
        const btnAgregarContacto = document.getElementById('agregar-contacto');
        const contactosContainer = document.getElementById('contactos-container');
        const fotoInput = document.getElementById('foto');
        const vistaPrevia = document.getElementById('vista-previa');
        let contactoCounter = <?= count($contactos) ?: 1 ?>;
        
        // Cargar grupos iniciales
        cargarGrupos(<?= $alumno['id_grupo'] ?>);
        
        // Validación del formulario
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
        
        // Validación de CURP
        const curpInput = document.getElementById('curp');
        curpInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            
            // Validar formato CURP
            if (this.value.length === 18) {
                const curpRegex = /^[A-Z]{4}\d{6}[HM][A-Z]{5}[0-9A-Z]\d$/;
                if (!curpRegex.test(this.value)) {
                    this.setCustomValidity('El formato del CURP no es válido');
                } else {
                    this.setCustomValidity('');
                }
            } else {
                this.setCustomValidity('El CURP debe tener 18 caracteres');
            }
        });
        
        // Cargar grupos cuando cambia turno o grado
        function cargarGrupos(grupoSeleccionado = 0) {
            const turno = turnoSelect.value;
            const grado = gradoSelect.value;
            
            if (turno && grado) {
                grupoSelect.disabled = true;
                grupoSelect.innerHTML = '<option value="">Cargando grupos...</option>';
                
                fetch(`get_grupos.php?turno=${turno}&grado=${grado}`)
                    .then(response => response.json())
                    .then(data => {
                        grupoSelect.innerHTML = '<option value="">Seleccione un grupo</option>';
                        
                        if (data.length === 0) {
                            grupoSelect.innerHTML += '<option value="">No hay grupos disponibles</option>';
                        } else {
                            data.forEach(grupo => {
                                const selected = grupoSeleccionado == grupo.id_grupo ? 'selected' : '';
                                grupoSelect.innerHTML += `<option value="${grupo.id_grupo}" ${selected}>${grupo.nombre_grupo}</option>`;
                            });
                        }
                        
                        grupoSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error al cargar grupos:', error);
                        grupoSelect.innerHTML = '<option value="">Error al cargar grupos</option>';
                        grupoSelect.disabled = false;
                    });
            } else {
                grupoSelect.innerHTML = '<option value="">Primero seleccione turno y grado</option>';
                grupoSelect.disabled = true;
            }
        }
        
        turnoSelect.addEventListener('change', () => cargarGrupos());
        gradoSelect.addEventListener('change', () => cargarGrupos());
        
        // Vista previa de imagen
        fotoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    vistaPrevia.src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Funcionalidad para agregar contactos de emergencia
        btnAgregarContacto.addEventListener('click', function() {
            contactoCounter++;
            
            const contactoItem = document.createElement('div');
            contactoItem.className = 'contacto-item border rounded p-3 mb-3';
            contactoItem.innerHTML = `
                <input type="hidden" name="contacto_id[]" value="0">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Contacto #${contactoCounter}</h6>
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-contacto">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="contacto_nombre_${contactoCounter}" class="form-label required">Nombre Completo</label>
                        <input type="text" class="form-control" id="contacto_nombre_${contactoCounter}" name="contacto_nombre[]" required>
                        <div class="invalid-feedback">
                            Por favor ingrese el nombre del contacto
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="contacto_telefono_${contactoCounter}" class="form-label required">Teléfono</label>
                        <input type="tel" class="form-control" id="contacto_telefono_${contactoCounter}" name="contacto_telefono[]" required>
                        <div class="invalid-feedback">
                            Por favor ingrese un número de teléfono
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="contacto_parentesco_${contactoCounter}" class="form-label required">Parentesco</label>
                        <select class="form-select" id="contacto_parentesco_${contactoCounter}" name="contacto_parentesco[]" required>
                            <option value="">Seleccione un parentesco</option>
                            <option value="Madre">Madre</option>
                            <option value="Padre">Padre</option>
                            <option value="Tutor">Tutor</option>
                            <option value="Abuelo/a">Abuelo/a</option>
                            <option value="Tío/a">Tío/a</option>
                            <option value="Hermano/a">Hermano/a</option>
                            <option value="Otro">Otro</option>
                        </select>
                        <div class="invalid-feedback">
                            Por favor seleccione un parentesco
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="contacto_email_${contactoCounter}" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="contacto_email_${contactoCounter}" name="contacto_email[]">
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="contacto_principal[]" id="contacto_principal_${contactoCounter}" value="${contactoCounter}">
                        <label class="form-check-label" for="contacto_principal_${contactoCounter}">
                            Contacto principal
                        </label>
                    </div>
                </div>
            `;
            
            contactosContainer.appendChild(contactoItem);
            
            // Agregar manejador de eventos para botón eliminar dinámico
            contactoItem.querySelector('.btn-eliminar-contacto').addEventListener('click', function() {
                contactoItem.remove();
            });
        });
        
        // Configurar eliminación de contactos existentes
        document.querySelectorAll('.btn-eliminar-contacto[data-id]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const idContacto = this.getAttribute('data-id');
                document.getElementById('id-contacto').value = idContacto;
                
                const modal = new bootstrap.Modal(document.getElementById('eliminarContactoModal'));
                modal.show();
            });
        });
    });
</script>