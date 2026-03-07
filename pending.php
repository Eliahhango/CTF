<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
?>

<section class="status-screen">
  <div class="status-box status-pending">
    <h1 class="status-title">[ PENDING ]</h1>
    <p class="status-sub mb-2">ACCESS REQUEST QUEUED - AWAITING OPERATOR APPROVAL</p>
    <p class="small text-muted mb-0">Your instructor must approve your account before challenge access is enabled.</p>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
