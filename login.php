<?php
require_once __DIR__ . '/helpers.php';
start_session();
if (is_logged_in()) redirect('/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $userOrEmail = trim($_POST['username'] ?? '');
  $pass = (string)($_POST['password'] ?? '');

  $ip = ip_address();
  $lock = login_lock_status($userOrEmail, $ip);

  if ($lock['locked']) {
    $wait = $lock['seconds_left'];
    flash_set('danger', "Too many failed logins. Try again in {$wait} seconds.");
    redirect('/login.php'); // or '/login' if you use rewrite
  }

  $stmt = db()->prepare("SELECT id,username,email,password_hash,role,status,created_at FROM users WHERE username=? OR email=? LIMIT 1");
  $stmt->execute([$userOrEmail,$userOrEmail]);
  $u = $stmt->fetch();

  if (!$u || !password_verify($pass, $u['password_hash'])) {
    record_login_failure($userOrEmail, $ip);
    flash_set('danger','Invalid login. After 3 failed attempts you will be locked for 3 minutes.');
    redirect('/login.php');
  }

  unset($u['password_hash']);
  clear_login_attempts($userOrEmail, $ip);
  $_SESSION['user'] = $u;

  if (($u['status'] ?? '') === 'active') redirect('/dashboard.php');
  if (($u['status'] ?? '') === 'banned') redirect('/banned.php');
  redirect('/pending.php');
}

include __DIR__ . '/header.php';
?>

<section class="auth-shell">
  <div class="auth-terminal-card">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">auth@login-node:~</span>
    </div>

    <h2 class="h4 mb-2">Login</h2>
    <p class="small muted-cyber mb-4">
      Authenticate to continue. Failed attempts trigger temporary lockout.
    </p>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="mb-3 field-line">
        <label class="shell-label" for="username">username_or_email:</label>
        <input id="username" class="form-control terminal-input" name="username" required placeholder="operator@domain or username">
      </div>

      <div class="mb-4 field-line">
        <label class="shell-label" for="password">password:</label>
        <input id="password" class="form-control terminal-input" name="password" type="password" required placeholder="********">
      </div>

      <button class="btn btn-command w-100" type="submit">
        <span class="prompt">$</span> ./authenticate
      </button>

      <div class="mt-3 small muted-cyber">
        No account yet? <a href="<?= e(BASE_URL) ?>/register.php">./register</a>
      </div>
    </form>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
