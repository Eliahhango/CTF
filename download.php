<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_are_open() || !challenges_window_open()) {
    redirect('/403.php');
}

$fileId = sanitize_int($_GET['file_id'] ?? 0, 0, 1);
$challengeId = sanitize_int($_GET['challenge_id'] ?? 0, 0, 1);

if ($fileId <= 0 || $challengeId <= 0) {
    flash_set('danger', 'Invalid download request.');
    redirect('/challenges.php');
}

$stmt = db()->prepare(
    'SELECT cf.id, cf.challenge_id, cf.original_name, cf.stored_name, cf.file_size, cf.mime_type, c.is_active
     FROM challenge_files cf
     JOIN challenges c ON c.id = cf.challenge_id
     WHERE cf.id=? AND cf.challenge_id=?
     LIMIT 1'
);
$stmt->execute([$fileId, $challengeId]);
$file = $stmt->fetch();

if (!$file || (int)$file['is_active'] !== 1) {
    flash_set('danger', 'Attachment not available.');
    redirect('/challenge.php?id=' . $challengeId);
}

try {
    $fullPath = upload_path_for_stored_name((string)$file['stored_name']);
} catch (RuntimeException $e) {
    app_log_error('download invalid stored path', [
        'file_id' => $fileId,
        'challenge_id' => $challengeId,
        'error' => $e->getMessage(),
    ]);

    flash_set('danger', 'Attachment not available.');
    redirect('/challenge.php?id=' . $challengeId);
}

if (!is_file($fullPath) || !is_readable($fullPath)) {
    app_log_error('download missing attachment on disk', [
        'file_id' => $fileId,
        'challenge_id' => $challengeId,
        'path' => $fullPath,
    ]);

    flash_set('danger', 'Attachment file is missing.');
    redirect('/challenge.php?id=' . $challengeId);
}

$downloadName = sanitize_download_filename((string)$file['original_name']);
$mimeType = strtolower(trim((string)($file['mime_type'] ?? '')));
if (preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/i', $mimeType) !== 1) {
    $mimeType = 'application/octet-stream';
}

$fileSize = @filesize($fullPath);
$encodedName = rawurlencode($downloadName);
$quotedName = addcslashes($downloadName, "\\\"");

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $quotedName . '"; filename*=UTF-8\'\'' . $encodedName);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('Expires: 0');

if ($fileSize !== false) {
    header('Content-Length: ' . (string)$fileSize);
}

readfile($fullPath);
exit;

/**
 * Keep download names safe for attachment headers.
 */
function sanitize_download_filename(string $name): string
{
    $out = basename(str_replace('\\', '/', trim($name)));
    $out = str_replace(["\r", "\n", '"'], '_', $out);
    $out = preg_replace('/[^\pL\pN._ -]/u', '_', $out) ?? $out;
    $out = trim($out);

    if ($out === '') {
        return 'attachment.bin';
    }

    if (mb_strlen($out) > 200) {
        $out = mb_substr($out, 0, 200);
    }

    return $out;
}
