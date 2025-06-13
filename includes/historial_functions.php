<?php
/**
 * Funciones auxiliares para el módulo de historial escolar
 * Sistema Escolar - ESCUELA SECUNDARIA TECNICA #82
 */

/**
 * Obtiene el historial completo de un alumno
 * 
 * @param int $id_alumno ID del alumno
 * @param array $filtros Filtros opcionales (tipo, fecha_inicio, fecha_fin, etc)
 * @return array Registros del historial
 */
function obtener_historial_alumno($id_alumno, $filtros = []) {
    global $conexion;
    
    $where = ["h.id_alumno = ?", "h.eliminado = 0"];
    $params = [$id_alumno];
    $tipos = "i";
    
    // Aplicar filtros
    if (!empty($filtros['tipo']) && in_array($filtros['tipo'], 
        ['academico', 'asistencia', 'conducta', 'reconocimiento', 'observacion'])) {
        $where[] = "h.tipo_registro = ?";
        $params[] = $filtros['tipo'];
        $tipos .= "s";
    }
    
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
    
    if (!empty($filtros['relevancia'])) {
        $where[] = "h.relevancia = ?";
        $params[] = $filtros['relevancia'];
        $tipos .= "s";
    }
    
    if (!empty($filtros['busqueda'])) {
        $where[] = "(h.titulo LIKE ? OR h.descripcion LIKE ?)";
        $busqueda = "%" . $filtros['busqueda'] . "%";
        $params[] = $busqueda;
        $params[] = $busqueda;
        $tipos .= "ss";
    }
    
    $where_clause = implode(" AND ", $where);
    
    $query = "SELECT h.*, u.nombre_completo as registrado_por_nombre, 
             u2.nombre_completo as modificado_por_nombre 
             FROM historial_escolar h 
             LEFT JOIN usuarios u ON h.registrado_por = u.id_usuario 
             LEFT JOIN usuarios u2 ON h.modificado_por = u2.id_usuario 
             WHERE $where_clause 
             ORDER BY h.fecha_evento DESC, h.id_historial DESC";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historial = [];
    while ($row = $result->fetch_assoc()) {
        // Obtener adjuntos si existen
        if ($row['tiene_adjunto']) {
            $query_adj = "SELECT * FROM historial_adjuntos 
                         WHERE id_historial = ? AND eliminado = 0";
            $stmt_adj = $conexion->prepare($query_adj);
            $stmt_adj->bind_param("i", $row['id_historial']);
            $stmt_adj->execute();
            $adjuntos = $stmt_adj->get_result()->fetch_all(MYSQLI_ASSOC);
            $row['adjuntos'] = $adjuntos;
        } else {
            $row['adjuntos'] = [];
        }
        
        $historial[] = $row;
    }
    
    return $historial;
}

/**
 * Obtiene resumen rápido del historial de un alumno
 * 
 * @param int $id_alumno ID del alumno
 * @return array Datos resumidos del historial
 */
function obtener_resumen_historial($id_alumno) {
    global $conexion;
    
    $resumen = [
        'total_registros' => 0,
        'academico' => 0,
        'asistencia' => 0,
        'conducta' => 0,
        'reconocimiento' => 0,
        'observacion' => 0,
        'ultimo_registro' => null,
        'alta_relevancia' => 0
    ];
    
    // Contar registros por tipo
    $query = "SELECT tipo_registro, COUNT(*) as total 
             FROM historial_escolar 
             WHERE id_alumno = ? AND eliminado = 0 
             GROUP BY tipo_registro";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $resumen[$row['tipo_registro']] = $row['total'];
        $resumen['total_registros'] += $row['total'];
    }
    
    // Obtener último registro
    $query = "SELECT * FROM historial_escolar 
             WHERE id_alumno = ? AND eliminado = 0 
             ORDER BY fecha_evento DESC, id_historial DESC LIMIT 1";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $resumen['ultimo_registro'] = $row;
    }
    
    // Contar registros de alta relevancia
    $query = "SELECT COUNT(*) as total 
             FROM historial_escolar 
             WHERE id_alumno = ? AND eliminado = 0 AND relevancia = 'alta'";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $id_alumno);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $resumen['alta_relevancia'] = $row['total'];
    }
    
    return $resumen;
}

/**
 * Genera un color según el tipo de registro
 * 
 * @param string $tipo_registro Tipo de registro
 * @return string Clase CSS o código de color
 */
