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

try {
    $stmt = db()->prepare(
        'SELECT id,title,points,initial_points,floor_points,decay_solves,scoring_type,max_attempts,flag_type,flag_hash,flag_plaintext
         FROM challenges WHERE id=? AND is_active=1'
    );
    $stmt->execute([$challenge_id]);
    $c = $stmt->fetch();
} catch (Throwable $e) {
    // New columns missing - fall back to legacy-safe aliases.
    $stmt = db()->prepare(
        "SELECT id,title,points,points AS initial_points,100 AS floor_points,50 AS decay_solves,'static' AS scoring_type,0 AS max_attempts,'static' AS flag_type,flag_hash,'' AS flag_plaintext
         FROM challenges WHERE id=? AND is_active=1"
    );
    $stmt->execute([$challenge_id]);
    $c = $stmt->fetch();
}
if (!$c) {
    flash_set('danger', 'Challenge not found.');
    redirect('/challenges.php');
}
$c['max_attempts'] = (int)($c['max_attempts'] ?? 0);
$c['flag_type'] = (string)($c['flag_type'] ?? 'static');
$c['flag_plaintext'] = (string)($c['flag_plaintext'] ?? '');

$stmt2 = db()->prepare('SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1');
$stmt2->execute([$user_id, $challenge_id]);
if ($stmt2->fetchColumn()) {
    flash_set('info', 'Already solved.');
    redirect('/challenge.php?id=' . $challenge_id);
}

$pdo = db();
try {
    $maxAttempts = (int)($c['max_attempts'] ?? 0);
    if ($maxAttempts > 0) {
        $wrongCountStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM submissions WHERE user_id=? AND challenge_id=? AND is_correct=0'
        );
        $wrongCountStmt->execute([$user_id, $challenge_id]);
        if ((int)$wrongCountStmt->fetchColumn() >= $maxAttempts) {
            flash_set('danger', 'Maximum attempts reached.');
            redirect('/challenge.php?id=' . $challenge_id);
        }
    }
} catch (Throwable $e) {
    // Attempt limit check failed - allow submission to proceed
}

$ip = ip_address();
$is_correct = verify_flag($flag, $c);

