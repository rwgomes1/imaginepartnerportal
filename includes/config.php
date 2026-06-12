<?php
// Application configuration is loaded from environment variables first, then
// from includes/config.local.php for local or cPanel deployments.

$localConfigPath = __DIR__ . '/config.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];

if (!function_exists('app_config_value')) {
    function app_config_value(string $key, ?string $default = null): ?string
    {
        global $localConfig;

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (is_array($localConfig) && array_key_exists($key, $localConfig)) {
            return (string) $localConfig[$key];
        }

        return defined($key) ? constant($key) : $default;
    }
}

if (!defined('APP_NAME')) {
    define('APP_NAME', app_config_value('APP_NAME', 'Imagine Partner Portal'));
}

if (!defined('APP_URL')) {
    define('APP_URL', rtrim(app_config_value('APP_URL', 'https://rd6.imagineteam.solutions'), '/'));
}

if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', app_config_value('MAIL_FROM', 'no-reply@rd6.imagineteam.solutions'));
}

if (!defined('DB_DSN')) {
    define('DB_DSN', app_config_value('DB_DSN', 'mysql:host=localhost;dbname=isolutions_partnerportal;charset=utf8mb4'));
}

if (!defined('DB_USER')) {
    define('DB_USER', app_config_value('DB_USER', 'isolutions_mib2025'));
}

if (!defined('DB_PASS')) {
    define('DB_PASS', app_config_value('DB_PASS', 'JHGFutcf756tdcfujh876tkjhg'));
}

if (!defined('OPENWEATHER_API_KEY')) {
    define('OPENWEATHER_API_KEY', app_config_value('OPENWEATHER_API_KEY', ''));
}
