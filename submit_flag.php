<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_window_open()) {
    redirect('/403.php');
}

csrf_validate();

$u = current_user();
$user_id = sanitize_int($u['id'] ?? 0, 0, 1);
$challenge_id = sanitize_int($_POST['challenge_id'] ?? 0, 0, 1);
$flag = sanitize_str($_POST['flag'] ?? '', 255);

if ($user_id <= 0 || $challenge_id <= 0 || $flag === '') {
    flash_set('danger', 'Invalid submission.');
    redirect('/challenges.php');
}

rate_limit_submit($user_id);

$stmt = db()->prepare('SELECT id,title,points,flag_hash FROM challenges WHERE id=? AND is_active=1');
$stmt->execute([$challenge_id]);
$c = $stmt->fetch();
if (!$c) {
    flash_set('danger', 'Challenge not found.');
    redirect('/challenges.php');
}

$stmt2 = db()->prepare('SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1');
$stmt2->execute([$user_id, $challenge_id]);
if ($stmt2->fetchColumn()) {
    flash_set('info', 'Already solved.');
    redirect('/challenge.php?id=' . $challenge_id);
}

$ip = ip_address();
$is_correct = password_verify($flag, (string)($c['flag_hash'] ?? ''));
$pdo = db();

try {
    // Always log submission attempts with source IP.
    $pdo->prepare('INSERT INTO submissions (user_id,challenge_id,submitted_flag,is_correct,ip_addr,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([$user_id, $challenge_id, $flag, $is_correct ? 1 : 0, $ip]);

    if (!$is_correct) {
        flash_set('danger', 'Incorrect flag.');
        redirect('/challenge.php?id=' . $challenge_id);
    }

    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO solves (user_id,challenge_id,points_awarded,solved_at) VALUES (?,?,?,NOW())')
        ->execute([$user_id, $challenge_id, (int)$c['points']]);
    $pdo->commit();

    flash_set('success', 'Correct! Points awarded.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    app_log_error('submit_flag database failure', [
        'user_id' => $user_id,
        'challenge_id' => $challenge_id,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);

    if ((string)$e->getCode() === '23000') {
        flash_set('info', 'Solve already recorded.');
    } else {
        flash_set('danger', 'Could not record submission. Please try again.');
    }
}

redirect('/challenge.php?id=' . $challenge_id);
