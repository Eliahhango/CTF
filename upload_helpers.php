<?php
declare(strict_types=1);

/**
 * Parse the configured extension allow-list.
 *
 * @return list<string>
 */
function allowed_upload_extensions(): array
{
    $raw = array_map(
        static fn(string $ext): string => strtolower(trim(ltrim($ext, '.'))),
        explode(',', (string)ALLOWED_EXTENSIONS)
    );

    $clean = array_values(
        array_filter(
            array_unique($raw),
            static fn(string $ext): bool => $ext !== '' && preg_match('/^[a-z0-9]+(?:\.[a-z0-9]+)*$/', $ext) === 1
        )
    );

    usort($clean, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    return $clean;
}

/**
 * Normalize uploaded files from a single/multiple file input shape.
 *
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function normalize_uploaded_file_entries(array $fileField): array
{
    if (!isset($fileField['name'])) {
        return [];
    }

    $files = [];

    if (is_array($fileField['name'])) {
        $count = count($fileField['name']);
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name' => (string)($fileField['name'][$i] ?? ''),
                'type' => (string)($fileField['type'][$i] ?? ''),
                'tmp_name' => (string)($fileField['tmp_name'][$i] ?? ''),
                'error' => (int)($fileField['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($fileField['size'][$i] ?? 0),
            ];
        }

        return $files;
    }

    $files[] = [
        'name' => (string)($fileField['name'] ?? ''),
        'type' => (string)($fileField['type'] ?? ''),
        'tmp_name' => (string)($fileField['tmp_name'] ?? ''),
        'error' => (int)($fileField['error'] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int)($fileField['size'] ?? 0),
    ];

    return $files;
}

/**
 * Save one uploaded attachment for a challenge and record it in DB.
 *
 * @throws RuntimeException|PDOException
 */
function handle_challenge_upload(int $challenge_id, array $file): string
{
    if ($challenge_id <= 0) {
        throw new RuntimeException('Invalid challenge id.');
    }

    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('No file uploaded.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($error));
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid upload source.');
    }

    $originalName = sanitize_upload_original_name((string)($file['name'] ?? ''));
    $allowedExtensions = allowed_upload_extensions();
    $matchedExtension = detect_allowed_extension($originalName, $allowedExtensions);
    if ($matchedExtension === null) {
        throw new RuntimeException('File extension is not allowed.');
    }

    $actualSize = @filesize($tmpPath);
    $fileSize = $actualSize !== false ? (int)$actualSize : (int)($file['size'] ?? 0);
    $maxBytes = UPLOAD_MAX_MB * 1024 * 1024;

    if ($fileSize <= 0) {
        throw new RuntimeException('Uploaded file is empty.');
    }
    if ($fileSize > $maxBytes) {
        throw new RuntimeException('Uploaded file exceeds the maximum size of ' . UPLOAD_MAX_MB . ' MB.');
    }

    $detectedMime = strtolower(trim((string)@mime_content_type($tmpPath)));
    if ($detectedMime === '' || $detectedMime === 'application/x-empty') {
        throw new RuntimeException('Could not determine file type.');
    }

    $clientMime = strtolower(trim((string)($file['type'] ?? '')));
    if ($clientMime !== '' && !upload_mime_looks_consistent($clientMime, $detectedMime)) {
        throw new RuntimeException('Uploaded file type does not match its content.');
    }

    $baseDir = upload_base_dir();
    $challengeDir = $baseDir . DIRECTORY_SEPARATOR . $challenge_id;
    ensure_directory_exists($baseDir);
    ensure_directory_exists($challengeDir);

    $storedFile = '';
    $storedRelativePath = '';
    $destination = '';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $storedFile = bin2hex(random_bytes(8)) . '.' . $matchedExtension;
        $storedRelativePath = $challenge_id . '/' . $storedFile;
        $destination = $challengeDir . DIRECTORY_SEPARATOR . $storedFile;
        if (!is_file($destination)) {
            break;
        }
    }

    if ($destination === '' || is_file($destination)) {
        throw new RuntimeException('Could not allocate a unique filename for upload.');
    }

    if (!@move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }

    @chmod($destination, 0640);

    try {
        $stmt = db()->prepare(
            'INSERT INTO challenge_files (challenge_id, original_name, stored_name, file_size, mime_type, uploaded_at)
             VALUES (?,?,?,?,?,NOW())'
        );
        $stmt->execute([$challenge_id, $originalName, $storedRelativePath, $fileSize, $detectedMime]);
    } catch (Throwable $e) {
        @unlink($destination);
        throw $e;
    }

    return $storedRelativePath;
}

