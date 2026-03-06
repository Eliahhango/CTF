<?php
require_once __DIR__ . '/helpers.php';
start_session();
if (is_logged_in()) redirect('/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = (string)($_POST['password'] ?? '');

  if ($username==='' || $email==='' || $pass==='') { flash_set('danger','All fields required.'); redirect('/register.php'); }
  if (strlen($pass) < PASSWORD_MIN_LEN) { flash_set('danger','Password too short (min '.PASSWORD_MIN_LEN.').'); redirect('/register.php'); }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { flash_set('danger','Invalid email.'); redirect('/register.php'); }
  if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) { flash_set('danger','Username must be 3-20 chars (letters, numbers, underscore).'); redirect('/register.php'); }

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  try {
    $stmt = db()->prepare("INSERT INTO users (username,email,password_hash,role,status,created_at) VALUES (?,?,?,'user','pending',NOW())");
    $stmt->execute([$username,$email,$hash]);
    flash_set('success','Registered! Your account is pending approval.');
    redirect('/login.php');
  } catch (PDOException $e) {
    flash_set('danger','Username or email already exists.');
    redirect('/register.php');
  }
}

include __DIR__ . '/header.php';
?>

<section class="auth-shell">
  <div class="auth-terminal-card">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">auth@register-node:~</span>
    </div>

    <h2 class="h4 mb-2">Create Account</h2>
    <p class="small muted-cyber mb-4">
      New operators enter pending review until approved by an instructor.
    </p>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="mb-3 field-line">
        <label class="shell-label" for="username">username:</label>
        <input id="username" class="form-control terminal-input" name="username" required placeholder="3-20 chars: letters, numbers, underscore">
        <div class="form-text">Example: <code>z3r0trac3</code></div>
      </div>

      <div class="mb-3 field-line">
        <label class="shell-label" for="email">email:</label>
        <input id="email" class="form-control terminal-input" name="email" type="email" required placeholder="name@example.com">
      </div>

      <div class="mb-4 field-line">
        <label class="shell-label" for="password">password:</label>
        <input id="password" class="form-control terminal-input" name="password" type="password" required placeholder="min <?= PASSWORD_MIN_LEN ?> chars">
        <div class="form-text">Minimum length: <?= PASSWORD_MIN_LEN ?></div>
      </div>

      <button class="btn btn-command w-100" type="submit">
        <span class="prompt">$</span> ./register --init-profile
      </button>

      <div class="mt-3 small muted-cyber">
        Already onboarded? <a href="<?= e(BASE_URL) ?>/login.php">./login</a>
      </div>
    </form>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
