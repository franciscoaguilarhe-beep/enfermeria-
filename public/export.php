<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/bootstrap.php';

if (in_array((string)($_GET['format'] ?? $_GET['type'] ?? ''), ['prostata', 'pdf_prostata'], true)) {
    $ref = urlencode((string)($_GET['ref'] ?? $_GET['patient_ref'] ?? ''));
    redirect(base_url('index.php?r=pdf_prostata&ref=' . $ref));
}

if (in_array((string)($_GET['format'] ?? $_GET['type'] ?? ''), ['colon', 'pdf_colon'], true) || isset($_GET['q1'])) {
    // Redirigir o procesar formato de Colon y Recto manteniendo todos los parámetros GET
    $params = $_GET;
    $params['r'] = 'pdf_colon';
    unset($params['format'], $params['type']);
    redirect(base_url('index.php?' . http_build_query($params)));
}

if (in_array((string)($_GET['format'] ?? $_GET['type'] ?? ''), ['salud_mental', 'pdf_salud_mental'], true)) {
    $ref = urlencode((string)($_GET['ref'] ?? $_GET['patient_ref'] ?? ''));
    redirect(base_url('index.php?r=pdf_salud_mental&ref=' . $ref));
}

$pdo = db();
$exportUser = require_role('ADMINISTRADOR', 'SUPERVISOR');

// Enforce unit scope - SUPERVISOR can only export their own unit
$rolClave = (string)($exportUser['rol_clave'] ?? '');
$where = ['a.estado = "ACTIVA"'];
$params = [];

if ($rolClave === 'SUPERVISOR' && (int)($exportUser['unidad_id'] ?? 0) > 0) {
    $where[] = 'a.unidad_id = ?';
    $params[] = (int)$exportUser['unidad_id'];
}

foreach (['unidad_id', 'consultorio_id', 'turno_id', 'enfermera_id'] as $field) {
    $value = (string)($_GET[$field] ?? '');
    if (ctype_digit($value) && $value !== '0') {
        // For SUPERVISOR, validate that requested unidad_id matches their own
        if ($field === 'unidad_id' && $rolClave === 'SUPERVISOR') {
            if ((int)$value !== (int)($exportUser['unidad_id'] ?? 0)) {
                http_response_code(403);
                exit('No tienes acceso a esa unidad.');
            }
            continue; // Already added above
        }
        $where[] = 'a.' . $field . ' = ?';
        $params[] = (int)$value;
    }
}

$from = (string)($_GET['desde'] ?? '');
$to = (string)($_GET['hasta'] ?? '');
if (valid_date($from)) { $where[] = 'a.fecha >= ?'; $params[] = $from; }
if (valid_date($to)) { $where[] = 'a.fecha <= ?'; $params[] = $to; }

$sql = 'SELECT a.fecha, a.hora, p.nss, p.dv, p.agregado, p.apellido_paterno, p.apellido_materno, p.nombres,
               u.clave unidad_clave, u.nombre unidad, c.clave consultorio_clave, c.nombre_descriptivo consultorio,
               t.clave turno_clave, t.nombre turno, e.matricula, e.nombre enfermera,
               a.edad_anios, a.edad_meses, a.edad_dias, a.temperatura, a.frecuencia_cardiaca,
               a.frecuencia_respiratoria, a.presion_sistolica, a.presion_diastolica, a.peso, a.talla,
               a.agudeza_visual_od, a.agudeza_visual_oi, a.vitamina_a, a.desparasitacion,
               a.cepillado_dental, a.educacion_salud, a.envio_medico_familiar,
               a.acciones_preventivas, a.nota_enfermeria, a.observaciones
        FROM atenciones a
        JOIN pacientes p ON p.paciente_id = a.paciente_id
        JOIN unidades u ON u.unidad_id = a.unidad_id
        JOIN consultorios c ON c.consultorio_id = a.consultorio_id
        JOIN turnos t ON t.turno_id = a.turno_id
        JOIN usuarios e ON e.usuario_id = a.enfermera_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.fecha DESC, a.hora DESC
        LIMIT 5000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
audit($pdo, $exportUser, 'EXPORTACIÓN CSV', 'atenciones', null, null, ['filtros' => array_keys($_GET), 'limite' => 5000]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="srae_reporte_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-store');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'wb');
$headers = ['fecha', 'hora', 'nss', 'dv', 'agregado', 'apellido_paterno', 'apellido_materno', 'nombres', 'unidad_clave', 'unidad', 'consultorio_clave', 'consultorio', 'turno_clave', 'turno', 'matricula', 'enfermera', 'edad_anios', 'edad_meses', 'edad_dias', 'temperatura', 'frecuencia_cardiaca', 'frecuencia_respiratoria', 'presion_sistolica', 'presion_diastolica', 'peso', 'talla', 'agudeza_visual_od', 'agudeza_visual_oi', 'vitamina_a', 'desparasitacion', 'cepillado_dental', 'educacion_salud', 'envio_medico_familiar', 'acciones_preventivas', 'nota_enfermeria', 'observaciones'];
fputcsv($out, $headers);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // CSV formula injection protection
    $safeRow = array_map('csv_safe', array_map('strval', $row));
    fputcsv($out, $safeRow);
}
fclose($out);
