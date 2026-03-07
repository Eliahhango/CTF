<?php
declare(strict_types=1);

/**
 * Start and harden the PHP session.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    if (
        isset($_SESSION['user'])
        && SESSION_IDLE_TIMEOUT > 0
        && (time() - (int)$_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT
    ) {
        clear_session_and_redirect('/login.php', ['type' => 'warning', 'msg' => 'Session expired. Please sign in again.']);
    }

    $_SESSION['last_activity'] = time();
    enforce_maintenance_mode();
}

/**
 * HTML-escape output.
 */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to an internal application path and terminate.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

/**
 * Clear session data and redirect.
 *
 * @param array{type:string,msg:string}|null $flash
 */
function clear_session_and_redirect(string $path, ?array $flash = null): never
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        start_session();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }

    session_destroy();

    if ($flash !== null) {
        session_name(SESSION_NAME);
        session_start();
        $_SESSION['flash'][] = $flash;
    }

    redirect($path);
}

/**
 * Return true when a user exists in session.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']) && is_array($_SESSION['user']);
}

/**
 * Return current logged-in user from session.
 *
 * @return array<string,mixed>|null
 */
function current_user(): ?array
{
    return is_logged_in() ? $_SESSION['user'] : null;
}

/**
 * Require a valid authenticated user.
 */
function require_login(): void
{
    start_session();
    if (!is_logged_in()) {
        redirect('/login.php');
    }
}

/**
 * Require a logged-in active user.
 */
function require_active_user(): void
{
    require_login();

    $status = (string)($_SESSION['user']['status'] ?? '');
    if ($status !== 'active') {
        if ($status === 'banned') {
            redirect('/banned.php');
        }
        redirect('/pending.php');
    }
}

/**
 * Require an admin account from session.
 */
function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}

/**
 * Re-validate admin role from DB for sensitive write actions.
 */
function verify_admin_fresh_from_db(): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }

    $stmt = db()->prepare('SELECT id, username, email, role, status, created_at FROM users WHERE id=? LIMIT 1');
    $stmt->execute([$uid]);
    $dbUser = $stmt->fetch();

    if (!$dbUser || ($dbUser['role'] ?? '') !== 'admin' || ($dbUser['status'] ?? '') === 'banned') {
        return false;
    }

    $_SESSION['user'] = $dbUser;
    return true;
}

/**
 * Require admin write access with session freshness and DB role check.
 */
function require_admin_write_access(): void
{
    require_admin();

    $authTime = (int)($_SESSION['auth_time'] ?? 0);
    if ($authTime <= 0 || (time() - $authTime) > ADMIN_WRITE_SESSION_MAX_AGE) {
        clear_session_and_redirect('/login.php', ['type' => 'warning', 'msg' => 'Admin write session expired. Please sign in again.']);
    }

    if (!verify_admin_fresh_from_db()) {
        clear_session_and_redirect('/login.php', ['type' => 'danger', 'msg' => 'Admin privileges could not be verified.']);
    }
}

/**
 * Create or refresh CSRF token.
 */
function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf']['token']) || empty($_SESSION['csrf']['exp']) || time() > $_SESSION['csrf']['exp']) {
        $_SESSION['csrf'] = [
            'token' => bin2hex(random_bytes(32)),
            'exp' => time() + CSRF_TTL_SECONDS,
        ];
    }

    return (string)$_SESSION['csrf']['token'];
}

/**
 * Validate CSRF token from POST requests.
 */
function csrf_validate(): void
{
    start_session();

    $token = sanitize_str($_POST['csrf'] ?? '', 128);
    $ok = !empty($_SESSION['csrf']['token'])
        && hash_equals((string)$_SESSION['csrf']['token'], $token)
        && time() <= (int)($_SESSION['csrf']['exp'] ?? 0);

    if (!$ok) {
        http_response_code(400);
        echo 'Bad Request (CSRF)';
        exit;
    }
}

/**
 * Sanitize an integer input value.
 */
function sanitize_int(mixed $value, int $default = 0, ?int $min = null, ?int $max = null): int
{
    if (is_bool($value) || is_array($value) || is_object($value)) {
        return $default;
    }

    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    if ($filtered === false) {
        return $default;
    }

    $out = (int)$filtered;
    if ($min !== null && $out < $min) {
        return $min;
    }
    if ($max !== null && $out > $max) {
        return $max;
    }

    return $out;
}

/**
 * Sanitize a generic string input.
 */
function sanitize_str(mixed $value, int $maxLen = 5000): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $out = trim((string)$value);
    if ($maxLen > 0 && mb_strlen($out) > $maxLen) {
        $out = mb_substr($out, 0, $maxLen);
    }

    return $out;
}

