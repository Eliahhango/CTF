<?php
declare(strict_types=1);

// config.php

if (!function_exists('load_env_file')) {
    /**
     * Load key/value pairs from a .env file into $_ENV and process env.
     */
    function load_env_file(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($key, $_ENV) || $_ENV[$key] === '') {
                $_ENV[$key] = $value;
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
        }
    }
}

if (!function_exists('env_value')) {
    /**
     * Get an environment variable with a fallback value.
     */
    function env_value(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string)$value;
    }
}

if (!function_exists('env_bool')) {
    /**
     * Parse a boolean environment variable.
     */
    function env_bool(string $key, bool $default = false): bool
    {
        $raw = env_value($key, null);
        if ($raw === null) {
            return $default;
        }

        $filtered = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $filtered ?? $default;
    }
}

load_env_file(__DIR__ . '/.env');

// ENV

define('APP_ENV', env_value('APP_ENV', 'production'));
define('APP_DEBUG', env_bool('APP_DEBUG', false));

// DATABASE (from environment)
define('DB_HOST', env_value('DB_HOST', ''));
define('DB_NAME', env_value('DB_NAME', ''));
define('DB_USER', env_value('DB_USER', ''));
define('DB_PASS', env_value('DB_PASS', ''));
define('DB_CHARSET', env_value('DB_CHARSET', 'utf8mb4'));

$dbMissing = [];
if (DB_HOST === '') {
    $dbMissing[] = 'DB_HOST';
}
if (DB_NAME === '') {
    $dbMissing[] = 'DB_NAME';
}
if (DB_USER === '') {
    $dbMissing[] = 'DB_USER';
}

define(
    'DB_CONFIG_ERROR',
    $dbMissing ? 'Database configuration is incomplete. Missing: ' . implode(', ', $dbMissing) . '. Set these values in .env.' : ''
);

// APP
define('APP_NAME', env_value('APP_NAME', 'Cyber Club DIT CTF'));
define('BASE_URL', env_value('BASE_URL', '/ccd'));
define('SESSION_NAME', env_value('SESSION_NAME', 'ctf_session'));
define('SESSION_IDLE_TIMEOUT', (int)env_value('SESSION_IDLE_TIMEOUT', '3600'));
define('ADMIN_WRITE_SESSION_MAX_AGE', (int)env_value('ADMIN_WRITE_SESSION_MAX_AGE', '1800'));
define('MAINTENANCE_MODE', env_bool('MAINTENANCE_MODE', false));

// SECURITY
define('CSRF_TTL_SECONDS', (int)env_value('CSRF_TTL_SECONDS', '7200'));
define('FLAG_SUBMIT_RATE_LIMIT_PER_MIN', (int)env_value('FLAG_SUBMIT_RATE_LIMIT_PER_MIN', '12'));
define('PASSWORD_MIN_LEN', (int)env_value('PASSWORD_MIN_LEN', '8'));

// TIMEZONE / EVENT WINDOWS
define('APP_TIMEZONE', env_value('APP_TIMEZONE', 'Africa/Dar_es_Salaam'));
define('CHALLENGES_OPEN_AT', env_value('CHALLENGES_OPEN_AT', '2026-02-27 21:10:00'));
define('CHALLENGES_CLOSE_AT', env_value('CHALLENGES_CLOSE_AT', '2026-03-01 21:00:00'));
define('FREEZE_SCOREBOARD_AT', env_value('FREEZE_SCOREBOARD_AT', ''));

// UPLOADS
$uploadDirValue = env_value('UPLOAD_DIR', __DIR__ . '/uploads');
if ($uploadDirValue === null) {
    $uploadDirValue = __DIR__ . '/uploads';
}
if (!preg_match('/^([a-zA-Z]:[\\\\\\/]|\\\\\\\\|\\/)/', $uploadDirValue)) {
    $uploadDirValue = __DIR__ . '/' . ltrim($uploadDirValue, '/\\');
}
define('UPLOAD_DIR', $uploadDirValue);
define('UPLOAD_MAX_MB', max(1, (int)env_value('UPLOAD_MAX_MB', '50')));
define('ALLOWED_EXTENSIONS', env_value('ALLOWED_EXTENSIONS', 'zip,tar.gz,pcap,png,jpg,pdf,txt,py,c'));

// LOGGING
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/error.log');
