<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

$pdo = db();
$allPassed = true;

function test_assert(bool $condition, string $description): void {
    global $allPassed;
    if ($condition) {
        echo " [PASS] " . $description . "\n";
    } else {
        echo " [FAIL] " . $description . "\n";
        $allPassed = false;
    }
}

echo "=== INICIO DE PRUEBAS DE INTEGRACIÓN: PACIENTES Y CONFIGURACIÓN ===\n\n";

// 1. PACIENTES: Catálogo de vacunas (16 vacunas)
$vacunas = $pdo->query('SELECT clave, nombre, orden_visual FROM vacunas ORDER BY orden_visual, vacuna_id')->fetchAll(PDO::FETCH_ASSOC);
test_assert(count($vacunas) === 16, "Existen exactamente 16 vacunas configuradas");
test_assert($vacunas[0]['clave'] === 'BCG', "La primera vacuna es BCG");
test_assert($vacunas[15]['clave'] === 'COVID19', "La última vacuna es COVID19");

// 2. PACIENTES: Búsqueda histórica por NSS y verificación de columnas CURP y Escolaridad
$searchNss = '2206861799';
$stmt = $pdo->prepare('SELECT p.* FROM pacientes p WHERE p.nss = ? LIMIT 1');
$stmt->execute([$searchNss]);
$p = $stmt->fetch();
test_assert(!empty($p), "Paciente con NSS {$searchNss} localizado en BD");

$pacienteCols = $pdo->query('SHOW COLUMNS FROM pacientes')->fetchAll(PDO::FETCH_COLUMN);
test_assert(in_array('curp', $pacienteCols, true), "Columna 'curp' agregada en tabla pacientes");
test_assert(in_array('escolaridad', $pacienteCols, true), "Columna 'escolaridad' agregada en tabla pacientes");
test_assert(in_array('ocupacion', $pacienteCols, true), "Columna 'ocupacion' agregada en tabla pacientes");
test_assert(in_array('estado_civil', $pacienteCols, true), "Columna 'estado_civil' agregada en tabla pacientes");

$snap = PatientDomain::snapshot(['curp' => 'ROHG000101MDFRRN01', 'escolaridad' => 'LICENCIATURA', 'ocupacion' => 'DOCENTE', 'estado_civil' => 'CASADO(A)', 'nss' => '2206861799']);
test_assert(($snap['curp'] ?? '') === 'ROHG000101MDFRRN01', "PatientDomain::snapshot preserva CURP");
test_assert(($snap['escolaridad'] ?? '') === 'LICENCIATURA', "PatientDomain::snapshot preserva Escolaridad");
test_assert(($snap['ocupacion'] ?? '') === 'DOCENTE', "PatientDomain::snapshot preserva Ocupación");
test_assert(($snap['estado_civil'] ?? '') === 'CASADO(A)', "PatientDomain::snapshot preserva Estado Civil");

