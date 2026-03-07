<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
start_session();

if (is_logged_in()) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $email = sanitize_str($_POST['email'] ?? '', 120);

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pdo = db();
        $userStmt = $pdo->prepare("SELECT id, email, status FROM users WHERE email=? LIMIT 1");
        $userStmt->execute([$email]);
        $user = $userStmt->fetch();

        if ($user && ($user['status'] ?? '') === 'active') {
            $limitStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM password_resets pr
                 JOIN users u ON u.id = pr.user_id
                 WHERE u.email = ?
                   AND pr.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $limitStmt->execute([$email]);
            $recentCount = (int)$limitStmt->fetchColumn();

            if ($recentCount < 3) {
                try {
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $ttlMinutes = max(1, RESET_TOKEN_TTL_MINUTES);
                    $expiresAt = date('Y-m-d H:i:s', time() + ($ttlMinutes * 60));

                    $insertStmt = $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at)
                         VALUES (?,?,?,NULL,NOW())'
                    );
                    $insertStmt->execute([(int)$user['id'], $tokenHash, $expiresAt]);

                    $baseFull = rtrim(BASE_URL_FULL, '/');
                    $resetLink = $baseFull . '/reset_password.php?token=' . urlencode($rawToken);

                    $to = (string)$user['email'];
                    $subject = 'Password Reset – ' . APP_NAME;
                    $body = "Click the link below to reset your password (expires in " . $ttlMinutes . " minutes):\n\n" . $resetLink;

                    $headers = [];
                    $fromEmail = str_replace(["\r", "\n"], '', MAIL_FROM);
                    $fromName = str_replace(["\r", "\n"], '', MAIL_FROM_NAME);

                    if ($fromEmail !== '') {
                        if ($fromName !== '') {
                            $headers[] = 'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>';
                        } else {
                            $headers[] = 'From: ' . $fromEmail;
                        }
                    }

                    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

                    @mail($to, $subject, $body, implode("\r\n", $headers));
                } catch (Throwable $e) {
                    app_log_error('password reset request failed', [
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    flash_set('success', 'If that email is registered and active, a reset link has been sent.');
    redirect('/forgot_password.php');
}

include __DIR__ . '/header.php';
?>

<section class="auth-page">
  <div class="card auth-card">
    <div class="card-body p-4">
      <h1 class="h4 mb-1">Forgot Password</h1>
      <p class="text-muted mb-4">Enter your email to receive a password reset link.</p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="mb-4">
          <label class="form-label" for="email">Email</label>
          <input id="email" class="form-control" name="email" type="email" required placeholder="name@example.com">
        </div>

        <button class="btn btn-primary w-100" type="submit">Send Reset Link</button>

        <div class="text-center mt-3 small text-muted">
          Remembered your password? <a href="<?= e(BASE_URL) ?>/login.php">Sign in</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