try {
    // Always log submission attempts with source IP.
    $pdo->prepare('INSERT INTO submissions (user_id,challenge_id,submitted_flag,is_correct,ip_addr,created_at) VALUES (?,?,?,?,?,NOW())')
        ->execute([$user_id, $challenge_id, $flag, $is_correct ? 1 : 0, $ip]);

    if (!$is_correct) {
        flash_set('danger', 'Incorrect flag.');
        redirect('/challenge.php?id=' . $challenge_id);
    }

    $pointsAwarded = (int)($c['points'] ?? 0);

    $pdo->beginTransaction();

    if (($c['scoring_type'] ?? 'static') === 'dynamic') {
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM solves WHERE challenge_id=?');
        $countStmt->execute([$challenge_id]);
        $nextSolveNumber = ((int)$countStmt->fetchColumn()) + 1;

        $pointsAwarded = calculate_dynamic_points(
            (int)($c['initial_points'] ?? 500),
            (int)($c['floor_points'] ?? 100),
            (int)($c['decay_solves'] ?? 50),
            $nextSolveNumber
        );
    }

    $pdo->prepare('INSERT INTO solves (user_id,challenge_id,points_awarded,solved_at) VALUES (?,?,?,NOW())')
        ->execute([$user_id, $challenge_id, $pointsAwarded]);
    $pdo->commit();

    // CHECK 1: copied correct flag
    try {
        $copyStmt = $pdo->prepare(
            'SELECT DISTINCT user_id FROM submissions
             WHERE challenge_id=? AND submitted_flag=? AND is_correct=1 AND user_id != ?
             LIMIT 10'
        );
        $copyStmt->execute([$challenge_id, $flag, $user_id]);
        $copiedFrom = $copyStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($copiedFrom)) {
            raise_cheat_alert(
                $user_id,
                $challenge_id,
                'copied_correct_flag',
                'Submitted exact correct flag already used by user_id(s): ' . implode(',', $copiedFrom),
                'high'
            );

            foreach ($copiedFrom as $srcUid) {
                raise_cheat_alert(
                    (int)$srcUid,
                    $challenge_id,
                    'copied_correct_flag',
                    'Their correct flag was submitted verbatim by user_id: ' . $user_id,
                    'high'
                );
            }

            try {
                $pdo->prepare('UPDATE submissions SET flagged=1 WHERE challenge_id=? AND submitted_flag=? AND is_correct=1')
                    ->execute([$challenge_id, $flag]);
            } catch (Throwable $e) {
                // flagged column may not exist yet - skip silently
            }
        }
    } catch (Throwable $e) {
        app_log_error('cheat_check_1', ['e' => $e->getMessage()]);
    }

    // CHECK 2: shared wrong flag clusters
    try {
        $wrongStmt = $pdo->prepare(
            'SELECT submitted_flag FROM submissions
             WHERE user_id=? AND challenge_id=? AND is_correct=0
             ORDER BY created_at DESC LIMIT 20'
        );
        $wrongStmt->execute([$user_id, $challenge_id]);
        $myWrongs = $wrongStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($myWrongs as $wrongFlag) {
            $otherUsersStmt = $pdo->prepare(
                'SELECT COUNT(DISTINCT user_id) FROM submissions
                 WHERE challenge_id=? AND submitted_flag=? AND user_id != ? AND is_correct=0
                 AND created_at >= NOW() - INTERVAL 48 HOUR'
            );
            $otherUsersStmt->execute([$challenge_id, $wrongFlag, $user_id]);
            $otherCount = (int)$otherUsersStmt->fetchColumn();

            if ($otherCount >= 2) {
                raise_cheat_alert(
                    $user_id,
                    $challenge_id,
                    'shared_wrong_flag',
                    'Wrong flag string "' . substr((string)$wrongFlag, 0, 30) . '..." also submitted by ' . $otherCount . ' other users',
                    'high'
                );
                break;
            }
        }
    } catch (Throwable $e) {
        app_log_error('cheat_check_2', ['e' => $e->getMessage()]);
    }

    // CHECK 3: speed solve (fast follow)
    try {
        $prevSolveStmt = $pdo->prepare(
            'SELECT solved_at FROM solves WHERE challenge_id=? AND user_id != ? ORDER BY solved_at DESC LIMIT 1'
        );
        $prevSolveStmt->execute([$challenge_id, $user_id]);
        $prevSolvedAt = $prevSolveStmt->fetchColumn();

        if ($prevSolvedAt) {
            $gap = time() - strtotime((string)$prevSolvedAt);
            if ($gap >= 0 && $gap <= 120) {
                raise_cheat_alert(
                    $user_id,
                    $challenge_id,
                    'speed_solve',
                    'Solved ' . $gap . ' seconds after another user solved the same challenge',
                    'medium'
                );
            }
        }
    } catch (Throwable $e) {
        app_log_error('cheat_check_3', ['e' => $e->getMessage()]);
    }

    // CHECK 4: rapid solves across challenges
    try {
        $rapidStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM solves WHERE user_id=? AND solved_at >= NOW() - INTERVAL 8 MINUTE'
        );
        $rapidStmt->execute([$user_id]);
        $recentSolves = (int)$rapidStmt->fetchColumn();

        if ($recentSolves >= 4) {
            raise_cheat_alert(
                $user_id,
                $challenge_id,
                'rapid_solves',
                $recentSolves . ' challenges solved in under 8 minutes',
                'low'
            );
        }
    } catch (Throwable $e) {
        app_log_error('cheat_check_4', ['e' => $e->getMessage()]);
    }

    // CHECK 5: same IP solves on same challenge by other active users
    try {
        $sameIpStmt = $pdo->prepare(
            "SELECT DISTINCT s2.user_id FROM submissions s1
             JOIN submissions s2 ON s2.ip_addr=s1.ip_addr AND s2.user_id != s1.user_id AND s2.is_correct=1 AND s2.challenge_id=?
             JOIN users u2 ON u2.id = s2.user_id AND u2.status='active' AND u2.role='user'
             WHERE s1.user_id=? AND s1.is_correct=1 AND s1.challenge_id=?
             LIMIT 5"
        );
        $sameIpStmt->execute([$challenge_id, $user_id, $challenge_id]);
        $sameIpUsers = $sameIpStmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($sameIpUsers)) {
            raise_cheat_alert(
                $user_id,
                $challenge_id,
                'same_ip_solve',
                'Challenge also solved from same IP by user_id(s): ' . implode(',', $sameIpUsers),
                'medium'
            );
        }
    } catch (Throwable $e) {
        app_log_error('cheat_check_5', ['e' => $e->getMessage()]);
    }

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
