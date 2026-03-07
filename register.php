<?php
require_once __DIR__ . '/helpers.php';
start_session();
if (is_logged_in()) redirect('/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $honeypot = sanitize_str($_POST['company'] ?? '', 255);
  $username = sanitize_str($_POST['username'] ?? '', 50);
  $email = sanitize_str($_POST['email'] ?? '', 120);
  $pass = sanitize_str($_POST['password'] ?? '', 255);

  if ($honeypot !== '') {
    flash_set('success','Registration received. Check back shortly.');
    redirect('/login.php');
  }

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

<section class="auth-screen">
  <div class="container-fluid px-0">
    <div class="row justify-content-center">
      <div class="col-lg-5 col-md-7">
        <div class="auth-shell-wrap box-glow">
          <div class="auth-titlebar"><span class="balls">[ &#9679; &#9679; &#9679; ]</span>auth@cyberclub:~$ <span class="blink"></span></div>

          <h2 class="h5 mb-3">Register Operator</h2>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="text" name="company" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">

            <div class="mb-3">
              <label class="prompt-label" for="username">Username</label>
              <input id="username" class="form-control" name="username" required placeholder="3-20 chars (letters, numbers, underscore)">
              <div class="form-text">Example: <code>z3r0trac3</code></div>
            </div>

            <div class="mb-3">
              <label class="prompt-label" for="email">Email</label>
              <input id="email" class="form-control" name="email" type="email" required placeholder="name@example.com">
            </div>

            <div class="mb-4">
              <label class="prompt-label" for="password">Password</label>
              <input id="password" class="form-control" name="password" type="password" required placeholder="minimum <?= PASSWORD_MIN_LEN ?> chars">
            </div>

            <button class="btn auth-submit w-100" type="submit">Create Account</button>

            <div class="mt-3 small text-muted">
              Existing operator? <a href="<?= e(BASE_URL) ?>/login.php">./login</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
