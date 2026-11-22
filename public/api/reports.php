<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$user = Auth::requireUser();

function report_scope(PDO $pdo, array $user): array {
    $rolClave = (string)($user['rol_clave'] ?? '');
    if ($rolClave === 'ADMINISTRADOR') return ['1=1', []];
    if ($rolClave === 'SUPERVISOR') {
        if ((int)($user['unidad_id'] ?? 0) > 0) return ['a.unidad_id=?', [(int)$user['unidad_id']]];
        return ['1=1', []];
    }
    $unitId = (int)($user['unidad_id'] ?? 0);
    if ($unitId > 0) return ['a.unidad_id=?', [$unitId]];
    return ['1=0', []];
}

$action = (string)($_GET['action'] ?? '');
$pdo = db();

if ($action === 'context') {
    $roles = (string)($user['rol_clave'] ?? '');
    if ($roles === 'ADMINISTRADOR' || $roles === 'SUPERVISOR') {
        $units = $pdo->query('SELECT unidad_id, clave, nombre FROM unidades WHERE activo=1 ORDER BY nombre')->fetchAll();
        $clinics = $pdo->query('SELECT c.consultorio_id, uct.unidad_id, c.clave, c.nombre_descriptivo FROM consultorios c JOIN unidad_consultorio_turno uct ON c.consultorio_id=uct.consultorio_id WHERE c.activo=1 AND uct.activo=1 ORDER BY uct.unidad_id, c.clave')->fetchAll();
    } else {
        $units = $pdo->prepare('SELECT unidad_id, clave, nombre FROM unidades WHERE unidad_id=? AND activo=1');
        $units->execute([(int)($user['unidad_id'] ?? 0)]);
        $units = $units->fetchAll();
        $clinics = $pdo->prepare('SELECT c.consultorio_id, uct.unidad_id, c.clave, c.nombre_descriptivo FROM consultorios c JOIN unidad_consultorio_turno uct ON c.consultorio_id=uct.consultorio_id WHERE uct.unidad_id=? AND c.activo=1 AND uct.activo=1');
        $clinics->execute([(int)($user['unidad_id'] ?? 0)]);
        $clinics = $clinics->fetchAll();
    }
    $shifts = $pdo->query('SELECT turno_id, clave, nombre FROM turnos WHERE activo=1 ORDER BY nombre')->fetchAll();
    $nurses = [];
    if (in_array($roles, ['ADMINISTRADOR', 'SUPERVISOR'], true)) {
        $nurses = $pdo->query('SELECT usuario_id, matricula, nombre FROM usuarios WHERE activo=1 AND es_personal_clinico=1 ORDER BY nombre')->fetchAll();
    }
    json_response(['ok' => true, 'unidades' => $units, 'consultorios' => $clinics, 'turnos' => $shifts, 'enfermeras' => $nurses, 'rol' => $roles, 'mi_unidad' => $user['unidad_id'] ?? null]);
}

$fechaInicio = (string)($_GET['fecha_inicio'] ?? date('Y-m-01'));
$fechaFin = (string)($_GET['fecha_fin'] ?? date('Y-m-t'));
$unidadId = (int)($_GET['unidad_id'] ?? 0);
$consultorioId = (int)($_GET['consultorio_id'] ?? 0);
$turnoId = (int)($_GET['turno_id'] ?? 0);
$enfermeraId = (int)($_GET['enfermera_id'] ?? 0);

if (!valid_date($fechaInicio) || !valid_date($fechaFin)) {
    json_response(['ok' => false, 'message' => 'Fechas no válidas'], 400);
}

[$scopeSql, $scopeParams] = report_scope($pdo, $user);

$where = ["a.estado='ACTIVA'", "a.fecha >= ?", "a.fecha <= ?", $scopeSql];
$params = [$fechaInicio, $fechaFin, ...$scopeParams];

if ($unidadId > 0) {
    $roles = (string)($user['rol_clave'] ?? '');
    if ($roles !== 'ADMINISTRADOR' && $roles !== 'SUPERVISOR') {
        $unidadId = (int)($user['unidad_id'] ?? 0);
    }
    $where[] = "a.unidad_id = ?";
    $params[] = $unidadId;
}
if ($consultorioId > 0) {
    $where[] = "a.consultorio_id = ?";
    $params[] = $consultorioId;
}
if ($turnoId > 0) {
    $where[] = "a.turno_id = ?";
    $params[] = $turnoId;
}
if ($enfermeraId > 0) {
    $roles = (string)($user['rol_clave'] ?? '');
    if (in_array($roles, ['ADMINISTRADOR', 'SUPERVISOR'], true)) {
        $where[] = "a.enfermera_id = ?";
        $params[] = $enfermeraId;
    }
}

$whereClause = implode(' AND ', $where);

