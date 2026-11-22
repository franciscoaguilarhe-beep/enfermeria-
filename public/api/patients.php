<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

function patient_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function public_id(): string
{
    return bin2hex(random_bytes(16));
}

function patient_user(PDO $pdo): array
{
    $session = Auth::requireUser();
    $stmt = $pdo->prepare('SELECT u.*, r.clave rol_clave, r.nombre rol_nombre, un.clave unidad_clave, un.nombre unidad_nombre, c.clave consultorio_clave, c.nombre_descriptivo consultorio_nombre FROM usuarios u LEFT JOIN roles r ON r.rol_id=u.rol_id LEFT JOIN unidades un ON un.unidad_id=u.unidad_id LEFT JOIN consultorios c ON c.consultorio_id=u.consultorio_id WHERE u.usuario_id=? LIMIT 1');
    $stmt->execute([(int)$session['usuario_id']]);
    $user = $stmt->fetch();
    if (!$user || !(bool)$user['activo']) {
        Auth::logout();
        patient_json(['ok' => false, 'message' => 'La sesión ya no está activa.'], 401);
    }
    return $user;
}

function patient_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') patient_json(['ok' => false, 'message' => 'Método no permitido.'], 405);
    Security::verifyCsrf();
}

function patient_value(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function patient_int(string $key): ?int
{
    $value = patient_value($key);
    return $value === '' ? null : (ctype_digit($value) ? (int)$value : null);
}

function patient_decimal(string $key): ?float
{
    $value = str_replace(',', '.', patient_value($key));
    return $value === '' ? null : (is_numeric($value) ? (float)$value : null);
}

function patient_date(string $key): ?string
{
    $value = patient_value($key);
    if ($value === '') return null;
    if (!valid_date($value)) throw new InvalidArgumentException('La fecha de ' . str_replace('_', ' ', $key) . ' no es válida.');
    return $value;
}

function patient_tri(string $key): ?int
{
    $value = patient_value($key);
    return $value === '' ? null : (in_array($value, ['0', '1'], true) ? (int)$value : null);
}

function patient_scope(array $user, string $patientAlias = 'p', string $attentionAlias = 'a'): array
{
    $rolClave = (string)($user['rol_clave'] ?? '');
    // ADMINISTRADOR: acceso global
    if ($rolClave === 'ADMINISTRADOR') {
        return ['1=1', []];
    }
    // SUPERVISOR: acceso a su unidad asignada
    if ($rolClave === 'SUPERVISOR') {
        $unitId = (int)($user['unidad_id'] ?? 0);
        if ($unitId > 0) {
            return ["{$attentionAlias}.unidad_id=?", [$unitId]];
        }
        return ['1=1', []];
    }
    // ENFERMERIA y otros: solo su unidad asignada
    $unitId = (int)($user['unidad_id'] ?? 0);
    if ($unitId > 0) {
        return ["{$attentionAlias}.unidad_id=?", [$unitId]];
    }
    // Sin unidad asignada = sin acceso a atenciones
    return ['1=0', []];
}

function scoped_patient(PDO $pdo, array $user, string $ref): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/i', $ref)) return null;
    $stmt = $pdo->prepare("SELECT p.* FROM pacientes p WHERE p.public_id=? LIMIT 1");
    $stmt->execute([$ref]);
    return $stmt->fetch() ?: null;
}

function present_patient(array $patient): array
{
    $patient['nombre_completo'] = normalize_spaces(implode(' ', array_filter([
        $patient['apellido_paterno'] ?? null,
        $patient['apellido_materno'] ?? null,
        $patient['nombres'] ?? null,
    ], static fn($value): bool => $value !== null && trim((string)$value) !== '')));
    $patient['identidad'] = trim((string)$patient['nss'] . '-' . (string)($patient['dv'] ?? '') . ' ' . (string)$patient['agregado']);
    $patient['curp'] = $patient['curp'] ?? null;
    $patient['escolaridad'] = $patient['escolaridad'] ?? null;
    $patient['ocupacion'] = $patient['ocupacion'] ?? null;
    $patient['estado_civil'] = $patient['estado_civil'] ?? null;
    return $patient;
}

function patient_payload(): array
{
    $nss = trim(patient_value('nss'));
    $dv = trim(patient_value('dv'));
    $agregado = PatientDomain::normalizeAggregate(patient_value('agregado'));
    PatientDomain::validateIdentity($nss, $dv, $agregado);
    $names = normalize_spaces(mb_strtoupper(patient_value('nombres'), 'UTF-8'));
    if ($names === '') throw new InvalidArgumentException('Los nombres son obligatorios.');
    $cp = patient_value('codigo_postal');
    if ($cp !== '' && !preg_match('/^\d{5}$/D', $cp)) throw new InvalidArgumentException('El C.P. debe contener 5 dígitos.');
    $birth = patient_date('fecha_nacimiento');
    $value = static fn(string $key): ?string => ($v = normalize_spaces(mb_strtoupper(patient_value($key), 'UTF-8'))) === '' ? null : $v;
    $curp = normalize_text(mb_strtoupper(patient_value('curp'), 'UTF-8'));
    if ($curp !== '') {
        if (!preg_match('/^[A-Z0-9]{18}$/D', $curp)) {
            throw new InvalidArgumentException('El CURP debe contener exactamente 18 caracteres alfanuméricos.');
        }
    } else {
        $curp = null;
    }
    $escolaridad = $value('escolaridad');
    $ocupacion = $value('ocupacion');
    $estadoCivil = $value('estado_civil');
    $data = [
        'nss' => $nss,
        'dv' => $dv,
        'agregado' => $agregado,
        'curp' => $curp,
        'escolaridad' => $escolaridad,
        'ocupacion' => $ocupacion,
        'estado_civil' => $estadoCivil,
        'apellido_paterno' => $value('apellido_paterno'),
        'apellido_materno' => $value('apellido_materno'),
        'nombres' => $names,
        'fecha_nacimiento' => $birth,
        'lugar_nacimiento' => $value('lugar_nacimiento'),
        'sexo' => ($v = patient_value('sexo')) === '' ? null : $v,
        'indigena' => patient_tri('indigena'),
        'afromexicano' => patient_tri('afromexicano'),
        'discapacidad' => patient_tri('discapacidad'),
        'pais_residencia' => $value('pais_residencia'),
        'entidad_residencia' => $value('entidad_residencia'),
        'municipio' => $value('municipio'),
        'colonia' => $value('colonia'),
        'calle' => $value('calle'),
        'numero_exterior' => null,
        'numero_interior' => null,
        'codigo_postal' => $cp === '' ? null : $cp,
        'telefono' => $value('telefono'),
        'otro_contacto' => $value('otro_contacto'),
        'domicilio' => null,
    ];
    $data['domicilio'] = PatientDomain::address($data);
    return $data;
}

function context_data(PDO $pdo, array $user): array
{
    $isBroad = in_array((string)($user['rol_clave'] ?? ''), ['ADMINISTRADOR', 'SUPERVISOR'], true);
    if ($isBroad) {
        $units = $pdo->query('SELECT unidad_id,clave,nombre FROM unidades WHERE activo=1 ORDER BY nombre')->fetchAll();
        $clinics = $pdo->query('SELECT DISTINCT c.consultorio_id, uct.unidad_id, c.clave, c.nombre_descriptivo FROM consultorios c JOIN unidad_consultorio_turno uct ON c.consultorio_id=uct.consultorio_id WHERE c.activo=1 AND uct.activo=1 ORDER BY uct.unidad_id, c.clave')->fetchAll();
        $shifts = $pdo->query('SELECT turno_id,clave,nombre FROM turnos WHERE activo=1 ORDER BY COALESCE(hora_inicio,"23:59:59"),nombre')->fetchAll();
        $matrix = $pdo->query('SELECT unidad_id,consultorio_id,turno_id FROM unidad_consultorio_turno WHERE activo=1')->fetchAll();
    } else {
        $units = $user['unidad_id'] ? $pdo->prepare('SELECT unidad_id,clave,nombre FROM unidades WHERE unidad_id=? AND activo=1') : null;
        if ($units) { $units->execute([(int)$user['unidad_id']]); $units = $units->fetchAll(); } else $units = [];
        $stmt = $pdo->prepare('SELECT c.consultorio_id, uct.unidad_id, c.clave, c.nombre_descriptivo FROM consultorios c JOIN unidad_consultorio_turno uct ON c.consultorio_id=uct.consultorio_id WHERE c.consultorio_id=? AND c.activo=1');
        $stmt->execute([(int)($user['consultorio_id'] ?? 0)]); $clinics = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT t.turno_id,t.clave,t.nombre FROM turnos t JOIN unidad_consultorio_turno m ON m.turno_id=t.turno_id WHERE m.unidad_id=? AND m.consultorio_id=? AND m.activo=1 AND t.activo=1 ORDER BY COALESCE(t.hora_inicio,"23:59:59"),t.nombre');
        $stmt->execute([(int)($user['unidad_id'] ?? 0), (int)($user['consultorio_id'] ?? 0)]); $shifts = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT unidad_id,consultorio_id,turno_id FROM unidad_consultorio_turno WHERE unidad_id=? AND consultorio_id=? AND activo=1');
        $stmt->execute([(int)($user['unidad_id'] ?? 0), (int)($user['consultorio_id'] ?? 0)]); $matrix = $stmt->fetchAll();
    }
    return ['broad' => $isBroad, 'user' => ['nombre' => $user['nombre'], 'matricula' => $user['matricula'], 'unidad_id' => $user['unidad_id'], 'consultorio_id' => $user['consultorio_id']], 'unidades' => $units, 'consultorios' => $clinics, 'turnos' => $shifts, 'matriz' => $matrix];
}

