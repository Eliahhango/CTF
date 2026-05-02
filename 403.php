<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
http_response_code(403);
?>

<div class="status-page-wrap">
  <div class="status-card">
    <span class="status-emoji">??</span>
    <h1 class="status-heading" style="color:#dc2626;">Access Restricted</h1>
    <p class="status-body">
      Challenges are currently locked, or this area is not accessible to your account.<br>
      <span class="text-muted small">Your session has been logged.</span>
    </p>
    <div class="status-actions">
      <a href="<?= e(BASE_URL) ?>/dashboard.php" class="btn btn-outline-secondary">? Dashboard</a>
      <a href="<?= e(BASE_URL) ?>/info.php" class="btn btn-primary">View Event Info</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