/**
 * Return all attachment rows for a challenge.
 *
 * @return array<int, array<string,mixed>>
 */
function get_challenge_files(int $challenge_id): array
{
    if ($challenge_id <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT id, challenge_id, original_name, stored_name, file_size, mime_type, uploaded_at
         FROM challenge_files
         WHERE challenge_id=?
         ORDER BY uploaded_at DESC, id DESC'
    );
    $stmt->execute([$challenge_id]);
    $rows = $stmt->fetchAll();

    return is_array($rows) ? $rows : [];
}

/**
 * Delete one attachment row and remove its file from disk.
 *
 * @throws RuntimeException|PDOException
 */
function delete_challenge_file(int $file_id): void
{
    if ($file_id <= 0) {
        throw new RuntimeException('Invalid file id.');
    }

    $stmt = db()->prepare('SELECT id, stored_name FROM challenge_files WHERE id=? LIMIT 1');
    $stmt->execute([$file_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return;
    }

    $storedName = (string)($row['stored_name'] ?? '');
    $fullPath = upload_path_for_stored_name($storedName);

    $deleteStmt = db()->prepare('DELETE FROM challenge_files WHERE id=?');
    $deleteStmt->execute([$file_id]);

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    $challengeFolder = dirname($fullPath);
    if (is_dir($challengeFolder)) {
        $items = @scandir($challengeFolder);
        if (is_array($items) && count($items) === 2) {
            @rmdir($challengeFolder);
        }
    }
}

/**
 * Map a stored relative filename to an absolute filesystem path.
 *
 * @throws RuntimeException
 */
function upload_path_for_stored_name(string $storedName): string
{
    $normalized = str_replace('\\', '/', trim($storedName));
    $normalized = ltrim($normalized, '/');

    if ($normalized === '' || str_contains($normalized, '..')) {
        throw new RuntimeException('Invalid stored file path.');
    }

    if (preg_match('/^\d+\/[a-z0-9]+(?:\.[a-z0-9]+)+$/i', $normalized) !== 1) {
        throw new RuntimeException('Stored file path format is invalid.');
    }

    return upload_base_dir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
}

/**
 * Format file size for UI.
 */
function format_upload_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return max(0, $bytes) . ' B';
}

/**
 * Return upload base directory.
 */
function upload_base_dir(): string
{
    return rtrim((string)UPLOAD_DIR, '\\/');
}

/**
 * Sanitize original filename for DB storage/display.
 */
function sanitize_upload_original_name(string $name): string
{
    $normalized = basename(str_replace('\\', '/', trim($name)));
    $normalized = preg_replace('/[\x00-\x1F\x7F]/u', '', $normalized) ?? $normalized;
    $normalized = trim($normalized);

    if ($normalized === '') {
        $normalized = 'attachment.bin';
    }

    if (mb_strlen($normalized) > 255) {
        $normalized = mb_substr($normalized, 0, 255);
    }

    return $normalized;
}

/**
 * Resolve the extension from an allow-list match.
 *
 * @param list<string> $allowedExtensions
 */
function detect_allowed_extension(string $originalName, array $allowedExtensions): ?string
{
    $lower = strtolower($originalName);

    foreach ($allowedExtensions as $ext) {
        $suffix = '.' . $ext;
        if (str_ends_with($lower, $suffix)) {
            return $ext;
        }
    }

    return null;
}

/**
 * Ensure an upload directory exists and is writable.
 *
 * @throws RuntimeException
 */
function ensure_directory_exists(string $path): void
{
    if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    if (!is_writable($path)) {
        throw new RuntimeException('Upload directory is not writable.');
    }
}

/**
 * Basic MIME consistency check between client-provided and detected values.
 */
function upload_mime_looks_consistent(string $clientMime, string $detectedMime): bool
{
    $client = strtolower(trim(explode(';', $clientMime, 2)[0] ?? ''));
    $detected = strtolower(trim(explode(';', $detectedMime, 2)[0] ?? ''));

    if ($client === '' || $client === 'application/octet-stream') {
        return true;
    }

    if ($client === $detected) {
        return true;
    }

    $clientMajor = explode('/', $client, 2)[0] ?? '';
    $detectedMajor = explode('/', $detected, 2)[0] ?? '';

    return $clientMajor !== '' && $clientMajor === $detectedMajor;
}

/**
 * Human-readable upload error message.
 */
function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds allowed size.',
        UPLOAD_ERR_PARTIAL => 'File upload was incomplete.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server upload temp directory is missing.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A server extension blocked the upload.',
        default => 'File upload failed.',
    };
}
