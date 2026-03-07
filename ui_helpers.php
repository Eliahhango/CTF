<?php
declare(strict_types=1);

/**
 * Normalize category into a known display key.
 */
function category_key(string $rawCategory): string
{
    $catRaw = strtolower(trim($rawCategory));

    if (str_contains($catRaw, 'web')) {
        return 'web';
    }
    if (str_contains($catRaw, 'crypto')) {
        return 'crypto';
    }
    if (str_contains($catRaw, 'forensic')) {
        return 'forensics';
    }
    if (str_contains($catRaw, 'pwn')) {
        return 'pwn';
    }
    if (str_contains($catRaw, 'linux')) {
        return 'linux';
    }

    return 'default';
}

/**
 * Render a reusable stat card block.
 */
function render_stat_card(string $label, string $value, string $class = '', string $glowClass = ''): string
{
    $cardClass = trim('stat-card ' . $class);
    $valueClass = trim('value ' . $glowClass);

    return '<div class="' . e($cardClass) . '">'
        . '<div class="label">' . e($label) . '</div>'
        . '<div class="' . e($valueClass) . '">' . e($value) . '</div>'
        . '</div>';
}

/**
 * Render a reusable challenge card.
 *
 * Expected keys in $challenge:
 * id,title,category,points,cat_key,solve_count,first_blood
 */
function render_challenge_card(array $challenge, bool $solved): string
{
    $id = (int)($challenge['id'] ?? 0);
    $title = (string)($challenge['title'] ?? '');
    $category = (string)($challenge['category'] ?? '');
    $points = (int)($challenge['points'] ?? 0);
    $catKey = (string)($challenge['cat_key'] ?? 'default');
    $solveCount = (int)($challenge['solve_count'] ?? 0);
    $firstBlood = (string)($challenge['first_blood'] ?? '');

    $statusClass = $solved ? 'status-solved' : 'status-open';
    $statusText = $solved ? '[ PWNED &#10003; ]' : '[ OPEN ]';

    $firstBloodHtml = '';
    if ($firstBlood !== '') {
        $firstBloodHtml = '<div class="first-blood-ribbon">FIRST BLOOD @' . e($firstBlood) . '</div>';
    }

    return '<article class="challenge-item" data-category="' . e($catKey) . '" data-title="' . e(strtolower($title)) . '">'
        . '<div class="challenge-strip strip-' . e($catKey) . '"></div>'
        . $firstBloodHtml
        . '<div class="d-flex justify-content-between align-items-center gap-2">'
        . '<span class="small text-muted terminal-mono">TARGET</span>'
        . '<span class="challenge-cat cat-' . e($catKey) . '">' . e($category) . '</span>'
        . '</div>'
        . '<h3 class="challenge-title">' . e($title) . '</h3>'
        . '<div class="challenge-points">[ ' . e((string)$points) . ' ]</div>'
        . '<div class="d-flex justify-content-between align-items-center gap-2 mb-2">'
        . '<span class="challenge-status ' . e($statusClass) . '">' . $statusText . '</span>'
        . '<span class="badge text-bg-info">' . e((string)$solveCount) . ' solves</span>'
        . '</div>'
        . '<a class="btn btn-sm btn-outline-light" href="' . e(BASE_URL) . '/challenge.php?id=' . e((string)$id) . '">OPEN</a>'
        . '</article>';
}