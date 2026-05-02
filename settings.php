<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();
$userId = sanitize_int($u['id'] ?? 0, 0, 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = sanitize_str($_POST['action'] ?? '', 30);

    $authStmt = db()->prepare('SELECT id, username, email, password_hash FROM users WHERE id=? LIMIT 1');
    $authStmt->execute([$userId]);
    $dbUser = $authStmt->fetch();

    if (!$dbUser) {
        clear_session_and_redirect('/login.php', ['type' => 'danger', 'msg' => 'Session invalid. Please sign in again.']);
    }

    $currentPassword = sanitize_str($_POST['current_password'] ?? '', 255);
    if ($currentPassword === '' || !password_verify($currentPassword, (string)$dbUser['password_hash'])) {
        flash_set('danger', 'Current password is incorrect.');
        redirect('/settings.php');
    }

    if ($action === 'update_username') {
        $newUsername = sanitize_str($_POST['username'] ?? '', 50);
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $newUsername)) {
            flash_set('danger', 'Username must be 3-20 chars (letters, numbers, underscore).');
            redirect('/settings.php');
        }

        if (strcasecmp($newUsername, (string)$dbUser['username']) === 0) {
            flash_set('info', 'Username is unchanged.');
            redirect('/settings.php');
        }

        $existsStmt = db()->prepare('SELECT 1 FROM users WHERE username=? AND id<>? LIMIT 1');
        $existsStmt->execute([$newUsername, $userId]);
        if ($existsStmt->fetchColumn()) {
            flash_set('danger', 'Username is already taken.');
            redirect('/settings.php');
        }

        db()->prepare('UPDATE users SET username=? WHERE id=?')->execute([$newUsername, $userId]);
        $_SESSION['user']['username'] = $newUsername;
        flash_set('success', 'Username updated.');
        redirect('/settings.php');
    }

    if ($action === 'update_email') {
        $newEmail = sanitize_str($_POST['email'] ?? '', 120);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            flash_set('danger', 'Invalid email format.');
            redirect('/settings.php');
        }

        if (strcasecmp($newEmail, (string)$dbUser['email']) === 0) {
            flash_set('info', 'Email is unchanged.');
            redirect('/settings.php');
        }

        $existsStmt = db()->prepare('SELECT 1 FROM users WHERE email=? AND id<>? LIMIT 1');
        $existsStmt->execute([$newEmail, $userId]);
        if ($existsStmt->fetchColumn()) {
            flash_set('danger', 'Email is already in use.');
            redirect('/settings.php');
        }

        db()->prepare('UPDATE users SET email=? WHERE id=?')->execute([$newEmail, $userId]);
        $_SESSION['user']['email'] = $newEmail;
        flash_set('success', 'Email updated.');
        redirect('/settings.php');
    }

    if ($action === 'update_password') {
        $newPassword = sanitize_str($_POST['new_password'] ?? '', 255);
        $confirmPassword = sanitize_str($_POST['confirm_password'] ?? '', 255);

        if (mb_strlen($newPassword) < PASSWORD_MIN_LEN) {
            flash_set('danger', 'New password must be at least ' . PASSWORD_MIN_LEN . ' characters.');
            redirect('/settings.php');
        }

        if (!hash_equals($newPassword, $confirmPassword)) {
            flash_set('danger', 'Password confirmation does not match.');
            redirect('/settings.php');
        }

        if (password_verify($newPassword, (string)$dbUser['password_hash'])) {
            flash_set('danger', 'New password must be different from your current password.');
            redirect('/settings.php');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$newHash, $userId]);
        flash_set('success', 'Password updated successfully.');
        redirect('/settings.php');
    }

    flash_set('danger', 'Unknown settings action.');
    redirect('/settings.php');
}

include __DIR__ . '/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
      <div>
        <h1 class="page-title mb-0">Settings</h1>
        <p class="page-subtitle">Manage your account details and security</p>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h5 mb-3">Update Username</h2>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_username">

              <div class="mb-3">
                <label class="form-label" for="settings_username">Username</label>
                <input
                  id="settings_username"
                  class="form-control"
                  name="username"
                  value="<?= e((string)($u['username'] ?? '')) ?>"
                  required
                >
                <div class="form-text">3-20 characters, letters/numbers/underscore.</div>
              </div>

              <div class="mb-3">
                <label class="form-label" for="settings_username_password">Current Password</label>
                <input id="settings_username_password" class="form-control" name="current_password" type="password" required>
              </div>

              <button class="btn btn-primary" type="submit">Save Username</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h5 mb-3">Update Email</h2>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_email">

              <div class="mb-3">
                <label class="form-label" for="settings_email">Email</label>
                <input
                  id="settings_email"
                  class="form-control"
                  name="email"
                  type="email"
                  value="<?= e((string)($u['email'] ?? '')) ?>"
                  required
                >
              </div>

              <div class="mb-3">
                <label class="form-label" for="settings_email_password">Current Password</label>
                <input id="settings_email_password" class="form-control" name="current_password" type="password" required>
              </div>

              <button class="btn btn-primary" type="submit">Save Email</button>
            </form>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <h2 class="h5 mb-3">Change Password</h2>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="action" value="update_password">

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label" for="settings_current_password">Current Password</label>
                  <input id="settings_current_password" class="form-control" name="current_password" type="password" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="settings_new_password">New Password</label>
                  <input id="settings_new_password" class="form-control" name="new_password" type="password" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="settings_confirm_password">Confirm New Password</label>
                  <input id="settings_confirm_password" class="form-control" name="confirm_password" type="password" required>
                </div>
              </div>

              <div class="mt-3">
                <button class="btn btn-primary" type="submit">Update Password</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
