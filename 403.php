<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
http_response_code(403);
?>

<div class="card">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">security@forbidden:~</span>
    </div>

    <h2 class="h4 mb-3">403 Forbidden</h2>
    <p class="mb-2">Challenges are currently locked.</p>
    <p class="mb-0 muted-cyber">Wait for the instructor to open the competition window.</p>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
