<?php
/**
 * Crear Alumno
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

// Incluir archivos necesarios
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/session_checker.php';

// Verificar permisos (solo superadmin y organizador pueden crear alumnos)
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

// Grupos (se cargarán vía AJAX dependiendo del grado y turno seleccionado)

// Incluir header
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1><i class="fas fa-user-plus"></i> Agregar Alumno</h1>
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
            <form id="form-alumno" action="guardar.php" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="row">
                    <!-- Datos personales -->
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Datos Personales</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="apellido_paterno" class="form-label required">Apellido Paterno</label>
                                        <input type="text" class="form-control" id="apellido_paterno" name="apellido_paterno" required>
                                        <div class="invalid-feedback">
                                            Por favor ingrese el apellido paterno
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido_materno" class="form-label required">Apellido Materno</label>
                                        <input type="text" class="form-control" id="apellido_materno" name="apellido_materno" required>
                                        <div class="invalid-feedback">
                                            Por favor ingrese el apellido materno
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="nombres" class="form-label required">Nombre(s)</label>
                                    <input type="text" class="form-control" id="nombres" name="nombres" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese el/los nombre(s)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="curp" class="form-label required">CURP</label>
                                    <input type="text" class="form-control" id="curp" name="curp" maxlength="18" required>
                                    <div class="invalid-feedback">
                                        Por favor ingrese un CURP válido
                                    </div>
                                    <div class="form-text">
                                        El CURP debe tener 18 caracteres (Ej: MAAA000101HDFRRL09)
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fecha_nacimiento" class="form-label required">Fecha de Nacimiento</label>
                                    <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required>
                                    <div class="invalid-feedback">
                                        Por favor seleccione la fecha de nacimiento
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="foto" class="form-label">Fotografía</label>
                                    <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg,image/png">
                                    <div class="form-text">
                                        Formatos permitidos: JPG, PNG. Tamaño máximo: 2MB
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
                                    <label for="grupo" class="form-label required">Grupo</label>
                                    <select class="form-select" id="grupo" name="grupo" required disabled>
                                        <option value="">Primero seleccione turno y grado</option>
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
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="enfermedades" class="form-label">Enfermedades o Condiciones Médicas</label>
                                    <textarea class="form-control" id="enfermedades" name="enfermedades" rows="3"></textarea>
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
                        <div class="card mb-3">
                            <div class="card-header bg-warning">
                                <h5 class="mb-0"><i class="fas fa-phone-alt"></i> Contactos de Emergencia</h5>
                            </div>
                            <div class="card-body">
                                <div id="contactos-container">
                                    <div class="contacto-item border rounded p-3 mb-3">
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
                            <i class="fas fa-save"></i> Guardar Alumno
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
        const form = document.getElementById('form-alumno');
        const turnoSelect = document.getElementById('turno');
        const gradoSelect = document.getElementById('grado');
        const grupoSelect = document.getElementById('grupo');
        const btnAgregarContacto = document.getElementById('agregar-contacto');
        const contactosContainer = document.getElementById('contactos-container');
        let contactoCounter = 1;
        
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
        function cargarGrupos() {
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
                                grupoSelect.innerHTML += `<option value="${grupo.id_grupo}">${grupo.nombre_grupo}</option>`;
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
        
        turnoSelect.addEventListener('change', cargarGrupos);
        gradoSelect.addEventListener('change', cargarGrupos);
        
        // Funcionalidad para agregar contactos de emergencia
        btnAgregarContacto.addEventListener('click', function() {
            contactoCounter++;
            
            const contactoItem = document.createElement('div');
            contactoItem.className = 'contacto-item border rounded p-3 mb-3';
            contactoItem.innerHTML = `
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
            
            // Agregar manejador de eventos para botón eliminar
            contactoItem.querySelector('.btn-eliminar-contacto').addEventListener('click', function() {
                contactoItem.remove();
            });
        });
    });
</script>