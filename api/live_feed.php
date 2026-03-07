<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/helpers.php';
start_session();
require_active_user();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$since = sanitize_str($_GET['since'] ?? '', 30);
// Validate datetime format, fallback to 60s ago
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $since)) {
    $since = date('Y-m-d H:i:s', time() - 60);
}

$stmt = db()->prepare(
    'SELECT s.solved_at, u.username, c.title, c.id AS challenge_id, c.category, s.points_awarded
     FROM solves s
     JOIN users u ON u.id = s.user_id
     JOIN challenges c ON c.id = s.challenge_id
     WHERE s.solved_at > ?
     ORDER BY s.solved_at DESC
     LIMIT 8'
);
$stmt->execute([$since]);
$solves = $stmt->fetchAll();

$annStmt = db()->prepare(
    'SELECT id, title, created_at FROM announcements WHERE created_at > ? ORDER BY created_at DESC LIMIT 3'
);
$annStmt->execute([$since]);
$announcements = $annStmt->fetchAll();

echo json_encode([
    'ts'            => date('Y-m-d H:i:s'),
    'solves'        => $solves,
    'announcements' => $announcements,
], JSON_UNESCAPED_UNICODE);
