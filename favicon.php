<?php
declare(strict_types=1);

$logoPath = __DIR__ . '/assets/logo.png';

if (is_file($logoPath)) {
    $mime = mime_content_type($logoPath) ?: 'image/png';
    header('Content-Type: ' . $mime);
    readfile($logoPath);
    exit;
}

header('Content-Type: image/svg+xml; charset=UTF-8');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="10" fill="#060b14"/><path d="M14 16h36v8H22v8h20v8H22v8h28v8H14z" fill="#00ff88"/><circle cx="50" cy="14" r="5" fill="#00d4ff"/></svg>';