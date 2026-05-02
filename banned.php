<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
?>

<div class="status-page-wrap">
  <div class="status-card" style="border-color:rgba(220,38,38,.35);">
    <span class="status-emoji">??</span>
    <h1 class="status-heading" style="color:#dc2626;">Account Suspended</h1>
    <p class="status-body">
      Your account has been suspended from this platform.<br>
      If you believe this is a mistake, contact your instructor directly.
    </p>
    <div class="status-actions">
      <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-outline-danger">Sign Out</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
