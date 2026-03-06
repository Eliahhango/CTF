<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
?>

<div class="card">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">account@restricted:~</span>
    </div>

    <h2 class="h4 mb-3">Account Banned</h2>
    <p class="mb-2">Your account has been banned due to suspicious activity.</p>
    <p class="mb-2 muted-cyber">If this is a mistake, contact the instructor to request manual review.</p>
    <p class="mb-0 muted-cyber">Hack to Secure the World.</p>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