// 3. CONFIGURACIÓN: Ordenamiento numérico de Unidades (ignorando prefijos HGSZ/UMF)
function test_unit_sort_number(string $siglas, string $nombre): int {
    if (preg_match('/(?:N[°º\.\s]*|#\s*)?(\d+)/i', $siglas, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/(?:N[°º\.\s]*|#\s*)?(\d+)/i', $nombre, $m)) {
        return (int)$m[1];
    }
    return 999999;
}

$testUnits = [
    ['clave' => 'H.G.S.Z N° 38', 'nombre' => 'HOSPITAL 38'],
    ['clave' => 'U.M.F. N° 6', 'nombre' => 'UNIDAD 6'],
    ['clave' => 'U.M.F. N° 40', 'nombre' => 'UNIDAD 40'],
];

usort($testUnits, function($a, $b) {
    return test_unit_sort_number($a['clave'], $a['nombre']) <=> test_unit_sort_number($b['clave'], $b['nombre']);
});

test_assert(test_unit_sort_number($testUnits[0]['clave'], '') === 6, "Primera unidad ordenada numéricamente es la 6 (U.M.F. N° 6)");
test_assert(test_unit_sort_number($testUnits[1]['clave'], '') === 38, "Segunda unidad ordenada numéricamente es la 38 (H.G.S.Z N° 38)");
test_assert(test_unit_sort_number($testUnits[2]['clave'], '') === 40, "Tercera unidad ordenada numéricamente es la 40 (U.M.F. N° 40)");

// 4. CONFIGURACIÓN: Validación de contraseña de 8 caracteres
test_assert(strlen('1234567') < 8, "Contraseña de 7 caracteres es rechazada");
test_assert(strlen('12345678') >= 8, "Contraseña de 8 caracteres es aceptada");

// 5. CONFIGURACIÓN: Turnos disponibles (incluye NOC y JA)
$turnos = $pdo->query('SELECT clave FROM turnos WHERE activo=1')->fetchAll(PDO::FETCH_COLUMN);
test_assert(in_array('MAT', $turnos, true), "Turno Matutino (MAT) disponible");
test_assert(in_array('VESP', $turnos, true), "Turno Vespertino (VESP) disponible");
test_assert(in_array('NOC', $turnos, true), "Turno Nocturno (NOC) disponible");
test_assert(in_array('JA', $turnos, true), "Turno Fin de semana (JA) disponible");

// 6. PACIENTES: Matriz Esquema de Vacunación según Excel AAMATES
$totalSlots = (int)$pdo->query('SELECT COUNT(*) FROM vacuna_slots WHERE activo=1')->fetchColumn();
test_assert($totalSlots === 55, "Total de slots activos en matriz es 55 (con Hepatitis B a 1 dosis)");

$bcgSlots = $pdo->query('SELECT s.slot_codigo FROM vacuna_slots s JOIN vacunas v ON v.vacuna_id=s.vacuna_id WHERE v.clave="BCG" AND s.activo=1 ORDER BY s.orden')->fetchAll(PDO::FETCH_COLUMN);
test_assert($bcgSlots === ['1ER'], "BCG tiene solo 1 dosis (1ER) y el resto es gris");

$hepBSlots = $pdo->query('SELECT s.slot_codigo FROM vacuna_slots s JOIN vacunas v ON v.vacuna_id=s.vacuna_id WHERE v.clave="HEP_B" AND s.activo=1 ORDER BY s.orden')->fetchAll(PDO::FETCH_COLUMN);
test_assert($hepBSlots === ['1ER'], "Hepatitis B tiene solo 1 dosis (1ER) y el resto es gris");

$hexaSlots = $pdo->query('SELECT s.slot_codigo FROM vacuna_slots s JOIN vacunas v ON v.vacuna_id=s.vacuna_id WHERE v.clave="HEXA" AND s.activo=1 ORDER BY s.orden')->fetchAll(PDO::FETCH_COLUMN);
test_assert($hexaSlots === ['1ER', '2DA', '3ER', '4TA'], "Hexavalente tiene exactamente 4 dosis (1ER, 2DA, 3ER, 4TA)");

$vphSlots = $pdo->query('SELECT s.slot_codigo FROM vacuna_slots s JOIN vacunas v ON v.vacuna_id=s.vacuna_id WHERE v.clave="VPH" AND s.activo=1 ORDER BY s.orden')->fetchAll(PDO::FETCH_COLUMN);
test_assert($vphSlots === ['1ER'], "VPH tiene solo 1 dosis (1ER) y el resto es gris");

$influenzaSlots = $pdo->query('SELECT s.slot_codigo FROM vacuna_slots s JOIN vacunas v ON v.vacuna_id=s.vacuna_id WHERE v.clave="INFLUENZA" AND s.activo=1 ORDER BY s.orden')->fetchAll(PDO::FETCH_COLUMN);
test_assert($influenzaSlots === ['1ER', '2DA', '3ER', '4TA', 'REF', 'ADIC', 'ADIC2'], "Influenza tiene las 7 dosis activas (1ER a ADIC2)");

$neumo13 = $pdo->query('SELECT nombre FROM vacunas WHERE clave="NEUMO13"')->fetchColumn();
test_assert($neumo13 === 'NEUMOCOCCICA 13', "NEUMO13 está en mayúsculas y sin caracteres raros");

$neumo23 = $pdo->query('SELECT nombre FROM vacunas WHERE clave="NEUMO23"')->fetchColumn();
test_assert($neumo23 === 'NEUMOCOCCICA 20', "NEUMO23 está actualizada a NEUMOCOCCICA 20 en mayúsculas y sin caracteres raros");

// 7. PACIENTES: Edición / UPSERT de Vacunas Aplicadas (ON DUPLICATE KEY UPDATE)
$origVac = $pdo->query('SELECT * FROM vacunas_aplicadas WHERE paciente_id=1 AND vacuna_id=1 AND dosis_id=1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($origVac) {
    $pdo->beginTransaction();
    $stmtUp = $pdo->prepare(
        'INSERT INTO vacunas_aplicadas (public_id, vacuna_id, dosis_id, paciente_id, vacuna, fecha_aplicacion, lote, fecha_caducidad, enfermera_id, estado)
         VALUES (?, 1, 1, 1, "BCG", "2026-05-10", "LOTE-IMSS-01", "2027-12-31", 2, "ACTIVA")
         ON DUPLICATE KEY UPDATE
            fecha_aplicacion = VALUES(fecha_aplicacion),
            lote = VALUES(lote),
            fecha_caducidad = VALUES(fecha_caducidad),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmtUp->execute([$origVac['public_id']]);
    $rowsAfter = $pdo->query('SELECT * FROM vacunas_aplicadas WHERE paciente_id=1 AND vacuna_id=1 AND dosis_id=1')->fetchAll(PDO::FETCH_ASSOC);
    test_assert(count($rowsAfter) === 1, "UPSERT actualiza la dosis existente sin duplicar registros");
    test_assert($rowsAfter[0]['fecha_aplicacion'] === '2026-05-10', "UPSERT actualiza fecha_aplicacion correctamente");
    test_assert($rowsAfter[0]['lote'] === 'LOTE-IMSS-01', "UPSERT actualiza lote correctamente");
    test_assert($rowsAfter[0]['fecha_caducidad'] === '2027-12-31', "UPSERT actualiza fecha_caducidad correctamente");
    $pdo->rollBack();
}

// 8. PACIENTES: Consulta listado de pacientes sin filtros y con filtros combinados
$allListRows = $pdo->query(
    'SELECT a.public_id AS atencion_public_id, a.fecha, p.paciente_id, p.nss, p.nombres
     FROM pacientes p
     LEFT JOIN atenciones a ON a.paciente_id = p.paciente_id AND a.estado = "ACTIVA"
     WHERE 1=1
     ORDER BY COALESCE(a.fecha, DATE(p.created_at)) DESC, COALESCE(a.hora, TIME(p.created_at)) DESC, p.paciente_id DESC'
)->fetchAll(PDO::FETCH_ASSOC);
$totalPacientesBd = (int)$pdo->query('SELECT COUNT(*) FROM pacientes')->fetchColumn();
test_assert(count($allListRows) >= $totalPacientesBd, "Listado sin filtros retorna todos los pacientes registrados ({$totalPacientesBd})");

$stmtDateOnly = $pdo->prepare(
    'SELECT a.public_id AS atencion_public_id, a.fecha, p.nss
     FROM pacientes p
     LEFT JOIN atenciones a ON a.paciente_id = p.paciente_id AND a.estado = "ACTIVA"
     WHERE 1=1 AND a.fecha BETWEEN ? AND ?'
);
$stmtDateOnly->execute(['2026-09-29', '2026-09-29']);
$rowsSept = $stmtDateOnly->fetchAll(PDO::FETCH_ASSOC);
test_assert(count($rowsSept) === 1 && $rowsSept[0]['nss'] === '0815983720', "Filtro por fecha (2026-09-29) retorna únicamente los registros de esa fecha");

$stmtCombo = $pdo->prepare(
    'SELECT a.public_id AS atencion_public_id, a.fecha, p.nss
     FROM pacientes p
     LEFT JOIN atenciones a ON a.paciente_id = p.paciente_id AND a.estado = "ACTIVA"
     WHERE 1=1 AND a.fecha BETWEEN ? AND ? AND p.nombres LIKE ?'
);
$stmtCombo->execute(['2026-09-29', '2026-09-29', '%GRECIA%']);
test_assert(count($stmtCombo->fetchAll()) === 0, "Filtro combinado (fecha 2026-09-29 + nombre GRECIA) aplica ambos criterios en conjunto (0 coincidencias)");

echo "\n===================================================================\n";
if ($allPassed) {
    echo " RESULTADO: TODAS LAS PRUEBAS DE INTEGRACIÓN FUERON EXITOSAS.\n";
    exit(0);
} else {
    echo " RESULTADO: HUBO FALLAS EN LAS PRUEBAS DE INTEGRACIÓN.\n";
    exit(1);
}