function obtener_color_tipo_registro($tipo_registro) {
    $colores = [
        'academico' => 'primary',
        'asistencia' => 'info',
        'conducta' => 'warning',
        'reconocimiento' => 'success',
        'observacion' => 'secondary'
    ];
    
    return $colores[$tipo_registro] ?? 'secondary';
}

/**
 * Genera un ícono según el tipo de registro
 * 
 * @param string $tipo_registro Tipo de registro
 * @return string Clase de ícono FontAwesome
 */
function obtener_icono_tipo_registro($tipo_registro) {
    $iconos = [
        'academico' => 'fas fa-graduation-cap',
        'asistencia' => 'fas fa-calendar-check',
        'conducta' => 'fas fa-exclamation-triangle',
        'reconocimiento' => 'fas fa-award',
        'observacion' => 'fas fa-comment'
    ];
    
    return $iconos[$tipo_registro] ?? 'fas fa-file';
}

/**
 * Obtiene la categoría del historial
 * 
 * @param string $tipo_registro Tipo de registro
 * @return array Lista de categorías disponibles
 */
function obtener_categorias_historial($tipo_registro) {
    $categorias = [
        'academico' => [
            'evaluacion_parcial' => 'Evaluación Parcial',
            'evaluacion_final' => 'Evaluación Final',
            'proyecto' => 'Proyecto',
            'examen_extraordinario' => 'Examen Extraordinario',
            'concurso' => 'Concurso Académico',
            'olimpiada' => 'Olimpiada del Conocimiento',
            'otro' => 'Otro'
        ],
        'asistencia' => [
            'inasistencia' => 'Inasistencia',
            'retardo' => 'Retardo',
            'salida_anticipada' => 'Salida Anticipada',
            'justificacion' => 'Justificación',
            'otro' => 'Otro'
        ],
        'conducta' => [
            'reporte_menor' => 'Reporte Menor',
            'reporte_grave' => 'Reporte Grave',
            'suspension' => 'Suspensión',
            'citatorio' => 'Citatorio a Padres',
            'conducta_positiva' => 'Conducta Positiva',
            'otro' => 'Otro'
        ],
        'reconocimiento' => [
            'cuadro_honor' => 'Cuadro de Honor',
            'diploma' => 'Diploma',
            'medalla' => 'Medalla',
            'mencion_honorifica' => 'Mención Honorífica',
            'premio_deportivo' => 'Premio Deportivo',
            'premio_cultural' => 'Premio Cultural',
            'otro' => 'Otro'
        ],
        'observacion' => [
            'general' => 'General',
            'academica' => 'Académica',
            'conductual' => 'Conductual',
            'familiar' => 'Familiar',
            'salud' => 'Salud',
            'otro' => 'Otro'
        ]
    ];
    
    return $categorias[$tipo_registro] ?? [];
}

/**
 * Formatea el tamaño de un archivo para mostrarlo en formato legible
 * 
 * @param int $tamano Tamaño en bytes
 * @return string Tamaño formateado (KB, MB, etc.)
 */
function formatear_tamano_archivo($tamano) {
    $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    
    while ($tamano >= 1024 && $i < count($unidades) - 1) {
        $tamano /= 1024;
        $i++;
    }
    
    return round($tamano, 2) . ' ' . $unidades[$i];
}

/**
 * Registra una nueva entrada en la tabla historial_consultas
 * 
 * @param int $id_alumno ID del alumno consultado
 * @param int $id_usuario ID del usuario que realiza la consulta
 * @return bool Resultado de la operación
 */
function registrar_consulta_historial($id_alumno, $id_usuario) {
    global $conexion;
    
    $query = "INSERT INTO historial_consultas (id_alumno, id_usuario, fecha_registro)
              VALUES (?, ?, NOW())";
    
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ii", $id_alumno, $id_usuario);
    
    return $stmt->execute();
}

/**
 * Crea la tabla historial_consultas si no existe
 * 
 * @return bool Resultado de la operación
 */
function crear_tabla_historial_consultas() {
    global $conexion;
    
    $query = "CREATE TABLE IF NOT EXISTS `historial_consultas` (
                `id_consulta` int(11) NOT NULL AUTO_INCREMENT,
                `id_alumno` int(11) NOT NULL,
                `id_usuario` int(11) NOT NULL,
                `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id_consulta`),
                KEY `idx_alumno` (`id_alumno`),
                KEY `idx_usuario` (`id_usuario`),
                KEY `idx_fecha` (`fecha_registro`)
              ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci";
    
    return $conexion->query($query);
}

// Crear tabla historial_consultas si no existe
crear_tabla_historial_consultas();