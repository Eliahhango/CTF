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

<div class="alert alert-warning d-flex align-items-center gap-2 mb-3" role="alert">
  <i class="bi bi-shield-exclamation"></i>
  <div><strong>Admin Console:</strong> Restricted access area for system management.</div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-rank">
      <div class="card-body">
        <div class="stat-card-label">Pending</div>
        <div class="stat-card-value" style="color:#d97706;"><?= e((string)$pending) ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern">
      <div class="card-body">
        <div class="stat-card-label">Active Users</div>
        <div class="stat-card-value text-primary"><?= e((string)$active) ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-solved">
      <div class="card-body">
        <div class="stat-card-label">Challenges</div>
        <div class="stat-card-value text-success"><?= e((string)$challs) ?></div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-remaining">
      <div class="card-body">
        <div class="stat-card-label">Solves</div>
        <div class="stat-card-value text-danger"><?= e((string)$solves) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h2 class="h5 mb-3">Quick Actions</h2>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/admin_users.php">Manage Users</a>
      <a class="btn btn-success" href="<?= e(BASE_URL) ?>/admin_challenges.php">Manage Challenges</a>
      <a class="btn btn-warning text-white" href="<?= e(BASE_URL) ?>/admin_solves.php">View Solves Log</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
