<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers.php';
start_session();
require_active_user();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$lastSeenTs = sanitize_int($_SESSION['last_seen_announcements'] ?? 0, 0, 0);
$latestId = 0;
$count = 0;
try {
    $latestId = (int)db()->query('SELECT COALESCE(MAX(id),0) FROM announcements')->fetchColumn();

    if ($lastSeenTs > 0) {
        $cutoff = date('Y-m-d H:i:s', $lastSeenTs);
        $stmt = db()->prepare('SELECT COUNT(*) FROM announcements WHERE created_at > ?');
        $stmt->execute([$cutoff]);
        $count = (int)$stmt->fetchColumn();
    } else {
        $count = (int)db()->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    }
} catch (Throwable $e) {
    $latestId = 0;
    $count = 0;
}

echo json_encode([
    'count' => max(0, $count),
    'latest_id' => max(0, $latestId),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
