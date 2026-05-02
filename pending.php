<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
?>

<div class="status-page-wrap">
  <div class="status-card pending-card">
    <span class="status-emoji">?</span>
    <h1 class="status-heading" style="color:#d97706;">Awaiting Approval</h1>
    <p class="status-body">
      Your registration is pending instructor approval.<br>
      You'll be able to access challenges once your account is activated.<br>
      <span class="text-muted small">?? Tip: Contact your instructor if you've been waiting.</span>
    </p>
    <div class="status-actions">
      <a href="<?= e(BASE_URL) ?>/logout.php" class="btn btn-outline-secondary">Sign Out</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
