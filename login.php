<?php
require_once __DIR__ . '/helpers.php';
start_session();
if (is_logged_in()) redirect('/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $userOrEmail = sanitize_str($_POST['username'] ?? '', 120);
  $pass = sanitize_str($_POST['password'] ?? '', 255);

  $ip = ip_address();
  $lock = login_lock_status($userOrEmail, $ip);

  if ($lock['locked']) {
    $wait = $lock['seconds_left'];
    flash_set('danger', "Too many failed logins. Try again in {$wait} seconds.");
    redirect('/login.php');
  }

  $stmt = db()->prepare("SELECT id,username,email,password_hash,role,status,created_at FROM users WHERE username=? OR email=? LIMIT 1");
  $stmt->execute([$userOrEmail, $userOrEmail]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($pass, $u['password_hash'])) {
    record_login_failure($userOrEmail, $ip);
    flash_set('danger', 'Invalid login. After 3 failed attempts you will be locked for 3 minutes.');
    redirect('/login.php');
  }

  unset($u['password_hash']);
  clear_login_attempts($userOrEmail, $ip);
  session_regenerate_id(true);
  $_SESSION['user'] = $u;
  $_SESSION['auth_time'] = time();
  $_SESSION['last_activity'] = time();

  if (($u['status'] ?? '') === 'active') redirect('/dashboard.php');
  if (($u['status'] ?? '') === 'banned') redirect('/banned.php');
  redirect('/pending.php');
}

include __DIR__ . '/header.php';
?>

<section class="auth-page">
  <div class="card auth-card">
    <div class="card-body p-4">
      <h1 class="h4 mb-1">Sign In</h1>
      <p class="text-muted mb-4">Access your CTF account to continue.</p>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="mb-3">
          <label class="form-label" for="username">Username or Email</label>
          <input id="username" class="form-control" name="username" required placeholder="you@example.com">
        </div>

        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <input id="password" class="form-control" name="password" type="password" required placeholder="Enter your password">
        </div>

        <button class="btn btn-primary w-100" type="submit">Sign In</button>
        <div class="text-center mt-3">
          <a href="<?= e(BASE_URL) ?>/forgot_password.php" class="text-muted small">Forgot password?</a>
        </div>

        <div class="mt-3 small text-muted">
          New user? <a href="<?= e(BASE_URL) ?>/register.php">Create account</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
