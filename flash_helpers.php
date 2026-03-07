<?php
declare(strict_types=1);

/**
 * Queue a flash message for the next request.
 */
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

/**
 * Consume all queued flash messages.
 *
 * @return array<int, array{type:string,msg:string}>
 */
function flash_get_all(): array
{
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($out) ? $out : [];
}