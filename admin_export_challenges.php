<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$rows = db()->query(
    'SELECT id, title, category, points, is_active, created_at
     FROM challenges
     ORDER BY id ASC'
)->fetchAll(PDO::FETCH_ASSOC);

$filename = 'challenges_export_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    echo 'Unable to open output stream.';
    exit;
}

fputcsv($output, ['id', 'title', 'category', 'points', 'is_active', 'created_at']);

foreach ($rows as $row) {
    fputcsv($output, [
        (string)($row['id'] ?? ''),
        (string)($row['title'] ?? ''),
        (string)($row['category'] ?? ''),
        (string)($row['points'] ?? ''),
        (string)($row['is_active'] ?? ''),
        (string)($row['created_at'] ?? ''),
    ]);
}

fclose($output);
exit;
