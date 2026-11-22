<?php
declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sessionPath = ENFERMERIA_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
            if (!is_dir($sessionPath)) @mkdir($sessionPath, 0770, true);
            if (is_dir($sessionPath) && is_writable($sessionPath)) session_save_path($sessionPath);
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path' => '/']);
            session_start();
        }
    }

    public static function sendHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; img-src 'self' data:");
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return (string)$_SESSION['_csrf'];
    }

    public static function csrfInput(): string { return '<input type="hidden" name="_csrf" value="' . h(self::csrfToken()) . '">'; }

    public static function verifyCsrf(): void
    {
        $token = (string)($_POST['_csrf'] ?? '');
        if (!hash_equals((string)($_SESSION['_csrf'] ?? ''), $token)) {
            http_response_code(419);
            exit('Solicitud inválida. Recarga la página e inténtalo de nuevo.');
        }
    }
}
