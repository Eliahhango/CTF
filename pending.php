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
      <span class="small muted-cyber ms-2">account@pending-review:~</span>
    </div>

    <h2 class="h4 mb-3">Account Pending Approval</h2>
    <p class="mb-2 muted-cyber">Your instructor must approve your account before challenge access is enabled.</p>
    <p class="mb-2 muted-cyber">Confirm you are a DIT student and Cyber Club DIT member.</p>
    <p class="mb-0 muted-cyber">If this status persists, contact your instructor for verification.</p>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