if ($action === 'summary') {
    $sql = "SELECT 
        COUNT(DISTINCT a.paciente_id) as pacientes_unicos,
        COUNT(a.atencion_id) as atenciones,
        SUM(CASE WHEN a.vitamina_a=1 THEN 1 ELSE 0 END) as vitamina_a,
        SUM(CASE WHEN a.desparasitacion=1 THEN 1 ELSE 0 END) as desparasitacion,
        SUM(CASE WHEN a.cepillado_dental=1 THEN 1 ELSE 0 END) as cepillado_dental,
        SUM(CASE WHEN a.sulfato_ferroso_fecha IS NOT NULL THEN 1 ELSE 0 END) as sulfato_ferroso,
        SUM(CASE WHEN a.envio_medico_familiar=1 THEN 1 ELSE 0 END) as envio_medico_familiar
    FROM atenciones a WHERE {$whereClause}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $summary = $stmt->fetch();

    $vacSql = "SELECT COUNT(*) as vacunas FROM vacunas_aplicadas va JOIN atenciones a ON a.atencion_id=va.atencion_id WHERE {$whereClause} AND va.estado='ACTIVA'";
    $stmt = $pdo->prepare($vacSql);
    $stmt->execute($params);
    $vacunas = $stmt->fetchColumn();
    $summary['vacunas_aplicadas'] = (int)$vacunas;

    $sqlTurno = "SELECT t.nombre, COUNT(*) as total FROM atenciones a JOIN turnos t ON t.turno_id=a.turno_id WHERE {$whereClause} GROUP BY a.turno_id ORDER BY total DESC";
    $stmt = $pdo->prepare($sqlTurno);
    $stmt->execute($params);
    $por_turno = $stmt->fetchAll();

    $sqlCons = "SELECT c.nombre_descriptivo as nombre, COUNT(*) as total FROM atenciones a JOIN consultorios c ON c.consultorio_id=a.consultorio_id WHERE {$whereClause} GROUP BY a.consultorio_id ORDER BY total DESC";
    $stmt = $pdo->prepare($sqlCons);
    $stmt->execute($params);
    $por_consultorio = $stmt->fetchAll();

    $sqlEnf = "SELECT e.nombre, COUNT(*) as total FROM atenciones a JOIN usuarios e ON e.usuario_id=a.enfermera_id WHERE {$whereClause} GROUP BY a.enfermera_id ORDER BY total DESC";
    $stmt = $pdo->prepare($sqlEnf);
    $stmt->execute($params);
    $por_enfermera = $stmt->fetchAll();

    json_response(['ok' => true, 'summary' => $summary, 'por_turno' => $por_turno, 'por_consultorio' => $por_consultorio, 'por_enfermera' => $por_enfermera]);
}

if ($action === 'detail') {
    $sql = "SELECT a.public_id, a.fecha, a.hora, p.nss, p.dv, p.agregado, p.apellido_paterno, p.apellido_materno, p.nombres,
            u.nombre as unidad_nombre, c.nombre_descriptivo as consultorio_nombre, t.nombre as turno_nombre, e.nombre as enfermera_nombre
            FROM atenciones a 
            JOIN pacientes p ON p.paciente_id=a.paciente_id
            JOIN unidades u ON u.unidad_id=a.unidad_id
            JOIN consultorios c ON c.consultorio_id=a.consultorio_id
            JOIN turnos t ON t.turno_id=a.turno_id
            JOIN usuarios e ON e.usuario_id=a.enfermera_id
            WHERE {$whereClause} ORDER BY a.fecha DESC, a.hora DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    foreach ($rows as &$row) {
        $row['paciente_nombre'] = normalize_spaces(implode(' ', array_filter([$row['apellido_paterno'], $row['apellido_materno'], $row['nombres']], static fn($v) => trim((string)$v) !== '')));
        $row['identidad'] = trim($row['nss'] . '-' . (string)$row['dv'] . ' ' . $row['agregado']);
    }
    json_response(['ok' => true, 'rows' => $rows]);
}

if ($action === 'export_csv') {
    $sql = "SELECT a.public_id, a.fecha, a.hora, p.nss, p.dv, p.agregado, p.apellido_paterno, p.apellido_materno, p.nombres,
            u.nombre as unidad_nombre, c.nombre_descriptivo as consultorio_nombre, t.nombre as turno_nombre, e.nombre as enfermera_nombre,
            a.vitamina_a, a.desparasitacion, a.cepillado_dental, a.sulfato_ferroso_fecha, a.envio_medico_familiar, a.peso, a.talla, a.nota_enfermeria
            FROM atenciones a 
            JOIN pacientes p ON p.paciente_id=a.paciente_id
            JOIN unidades u ON u.unidad_id=a.unidad_id
            JOIN consultorios c ON c.consultorio_id=a.consultorio_id
            JOIN turnos t ON t.turno_id=a.turno_id
            JOIN usuarios e ON e.usuario_id=a.enfermera_id
            WHERE {$whereClause} ORDER BY a.fecha DESC, a.hora DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_atenciones_' . $fechaInicio . '_a_' . $fechaFin . '.csv"');
    
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); 
    
    fputcsv($out, array_map('csv_safe', ['FECHA', 'HORA', 'NSS', 'AGREGADO', 'PACIENTE', 'UNIDAD', 'CONSULTORIO', 'TURNO', 'ENFERMERA', 'PESO', 'TALLA', 'VITAMINA A', 'DESPARASITACION', 'CEPILLADO', 'SULFATO FERROSO', 'ENVIO MEDICO', 'NOTA']));
    
    while ($row = $stmt->fetch()) {
        $nombre = normalize_spaces(implode(' ', array_filter([$row['apellido_paterno'], $row['apellido_materno'], $row['nombres']], static fn($v) => trim((string)$v) !== '')));
        fputcsv($out, array_map('csv_safe', [
            $row['fecha'],
            $row['hora'],
            $row['nss'] . '-' . $row['dv'],
            $row['agregado'],
            $nombre,
            $row['unidad_nombre'],
            $row['consultorio_nombre'],
            $row['turno_nombre'],
            $row['enfermera_nombre'],
            $row['peso'],
            $row['talla'],
            $row['vitamina_a'] ? 'SI' : 'NO',
            $row['desparasitacion'] ? 'SI' : 'NO',
            $row['cepillado_dental'] ? 'SI' : 'NO',
            $row['sulfato_ferroso_fecha'] ? 'SI' : 'NO',
            $row['envio_medico_familiar'] ? 'SI' : 'NO',
            $row['nota_enfermeria']
        ]));
    }
    fclose($out);
    exit;
}

json_response(['ok' => false, 'message' => 'Acción no válida'], 400);


