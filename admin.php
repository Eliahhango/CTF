<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pending = (int)db()->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$active = (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$challs = (int)db()->query("SELECT COUNT(*) FROM challenges")->fetchColumn();
$solves = (int)db()->query("SELECT COUNT(*) FROM solves")->fetchColumn();

include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">root@admin-core:~</span>
    </div>

    <h2 class="h4 mb-2">Admin Panel</h2>
    <p class="small muted-cyber mb-0">Manage users, challenges, and solve telemetry.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="small muted-cyber mb-2">Pending</div>
        <div class="admin-stat-number neon-amber"><?= e((string)$pending) ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="small muted-cyber mb-2">Active Users</div>
        <div class="admin-stat-number neon-green"><?= e((string)$active) ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="small muted-cyber mb-2">Challenges</div>
        <div class="admin-stat-number neon-cyan"><?= e((string)$challs) ?></div>
      </div>
    </div>
  </div>

  <div class="col-md-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="small muted-cyber mb-2">Solves</div>
        <div class="admin-stat-number neon-red"><?= e((string)$solves) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h3 class="h6 mb-3">Admin Commands</h3>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/admin_users.php">./manage_users</a>
      <a class="btn btn-warning" href="<?= e(BASE_URL) ?>/admin_challenges.php">./manage_challenges</a>
      <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/admin_solves.php">./solves_log</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
