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

<section class="auth-screen">
  <div class="container-fluid px-0">
    <div class="row g-4 justify-content-center align-items-stretch">
      <div class="col-lg-5 col-md-7">
        <div class="auth-shell-wrap box-glow">
          <div class="auth-titlebar"><span class="balls">[ &#9679; &#9679; &#9679; ]</span>auth@cyberclub:~$ <span class="blink"></span></div>

          <h2 class="h5 mb-3">Operator Login</h2>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <div class="mb-3">
              <label class="prompt-label" for="username">Username or Email</label>
              <input id="username" class="form-control" name="username" required placeholder="operator@domain or username">
            </div>

            <div class="mb-4">
              <label class="prompt-label" for="password">Password</label>
              <input id="password" class="form-control" name="password" type="password" required placeholder="********">
            </div>

            <button class="btn auth-submit w-100" type="submit">Authenticate</button>

            <div class="mt-3 small text-muted">
              New operator? <a href="<?= e(BASE_URL) ?>/register.php">./register</a>
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-4 d-none d-lg-block">
        <div class="term-block auth-policy h-100">
          <div class="policy-line"><span class="shell">$</span>policy -> 3 failed logins = 3 minute lock</div>
          <div class="policy-line"><span class="shell">$</span>status -> pending accounts need approval</div>
          <div class="policy-line"><span class="shell">$</span>rule -> one account per operator</div>
          <div class="policy-line"><span class="shell">$</span>hint -> verify domain before typing credentials</div>
          <div class="policy-line"><span class="shell">$</span>motto -> hack_to_secure_the_world</div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
