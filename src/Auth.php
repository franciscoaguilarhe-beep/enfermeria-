<?php
declare(strict_types=1);

final class Auth
{
    private const SESSION_KEY = 'srae_user';
    private const MAX_FAILURES = 5;
    private const LOCK_MINUTES = 15;

    public static function user(): ?array
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return null;
        }
        $user = $_SESSION[self::SESSION_KEY];
        if (!isset($user['prefijo']) || trim((string)$user['prefijo']) === '') {
            $user['prefijo'] = 'L.E.';
        }
        return $user;
    }

    public static function login(PDO $pdo, string $matricula, string $password): array
    {
        $stmt = $pdo->prepare('SELECT u.*, COALESCE(NULLIF(TRIM(u.prefijo), \'\'), \'L.E.\') AS prefijo, r.clave rol_clave, r.nombre rol_nombre, un.clave unidad_clave, un.nombre unidad_nombre, c.clave consultorio_clave, c.nombre_descriptivo consultorio_nombre FROM usuarios u LEFT JOIN roles r ON r.rol_id=u.rol_id LEFT JOIN unidades un ON un.unidad_id=u.unidad_id LEFT JOIN consultorios c ON c.consultorio_id=u.consultorio_id WHERE u.matricula=? LIMIT 1');
        $stmt->execute([mb_strtoupper(trim($matricula), 'UTF-8')]);
        $user = $stmt->fetch();
        if (!$user || !(bool)$user['activo']) throw new RuntimeException('Credenciales no válidas.');
        if ($user['bloqueado_hasta'] !== null && strtotime((string)$user['bloqueado_hasta']) > time()) throw new RuntimeException('La cuenta está temporalmente bloqueada.');
        if (!is_string($user['password_hash']) || $user['password_hash'] === '' || !password_verify($password, $user['password_hash'])) {
            $failures = (int)$user['intentos_fallidos'] + 1;
            $blocked = $failures >= self::MAX_FAILURES ? date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60) : null;
            $pdo->prepare('UPDATE usuarios SET intentos_fallidos=?, bloqueado_hasta=? WHERE usuario_id=?')->execute([$failures, $blocked, $user['usuario_id']]);
            throw new RuntimeException('Credenciales no válidas.');
        }
        session_regenerate_id(true);
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=NULL, ultimo_acceso=NOW() WHERE usuario_id=?')->execute([(int)$user['usuario_id']]);
        unset($user['password_hash']);
        $user['prefijo'] = trim((string)($user['prefijo'] ?? 'L.E.')) ?: 'L.E.';
        $_SESSION[self::SESSION_KEY] = $user;
        return $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) { flash('error', 'Inicia sesión para continuar.'); redirect(base_url('index.php?r=login')); }
        return $user;
    }

    public static function can(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array((string)($user['rol_clave'] ?? ''), $roles, true);
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::requireUser();
        if (!self::can(...$roles)) { http_response_code(403); exit('No tienes permisos para esta operación.'); }
        return $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }
}
