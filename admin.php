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
  <?= render_stat_card('Pending', (string)$pending, 'stat-rank', 'glow-amber') ?>
  <?= render_stat_card('Active Users', (string)$active, 'stat-points', 'glow-green') ?>
  <?= render_stat_card('Challenges', (string)$challs, 'stat-solved', 'glow-cyan') ?>
  <?= render_stat_card('Solves', (string)$solves, '', '') ?>
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