/**
 * Return normalized client IP address.
 */
function ip_address(): string
{
    $ip = sanitize_str($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 64);
    return $ip === '' ? '0.0.0.0' : $ip;
}

/**
 * Persist an admin action in the audit trail.
 */
function log_admin_action(string $action, string $target_type, ?int $target_id, string $details = ''): void
{
    if (!is_logged_in()) {
        return;
    }

    $adminId = sanitize_int($_SESSION['user']['id'] ?? 0, 0, 1);
    if ($adminId <= 0) {
        return;
    }

    $cleanAction = sanitize_str($action, 100);
    $cleanTargetType = sanitize_str($target_type, 50);
    $cleanDetails = sanitize_str($details, 4000);
    $cleanTargetId = ($target_id !== null && $target_id > 0) ? $target_id : null;

    if ($cleanAction === '' || $cleanTargetType === '') {
        return;
    }

    try {
        $stmt = db()->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_addr, created_at)
             VALUES (?,?,?,?,?,?,NOW())'
        );
        $stmt->execute([$adminId, $cleanAction, $cleanTargetType, $cleanTargetId, $cleanDetails, ip_address()]);
    } catch (Throwable $e) {
        app_log_error('admin audit log write failed', [
            'admin_id' => $adminId,
            'action' => $cleanAction,
            'target_type' => $cleanTargetType,
            'target_id' => $cleanTargetId,
            'error' => $e->getMessage(),
        ]);
    }
}

/**
 * Check if challenge window has opened.
 */
function challenges_are_open(): bool
{
    return time() >= (int)strtotime(CHALLENGES_OPEN_AT);
}

/**
 * Check if challenge window has ended.
 */
function challenges_ended(): bool
{
    return time() >= (int)strtotime(CHALLENGES_CLOSE_AT);
}

/**
 * Check if challenge window is currently open.
 */
function challenges_window_open(): bool
{
    $now = time();
    return $now >= (int)strtotime(CHALLENGES_OPEN_AT) && $now < (int)strtotime(CHALLENGES_CLOSE_AT);
}

/**
 * Return active scoreboard freeze timestamp or null if inactive.
 */
function scoreboard_freeze_ts(): ?int
{
    if (FREEZE_SCOREBOARD_AT === '') {
        return null;
    }

    $ts = strtotime(FREEZE_SCOREBOARD_AT);
    if ($ts === false || time() < $ts) {
        return null;
    }

    return (int)$ts;
}

/**
 * Return SQL datetime cutoff for frozen scoreboard queries.
 */
function scoreboard_cutoff_datetime(): ?string
{
    $ts = scoreboard_freeze_ts();
    return $ts !== null ? date('Y-m-d H:i:s', $ts) : null;
}

/**
 * Convert plain URLs to safe links while preserving escaped text.
 */
function linkify(string $text): string
{
    $pattern = '~(https?://[^\s<>"\']+)~i';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

    if (!is_array($parts)) {
        return e($text);
    }

    $out = '';

    foreach ($parts as $index => $part) {
        if ($index % 2 === 0) {
            $out .= e($part);
            continue;
        }

        $trimmed = rtrim($part, '.,;:!?)]}');
        $suffix = substr($part, strlen($trimmed));

        if ($trimmed !== '' && is_safe_http_url($trimmed)) {
            $safe = e($trimmed);
            $out .= '<a href="' . $safe . '" target="_blank" rel="noopener noreferrer">' . $safe . '</a>';
            $out .= e($suffix);
        } else {
            $out .= e($part);
        }
    }

    return $out;
}

/**
 * Return true if URL has an explicit http/https scheme.
 */
function is_safe_http_url(string $url): bool
{
    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }

    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    return $scheme === 'http' || $scheme === 'https';
}

/**
 * Apply HTTP security headers for all dynamic responses.
 */
function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $csp = [
        "default-src 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "form-action 'self'",
        "object-src 'none'",
        "img-src 'self' data: https:",
        "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
        "connect-src 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()');
}

/**
 * Enforce maintenance mode for non-admin accounts.
 */
function enforce_maintenance_mode(): void
{
    if (!MAINTENANCE_MODE) {
        return;
    }

    $script = strtolower((string)basename($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = ['maintenance.php', 'favicon.php'];

    if (in_array($script, $allowed, true)) {
        return;
    }

    $isAdmin = isset($_SESSION['user']) && (($_SESSION['user']['role'] ?? '') === 'admin');
    if (!$isAdmin) {
        redirect('/maintenance.php');
    }
}
