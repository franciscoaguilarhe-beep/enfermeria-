<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

function config_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function config_user(PDO $pdo): array
{
    $admin = Auth::requireRole('ADMINISTRADOR');
    $stmt = $pdo->prepare('SELECT u.*, r.clave rol_clave FROM usuarios u LEFT JOIN roles r ON r.rol_id=u.rol_id WHERE u.usuario_id=? LIMIT 1');
    $stmt->execute([(int)$admin['usuario_id']]);
    $user = $stmt->fetch();
    if (!$user || !(bool)$user['activo']) {
        Auth::logout();
        config_json(['ok' => false, 'message' => 'Sesión no válida o inactiva.'], 401);
    }
    return $user;
}

function config_require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        config_json(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
    Security::verifyCsrf();
}

function unit_sort_number(string $siglas, string $nombre): int
{
    if (preg_match('/(?:N[°º\.\s]*|#\s*)?(\d+)/i', $siglas, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/(?:N[°º\.\s]*|#\s*)?(\d+)/i', $nombre, $m)) {
        return (int)$m[1];
    }
    return 999999;
}

try {
    $pdo = db();
    $adminUser = config_user($pdo);
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    // =========================================================================
    // PETICIONES GET
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 1. Catálogos base (Roles, Unidades, Turnos, Consultorios)
        if ($action === 'catalogs') {
            $roles = $pdo->query('SELECT rol_id, clave, nombre FROM roles WHERE activo=1 ORDER BY rol_id')->fetchAll();
            $turnos = $pdo->query('SELECT turno_id, clave, nombre FROM turnos WHERE activo=1 ORDER BY turno_id')->fetchAll();
            $rawUnits = $pdo->query('SELECT unidad_id, clave, nombre, activo FROM unidades ORDER BY activo DESC, nombre')->fetchAll();
            $consultorios = $pdo->query('SELECT DISTINCT c.consultorio_id, uct.unidad_id, c.clave, c.nombre_descriptivo FROM consultorios c JOIN unidad_consultorio_turno uct ON c.consultorio_id = uct.consultorio_id WHERE c.activo=1 AND uct.activo=1 ORDER BY uct.unidad_id, c.consultorio_id')->fetchAll();

            usort($rawUnits, static fn($a, $b) => unit_sort_number((string)$a['clave'], (string)$a['nombre']) <=> unit_sort_number((string)$b['clave'], (string)$b['nombre']));

            config_json([
                'ok' => true,
                'roles' => $roles,
                'turnos' => $turnos,
                'unidades' => $rawUnits,
                'consultorios' => $consultorios
            ]);
        }

        // 2. Listado de Usuarios (con búsqueda por nombre o matrícula)
        if ($action === 'users_list') {
            $q = normalize_spaces((string)($_GET['q'] ?? ''));
            $sql = 'SELECT u.usuario_id, u.matricula, COALESCE(NULLIF(TRIM(u.prefijo), \'\'), \'L.E.\') AS prefijo, u.nombre, u.activo, u.rol_id, u.unidad_id, u.consultorio_id,
                           r.nombre AS rol_nombre, r.clave AS rol_clave,
                           un.clave AS unidad_siglas, un.nombre AS unidad_nombre,
                           c.clave AS consultorio_clave, c.nombre_descriptivo AS consultorio_nombre
                    FROM usuarios u
                    LEFT JOIN roles r ON r.rol_id = u.rol_id
                    LEFT JOIN unidades un ON un.unidad_id = u.unidad_id
                    LEFT JOIN consultorios c ON c.consultorio_id = u.consultorio_id';

            $params = [];
            if ($q !== '') {
                $sql .= ' WHERE u.matricula LIKE ? OR u.nombre LIKE ?';
                $like = '%' . $q . '%';
                $params = [$like, $like];
            }
            $sql .= ' ORDER BY u.activo DESC, u.nombre ASC LIMIT 200';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();

            foreach ($users as &$u) {
                $u['consultorio_turno_label'] = trim((string)($u['consultorio_clave'] ?? '') . '-' . (string)($u['consultorio_nombre'] ?? ''), '-');
                if ($u['consultorio_turno_label'] === '') {
                    $u['consultorio_turno_label'] = 'SIN ASIGNAR';
                }
            }
            unset($u);

            config_json(['ok' => true, 'users' => $users]);
        }

        // 3. Listado de Unidades ordenadas por número
        if ($action === 'units_list') {
            $rawUnits = $pdo->query('SELECT unidad_id, clave, nombre, activo FROM unidades ORDER BY activo DESC, nombre')->fetchAll();
            usort($rawUnits, static function ($a, $b) {
                return unit_sort_number((string)$a['clave'], (string)$a['nombre']) <=> unit_sort_number((string)$b['clave'], (string)$b['nombre']);
            });

            config_json(['ok' => true, 'units' => $rawUnits]);
        }

        // 4. Listado de Consultorios por Unidad seleccionada
        if ($action === 'consultorios_by_unit') {
            $unitId = (int)($_GET['unidad_id'] ?? 0);
            if ($unitId < 1) {
                config_json(['ok' => false, 'message' => 'Unidad no vǭlida.'], 422);
            }

            // Consultar consultorios y turnos para la matriz
            $sql = 'SELECT c.consultorio_id, c.clave AS consultorio_clave, c.nombre_descriptivo, c.activo AS consultorio_activo,
                           t.turno_id, t.clave AS turno_clave, t.nombre AS turno_nombre,
                           m.id AS matriz_id, COALESCE(m.activo, 0) AS matriz_activo
                    FROM consultorios c
                    JOIN turnos t ON t.activo = 1
                    LEFT JOIN unidad_consultorio_turno m ON m.consultorio_id = c.consultorio_id AND m.turno_id = t.turno_id AND m.unidad_id = ?
                    WHERE c.activo = 1
                    ORDER BY c.consultorio_id, t.turno_id';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$unitId]);
            $rows = $stmt->fetchAll();

            $pills = [];
            foreach ($rows as $r) {
                // Clave amigable como 1-M, 2-M, 1-V, etc.
                $cNum = preg_replace('/[^\d]/', '', (string)$r['consultorio_clave']) ?: $r['consultorio_clave'];
                $tLetter = mb_substr((string)$r['turno_clave'], 0, 1, 'UTF-8');
                $label = $cNum . '-' . $tLetter;

                $pills[] = [
                    'matriz_id' => $r['matriz_id'] ? (int)$r['matriz_id'] : null,
                    'consultorio_id' => (int)$r['consultorio_id'],
                    'turno_id' => (int)$r['turno_id'],
                    'label' => $label,
                    'consultorio_nombre' => $r['nombre_descriptivo'],
                    'turno_nombre' => $r['turno_nombre'],
                    'activo' => (bool)$r['matriz_activo']
                ];
            }

            config_json(['ok' => true, 'consultorios' => $pills]);
        }

        config_json(['ok' => false, 'message' => 'Acción GET no reconocida.'], 404);
    }

    // =========================================================================
    // PETICIONES POST
    // =========================================================================
    config_require_post();

    // 1. Toggle Estado de Usuario (Activo/Inactivo Switch)
    if ($action === 'user_toggle') {
        $userId = (int)($_POST['usuario_id'] ?? 0);
        if ($userId < 1) config_json(['ok' => false, 'message' => 'Usuario no válido.'], 422);

        $stmt = $pdo->prepare('SELECT usuario_id, matricula, activo FROM usuarios WHERE usuario_id=? LIMIT 1');
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if (!$u) config_json(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);

        if ($userId === (int)$adminUser['usuario_id']) {
            config_json(['ok' => false, 'message' => 'No puedes desactivar tu propia cuenta.'], 403);
        }

        $newStatus = $u['activo'] ? 0 : 1;
        $pdo->prepare('UPDATE usuarios SET activo=? WHERE usuario_id=?')->execute([$newStatus, $userId]);
        audit($pdo, $adminUser, 'TOGGLE USUARIO', 'usuarios', $userId, ['activo' => $u['activo']], ['activo' => $newStatus]);

        config_json([
            'ok' => true,
            'message' => 'Estado actualizado.',
            'activo' => $newStatus
        ]);
    }

    // 2. Guardar / Editar Usuario
    if ($action === 'user_save') {
        $userId = (int)($_POST['usuario_id'] ?? 0);
        $matricula = mb_strtoupper(trim((string)($_POST['matricula'] ?? '')), 'UTF-8');
        $prefijo = normalize_spaces(mb_strtoupper(trim((string)($_POST['prefijo'] ?? 'L.E.')), 'UTF-8'));
        if ($prefijo === '') {
            $prefijo = 'L.E.';
        }

        // Permitir captura separada de nombres o unificada
        $paterno = normalize_spaces((string)($_POST['apellido_paterno'] ?? ''));
        $materno = normalize_spaces((string)($_POST['apellido_materno'] ?? ''));
        $nombres = normalize_spaces((string)($_POST['nombres'] ?? ''));

        if ($nombres !== '') {
            $nombreCompleto = normalize_spaces(trim("{$paterno} {$materno} {$nombres}"));
        } else {
            $nombreCompleto = normalize_spaces((string)($_POST['nombre'] ?? ''));
        }

        $unitId = (int)($_POST['unidad_id'] ?? 0);
        $consultId = (int)($_POST['consultorio_id'] ?? 0);
        $rolId = (int)($_POST['rol_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($matricula === '' || $nombreCompleto === '') {
            config_json(['ok' => false, 'message' => 'Matrícula y nombre son obligatorios.'], 422);
        }
        if ($rolId < 1) {
            config_json(['ok' => false, 'message' => 'Selecciona un rol válido.'], 422);
        }

        // Validación de contraseña reducida a mínimo 8 caracteres
        if ($userId === 0) {
            if (strlen($password) < 8) {
                config_json(['ok' => false, 'message' => 'La contraseña inicial debe tener al menos 8 caracteres.'], 422);
            }
            if ($passwordConfirm !== '' && !hash_equals($password, $passwordConfirm)) {
                config_json(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
            }
        } else if ($password !== '') {
            if (strlen($password) < 8) {
                config_json(['ok' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
            }
            if ($passwordConfirm !== '' && !hash_equals($password, $passwordConfirm)) {
                config_json(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
            }
        }

        // Validar unicidad de matrícula
        $dupe = $pdo->prepare('SELECT usuario_id FROM usuarios WHERE matricula=? AND usuario_id<>? LIMIT 1');
        $dupe->execute([$matricula, $userId]);
        if ($dupe->fetch()) {
            config_json(['ok' => false, 'message' => 'Ya existe un usuario con esa matrícula.'], 409);
        }

        $unitVal = $unitId > 0 ? $unitId : null;
        $consultVal = $consultId > 0 ? $consultId : null;
        $clinical = ($unitVal !== null && $consultVal !== null) ? 1 : 0;

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        if ($userId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario_id=?');
            $stmt->execute([$userId]);
            $before = $stmt->fetch();
            if (!$before) config_json(['ok' => false, 'message' => 'Usuario no encontrado.'], 404);

            if ($password !== '') {
                $sql = 'UPDATE usuarios SET matricula=?, prefijo=?, nombre=?, rol_id=?, unidad_id=?, consultorio_id=?, es_personal_clinico=?, password_hash=? WHERE usuario_id=?';
                $pdo->prepare($sql)->execute([$matricula, $prefijo, $nombreCompleto, $rolId, $unitVal, $consultVal, $clinical, password_hash($password, $algo), $userId]);
            } else {
                $sql = 'UPDATE usuarios SET matricula=?, prefijo=?, nombre=?, rol_id=?, unidad_id=?, consultorio_id=?, es_personal_clinico=? WHERE usuario_id=?';
                $pdo->prepare($sql)->execute([$matricula, $prefijo, $nombreCompleto, $rolId, $unitVal, $consultVal, $clinical, $userId]);
            }
            if ($userId === (int)$adminUser['usuario_id'] && isset($_SESSION['srae_user'])) {
                $_SESSION['srae_user']['prefijo'] = $prefijo;
                $_SESSION['srae_user']['nombre'] = $nombreCompleto;
            }
            audit($pdo, $adminUser, 'MODIFICACION USUARIO', 'usuarios', $userId, $before, ['matricula' => $matricula, 'prefijo' => $prefijo, 'nombre' => $nombreCompleto]);
            config_json(['ok' => true, 'message' => 'Usuario actualizado correctamente.']);
        } else {
            $sql = 'INSERT INTO usuarios (matricula, prefijo, nombre, password_hash, rol_id, unidad_id, consultorio_id, es_personal_clinico, activo, debe_cambiar_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 0)';
            $pdo->prepare($sql)->execute([$matricula, $prefijo, $nombreCompleto, password_hash($password, $algo), $rolId, $unitVal, $consultVal, $clinical]);
            $newId = (int)$pdo->lastInsertId();
            audit($pdo, $adminUser, 'ALTA USUARIO', 'usuarios', $newId, null, ['matricula' => $matricula, 'prefijo' => $prefijo, 'nombre' => $nombreCompleto]);
            config_json(['ok' => true, 'message' => 'Usuario registrado correctamente.']);
        }
    }

    // 3. Guardar / Editar Unidad
    if ($action === 'unit_save') {
        $unitId = (int)($_POST['unidad_id'] ?? 0);
        $nombre = normalize_spaces(mb_strtoupper((string)($_POST['nombre'] ?? ''), 'UTF-8'));
        $siglas = normalize_spaces(mb_strtoupper((string)($_POST['siglas'] ?? $_POST['clave'] ?? ''), 'UTF-8'));

        if ($nombre === '' || $siglas === '') {
            config_json(['ok' => false, 'message' => 'Nombre y siglas son obligatorios.'], 422);
        }

        if ($unitId > 0) {
            $stmt = $pdo->prepare('UPDATE unidades SET clave=?, nombre=? WHERE unidad_id=?');
            $stmt->execute([$siglas, $nombre, $unitId]);
            audit($pdo, $adminUser, 'MODIFICACION UNIDAD', 'unidades', $unitId, null, ['clave' => $siglas, 'nombre' => $nombre]);
            config_json(['ok' => true, 'message' => 'Unidad actualizada correctamente.']);
        } else {
            $stmt = $pdo->prepare('INSERT INTO unidades (clave, nombre, activo) VALUES (?, ?, 1)');
            $stmt->execute([$siglas, $nombre]);
            $newId = (int)$pdo->lastInsertId();
            audit($pdo, $adminUser, 'ALTA UNIDAD', 'unidades', $newId, null, ['clave' => $siglas, 'nombre' => $nombre]);
            config_json(['ok' => true, 'message' => 'Unidad registrada correctamente.']);
        }
    }

    // 4. Toggle Consultorio (Switch interactivo en cuadrícula)
    if ($action === 'consultorio_toggle') {
        $unitId = (int)($_POST['unidad_id'] ?? 0);
        $consultId = (int)($_POST['consultorio_id'] ?? 0);
        $shiftId = (int)($_POST['turno_id'] ?? 0);

        if ($unitId < 1 || $consultId < 1 || $shiftId < 1) {
            config_json(['ok' => false, 'message' => 'Parámetros no válidos.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id, activo FROM unidad_consultorio_turno WHERE unidad_id=? AND consultorio_id=? AND turno_id=? LIMIT 1');
        $stmt->execute([$unitId, $consultId, $shiftId]);
        $row = $stmt->fetch();

        if ($row) {
            $newActive = $row['activo'] ? 0 : 1;
            $pdo->prepare('UPDATE unidad_consultorio_turno SET activo=? WHERE id=?')->execute([$newActive, $row['id']]);
        } else {
            $newActive = 1;
            $pdo->prepare('INSERT INTO unidad_consultorio_turno (unidad_id, consultorio_id, turno_id, activo) VALUES (?, ?, ?, 1)')->execute([$unitId, $consultId, $shiftId]);
        }

        audit($pdo, $adminUser, 'TOGGLE CONSULTORIO', 'unidad_consultorio_turno', null, null, ['unidad_id' => $unitId, 'consultorio_id' => $consultId, 'turno_id' => $shiftId, 'activo' => $newActive]);

        config_json([
            'ok' => true,
            'message' => 'Consultorio actualizado.',
            'activo' => (bool)$newActive
        ]);
    }

    // 5. Registrar Consultorio para Unidad
    if ($action === 'consultorio_save') {
        $unitId = (int)($_POST['unidad_id'] ?? 0);
        $numero = trim((string)($_POST['numero'] ?? ''));
        $shiftId = (int)($_POST['turno_id'] ?? 0);

        if ($unitId < 1 || $numero === '' || $shiftId < 1) {
            config_json(['ok' => false, 'message' => 'Unidad, número y turno son obligatorios.'], 422);
        }

        $clave = 'C-' . $numero;
        $nombreDescriptivo = 'Consultorio ' . $numero;

        // Verificar o crear en tabla consultorios
        $stmt = $pdo->prepare('SELECT consultorio_id FROM consultorios WHERE clave=? OR clave=? LIMIT 1');
        $stmt->execute([$clave, $numero]);
        $existing = $stmt->fetch();

        if ($existing) {
            $consultId = (int)$existing['consultorio_id'];
        } else {
            $stmt = $pdo->prepare('INSERT INTO consultorios (clave, nombre_descriptivo, activo) VALUES (?, ?, 1)');
            $stmt->execute([$clave, $nombreDescriptivo]);
            $consultId = (int)$pdo->lastInsertId();
        }

        // Activar en unidad_consultorio_turno
        $stmt = $pdo->prepare('INSERT INTO unidad_consultorio_turno (unidad_id, consultorio_id, turno_id, activo) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE activo=1');
        $stmt->execute([$unitId, $consultId, $shiftId]);

        audit($pdo, $adminUser, 'ALTA CONSULTORIO', 'consultorios', $consultId, null, ['unidad_id' => $unitId, 'numero' => $numero, 'turno_id' => $shiftId]);

        config_json(['ok' => true, 'message' => 'Consultorio agregado exitosamente.']);
    }

    config_json(['ok' => false, 'message' => 'Acción POST no reconocida.'], 404);

} catch (Throwable $e) {
    error_log('Config API error: ' . $e->getMessage());
    $status = $e instanceof InvalidArgumentException ? 422 : 500;
    config_json(['ok' => false, 'message' => $e->getMessage()], $status);
}

