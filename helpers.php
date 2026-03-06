<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

date_default_timezone_set(APP_TIMEZONE);

function start_session(): void {
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
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user(): ?array { return $_SESSION['user'] ?? null; }

function require_login(): void { if (!is_logged_in()) redirect('/login.php'); }
function require_active_user(): void {
    require_login();
    if (($_SESSION['user']['status'] ?? '') !== 'active') redirect('/pending.php');
}
function require_admin(): void {
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'admin') { http_response_code(403); echo '403 Forbidden'; exit; }
}

function flash_set(string $type, string $msg): void { $_SESSION['flash'][] = ['type'=>$type,'msg'=>$msg]; }
function flash_get_all(): array { $out = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $out; }

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf']['token']) || empty($_SESSION['csrf']['exp']) || time() > $_SESSION['csrf']['exp']) {
        $_SESSION['csrf'] = ['token'=>bin2hex(random_bytes(32)), 'exp'=> time()+CSRF_TTL_SECONDS];
    }
    return $_SESSION['csrf']['token'];
}
function csrf_validate(): void {
    start_session();
    $t = $_POST['csrf'] ?? '';
    $ok = !empty($_SESSION['csrf']['token']) && hash_equals($_SESSION['csrf']['token'], $t) && time() <= ($_SESSION['csrf']['exp'] ?? 0);
    if (!$ok) { http_response_code(400); echo 'Bad Request (CSRF)'; exit; }
}

function ip_address(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

function rate_limit_submit(int $user_id): void {
    $stmt = db()->prepare("SELECT COUNT(*) FROM submissions WHERE user_id=? AND created_at >= (NOW() - INTERVAL 60 SECOND)");
    $stmt->execute([$user_id]);
    if ((int)$stmt->fetchColumn() >= FLAG_SUBMIT_RATE_LIMIT_PER_MIN) { http_response_code(429); echo 'Too many submissions. Slow down.'; exit; }
}

function user_points(int $user_id): int {
    $stmt = db()->prepare("SELECT COALESCE(SUM(points_awarded),0) FROM solves WHERE user_id=?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}
function solved_count(int $user_id): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM solves WHERE user_id=?");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}


function linkify(string $text): string {
    $text = e($text); // escape first (XSS protection)
    $pattern = '~(https?://[^\s]+)~';
    $replace = '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>';
    return preg_replace($pattern, $replace, $text);
}


function login_lock_status(string $identifier, string $ip): array {
    $identifier = strtolower(trim($identifier));
    $stmt = db()->prepare("
        SELECT attempts,
               locked_until,
               IF(locked_until IS NOT NULL AND locked_until > NOW(),
                  TIMESTAMPDIFF(SECOND, NOW(), locked_until),
                  0
               ) AS seconds_left
        FROM login_attempts
        WHERE identifier=? AND ip_addr=?
        LIMIT 1
    ");
    $stmt->execute([$identifier, $ip]);
    $row = $stmt->fetch();
    if (!$row) return ['locked' => false, 'seconds_left' => 0, 'attempts' => 0];

    return [
        'locked' => ((int)$row['seconds_left'] > 0),
        'seconds_left' => (int)$row['seconds_left'],
        'attempts' => (int)$row['attempts'],
    ];
}

function record_login_failure(string $identifier, string $ip): void {
    $identifier = strtolower(trim($identifier));

    // Upsert attempt counter
    $stmt = db()->prepare("
        INSERT INTO login_attempts (identifier, ip_addr, attempts, last_attempt, locked_until)
        VALUES (?, ?, 1, NOW(), NULL)
        ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = NOW(),
            locked_until = IF(attempts + 1 >= 3, DATE_ADD(NOW(), INTERVAL 3 MINUTE), locked_until)
    ");
    $stmt->execute([$identifier, $ip]);

    // Optional cleanup: remove very old rows (keeps table small)
    db()->exec("DELETE FROM login_attempts WHERE last_attempt < (NOW() - INTERVAL 7 DAY)");
}

function clear_login_attempts(string $identifier, string $ip): void {
    $identifier = strtolower(trim($identifier));
    $stmt = db()->prepare("DELETE FROM login_attempts WHERE identifier=? AND ip_addr=?");
    $stmt->execute([$identifier, $ip]);
}

function challenges_are_open(): bool {
    return time() >= strtotime(CHALLENGES_OPEN_AT);
}

function challenges_ended(): bool {
    return time() >= strtotime(CHALLENGES_CLOSE_AT);
}

// True only during the allowed window
function challenges_window_open(): bool {
    $now = time();
    return $now >= strtotime(CHALLENGES_OPEN_AT) && $now < strtotime(CHALLENGES_CLOSE_AT);
}

