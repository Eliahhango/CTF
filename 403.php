<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
http_response_code(403);
?>

<section class="status-screen">
  <div class="status-box status-403">
    <h1 class="status-title">[ 403 ]</h1>
    <p class="status-sub mb-2">UNAUTHORIZED ACCESS ATTEMPT LOGGED</p>
    <p class="small text-muted mb-1">Challenges are currently locked.</p>
    <p class="small text-muted mb-0">Your IP has been recorded.</p>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