function attention_context(PDO $pdo, array $user, int $unitId, int $consultorioId, ?int $turnoId): array
{
    $unitId = (int)($user['unidad_id'] ?? 0);
    $consultorioId = (int)($user['consultorio_id'] ?? 0);
    if ($unitId < 1 || $consultorioId < 1) {
        $firstU = $pdo->query('SELECT u.unidad_id, c.consultorio_id FROM unidades u JOIN unidad_consultorio_turno uct ON u.unidad_id=uct.unidad_id JOIN consultorios c ON c.consultorio_id=uct.consultorio_id WHERE u.activo=1 AND c.activo=1 AND uct.activo=1 LIMIT 1')->fetch();
        if ($firstU) {
            $unitId = (int)$firstU['unidad_id'];
            $consultorioId = (int)$firstU['consultorio_id'];
        } else {
            throw new InvalidArgumentException('No hay unidades ni consultorios activos en el sistema.');
        }
    }
    $stmt = $pdo->prepare('SELECT id,turno_id FROM unidad_consultorio_turno WHERE unidad_id=? AND consultorio_id=? AND activo=1');
    $stmt->execute([$unitId, $consultorioId]);
    $allowed = $stmt->fetchAll();
    if (!$allowed) {
        $firstT = $pdo->query('SELECT turno_id FROM turnos WHERE activo=1 LIMIT 1')->fetch();
        $allowedIds = $firstT ? [(int)$firstT['turno_id']] : [1];
    } else {
        $allowedIds = array_map(static fn(array $row): int => (int)$row['turno_id'], $allowed);
    }
    if ($turnoId === null || !in_array($turnoId, $allowedIds, true)) {
        $nowTime = (new DateTimeImmutable('now'))->format('H:i:s');
        $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
        $stmtT = $pdo->prepare("SELECT turno_id, hora_inicio, hora_fin FROM turnos WHERE turno_id IN ({$placeholders})");
        $stmtT->execute($allowedIds);
        $matchedTurno = null;
        foreach ($stmtT->fetchAll() as $tRow) {
            if (!empty($tRow['hora_inicio']) && !empty($tRow['hora_fin'])) {
                if ($nowTime >= $tRow['hora_inicio'] && $nowTime < $tRow['hora_fin']) {
                    $matchedTurno = (int)$tRow['turno_id'];
                    break;
                }
            }
        }
        $turnoId = $matchedTurno ?: $allowedIds[0];
    }
    $stmt = $pdo->prepare('SELECT u.unidad_id,u.clave unidad_clave,u.nombre unidad_nombre,c.consultorio_id,c.clave consultorio_clave,c.nombre_descriptivo consultorio_nombre,t.turno_id,t.clave turno_clave,t.nombre turno_nombre FROM unidades u JOIN unidad_consultorio_turno uct ON u.unidad_id=uct.unidad_id JOIN consultorios c ON c.consultorio_id=uct.consultorio_id JOIN turnos t ON t.turno_id=? WHERE u.unidad_id=? AND c.consultorio_id=? AND uct.activo=1 LIMIT 1');
    $stmt->execute([$turnoId, $unitId, $consultorioId]);
    $context = $stmt->fetch();
    if (!$context) throw new InvalidArgumentException('El contexto de atención no es válido.');
    return $context;
}

function companion_payload(): ?array
{
    if (patient_value('sin_acompanante') === '1') return null;
    $names = normalize_spaces(mb_strtoupper(patient_value('acompanante_nombres'), 'UTF-8'));
    if ($names === '') throw new InvalidArgumentException('Indica el nombre del acompañante o marca SIN ACOMPAÑANTE.');
    $value = static fn(string $key): ?string => ($v = normalize_spaces(mb_strtoupper(patient_value($key), 'UTF-8'))) === '' ? null : $v;
    return ['apellido_paterno' => $value('acompanante_apellido_paterno'), 'apellido_materno' => $value('acompanante_apellido_materno'), 'nombres' => $names, 'fecha_nacimiento' => patient_date('acompanante_fecha_nacimiento'), 'lugar_nacimiento' => $value('acompanante_lugar_nacimiento'), 'sexo' => ($v = patient_value('acompanante_sexo')) === '' ? null : $v, 'parentesco' => $value('acompanante_parentesco'), 'observaciones' => ($v = normalize_multiline(patient_value('acompanante_observaciones'))) === '' ? null : $v];
}

function attention_numeric(string $key, bool $integer = false): mixed
{
    $value = patient_value($key);
    if ($value === '') return null;
    if ($integer && !preg_match('/^\d+$/D', $value)) throw new InvalidArgumentException('El campo ' . str_replace('_', ' ', $key) . ' debe ser entero.');
    if (!$integer && !is_numeric(str_replace(',', '.', $value))) throw new InvalidArgumentException('El campo ' . str_replace('_', ' ', $key) . ' debe ser numérico.');
    return $integer ? (int)$value : (float)str_replace(',', '.', $value);
}

