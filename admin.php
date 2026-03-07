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

<div class="admin-banner">
  <span class="dot-danger"></span>
  <span>// ADMIN CONSOLE - RESTRICTED ACCESS</span>
</div>

<div class="stats-grid mb-3">
  <div class="stat-card stat-rank">
    <div class="label">Pending</div>
    <div class="value glow-amber"><?= e((string)$pending) ?></div>
  </div>

  <div class="stat-card stat-points">
    <div class="label">Active Users</div>
    <div class="value glow-green"><?= e((string)$active) ?></div>
  </div>

  <div class="stat-card stat-solved">
    <div class="label">Challenges</div>
    <div class="value glow-cyan"><?= e((string)$challs) ?></div>
  </div>

  <div class="stat-card" style="border-left: 3px solid var(--purple);">
    <div class="label">Solves</div>
    <div class="value" style="color: var(--purple);"><?= e((string)$solves) ?></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h3 class="section-head">// ACTIONS</h3>
    <div class="admin-actions">
      <a class="cmd-action users" href="<?= e(BASE_URL) ?>/admin_users.php">[ manage_users ]</a>
      <a class="cmd-action challs" href="<?= e(BASE_URL) ?>/admin_challenges.php">[ manage_challenges ]</a>
      <a class="cmd-action solves" href="<?= e(BASE_URL) ?>/admin_solves.php">[ view_solves_log ]</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
