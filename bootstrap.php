<?php
declare(strict_types=1);

if (defined('APP_BOOTSTRAPPED')) {
    return;
}

define('APP_BOOTSTRAPPED', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/flash_helpers.php';
require_once __DIR__ . '/rate_limit_helpers.php';
require_once __DIR__ . '/auth_helpers.php';
require_once __DIR__ . '/scoring_helpers.php';
require_once __DIR__ . '/upload_helpers.php';
require_once __DIR__ . '/ui_helpers.php';

date_default_timezone_set(APP_TIMEZONE);

/**
 * Basic autoloader placeholder for future namespaced classes.
 */
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
        $file = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relative . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

/**
 * Write structured errors to the application log file.
 *
 * @param array<string,mixed> $context
 */
function app_log_error(string $message, array $context = []): void
{
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0775, true);
    }

    $line = '[' . date('c') . '] ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    @file_put_contents(LOG_FILE, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Render a generic 500 response.
 */
function render_generic_500(): void
{
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Server Error</title>';
    echo '<style>body{margin:0;background:#060b14;color:#c8dce8;font-family:Consolas,monospace;display:grid;place-items:center;min-height:100vh}';
    echo '.box{border:1px solid rgba(0,255,136,.35);padding:1.3rem 1.4rem;border-radius:6px;background:rgba(0,0,0,.35);max-width:720px}';
    echo 'h1{margin:0 0 .55rem 0;color:#ff3355;font-size:1.15rem}p{margin:.25rem 0;color:rgba(200,220,232,.8)}</style></head><body>';
    echo '<div class="box"><h1>[ 500 ] INTERNAL SYSTEM ERROR</h1><p>Something went wrong. The incident has been logged.</p></div>';
    echo '</body></html>';
}

set_error_handler(
    static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return true;
        }

        app_log_error('PHP error', [
            'severity' => $severity,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);

        if (APP_DEBUG) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }
);

set_exception_handler(
    static function (Throwable $exception): void {
        app_log_error('Uncaught exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);

        http_response_code(500);

        if (APP_DEBUG) {
            echo '<pre>' . e((string)$exception) . '</pre>';
            exit;
        }

        render_generic_500();
        exit;
    }
);

register_shutdown_function(
    static function (): void {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatalTypes, true)) {
            return;
        }

        app_log_error('Fatal error', [
            'type' => $error['type'],
            'message' => $error['message'] ?? '',
            'file' => $error['file'] ?? '',
            'line' => $error['line'] ?? 0,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ]);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (!APP_DEBUG) {
            render_generic_500();
        }
    }
);

apply_security_headers();
