<?php
declare(strict_types=1);

define('ENFERMERIA_ROOT', dirname(__DIR__));

function env_value(string $key, ?string $default = null): ?string
{
    static $values = null;
    if ($values === null) {
        $values = [];
        $processValues = [];
        foreach (getenv() as $name => $value) {
            if (is_string($name) && is_string($value)) { $values[$name] = $value; $processValues[$name] = true; }
        }
        $file = ENFERMERIA_ROOT . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                if ($value !== '' && in_array($value[0], ['"', "'"], true)) $value = trim($value, "\"'");
                if (!isset($processValues[$name]) && !array_key_exists($name, $values)) $values[$name] = $value;
            }
        }
    }
    return array_key_exists($key, $values) ? $values[$key] : $default;
}

date_default_timezone_set(env_value('APP_TIMEZONE', 'America/Mazatlan') ?: 'America/Mazatlan');
$environment = strtolower(env_value('APP_ENV', 'production') ?: 'production');
error_reporting(E_ALL);
ini_set('display_errors', $environment === 'development' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ENFERMERIA_ROOT . '/storage/logs/php-error.log');

require_once ENFERMERIA_ROOT . '/src/Database.php';
require_once ENFERMERIA_ROOT . '/src/Security.php';
require_once ENFERMERIA_ROOT . '/src/Auth.php';
require_once ENFERMERIA_ROOT . '/src/PatientDomain.php';
require_once ENFERMERIA_ROOT . '/src/PdfTemplate.php';

Security::startSession();
Security::sendHeaders();

// Session idle timeout
$sessionIdleTimeout = (int)(env_value('SESSION_IDLE_TIMEOUT', '1800') ?: 1800);
if (isset($_SESSION['srae_last_activity'])) {
    if (time() - (int)$_SESSION['srae_last_activity'] > $sessionIdleTimeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['flash'][] = ['type' => 'error', 'message' => 'Tu sesión expiró por inactividad.'];
    }
}
$_SESSION['srae_last_activity'] = time();

function db(): PDO { return Database::connection(); }
function h(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/enfermeria/public/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type' => $type, 'message' => $message]; }
function take_flash(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function old_input(string $key, string $default = ''): string { return h((string)($_POST[$key] ?? $default)); }
function post_string(string $key): string { return mb_strtoupper(trim((string)($_POST[$key] ?? '')), 'UTF-8'); }
function normalize_spaces(string $value): string { return trim((string)preg_replace('/\s+/u', ' ', $value)); }
function normalize_text(string $value): string { return mb_strtoupper(normalize_spaces($value), 'UTF-8'); }
function normalize_multiline(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", trim($value));
    $lines = array_map(static fn(string $line): string => normalize_spaces($line), explode("\n", $value));
    return trim(mb_strtoupper(implode("\n", $lines), 'UTF-8'));
}
function valid_date(string $value): bool { $d = DateTime::createFromFormat('Y-m-d', $value); return $d !== false && $d->format('Y-m-d') === $value; }

function require_fresh_user(): array
{
    $session = Auth::user();
    if (!$session) {
        flash('error', 'Inicia sesión para continuar.');
        redirect(base_url('index.php?r=login'));
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT u.*, r.clave rol_clave, r.nombre rol_nombre, un.clave unidad_clave, un.nombre unidad_nombre, c.clave consultorio_clave, c.nombre_descriptivo consultorio_nombre FROM usuarios u LEFT JOIN roles r ON r.rol_id=u.rol_id LEFT JOIN unidades un ON un.unidad_id=u.unidad_id LEFT JOIN consultorios c ON c.consultorio_id=u.consultorio_id WHERE u.usuario_id=? LIMIT 1');
    $stmt->execute([(int)$session['usuario_id']]);
    $user = $stmt->fetch();
    if (!$user || !(bool)$user['activo']) {
        Auth::logout();
        flash('error', 'Tu cuenta ha sido desactivada.');
        redirect(base_url('index.php?r=login'));
    }
    // Update session with fresh data
    unset($user['password_hash']);
    $user['prefijo'] = trim((string)($user['prefijo'] ?? 'L.E.')) ?: 'L.E.';
    $_SESSION['srae_user'] = $user;
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_fresh_user();
    if (!in_array((string)($user['rol_clave'] ?? ''), $roles, true)) {
        http_response_code(403);
        exit('No tienes permisos para esta operación.');
    }
    return $user;
}

function require_admin(): array
{
    return require_role('ADMINISTRADOR');
}

function require_clinical_staff(): array
{
    $user = require_fresh_user();
    if (!(bool)($user['es_personal_clinico'] ?? false)) {
        http_response_code(403);
        exit('Esta operación requiere personal clínico.');
    }
    if (!(int)($user['unidad_id'] ?? 0) || !(int)($user['consultorio_id'] ?? 0)) {
        http_response_code(403);
        exit('Tu cuenta no tiene unidad y consultorio asignados.');
    }
    return $user;
}

function csv_safe(string $value): string
{
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

function audit(PDO $pdo, ?array $staff, string $action, string $entity, ?int $entityId, ?array $before, ?array $after, ?string $reason = null): void
{
    $beforeMeta = audit_meta($before);
    $afterMeta = audit_meta($after);
    $fields = array_values(array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))));
    $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, entidad, entidad_id, ip, user_agent, valor_anterior, valor_nuevo, motivo, resultado, campos_modificados, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $staff['usuario_id'] ?? null,
        $action,
        $entity,
        $entityId,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        $beforeMeta === null ? null : json_encode($beforeMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $afterMeta === null ? null : json_encode($afterMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $reason,
        'OK',
        json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}

function audit_meta(?array $value): ?array
{
    if ($value === null) return null;
    return ['campos' => array_keys($value), 'hash' => hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))];
}
