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
$userId = sanitize_int($u['id'] ?? 0, 0, 1);
$hintId = sanitize_int($_POST['hint_id'] ?? 0, 0, 1);
$challengeId = sanitize_int($_POST['challenge_id'] ?? 0, 0, 1);

if ($userId <= 0 || $hintId <= 0 || $challengeId <= 0) {
    flash_set('danger', 'Invalid hint request.');
    redirect('/challenges.php');
}

$pdo = db();
try {
    $hintStmt = $pdo->prepare(
        'SELECT h.id, h.challenge_id, h.cost, c.is_active
         FROM hints h
         JOIN challenges c ON c.id = h.challenge_id
         WHERE h.id=?
         LIMIT 1'
    );
    $hintStmt->execute([$hintId]);
    $hint = $hintStmt->fetch();
} catch (Throwable $e) {
    flash_set('warning', 'Hints are not available yet. Run DB migrations first.');
    redirect('/challenge.php?id=' . $challengeId);
}

if (
    !$hint
    || (int)$hint['challenge_id'] !== $challengeId
    || (int)$hint['is_active'] !== 1
) {
    flash_set('danger', 'Hint not found.');
    redirect('/challenge.php?id=' . $challengeId);
}

try {
    $existingStmt = $pdo->prepare('SELECT 1 FROM hint_unlocks WHERE user_id=? AND hint_id=? LIMIT 1');
    $existingStmt->execute([$userId, $hintId]);
    if ($existingStmt->fetchColumn()) {
        flash_set('info', 'Hint already unlocked.');
        redirect('/challenge.php?id=' . $challengeId);
    }
} catch (Throwable $e) {
    flash_set('warning', 'Hints are not available yet. Run DB migrations first.');
    redirect('/challenge.php?id=' . $challengeId);
}

$cost = max(0, (int)($hint['cost'] ?? 0));
if ($cost > 0 && user_points($userId) < $cost) {
    flash_set('danger', 'Not enough points to unlock this hint.');
    redirect('/challenge.php?id=' . $challengeId);
}

try {
    $pdo->beginTransaction();

    $unlockStmt = $pdo->prepare(
        'INSERT INTO hint_unlocks (user_id, hint_id, points_spent, unlocked_at)
         VALUES (?,?,?,NOW())'
    );
    $unlockStmt->execute([$userId, $hintId, $cost]);

    if ($cost > 0) {
        $deductStmt = $pdo->prepare(
            'INSERT INTO hint_deductions (user_id, hint_id, points_deducted, deducted_at)
             VALUES (?,?,?,NOW())'
        );
        $deductStmt->execute([$userId, $hintId, $cost]);
    }

    $pdo->commit();
    flash_set('success', 'Hint unlocked.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    app_log_error('unlock_hint failure', [
        'user_id' => $userId,
        'hint_id' => $hintId,
        'challenge_id' => $challengeId,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
    ]);

    if ((string)$e->getCode() === '23000') {
        flash_set('info', 'Hint already unlocked.');
    } else {
        flash_set('danger', 'Could not unlock hint right now.');
    }
}

redirect('/challenge.php?id=' . $challengeId);
