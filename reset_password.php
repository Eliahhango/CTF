<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
start_session();

/**
 * Resolve a valid, non-used password reset token.
 *
 * @return array<string,mixed>|null
 */
function find_valid_password_reset(string $rawToken): ?array
{
    if ($rawToken === '') {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $stmt = db()->prepare(
        'SELECT id, user_id
         FROM password_resets
         WHERE token_hash = ?
           AND used_at IS NULL
           AND expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    return $row ?: null;
}

$token = sanitize_str($_GET['token'] ?? '', 256);
$errorMessage = '';
$tokenRow = find_valid_password_reset($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $token = sanitize_str($_POST['token'] ?? '', 256);
    $newPassword = sanitize_str($_POST['new_password'] ?? '', 255);
    $confirmPassword = sanitize_str($_POST['confirm_password'] ?? '', 255);

    $tokenRow = find_valid_password_reset($token);
    if ($tokenRow === null) {
        $errorMessage = 'This reset link is invalid or has expired.';
    } elseif ($newPassword === '' || $confirmPassword === '') {
        $errorMessage = 'All fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = 'Passwords do not match.';
    } elseif (strlen($newPassword) < PASSWORD_MIN_LEN) {
        $errorMessage = 'Password too short (min ' . PASSWORD_MIN_LEN . ').';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateUser = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $updateUser->execute([$passwordHash, (int)$tokenRow['user_id']]);

            $consumeToken = $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE id=? AND used_at IS NULL');
            $consumeToken->execute([(int)$tokenRow['id']]);

            if ($consumeToken->rowCount() !== 1) {
                throw new RuntimeException('Reset token was already consumed.');
            }

            $pdo->commit();

            flash_set('success', 'Password updated. Please log in.');
            redirect('/login.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            app_log_error('password reset completion failed', [
                'error' => $e->getMessage(),
            ]);
            $errorMessage = 'Could not update password. Try again.';
        }
    }
}

include __DIR__ . '/header.php';
?>

<section class="auth-page">
  <div class="card auth-card">
    <div class="card-body p-4">
      <?php if ($tokenRow === null): ?>
        <h1 class="h4 mb-1">Reset Link Invalid</h1>
        <p class="text-muted mb-4">This reset link is invalid or expired.</p>
        <a class="btn btn-primary w-100" href="<?= e(BASE_URL) ?>/forgot_password.php">Request New Reset Link</a>
      <?php else: ?>
        <h1 class="h4 mb-1">Reset Password</h1>
        <p class="text-muted mb-4">Set your new account password.</p>

        <?php if ($errorMessage !== ''): ?>
          <div class="alert alert-danger" role="alert"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <div class="mb-3">
            <label class="form-label" for="new_password">New Password</label>
            <input id="new_password" class="form-control" name="new_password" type="password" required placeholder="Minimum <?= e((string)PASSWORD_MIN_LEN) ?> characters">
          </div>

          <div class="mb-4">
            <label class="form-label" for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" class="form-control" name="confirm_password" type="password" required placeholder="Repeat your new password">
          </div>

          <button class="btn btn-primary w-100" type="submit">Update Password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
