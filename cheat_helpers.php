<?php
declare(strict_types=1);

/**
 * Insert a cheat alert — deduplicated per user+challenge+reason per hour.
 */
function raise_cheat_alert(int $user_id, int $challenge_id, string $reason, string $detail, string $severity = 'medium'): void
{
    try {
        $exists = db()->prepare(
            'SELECT 1 FROM cheat_alerts WHERE user_id=? AND challenge_id=? AND reason=? AND created_at >= NOW() - INTERVAL 1 HOUR LIMIT 1'
        );
        $exists->execute([$user_id, $challenge_id, $reason]);
        if ($exists->fetchColumn()) {
            return;
        }

        db()->prepare(
            'INSERT INTO cheat_alerts (user_id, challenge_id, reason, detail, severity, created_at) VALUES (?,?,?,?,?,NOW())'
        )->execute([$user_id, $challenge_id, $reason, $detail, $severity]);
    } catch (Throwable $e) {
        // Detection must never break the solve flow
        app_log_error('cheat_alert_insert_failed', ['error' => $e->getMessage()]);
    }
}

/**
 * Returns total unreviewed alert count — used for admin badge.
 */
function cheat_alert_count(): int
{
    try {
        return (int)db()->query('SELECT COUNT(*) FROM cheat_alerts WHERE reviewed=0')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
