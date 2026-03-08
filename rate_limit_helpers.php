<?php
declare(strict_types=1);

/**
 * Enforce submission rate limits for a user.
 */
function rate_limit_submit(int $user_id): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM submissions WHERE user_id=? AND created_at >= (NOW() - INTERVAL 60 SECOND)');
    $stmt->execute([$user_id]);

    if ((int)$stmt->fetchColumn() >= FLAG_SUBMIT_RATE_LIMIT_PER_MIN) {
        http_response_code(429);
        echo 'Too many submissions. Slow down.';
        exit;
    }
}

/**
 * Fetch the total awarded points for a user.
 */
function user_points(int $user_id): int
{
    try {
        $stmt = db()->prepare(
            'SELECT
                COALESCE((SELECT SUM(points_awarded) FROM solves WHERE user_id=?), 0)
                -
                COALESCE((SELECT SUM(points_deducted) FROM hint_deductions WHERE user_id=?), 0)'
        );
        $stmt->execute([$user_id, $user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $stmt = db()->prepare('SELECT COALESCE(SUM(points_awarded), 0) FROM solves WHERE user_id=?');
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }
}

/**
 * Fetch total solved challenge count for a user.
 */
function solved_count(int $user_id): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM solves WHERE user_id=?');
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * Return lockout status for a login identifier and IP pair.
 *
 * @return array{locked:bool,seconds_left:int,attempts:int}
 */
function login_lock_status(string $identifier, string $ip): array
{
    $identifier = strtolower(trim($identifier));
    $stmt = db()->prepare(
        'SELECT attempts,
                locked_until,
                IF(locked_until IS NOT NULL AND locked_until > NOW(), TIMESTAMPDIFF(SECOND, NOW(), locked_until), 0) AS seconds_left
         FROM login_attempts
         WHERE identifier=? AND ip_addr=?
         LIMIT 1'
    );
    $stmt->execute([$identifier, $ip]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['locked' => false, 'seconds_left' => 0, 'attempts' => 0];
    }

    return [
        'locked' => ((int)$row['seconds_left'] > 0),
        'seconds_left' => (int)$row['seconds_left'],
        'attempts' => (int)$row['attempts'],
    ];
}

/**
 * Record a failed login attempt.
 */
function record_login_failure(string $identifier, string $ip): void
{
    $identifier = strtolower(trim($identifier));

    $stmt = db()->prepare(
        'INSERT INTO login_attempts (identifier, ip_addr, attempts, last_attempt, locked_until)
         VALUES (?, ?, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
            attempts = attempts + 1,
            last_attempt = NOW(),
            locked_until = IF(attempts + 1 >= 3, DATE_ADD(NOW(), INTERVAL 3 MINUTE), locked_until)'
    );
    $stmt->execute([$identifier, $ip]);

    db()->exec('DELETE FROM login_attempts WHERE last_attempt < (NOW() - INTERVAL 7 DAY)');
}

/**
 * Clear login attempts after a successful login.
 */
function clear_login_attempts(string $identifier, string $ip): void
{
    $identifier = strtolower(trim($identifier));
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier=? AND ip_addr=?');
    $stmt->execute([$identifier, $ip]);
}