function save_attention(PDO $pdo, array $user): array
{
    // Enforce clinical staff requirement
    if (!(bool)($user['es_personal_clinico'] ?? false)) {
        throw new RuntimeException('Solo el personal clínico puede registrar atenciones.');
    }
    if (!(int)($user['unidad_id'] ?? 0) || !(int)($user['consultorio_id'] ?? 0)) {
        throw new RuntimeException('Tu cuenta no tiene unidad y consultorio asignados. Solicita la configuración a Administración.');
    }
    $patient = scoped_patient($pdo, $user, patient_value('patient_ref'));
    if (!$patient) throw new InvalidArgumentException('Paciente no encontrado o fuera del ámbito autorizado.');
    // Force unit/consultorio from user profile, ignore POST values
    $context = attention_context($pdo, $user, (int)$user['unidad_id'], (int)$user['consultorio_id'], patient_int('turno_id'));
    $companion = companion_payload();
    $now = new DateTimeImmutable('now');
    $date = $now->format('Y-m-d'); $time = $now->format('H:i:s');
    $education = normalize_multiline(patient_value('educacion_salud_texto'));
    $education = $education === '' ? null : $education;
    $topicIds = array_values(array_filter(array_map('intval', is_array($_POST['educacion_tema'] ?? null) ? $_POST['educacion_tema'] : []), static fn(int $id): bool => $id > 0));
    $topics = [];
    if ($topicIds) {
        $marks = implode(',', array_fill(0, count($topicIds), '?'));
        $stmt = $pdo->prepare("SELECT tema_id,nombre FROM temas_educacion_salud WHERE activo=1 AND tema_id IN ({$marks})");
        $stmt->execute($topicIds); $topics = $stmt->fetchAll();
        $topicIds = array_map(static fn(array $row): int => (int)$row['tema_id'], $topics);
    }

    $vitAFecha = patient_date('vitamina_a_fecha');
    $despFecha = patient_date('desparasitacion_fecha');
    $cepFecha = patient_date('cepillado_dental_fecha');

    $requestedAtencionId = (int)patient_value('id_atencion');
    $attentionRef = patient_value('attention_ref');
    $existingAttention = null;
    if ($requestedAtencionId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM atenciones WHERE atencion_id=? AND paciente_id=? AND estado="ACTIVA" LIMIT 1');
        $stmt->execute([$requestedAtencionId, (int)$patient['paciente_id']]);
        $existingAttention = $stmt->fetch() ?: null;
        if (!$existingAttention) throw new InvalidArgumentException('La atención a editar no fue localizada.');
    } elseif ($attentionRef !== '') {
        if (!preg_match('/^[a-f0-9]{32}$/i', $attentionRef)) throw new InvalidArgumentException('Referencia de atención no válida.');
        $stmt = $pdo->prepare('SELECT * FROM atenciones WHERE public_id=? AND paciente_id=? AND estado="ACTIVA" LIMIT 1');
        $stmt->execute([$attentionRef, (int)$patient['paciente_id']]);
        $existingAttention = $stmt->fetch() ?: null;
        if (!$existingAttention) throw new InvalidArgumentException('La atención a editar no fue localizada.');
    }

    if ($existingAttention) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE atenciones SET
                sin_acompanante=?,
                temperatura=?,
                frecuencia_cardiaca=?,
                frecuencia_respiratoria=?,
                peso=?,
                talla=?,
                perimetro_cefalico=?,
                perimetro_toracico=?,
                perimetro_abdominal=?,
                vitamina_a=?,
                vitamina_a_fecha=?,
                desparasitacion=?,
                desparasitacion_fecha=?,
                cepillado_dental=?,
                cepillado_dental_fecha=?,
                agudeza_visual_od=?,
                agudeza_visual_oi=?,
                exploracion_fisica=?,
                nota_enfermeria=?,
                educacion_salud_texto=?,
                observaciones=?,
                companion_snapshot=?,
                datos_clinicos=?
                WHERE atencion_id=?');
            $stmt->execute([
                $companion === null ? 1 : 0,
                attention_numeric('temperatura'),
                attention_numeric('frecuencia_cardiaca', true),
                attention_numeric('frecuencia_respiratoria', true),
                attention_numeric('peso'),
                attention_numeric('talla'),
                attention_numeric('perimetro_cefalico'),
                attention_numeric('perimetro_toracico'),
                attention_numeric('perimetro_abdominal'),
                $vitAFecha ? 1 : 0,
                $vitAFecha,
                $despFecha ? 1 : 0,
                $despFecha,
                $cepFecha ? 1 : 0,
                $cepFecha,
                ($v = normalize_text(patient_value('agudeza_visual_od'))) === '' ? null : $v,
                ($v = normalize_text(patient_value('agudeza_visual_oi'))) === '' ? null : $v,
                ($v = normalize_multiline(patient_value('exploracion_fisica'))) === '' ? null : $v,
                ($v = normalize_multiline(patient_value('nota_enfermeria'))) === '' ? null : $v,
                $education,
                ($v = normalize_multiline(patient_value('observaciones_atencion'))) === '' ? null : $v,
                json_encode($companion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                normalize_multiline(patient_value('nota_enfermeria')) ?: null,
                (int)$existingAttention['atencion_id']
            ]);

            $stmtDel = $pdo->prepare('DELETE FROM acompanantes WHERE atencion_id=?');
            $stmtDel->execute([(int)$existingAttention['atencion_id']]);
            if ($companion !== null) {
                $stmtC = $pdo->prepare('INSERT INTO acompanantes (atencion_id,paciente_id,apellido_paterno,apellido_materno,nombres,fecha_nacimiento,lugar_nacimiento,sexo,parentesco,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmtC->execute([(int)$existingAttention['atencion_id'], (int)$patient['paciente_id'], $companion['apellido_paterno'], $companion['apellido_materno'], $companion['nombres'], $companion['fecha_nacimiento'], $companion['lugar_nacimiento'], $companion['sexo'], $companion['parentesco'], $companion['observaciones']]);
            }

            $stmtDelT = $pdo->prepare('DELETE FROM atencion_educacion_salud WHERE atencion_id=?');
            $stmtDelT->execute([(int)$existingAttention['atencion_id']]);
            if ($topicIds) {
                $stmtT = $pdo->prepare('INSERT INTO atencion_educacion_salud (atencion_id,tema_id,observacion) VALUES (?,?,NULL)');
                foreach ($topicIds as $topicId) $stmtT->execute([(int)$existingAttention['atencion_id'], $topicId]);
            }

            audit($pdo, $user, 'MODIFICACIÓN ATENCIÓN', 'atenciones', (int)$existingAttention['atencion_id'], $existingAttention, ['public_id' => $existingAttention['public_id'], 'paciente_id' => $patient['paciente_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return ['public_id' => $existingAttention['public_id'], 'fecha' => $existingAttention['fecha'], 'hora' => $existingAttention['hora'], 'patient' => present_patient($patient), 'is_edit' => true];
    }

    $patientSnapshot = PatientDomain::snapshot($patient);
    $contextSnapshot = ['usuario_id' => (int)$user['usuario_id'], 'matricula' => $user['matricula'], 'nombre' => $user['nombre'], 'unidad_id' => (int)$context['unidad_id'], 'unidad_clave' => $context['unidad_clave'], 'unidad_nombre' => $context['unidad_nombre'], 'consultorio_id' => (int)$context['consultorio_id'], 'consultorio_clave' => $context['consultorio_clave'], 'consultorio_nombre' => $context['consultorio_nombre'], 'turno_id' => (int)$context['turno_id'], 'turno_clave' => $context['turno_clave'], 'turno_nombre' => $context['turno_nombre']];
    $columns = ['public_id','paciente_id','unidad_id','consultorio_id','turno_id','enfermera_id','sin_acompanante','fecha','hora','temperatura','frecuencia_cardiaca','frecuencia_respiratoria','peso','talla','perimetro_cefalico','perimetro_toracico','perimetro_abdominal','vitamina_a','vitamina_a_fecha','desparasitacion','desparasitacion_fecha','cepillado_dental','cepillado_dental_fecha','agudeza_visual_od','agudeza_visual_oi','exploracion_fisica','nota_enfermeria','educacion_salud_texto','observaciones','patient_snapshot','companion_snapshot','attention_context_snapshot','snapshot_version','edad_anios','edad_meses','edad_dias','datos_clinicos'];
    $values = [
        public_id(),
        (int)$patient['paciente_id'],
        (int)$context['unidad_id'],
        (int)$context['consultorio_id'],
        (int)$context['turno_id'],
        (int)$user['usuario_id'],
        $companion === null ? 1 : 0,
        $date,
        $time,
        attention_numeric('temperatura'),
        attention_numeric('frecuencia_cardiaca', true),
        attention_numeric('frecuencia_respiratoria', true),
        attention_numeric('peso'),
        attention_numeric('talla'),
        attention_numeric('perimetro_cefalico'),
        attention_numeric('perimetro_toracico'),
        attention_numeric('perimetro_abdominal'),
        $vitAFecha ? 1 : 0,
        $vitAFecha,
        $despFecha ? 1 : 0,
        $despFecha,
        $cepFecha ? 1 : 0,
        $cepFecha,
        ($v = normalize_text(patient_value('agudeza_visual_od'))) === '' ? null : $v,
        ($v = normalize_text(patient_value('agudeza_visual_oi'))) === '' ? null : $v,
        ($v = normalize_multiline(patient_value('exploracion_fisica'))) === '' ? null : $v,
        ($v = normalize_multiline(patient_value('nota_enfermeria'))) === '' ? null : $v,
        $education,
        ($v = normalize_multiline(patient_value('observaciones_atencion'))) === '' ? null : $v,
        json_encode($patientSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($companion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($contextSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        1,
        ...PatientDomain::ageParts((string)($patient['fecha_nacimiento'] ?? ''), $date),
        normalize_multiline(patient_value('nota_enfermeria')) ?: null
    ];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO atenciones (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')');
        $stmt->execute($values);
        $attentionId = (int)$pdo->lastInsertId();
        if ($companion !== null) {
            $stmt = $pdo->prepare('INSERT INTO acompanantes (atencion_id,paciente_id,apellido_paterno,apellido_materno,nombres,fecha_nacimiento,lugar_nacimiento,sexo,parentesco,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$attentionId, (int)$patient['paciente_id'], $companion['apellido_paterno'], $companion['apellido_materno'], $companion['nombres'], $companion['fecha_nacimiento'], $companion['lugar_nacimiento'], $companion['sexo'], $companion['parentesco'], $companion['observaciones']]);
        }
        if ($topicIds) {
            $stmt = $pdo->prepare('INSERT INTO atencion_educacion_salud (atencion_id,tema_id,observacion) VALUES (?,?,NULL)');
            foreach ($topicIds as $topicId) $stmt->execute([$attentionId, $topicId]);
        }
        audit($pdo, $user, 'ALTA ATENCIÓN', 'atenciones', $attentionId, null, ['public_id' => $values[0], 'paciente_id' => $patient['paciente_id'], 'unidad_id' => $context['unidad_id'], 'consultorio_id' => $context['consultorio_id'], 'turno_id' => $context['turno_id']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
    return ['public_id' => $values[0], 'fecha' => $date, 'hora' => $time, 'patient' => present_patient($patient), 'is_edit' => false];
}

function sanitize_vaccine_date(?string $raw, string $fieldLabel, bool $required = false): ?string
{
    $value = trim((string)$raw);
    if ($value === '' || $value === '0000-00-00' || str_starts_with($value, '0000-')) {
        if ($required) {
            throw new InvalidArgumentException('La fecha de ' . $fieldLabel . ' es obligatoria y debe tener un formato válido.');
        }
        return null;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m)) {
        throw new InvalidArgumentException('La fecha de ' . $fieldLabel . ' no tiene un formato válido (AAAA-MM-DD).');
    }
    $year = (int)$m[1];
    $month = (int)$m[2];
    $day = (int)$m[3];
    if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
        throw new InvalidArgumentException('La fecha de ' . $fieldLabel . ' no es una fecha de calendario válida.');
    }
    return $value;
}

function clean_db_date(?string $value): ?string
{
    if ($value === null) return null;
    $trimmed = substr(trim($value), 0, 10);
    if ($trimmed === '' || $trimmed === '0000-00-00' || str_starts_with($trimmed, '0000-')) {
        return null;
    }
    return valid_date($trimmed) ? $trimmed : null;
}

function vaccine_state(PDO $pdo, array $user, array $patient): array
{
    $stmt = $pdo->prepare('SELECT v.vacuna_id,v.clave,v.nombre,v.orden_visual, s.slot_id,s.dosis_id,s.slot_codigo,s.orden,d.nombre dosis_nombre,va.public_id aplicacion_public_id,va.fecha_aplicacion,va.lote,va.fecha_caducidad FROM vacunas v JOIN vacuna_slots s ON s.vacuna_id=v.vacuna_id AND s.activo=1 JOIN dosis d ON d.dosis_id=s.dosis_id LEFT JOIN vacunas_aplicadas va ON va.paciente_id=? AND va.vacuna_id=v.vacuna_id AND va.dosis_id=s.dosis_id AND va.estado="ACTIVA" WHERE v.activo=1 ORDER BY v.orden_visual,v.vacuna_id,s.orden');
    $stmt->execute([(int)$patient['paciente_id']]);
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $key = (string)$row['clave'];
        if (!isset($result[$key])) {
            $result[$key] = [
                'clave' => $key,
                'nombre' => $row['nombre'],
                'orden_visual' => (int)$row['orden_visual'],
                'lote' => null,
                'caducidad' => null,
                'slots' => [],
            ];
        }
        $fechaAplicacion = clean_db_date($row['fecha_aplicacion'] ? (string)$row['fecha_aplicacion'] : null);
        $fechaCaducidad = clean_db_date($row['fecha_caducidad'] ? (string)$row['fecha_caducidad'] : null);
        $lote = $row['lote'] !== null && trim((string)$row['lote']) !== '' ? trim((string)$row['lote']) : null;
        $aplicacionPublicId = ($row['aplicacion_public_id'] && $fechaAplicacion !== null) ? (string)$row['aplicacion_public_id'] : null;

        if ($aplicacionPublicId !== null) {
            if ($lote !== null) {
                $result[$key]['lote'] = $lote;
            }
            if ($fechaCaducidad !== null) {
                $result[$key]['caducidad'] = $fechaCaducidad;
            }
        }

        $result[$key]['slots'][] = [
            'codigo' => $row['slot_codigo'],
            'nombre' => $row['dosis_nombre'],
            'orden' => (int)$row['orden'],
            'aplicacion_public_id' => $aplicacionPublicId,
            'fecha_aplicacion' => $fechaAplicacion,
            'lote' => $lote,
            'caducidad' => $fechaCaducidad,
        ];
    }
    foreach ($result as $vKey => $vaccine) {
        $firstPending = true;
        foreach ($vaccine['slots'] as $sIndex => $slot) {
            if ($slot['aplicacion_public_id']) {
                $result[$vKey]['slots'][$sIndex]['habilitado'] = false;
            } else {
                if ($firstPending) {
                    $result[$vKey]['slots'][$sIndex]['habilitado'] = true;
                    $firstPending = false;
                } else {
                    $result[$vKey]['slots'][$sIndex]['habilitado'] = false;
                }
            }
        }
    }
    return array_values($result);
}

/**
 * Registra o actualiza (UPSERT) una dosis de vacuna para un paciente.
 */
function upsert_vaccine_dose(PDO $pdo, array $user, array $patient, array $item, ?int $attentionId = null): array
{
    $vaccineKey = normalize_text((string)($item['vacuna_clave'] ?? ''));
    $doseKey = normalize_text((string)($item['dosis_clave'] ?? ''));
    if ($vaccineKey === '' || $doseKey === '') {
        throw new InvalidArgumentException('Selecciona vacuna y dosis válidas.');
    }

    $stmtSlot = $pdo->prepare(
        'SELECT v.vacuna_id, v.clave vacuna_clave, v.nombre vacuna_nombre, s.dosis_id, s.slot_codigo, d.nombre dosis_nombre, s.orden
         FROM vacunas v
         JOIN vacuna_slots s ON s.vacuna_id = v.vacuna_id AND s.activo = 1
         JOIN dosis d ON d.dosis_id = s.dosis_id
         WHERE v.clave = ? AND s.slot_codigo = ? AND v.activo = 1
         LIMIT 1'
    );
    $stmtSlot->execute([$vaccineKey, $doseKey]);
    $slot = $stmtSlot->fetch();
    if (!$slot) {
        throw new InvalidArgumentException("La dosis {$doseKey} no pertenece al esquema configurado de {$vaccineKey}.");
    }

    $label = $slot['vacuna_nombre'] . ' (' . $slot['slot_codigo'] . ')';
    $date = sanitize_vaccine_date((string)($item['fecha_aplicacion'] ?? ''), 'aplicación de ' . $label, true);

    $todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
    if ($date > $todayStr) {
        throw new InvalidArgumentException("La fecha de aplicación de {$label} no puede ser futura.");
    }

    $lotRaw = normalize_text((string)($item['lote'] ?? ''));
    $lot = $lotRaw === '' ? null : mb_substr($lotRaw, 0, 80, 'UTF-8');

    $cadRaw = (string)($item['caducidad'] ?? $item['fecha_caducidad'] ?? '');
    $cad = sanitize_vaccine_date($cadRaw, 'caducidad de ' . $slot['vacuna_nombre'], false);

    $obsRaw = normalize_multiline((string)($item['observaciones'] ?? ''));
    $obs = $obsRaw === '' ? null : $obsRaw;

    // Verificar si ya existe registro previo para este paciente, vacuna y dosis
    $stmtEx = $pdo->prepare('SELECT * FROM vacunas_aplicadas WHERE paciente_id = ? AND vacuna_id = ? AND dosis_id = ? LIMIT 1');
    $stmtEx->execute([(int)$patient['paciente_id'], (int)$slot['vacuna_id'], (int)$slot['dosis_id']]);
    $existing = $stmtEx->fetch() ?: null;

    // Si es un registro nuevo, validar que las dosis previas del esquema estén registradas
    if (!$existing) {
        $stmtPrev = $pdo->prepare(
            'SELECT s.slot_codigo
             FROM vacuna_slots s
             WHERE s.vacuna_id = ? AND s.activo = 1 AND s.orden < ?
               AND NOT EXISTS (
                   SELECT 1 FROM vacunas_aplicadas va
                   WHERE va.paciente_id = ? AND va.vacuna_id = s.vacuna_id AND va.dosis_id = s.dosis_id AND va.estado = "ACTIVA"
               )
             ORDER BY s.orden
             LIMIT 1'
        );
        $stmtPrev->execute([(int)$slot['vacuna_id'], (int)$slot['orden'], (int)$patient['paciente_id']]);
        if ($previous = $stmtPrev->fetch()) {
            throw new InvalidArgumentException('Para ' . $slot['vacuna_nombre'] . ', primero registra la dosis anterior (' . $previous['slot_codigo'] . ').');
        }
    }

    $public = $existing ? (string)$existing['public_id'] : public_id();

    $stmtUpsert = $pdo->prepare(
        'INSERT INTO vacunas_aplicadas (
            public_id, vacuna_id, dosis_id, paciente_id, atencion_id, vacuna,
            fecha_aplicacion, lote, fecha_caducidad, enfermera_id, estado, observaciones
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "ACTIVA", ?)
         ON DUPLICATE KEY UPDATE
            vacuna = VALUES(vacuna),
            fecha_aplicacion = VALUES(fecha_aplicacion),
            lote = VALUES(lote),
            fecha_caducidad = VALUES(fecha_caducidad),
            enfermera_id = VALUES(enfermera_id),
            estado = "ACTIVA",
            observaciones = COALESCE(VALUES(observaciones), observaciones),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmtUpsert->execute([
        $public,
        (int)$slot['vacuna_id'],
        (int)$slot['dosis_id'],
        (int)$patient['paciente_id'],
        $attentionId,
        $slot['vacuna_nombre'],
        $date,
        $lot,
        $cad,
        (int)$user['usuario_id'],
        $obs,
    ]);

    // Sincronizar lote y fecha_caducidad en las dosis activas de la misma vacuna para este paciente
    // de modo que la fila de la vacuna refleje siempre el lote/caducidad vigente editado
    if (array_key_exists('lote', $item) || array_key_exists('caducidad', $item) || array_key_exists('fecha_caducidad', $item)) {
        $stmtSyncMeta = $pdo->prepare(
            'UPDATE vacunas_aplicadas
             SET lote = ?, fecha_caducidad = ?
             WHERE paciente_id = ? AND vacuna_id = ? AND estado = "ACTIVA"'
        );
        $stmtSyncMeta->execute([$lot, $cad, (int)$patient['paciente_id'], (int)$slot['vacuna_id']]);
    }

    $entityId = $existing ? (int)$existing['aplicacion_id'] : (int)$pdo->lastInsertId();
    $afterPayload = [
        'public_id' => $public,
        'paciente_id' => (int)$patient['paciente_id'],
        'vacuna' => $vaccineKey,
        'dosis' => $doseKey,
        'fecha_aplicacion' => $date,
        'lote' => $lot,
        'fecha_caducidad' => $cad,
    ];
    audit(
        $pdo,
        $user,
        $existing ? 'MODIFICACIÓN VACUNACIÓN' : 'ALTA VACUNACIÓN',
        'vacunas_aplicadas',
        $entityId,
        $existing ?: null,
        $afterPayload
    );

    return [
        'public_id' => $public,
        'vacuna_clave' => $vaccineKey,
        'vacuna' => $slot['vacuna_nombre'],
        'dosis_clave' => $doseKey,
        'dosis' => $slot['dosis_nombre'],
        'fecha_aplicacion' => $date,
        'lote' => $lot,
        'caducidad' => $cad,
        'is_update' => (bool)$existing,
    ];
}

function save_vaccine(PDO $pdo, array $user): array
{
    $patient = scoped_patient($pdo, $user, patient_value('patient_ref'));
    if (!$patient) throw new InvalidArgumentException('Paciente no encontrado o fuera del ámbito autorizado.');

    $attentionId = null;
    $attentionRef = patient_value('atencion_ref');
    if ($attentionRef !== '') {
        $attention = fetch_attention_for_user($pdo, $user, $attentionRef, (int)$patient['paciente_id']);
        if (!$attention) throw new InvalidArgumentException('La atención seleccionada no es válida.');
        $attentionId = (int)$attention['atencion_id'];
    }

    // Soportar tanto envío por lote (JSON en 'vaccines' / 'items') como envío individual
    $rawBatch = $_POST['vaccines'] ?? $_POST['items'] ?? null;
    $items = [];
    if (is_string($rawBatch) && trim($rawBatch) !== '') {
        $decoded = json_decode($rawBatch, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('El formato del listado de vacunas no es válido.');
        }
        $items = $decoded;
    } elseif (is_array($rawBatch)) {
        $items = $rawBatch;
    } elseif (patient_value('vacuna_clave') !== '') {
        $items = [[
            'vacuna_clave' => patient_value('vacuna_clave'),
            'dosis_clave' => patient_value('dosis_clave'),
            'fecha_aplicacion' => patient_value('fecha_aplicacion'),
            'lote' => patient_value('lote'),
            'caducidad' => patient_value('caducidad') !== '' ? patient_value('caducidad') : patient_value('fecha_caducidad'),
            'observaciones' => patient_value('observaciones'),
        ]];
    }

    if (!$items) {
        throw new InvalidArgumentException('No se recibieron registros de vacunación para guardar.');
    }

    $pdo->beginTransaction();
    try {
        $saved = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            // Ignorar entradas vacías si vienen en lote sin fecha
            $rawDate = trim((string)($item['fecha_aplicacion'] ?? ''));
            if ($rawDate === '') continue;
            $saved[] = upsert_vaccine_dose($pdo, $user, $patient, $item, $attentionId);
        }
        if (!$saved) {
            throw new InvalidArgumentException('Captura o modifica al menos una fecha válida de vacunación.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    return [
        'saved' => $saved,
        'last' => $saved[0],
        'vaccines' => vaccine_state($pdo, $user, $patient),
    ];
}

function fetch_attention_for_user(PDO $pdo, array $user, string $ref, int $patientId): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/i', $ref)) return null;
    [$scope, $params] = patient_scope($user, 'p', 'a');
    $stmt = $pdo->prepare("SELECT a.* FROM atenciones a JOIN pacientes p ON p.paciente_id=a.paciente_id WHERE a.public_id=? AND a.paciente_id=? AND {$scope} LIMIT 1");
    $stmt->execute(array_merge([$ref, $patientId], $params));
    return $stmt->fetch() ?: null;
}

function attention_history_for_patient(PDO $pdo, array $patient): array
{
    $stmt = $pdo->prepare(
        'SELECT atencion_id AS id, public_id, fecha, hora
         FROM atenciones
         WHERE paciente_id = ? AND estado = "ACTIVA"
         ORDER BY fecha ASC, atencion_id ASC'
    );
    $stmt->execute([(int)$patient['paciente_id']]);
    $history = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $history[] = [
            'id' => (int)$row['id'],
            'public_id' => (string)$row['public_id'],
            'fecha' => (string)$row['fecha'],
            'hora' => (string)($row['hora'] ?? ''),
        ];
    }
    return $history;
}

function calculate_prostate_valoracion(int $score): string
{
    if ($score <= 0) return 'Normal';
    if ($score <= 7) return 'Sintomatología LEVE';
    if ($score <= 19) return 'Sintomatología MODERADA';
    return 'Sintomatología SEVERA';
}

function prostate_questionnaire_state(PDO $pdo, array $patient, ?int $requestedDcId = null): array
{
    $questions = $pdo->query('SELECT id, orden, pregunta, activo FROM cat_preguntas_prostata WHERE activo = 1 ORDER BY orden ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($questions as &$q) {
        $q['id'] = (int)$q['id'];
        $q['orden'] = (int)$q['orden'];
        $q['activo'] = (int)$q['activo'];
    }
    unset($q);

    $stmtHist = $pdo->prepare(
        'SELECT id, fecha, puntaje_total, valoracion
         FROM dc_prostata
         WHERE id_paciente = ?
         ORDER BY fecha ASC, id ASC'
    );
    $stmtHist->execute([(int)$patient['paciente_id']]);
    $history = [];
    foreach ($stmtHist->fetchAll(PDO::FETCH_ASSOC) as $hRow) {
        $pts = (int)$hRow['puntaje_total'];
        $history[] = [
            'id' => (int)$hRow['id'],
            'fecha' => (string)$hRow['fecha'],
            'puntaje_total' => $pts,
            'valoracion' => calculate_prostate_valoracion($pts),
        ];
    }

    if ($requestedDcId !== null && $requestedDcId > 0) {
        $stmtRec = $pdo->prepare(
            'SELECT dcp.*, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                    un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                    c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
             FROM dc_prostata dcp
             LEFT JOIN usuarios u ON u.usuario_id = dcp.id_usuario
             LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
             LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
             WHERE dcp.id = ? AND dcp.id_paciente = ?
             LIMIT 1'
        );
        $stmtRec->execute([$requestedDcId, (int)$patient['paciente_id']]);
    } else {
        $stmtRec = $pdo->prepare(
            'SELECT dcp.*, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                    un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                    c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
             FROM dc_prostata dcp
             LEFT JOIN usuarios u ON u.usuario_id = dcp.id_usuario
             LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
             LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
             WHERE dcp.id_paciente = ?
             ORDER BY dcp.fecha DESC, dcp.id DESC
             LIMIT 1'
        );
        $stmtRec->execute([(int)$patient['paciente_id']]);
    }
    $record = $stmtRec->fetch(PDO::FETCH_ASSOC) ?: null;

    $answers = [];
    if ($record) {
        $record['id'] = (int)$record['id'];
        $record['id_paciente'] = (int)$record['id_paciente'];
        $record['id_usuario'] = (int)$record['id_usuario'];
        $record['puntaje_total'] = (int)$record['puntaje_total'];
        $record['valoracion'] = calculate_prostate_valoracion($record['puntaje_total']);

        $stmtAns = $pdo->prepare('SELECT id_cp, valor FROM dc_prostata_respuestas WHERE id_dc_prostata = ?');
        $stmtAns->execute([$record['id']]);
        foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $rowAns) {
            $answers[(int)$rowAns['id_cp']] = (int)$rowAns['valor'];
        }
    }

    return [
        'questions' => $questions,
        'history' => $history,
        'record' => $record,
        'answers' => $answers,
    ];
}

function save_prostate_questionnaire(PDO $pdo, array $user): array
{
    $ref = patient_value('patient_ref') !== '' ? patient_value('patient_ref') : patient_value('ref');
    $patient = scoped_patient($pdo, $user, $ref);
    if (!$patient) {
        throw new InvalidArgumentException('Paciente no encontrado o fuera del ámbito autorizado.');
    }

    $rawFecha = patient_value('fecha');
    if ($rawFecha === '') {
        $rawFecha = (new DateTimeImmutable('today'))->format('Y-m-d');
    }
    $fecha = sanitize_vaccine_date($rawFecha, 'evaluación de próstata', true);

    $grupoRiesgo = patient_value('grupo_riesgo');
    if (!in_array($grupoRiesgo, ['40_44_AHF', '45_74'], true)) {
        $grupoRiesgo = '45_74';
    }

    $rawAnswers = $_POST['respuestas'] ?? $_POST['answers'] ?? null;
    if (is_string($rawAnswers) && trim($rawAnswers) !== '') {
        $decoded = json_decode($rawAnswers, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('El formato de las respuestas del cuestionario no es válido.');
        }
        $rawAnswers = $decoded;
    }
    if (!is_array($rawAnswers)) {
        throw new InvalidArgumentException('Debes responder las 7 preguntas del cuestionario.');
    }

    $questions = $pdo->query('SELECT id, orden FROM cat_preguntas_prostata WHERE activo = 1 ORDER BY orden ASC')->fetchAll(PDO::FETCH_ASSOC);
    if (!$questions) {
        throw new RuntimeException('El catálogo de preguntas de próstata no está disponible.');
    }

    $validatedAnswers = [];
    $puntajeTotal = 0;
    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $qOrden = (int)$q['orden'];
        $valRaw = $rawAnswers[$qId] ?? $rawAnswers[(string)$qId] ?? null;
        if ($valRaw === null || $valRaw === '' || !is_numeric($valRaw)) {
            throw new InvalidArgumentException("Falta seleccionar una respuesta para la pregunta #{$qOrden}.");
        }
        $val = (int)$valRaw;
        if ($val < 0 || $val > 5) {
            throw new InvalidArgumentException("El valor seleccionado en la pregunta #{$qOrden} debe estar entre 0 y 5.");
        }
        $validatedAnswers[$qId] = $val;
        $puntajeTotal += $val;
    }

    $valoracion = calculate_prostate_valoracion($puntajeTotal);

    $existing = null;
    $requestedDcId = (int)patient_value('id_dc_prostata');
    if ($requestedDcId > 0) {
        $stmtCheck = $pdo->prepare('SELECT * FROM dc_prostata WHERE id = ? AND id_paciente = ? LIMIT 1');
        $stmtCheck->execute([$requestedDcId, (int)$patient['paciente_id']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$existing) {
            throw new InvalidArgumentException('El registro de próstata a editar no fue localizado.');
        }
    }

    $pdo->beginTransaction();
    try {
        if ($existing) {
            $dcId = (int)$existing['id'];
            $stmtUp = $pdo->prepare(
                'UPDATE dc_prostata
                 SET id_usuario = ?, fecha = ?, grupo_riesgo = ?, puntaje_total = ?, valoracion = ?, f_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = ? AND id_paciente = ?'
            );
            $stmtUp->execute([
                (int)$user['usuario_id'],
                $fecha,
                $grupoRiesgo,
                $puntajeTotal,
                $valoracion,
                $dcId,
                (int)$patient['paciente_id'],
            ]);
        } else {
            $stmtIns = $pdo->prepare(
                'INSERT INTO dc_prostata (id_paciente, id_usuario, fecha, grupo_riesgo, puntaje_total, valoracion)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmtIns->execute([
                (int)$patient['paciente_id'],
                (int)$user['usuario_id'],
                $fecha,
                $grupoRiesgo,
                $puntajeTotal,
                $valoracion,
            ]);
            $dcId = (int)$pdo->lastInsertId();
        }

        $stmtAnsUpsert = $pdo->prepare(
            'INSERT INTO dc_prostata_respuestas (id_dc_prostata, id_cp, valor)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        foreach ($validatedAnswers as $idCp => $valor) {
            $stmtAnsUpsert->execute([$dcId, (int)$idCp, (int)$valor]);
        }

        $afterPayload = [
            'id' => $dcId,
            'id_paciente' => (int)$patient['paciente_id'],
            'id_usuario' => (int)$user['usuario_id'],
            'fecha' => $fecha,
            'grupo_riesgo' => $grupoRiesgo,
            'puntaje_total' => $puntajeTotal,
            'valoracion' => $valoracion,
            'respuestas' => $validatedAnswers,
        ];
        audit(
            $pdo,
            $user,
            $existing ? 'MODIFICACIÓN CUESTIONARIO PRÓSTATA' : 'ALTA CUESTIONARIO PRÓSTATA',
            'dc_prostata',
            $dcId,
            $existing ?: null,
            $afterPayload
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $state = prostate_questionnaire_state($pdo, $patient, $dcId);
    return [
        'patient' => present_patient($patient),
        'questions' => $state['questions'],
        'history' => $state['history'],
        'record' => $state['record'],
        'answers' => $state['answers'],
        'is_update' => (bool)$existing,
    ];
}

function evaluate_mental_health_blocks(int $puntajeAnsiedad, int $puntajeDepresion, int $ataquePanico): array
{
    $interpretaciones = [];
    $clavesUp = [];
    $sitioEnvio = '';

    // Bloque 1 (Preguntas 1 y 2): Suma > 3 (4, 5 o 6)
    $positivoAnsiedad = ($puntajeAnsiedad > 3);
    if ($positivoAnsiedad) {
        $interpretaciones[] = 'PB TRASTORNO DE ANSIEDAD';
        $clavesUp[] = 'UP307';
        $sitioEnvio = 'ENVIO MF';
    }

    // Bloque 2 (Preguntas 3 y 4): Suma >= 3 (3, 4, 5 o 6)
    $positivoDepresion = ($puntajeDepresion >= 3);
    if ($positivoDepresion) {
        $interpretaciones[] = 'PB TRASTORNO DEPRESIVO';
        $clavesUp[] = 'UP306';
        $sitioEnvio = 'ENVIO MF';
    }

    // Bloque 3 (Pregunta 5 - PHQ-TP): Respuesta == 1 (SI)
    $positivoPanico = ($ataquePanico === 1);
    if ($positivoPanico) {
        $interpretaciones[] = 'PB TRASTORNO DE PANICO, MIEDO';
        $clavesUp[] = 'UP308';
        $sitioEnvio = 'ENVIO MF';
    }

    if (!$interpretaciones) {
        $valoracion = 'SIN RIESGO DETECTADO / NORMAL';
        $claveUpStr = '';
    } else {
        $valoracion = implode(' / ', $interpretaciones);
        $claveUpStr = implode(', ', $clavesUp);
    }

    return [
        'positivo_ansiedad' => $positivoAnsiedad,
        'positivo_depresion' => $positivoDepresion,
        'positivo_panico' => $positivoPanico,
        'valoracion' => $valoracion,
        'clave_up' => $claveUpStr,
        'sitio_envio' => $sitioEnvio,
    ];
}

function mental_health_questionnaire_state(PDO $pdo, array $patient, ?int $requestedDcId = null): array
{
    $questions = $pdo->query('SELECT id, orden, seccion, pregunta, activo FROM cat_preguntas_salud_mental WHERE activo = 1 ORDER BY orden ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($questions as &$q) {
        $q['id'] = (int)$q['id'];
        $q['orden'] = (int)$q['orden'];
        $q['activo'] = (int)$q['activo'];
    }
    unset($q);

    $stmtHist = $pdo->prepare(
        'SELECT id, fecha, puntaje_phq4, puntaje_ansiedad, puntaje_depresion, ataque_panico, valoracion, clave_up, sitio_envio
         FROM dc_salud_mental
         WHERE id_paciente = ?
         ORDER BY fecha ASC, id ASC'
    );
    $stmtHist->execute([(int)$patient['paciente_id']]);
    $history = [];
    foreach ($stmtHist->fetchAll(PDO::FETCH_ASSOC) as $hRow) {
        $pAns = (int)($hRow['puntaje_ansiedad'] ?? 0);
        $pDep = (int)($hRow['puntaje_depresion'] ?? 0);
        $pPan = (int)($hRow['ataque_panico'] ?? 0);
        $eval = evaluate_mental_health_blocks($pAns, $pDep, $pPan);
        $history[] = [
            'id' => (int)$hRow['id'],
            'fecha' => (string)$hRow['fecha'],
            'puntaje_phq4' => (int)$hRow['puntaje_phq4'],
            'puntaje_ansiedad' => $pAns,
            'puntaje_depresion' => $pDep,
            'ataque_panico' => $pPan,
            'valoracion' => (string)($hRow['valoracion'] ?: $eval['valoracion']),
            'clave_up' => (string)($hRow['clave_up'] ?? $eval['clave_up']),
            'sitio_envio' => (string)($hRow['sitio_envio'] ?? $eval['sitio_envio']),
        ];
    }

    if ($requestedDcId !== null && $requestedDcId > 0) {
        $stmtRec = $pdo->prepare(
            'SELECT dcsm.*, u.prefijo AS enfermera_prefijo, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                    un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                    c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
             FROM dc_salud_mental dcsm
             LEFT JOIN usuarios u ON u.usuario_id = dcsm.id_usuario
             LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
             LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
             WHERE dcsm.id = ? AND dcsm.id_paciente = ?
             LIMIT 1'
        );
        $stmtRec->execute([$requestedDcId, (int)$patient['paciente_id']]);
    } else {
        $stmtRec = $pdo->prepare(
            'SELECT dcsm.*, u.prefijo AS enfermera_prefijo, u.nombre AS enfermera_nombre, u.matricula AS enfermera_matricula,
                    un.clave AS unidad_clave, un.nombre AS unidad_nombre,
                    c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
             FROM dc_salud_mental dcsm
             LEFT JOIN usuarios u ON u.usuario_id = dcsm.id_usuario
             LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
             LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id
             WHERE dcsm.id_paciente = ?
             ORDER BY dcsm.fecha DESC, dcsm.id DESC
             LIMIT 1'
        );
        $stmtRec->execute([(int)$patient['paciente_id']]);
    }
    $record = $stmtRec->fetch(PDO::FETCH_ASSOC) ?: null;

    $answers = [];
    if ($record) {
        $record['id'] = (int)$record['id'];
        $record['id_paciente'] = (int)$record['id_paciente'];
        $record['id_usuario'] = (int)$record['id_usuario'];
        $record['puntaje_phq4'] = (int)$record['puntaje_phq4'];
        $record['puntaje_ansiedad'] = (int)($record['puntaje_ansiedad'] ?? 0);
        $record['puntaje_depresion'] = (int)($record['puntaje_depresion'] ?? 0);
        $record['ataque_panico'] = (int)$record['ataque_panico'];

        $stmtAns = $pdo->prepare('SELECT id_pregunta, valor FROM dc_salud_mental_respuestas WHERE id_dc_sm = ?');
        $stmtAns->execute([$record['id']]);
        foreach ($stmtAns->fetchAll(PDO::FETCH_ASSOC) as $rowAns) {
            $answers[(int)$rowAns['id_pregunta']] = (int)$rowAns['valor'];
        }

        // Recalcular desglose desde respuestas reales por consistencia
        $calcAns = 0;
        $calcDep = 0;
        $calcPan = $record['ataque_panico'];
        foreach ($questions as $q) {
            $qId = (int)$q['id'];
            $ord = (int)$q['orden'];
            $v = $answers[$qId] ?? null;
            if ($v !== null) {
                if ($ord === 1 || $ord === 2) $calcAns += (int)$v;
                elseif ($ord === 3 || $ord === 4) $calcDep += (int)$v;
                elseif ($ord === 5) $calcPan = (int)$v;
            }
        }
        $eval = evaluate_mental_health_blocks($calcAns, $calcDep, $calcPan);
        $record['puntaje_ansiedad'] = $calcAns;
        $record['puntaje_depresion'] = $calcDep;
        $record['ataque_panico'] = $calcPan;
        $record['valoracion'] = $eval['valoracion'];
        $record['clave_up'] = $eval['clave_up'];
        $record['sitio_envio'] = $eval['sitio_envio'];
    }

    return [
        'questions' => $questions,
        'history' => $history,
        'record' => $record,
        'answers' => $answers,
    ];
}

function save_mental_health_questionnaire(PDO $pdo, array $user): array
{
    $ref = patient_value('patient_ref') !== '' ? patient_value('patient_ref') : patient_value('ref');
    $patient = scoped_patient($pdo, $user, $ref);
    if (!$patient) {
        throw new InvalidArgumentException('Paciente no encontrado o fuera del ámbito autorizado.');
    }

    $rawFecha = patient_value('fecha');
    if ($rawFecha === '') {
        $rawFecha = (new DateTimeImmutable('today'))->format('Y-m-d');
    }
    $fecha = sanitize_vaccine_date($rawFecha, 'evaluación de salud mental', true);

    $rawAnswers = $_POST['respuestas'] ?? $_POST['answers'] ?? null;
    if (is_string($rawAnswers) && trim($rawAnswers) !== '') {
        $decoded = json_decode($rawAnswers, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('El formato de las respuestas del cuestionario no es válido.');
        }
        $rawAnswers = $decoded;
    }
    if (!is_array($rawAnswers)) {
        throw new InvalidArgumentException('Debes responder las 5 preguntas del cuestionario.');
    }

    $questions = $pdo->query('SELECT id, orden, seccion FROM cat_preguntas_salud_mental WHERE activo = 1 ORDER BY orden ASC')->fetchAll(PDO::FETCH_ASSOC);
    if (!$questions) {
        throw new RuntimeException('El catálogo de preguntas de salud mental no está disponible.');
    }

    $validatedAnswers = [];
    $puntajePhq4 = 0;
    $puntajeAnsiedad = 0;
    $puntajeDepresion = 0;
    $ataquePanico = 0;

    foreach ($questions as $q) {
        $qId = (int)$q['id'];
        $qOrden = (int)$q['orden'];
        $seccion = (string)$q['seccion'];
        $valRaw = $rawAnswers[$qId] ?? $rawAnswers[(string)$qId] ?? null;
        if ($valRaw === null || $valRaw === '' || !is_numeric($valRaw)) {
            throw new InvalidArgumentException("Falta seleccionar una respuesta para la pregunta #{$qOrden}.");
        }
        $val = (int)$valRaw;
        if ($seccion === 'PHQTP' || $qOrden === 5) {
            if ($val !== 0 && $val !== 1) {
                throw new InvalidArgumentException("La respuesta de la pregunta #{$qOrden} debe ser Sí (1) o No (0).");
            }
            $ataquePanico = $val;
        } else {
            if ($val < 0 || $val > 3) {
                throw new InvalidArgumentException("El valor seleccionado en la pregunta #{$qOrden} debe estar entre 0 y 3.");
            }
            $puntajePhq4 += $val;
            if ($qOrden === 1 || $qOrden === 2) {
                $puntajeAnsiedad += $val;
            } elseif ($qOrden === 3 || $qOrden === 4) {
                $puntajeDepresion += $val;
            }
        }
        $validatedAnswers[$qId] = $val;
    }

    $eval = evaluate_mental_health_blocks($puntajeAnsiedad, $puntajeDepresion, $ataquePanico);
    $valoracion = $eval['valoracion'];
    $claveUp = $eval['clave_up'];
    $sitioEnvio = $eval['sitio_envio'];

    $existing = null;
    $requestedDcId = (int)(patient_value('id_dc_salud_mental') ?: patient_value('id_dc_sm'));
    if ($requestedDcId > 0) {
        $stmtCheck = $pdo->prepare('SELECT * FROM dc_salud_mental WHERE id = ? AND id_paciente = ? LIMIT 1');
        $stmtCheck->execute([$requestedDcId, (int)$patient['paciente_id']]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$existing) {
            throw new InvalidArgumentException('El registro de salud mental a editar no fue localizado.');
        }
    }

    $pdo->beginTransaction();
    try {
        if ($existing) {
            $dcId = (int)$existing['id'];
            $stmtUp = $pdo->prepare(
                'UPDATE dc_salud_mental
                 SET id_usuario = ?, fecha = ?, puntaje_phq4 = ?, puntaje_ansiedad = ?, puntaje_depresion = ?, ataque_panico = ?, valoracion = ?, clave_up = ?, sitio_envio = ?, f_actualizacion = CURRENT_TIMESTAMP
                 WHERE id = ? AND id_paciente = ?'
            );
            $stmtUp->execute([
                (int)$user['usuario_id'],
                $fecha,
                $puntajePhq4,
                $puntajeAnsiedad,
                $puntajeDepresion,
                $ataquePanico,
                $valoracion,
                $claveUp,
                $sitioEnvio,
                $dcId,
                (int)$patient['paciente_id'],
            ]);
        } else {
            $stmtIns = $pdo->prepare(
                'INSERT INTO dc_salud_mental (id_paciente, id_usuario, fecha, puntaje_phq4, puntaje_ansiedad, puntaje_depresion, ataque_panico, valoracion, clave_up, sitio_envio)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtIns->execute([
                (int)$patient['paciente_id'],
                (int)$user['usuario_id'],
                $fecha,
                $puntajePhq4,
                $puntajeAnsiedad,
                $puntajeDepresion,
                $ataquePanico,
                $valoracion,
                $claveUp,
                $sitioEnvio,
            ]);
            $dcId = (int)$pdo->lastInsertId();
        }

        $stmtAnsUpsert = $pdo->prepare(
            'INSERT INTO dc_salud_mental_respuestas (id_dc_sm, id_pregunta, valor)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        foreach ($validatedAnswers as $idPregunta => $valor) {
            $stmtAnsUpsert->execute([$dcId, (int)$idPregunta, (int)$valor]);
        }

        $afterPayload = [
            'id' => $dcId,
            'id_paciente' => (int)$patient['paciente_id'],
            'id_usuario' => (int)$user['usuario_id'],
            'fecha' => $fecha,
            'puntaje_phq4' => $puntajePhq4,
            'puntaje_ansiedad' => $puntajeAnsiedad,
            'puntaje_depresion' => $puntajeDepresion,
            'ataque_panico' => $ataquePanico,
            'valoracion' => $valoracion,
            'clave_up' => $claveUp,
            'sitio_envio' => $sitioEnvio,
            'respuestas' => $validatedAnswers,
        ];
        audit(
            $pdo,
            $user,
            $existing ? 'MODIFICACIÓN CUESTIONARIO SALUD MENTAL' : 'ALTA CUESTIONARIO SALUD MENTAL',
            'dc_salud_mental',
            $dcId,
            $existing ?: null,
            $afterPayload
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }

    $state = mental_health_questionnaire_state($pdo, $patient, $dcId);
    return [
        'patient' => present_patient($patient),
        'questions' => $state['questions'],
        'history' => $state['history'],
        'record' => $state['record'],
        'answers' => $state['answers'],
        'is_update' => (bool)$existing,
    ];
}

try {
    $pdo = db(); $user = patient_user($pdo); $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($action === 'context') patient_json(['ok' => true, 'context' => context_data($pdo, $user)]);
        if ($action === 'patient') { $patient = scoped_patient($pdo, $user, (string)($_GET['ref'] ?? '')); if (!$patient) patient_json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404); patient_json(['ok' => true, 'patient' => present_patient($patient)]); }
        if ($action === 'vaccines') { $patient = scoped_patient($pdo, $user, (string)($_GET['ref'] ?? '')); if (!$patient) patient_json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404); patient_json(['ok' => true, 'patient' => present_patient($patient), 'vaccines' => vaccine_state($pdo, $user, $patient)]); }
        if (in_array($action, ['attention_history', 'get_attention_history'], true)) {
            $ref = (string)($_GET['ref'] ?? $_GET['patient_ref'] ?? '');
            $patient = scoped_patient($pdo, $user, $ref);
            if (!$patient) patient_json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404);
            patient_json([
                'ok' => true,
                'patient' => present_patient($patient),
                'history' => attention_history_for_patient($pdo, $patient),
            ]);
        }
        if ($action === 'get_prostate_questionnaire') {
            $ref = (string)($_GET['ref'] ?? $_GET['patient_ref'] ?? '');
            $patient = scoped_patient($pdo, $user, $ref);
            if (!$patient) patient_json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404);
            $requestedDcId = (int)($_GET['id_dc_prostata'] ?? $_GET['id'] ?? 0);
            $state = prostate_questionnaire_state($pdo, $patient, $requestedDcId > 0 ? $requestedDcId : null);
            patient_json([
                'ok' => true,
                'patient' => present_patient($patient),
                'questions' => $state['questions'],
                'history' => $state['history'],
                'record' => $state['record'],
                'answers' => $state['answers'],
            ]);
        }
        if ($action === 'get_mental_health_questionnaire') {
            $ref = (string)($_GET['ref'] ?? $_GET['patient_ref'] ?? '');
            $patient = scoped_patient($pdo, $user, $ref);
            if (!$patient) patient_json(['ok' => false, 'message' => 'Paciente no encontrado.'], 404);
            $requestedDcId = (int)($_GET['id_dc_salud_mental'] ?? $_GET['id_dc_sm'] ?? $_GET['id'] ?? 0);
            $state = mental_health_questionnaire_state($pdo, $patient, $requestedDcId > 0 ? $requestedDcId : null);
            patient_json([
                'ok' => true,
                'patient' => present_patient($patient),
                'questions' => $state['questions'],
                'history' => $state['history'],
                'record' => $state['record'],
                'answers' => $state['answers'],
            ]);
        }
        if ($action === 'attention') {
            $idAtencion = (int)($_GET['id_atencion'] ?? $_GET['id'] ?? 0);
            $ref = (string)($_GET['ref'] ?? '');
            if ($idAtencion > 0) {
                $stmt = $pdo->prepare('SELECT a.*, p.public_id paciente_public_id, p.nss, p.dv, p.agregado, p.apellido_paterno, p.apellido_materno, p.nombres FROM atenciones a JOIN pacientes p ON p.paciente_id=a.paciente_id WHERE a.atencion_id=? AND a.estado="ACTIVA" LIMIT 1');
                $stmt->execute([$idAtencion]);
            } else {
                if (!preg_match('/^[a-f0-9]{32}$/i', $ref)) patient_json(['ok' => false, 'message' => 'Referencia de atención no válida.'], 404);
                $stmt = $pdo->prepare('SELECT a.*, p.public_id paciente_public_id, p.nss, p.dv, p.agregado, p.apellido_paterno, p.apellido_materno, p.nombres FROM atenciones a JOIN pacientes p ON p.paciente_id=a.paciente_id WHERE a.public_id=? AND a.estado="ACTIVA" LIMIT 1');
                $stmt->execute([$ref]);
            }
            $attention = $stmt->fetch();
            if (!$attention) patient_json(['ok' => false, 'message' => 'Atención no encontrada.'], 404);
            $stmtC = $pdo->prepare('SELECT * FROM acompanantes WHERE atencion_id=? LIMIT 1');
            $stmtC->execute([(int)$attention['atencion_id']]);
            $companion = $stmtC->fetch() ?: null;
            $stmtT = $pdo->prepare('SELECT tema_id FROM atencion_educacion_salud WHERE atencion_id=?');
            $stmtT->execute([(int)$attention['atencion_id']]);
            $topics = array_map('intval', $stmtT->fetchAll(PDO::FETCH_COLUMN));
            $patient = [
                'paciente_id' => (int)$attention['paciente_id'],
                'public_id' => $attention['paciente_public_id'],
                'nss' => $attention['nss'],
                'dv' => $attention['dv'],
                'agregado' => $attention['agregado'],
                'apellido_paterno' => $attention['apellido_paterno'],
                'apellido_materno' => $attention['apellido_materno'],
                'nombres' => $attention['nombres'],
            ];
            patient_json([
                'ok' => true,
                'attention' => $attention,
                'companion' => $companion,
                'topics' => $topics,
                'patient' => present_patient($patient),
                'history' => attention_history_for_patient($pdo, $patient),
            ]);
        }
        if ($action === 'list') {
            $rawDate = trim((string)($_GET['fecha'] ?? ''));
            $rawDateStart = trim((string)($_GET['fecha_inicio'] ?? $_GET['fecha_desde'] ?? ''));
            $rawDateEnd = trim((string)($_GET['fecha_fin'] ?? $_GET['fecha_hasta'] ?? ''));

            $dateStart = ($rawDateStart !== '' && valid_date($rawDateStart))
                ? $rawDateStart
                : (($rawDate !== '' && valid_date($rawDate)) ? $rawDate : '');
            $dateEnd = ($rawDateEnd !== '' && valid_date($rawDateEnd))
                ? $rawDateEnd
                : (($rawDate !== '' && valid_date($rawDate)) ? $rawDate : '');

            if ($dateStart !== '' && $dateEnd === '') $dateEnd = $dateStart;
            if ($dateEnd !== '' && $dateStart === '') $dateStart = $dateEnd;
            if ($dateStart !== '' && $dateEnd !== '' && $dateStart > $dateEnd) {
                [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
            }

            $rawQuery = trim((string)($_GET['q'] ?? ''));
            $query = $rawQuery !== '' ? normalize_text($rawQuery) : '';

            [$scope, $scopeParams] = patient_scope($user, 'p', 'a');
            $where = ['1=1'];
            $params = $scopeParams;

            // Solo agregar condición de fecha si los parámetros vienen presentes, no vacíos y con formato válido
            if ($dateStart !== '' && $dateEnd !== '') {
                $where[] = 'a.fecha BETWEEN ? AND ?';
                $params[] = $dateStart;
                $params[] = $dateEnd;
            }

            // Solo agregar condición de búsqueda por nombre/NSS si tiene al menos 1 carácter válido tras trim()
            if (mb_strlen($query, 'UTF-8') >= 1) {
                $compact = preg_replace('/[\s-]+/', '', $query);
                if (preg_match('/^(\d{10})(?:([0-9]))?([A-Z0-9]{8})?$/', (string)$compact, $m)) {
                    $where[] = 'p.nss=?';
                    $params[] = $m[1];
                    if (isset($m[2]) && $m[2] !== '') {
                        $where[] = 'p.dv=?';
                        $params[] = $m[2];
                    }
                    if (isset($m[3]) && $m[3] !== '') {
                        $where[] = 'p.agregado=?';
                        $params[] = $m[3];
                    }
                } else {
                    $like = '%' . $query . '%';
                    $where[] = '(p.nss LIKE ? OR p.agregado LIKE ? OR p.curp LIKE ? OR p.apellido_paterno LIKE ? OR p.apellido_materno LIKE ? OR p.nombres LIKE ? OR CONCAT_WS(" ",p.apellido_paterno,p.apellido_materno,p.nombres) LIKE ? OR CONCAT_WS(" ",p.nombres,p.apellido_paterno,p.apellido_materno) LIKE ?)';
                    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
                }
            }

            $sql = 'SELECT
                        a.public_id AS atencion_public_id,
                        a.fecha,
                        a.hora,
                        p.paciente_id,
                        p.public_id AS paciente_public_id,
                        p.nss,
                        p.dv,
                        p.agregado,
                        p.curp,
                        p.escolaridad,
                        p.apellido_paterno,
                        p.apellido_materno,
                        p.nombres,
                        p.sexo,
                        c.clave AS consultorio_clave,
                        t.clave AS turno_clave,
                        e.nombre AS enfermera_nombre,
                        (SELECT dcp.id FROM dc_prostata dcp WHERE dcp.id_paciente = p.paciente_id ORDER BY dcp.fecha DESC, dcp.id DESC LIMIT 1) AS prostata_id,
                        (SELECT dcp.puntaje_total FROM dc_prostata dcp WHERE dcp.id_paciente = p.paciente_id ORDER BY dcp.fecha DESC, dcp.id DESC LIMIT 1) AS prostata_puntaje,
                        (SELECT dcp.valoracion FROM dc_prostata dcp WHERE dcp.id_paciente = p.paciente_id ORDER BY dcp.fecha DESC, dcp.id DESC LIMIT 1) AS prostata_valoracion,
                        (SELECT dcsm.id FROM dc_salud_mental dcsm WHERE dcsm.id_paciente = p.paciente_id ORDER BY dcsm.fecha DESC, dcsm.id DESC LIMIT 1) AS salud_mental_id,
                        (SELECT dcsm.puntaje_phq4 FROM dc_salud_mental dcsm WHERE dcsm.id_paciente = p.paciente_id ORDER BY dcsm.fecha DESC, dcsm.id DESC LIMIT 1) AS salud_mental_puntaje,
                        (SELECT dcsm.ataque_panico FROM dc_salud_mental dcsm WHERE dcsm.id_paciente = p.paciente_id ORDER BY dcsm.fecha DESC, dcsm.id DESC LIMIT 1) AS salud_mental_panico,
                        (SELECT dcsm.valoracion FROM dc_salud_mental dcsm WHERE dcsm.id_paciente = p.paciente_id ORDER BY dcsm.fecha DESC, dcsm.id DESC LIMIT 1) AS salud_mental_valoracion
                    FROM pacientes p
                    LEFT JOIN atenciones a ON a.paciente_id = p.paciente_id AND a.estado = "ACTIVA" AND (' . $scope . ')
                    LEFT JOIN consultorios c ON c.consultorio_id = a.consultorio_id
                    LEFT JOIN turnos t ON t.turno_id = a.turno_id
                    LEFT JOIN usuarios e ON e.usuario_id = a.enfermera_id
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY COALESCE(a.fecha, DATE(p.created_at)) DESC, COALESCE(a.hora, TIME(p.created_at)) DESC, p.paciente_id DESC
                    LIMIT 250';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = array_map(static function (array $row): array {
                $hasAttention = !empty($row['atencion_public_id']);
                $row['patient_only'] = !$hasAttention;
                $row['has_prostata'] = !empty($row['prostata_id']);
                $row['has_salud_mental'] = !empty($row['salud_mental_id']);
                $row['nombre_completo'] = normalize_spaces(implode(' ', array_filter([
                    $row['apellido_paterno'],
                    $row['apellido_materno'],
                    $row['nombres'],
                ], static fn($v): bool => trim((string)$v) !== '')));
                $row['identidad'] = trim($row['nss'] . '-' . (string)$row['dv'] . ' ' . $row['agregado']);
                return $row;
            }, $stmt->fetchAll());

            patient_json([
                'ok' => true,
                'rows' => $rows,
                'patient_matches' => [],
                'fecha' => $dateStart,
                'fecha_fin' => $dateEnd,
                'q' => $query,
            ]);
        }
        patient_json(['ok' => false, 'message' => 'Acción no reconocida.'], 404);
    }
    patient_require_post();
    if ($action === 'save_patient') {
        $data = patient_payload(); $ref = patient_value('public_id'); $existing = $ref !== '' ? scoped_patient($pdo, $user, $ref) : null; if ($ref !== '' && !$existing) throw new InvalidArgumentException('Paciente no encontrado.');
        $dupe = $pdo->prepare('SELECT public_id FROM pacientes WHERE nss=? AND agregado=? AND (? = "" OR public_id <> ?) LIMIT 1'); $dupe->execute([$data['nss'], $data['agregado'], $ref, $ref]); if ($duplicate = $dupe->fetch()) patient_json(['ok' => false, 'code' => 'DUPLICATE', 'message' => 'Ya existe un paciente con ese NSS y agregado.', 'public_id' => $duplicate['public_id']], 409);
        $data['unidad_adscripcion_id'] = null;
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE pacientes SET dv=?,unidad_adscripcion_id=?,curp=?,escolaridad=?,ocupacion=?,estado_civil=?,apellido_paterno=?,apellido_materno=?,nombres=?,fecha_nacimiento=?,lugar_nacimiento=?,sexo=?,indigena=?,afromexicano=?,discapacidad=?,pais_residencia=?,entidad_residencia=?,municipio=?,colonia=?,calle=?,numero_exterior=?,numero_interior=?,codigo_postal=?,telefono=?,otro_contacto=?,domicilio=? WHERE paciente_id=?');
            $stmt->execute([$data['dv'], $data['unidad_adscripcion_id'], $data['curp'], $data['escolaridad'], $data['ocupacion'], $data['estado_civil'], $data['apellido_paterno'], $data['apellido_materno'], $data['nombres'], $data['fecha_nacimiento'], $data['lugar_nacimiento'], $data['sexo'], $data['indigena'], $data['afromexicano'], $data['discapacidad'], $data['pais_residencia'], $data['entidad_residencia'], $data['municipio'], $data['colonia'], $data['calle'], $data['numero_exterior'], $data['numero_interior'], $data['codigo_postal'], $data['telefono'], $data['otro_contacto'], $data['domicilio'], (int)$existing['paciente_id']]);
            $after = scoped_patient($pdo, $user, $ref);
            audit($pdo, $user, 'MODIFICACIÓN PACIENTE', 'pacientes', (int)$existing['paciente_id'], $existing, $after);
            $message = 'DATOS ACTUALIZADOS.';
        } else {
            $data['public_id'] = public_id();
            $stmt = $pdo->prepare('INSERT INTO pacientes (public_id,nss,dv,agregado,curp,escolaridad,ocupacion,estado_civil,unidad_adscripcion_id,apellido_paterno,apellido_materno,nombres,fecha_nacimiento,lugar_nacimiento,sexo,indigena,afromexicano,discapacidad,pais_residencia,entidad_residencia,municipio,colonia,calle,numero_exterior,numero_interior,codigo_postal,telefono,otro_contacto,domicilio) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$data['public_id'], $data['nss'], $data['dv'], $data['agregado'], $data['curp'], $data['escolaridad'], $data['ocupacion'], $data['estado_civil'], $data['unidad_adscripcion_id'], $data['apellido_paterno'], $data['apellido_materno'], $data['nombres'], $data['fecha_nacimiento'], $data['lugar_nacimiento'], $data['sexo'], $data['indigena'], $data['afromexicano'], $data['discapacidad'], $data['pais_residencia'], $data['entidad_residencia'], $data['municipio'], $data['colonia'], $data['calle'], $data['numero_exterior'], $data['numero_interior'], $data['codigo_postal'], $data['telefono'], $data['otro_contacto'], $data['domicilio']]);
            $after = scoped_patient($pdo, $user, $data['public_id']);
            audit($pdo, $user, 'ALTA PACIENTE', 'pacientes', (int)$pdo->lastInsertId(), null, $after);
            $message = 'PACIENTE REGISTRADO CORRECTAMENTE.';
        }
        patient_json(['ok'=>true,'message'=>$message,'patient'=>present_patient($after)]);
    }
    if ($action === 'save_attention') patient_json(['ok' => true, 'message' => 'ATENCIÓN REGISTRADA CORRECTAMENTE.', 'attention' => save_attention($pdo, $user)]);
    if (in_array($action, ['save_vaccine', 'save_vaccines', 'update_vaccine'], true)) {
        $res = save_vaccine($pdo, $user);
        patient_json([
            'ok' => true,
            'message' => 'ESQUEMA DE VACUNACIÓN ACTUALIZADO CORRECTAMENTE.',
            'vaccine' => $res['last'],
            'saved' => $res['saved'],
            'vaccines' => $res['vaccines'],
        ]);
    }
    if ($action === 'save_prostate_questionnaire') {
        $res = save_prostate_questionnaire($pdo, $user);
        patient_json([
            'ok' => true,
            'message' => $res['is_update'] ? 'CUESTIONARIO DE PRÓSTATA ACTUALIZADO CORRECTAMENTE.' : 'CUESTIONARIO DE PRÓSTATA REGISTRADO CORRECTAMENTE.',
            'patient' => $res['patient'],
            'questions' => $res['questions'],
            'history' => $res['history'],
            'record' => $res['record'],
            'answers' => $res['answers'],
        ]);
    }
    if ($action === 'save_mental_health_questionnaire') {
        $res = save_mental_health_questionnaire($pdo, $user);
        patient_json([
            'ok' => true,
            'message' => $res['is_update'] ? 'CUESTIONARIO DE SALUD MENTAL ACTUALIZADO CORRECTAMENTE.' : 'CUESTIONARIO DE SALUD MENTAL REGISTRADO CORRECTAMENTE.',
            'patient' => $res['patient'],
            'questions' => $res['questions'],
            'history' => $res['history'],
            'record' => $res['record'],
            'answers' => $res['answers'],
        ]);
    }
    patient_json(['ok' => false, 'message' => 'Acción no reconocida.'], 404);
} catch (Throwable $exception) {
    error_log('SRAE patient API error: ' . $exception->getMessage());
    $status = $exception instanceof InvalidArgumentException ? 422 : 500;
    patient_json(['ok' => false, 'message' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'No fue posible completar la operación.'], $status);
}





