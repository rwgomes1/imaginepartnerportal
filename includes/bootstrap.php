<?php

require_once __DIR__ . '/config.php';

if (!function_exists('app_is_https')) {
    function app_is_https(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('app_pdo')) {
    function app_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }
}

if (!function_exists('app_client_ip')) {
    function app_client_ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('app_csrf_token')) {
    function app_csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('app_verify_csrf')) {
    function app_verify_csrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('app_log_firewall')) {
    function app_log_firewall(string $eventType, string $details, ?int $userId = null): void
    {
        try {
            $stmt = app_pdo()->prepare(
                'INSERT INTO firewall_logs (event_type, ip_address, user_id, details)
                 VALUES (:event_type, :ip_address, :user_id, :details)'
            );
            $stmt->execute([
                ':event_type' => $eventType,
                ':ip_address' => app_client_ip(),
                ':user_id' => $userId,
                ':details' => $details,
            ]);
        } catch (Throwable $exception) {
            error_log('Unable to write firewall log: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('app_redirect_for_role')) {
    function app_redirect_for_role(string $role): void
    {
        $redirects = [
            'partner' => '/partners/dashboard.php',
            'admin' => '/admin/dashboard.php',
            'superadmin' => '/admin/dashboard.php',
            'user' => '/index.php',
        ];

        header('Location: ' . ($redirects[$role] ?? '/index.php'));
        exit;
    }
}

if (!function_exists('app_finish_login')) {
    function app_finish_login(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'] ?: $user['username'];
        $_SESSION['role'] = $user['role'];

        if (array_key_exists('partner_id', $user) && !empty($user['partner_id'])) {
            $_SESSION['partner_id'] = $user['partner_id'];
        }

        unset($_SESSION['temp_user_id'], $_SESSION['temp_user_role']);
    }
}
