<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();

$pending = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
$active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$challs = (int)$pdo->query('SELECT COUNT(*) FROM challenges')->fetchColumn();
$solves = (int)$pdo->query('SELECT COUNT(*) FROM solves')->fetchColumn();
$announcements = (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn();

$recentSolves = $pdo->query(
    'SELECT s.solved_at, u.username, c.title, s.points_awarded
     FROM solves s
     JOIN users u ON u.id = s.user_id
     JOIN challenges c ON c.id = s.challenge_id
     ORDER BY s.solved_at DESC
     LIMIT 10'
)->fetchAll();

$activePlayers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='user'")->fetchColumn();

$solveRateRows = $pdo->query(
    "SELECT c.id, c.title, COALESCE(sr.solve_count, 0) AS solve_count
     FROM challenges c
     LEFT JOIN (
       SELECT s.challenge_id, COUNT(*) AS solve_count
       FROM solves s
       JOIN users u ON u.id = s.user_id
       WHERE u.status='active' AND u.role='user'
       GROUP BY s.challenge_id
     ) sr ON sr.challenge_id = c.id
     ORDER BY solve_count ASC, c.title ASC"
)->fetchAll();

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
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern">
      <div class="card-body">
        <div class="stat-card-label">Announcements</div>
        <div class="stat-card-value text-primary"><?= e((string)$announcements) ?></div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="h5 mb-3">Quick Actions</h2>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/admin_users.php">Manage Users</a>
      <a class="btn btn-success" href="<?= e(BASE_URL) ?>/admin_challenges.php">Manage Challenges</a>
      <a class="btn btn-warning text-white" href="<?= e(BASE_URL) ?>/admin_solves.php">View Solves Log</a>
      <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/admin_announcements.php">Manage Announcements</a>
      <a class="btn btn-outline-dark" href="<?= e(BASE_URL) ?>/admin_audit.php">Admin Audit Log</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Recent Activity</h2>
          <small class="text-muted">Last 10 solves</small>
        </div>

        <?php if (!$recentSolves): ?>
          <div class="alert alert-info mb-0">No solve activity yet.</div>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($recentSolves as $solve): ?>
              <div class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold">@<?= e((string)$solve['username']) ?></div>
                  <div class="small text-muted"><?= e((string)$solve['title']) ?></div>
                  <div class="small text-muted"><?= e((string)$solve['solved_at']) ?></div>
                </div>
                <span class="badge text-bg-primary"><?= e((string)$solve['points_awarded']) ?> pts</span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Challenge Solve Rate</h2>
          <small class="text-muted">Based on <?= e((string)$activePlayers) ?> active players</small>
        </div>

        <div class="table-responsive">
          <table class="table table-striped align-middle">
            <thead>
              <tr>
                <th>Challenge</th>
                <th style="width: 120px;" class="text-end">Total Solves</th>
                <th style="width: 120px;" class="text-end">Solve Rate</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($solveRateRows as $row): ?>
                <?php
                $solveCount = (int)$row['solve_count'];
                $solveRate = ($activePlayers > 0) ? (($solveCount / $activePlayers) * 100) : 0.0;
                ?>
                <tr>
                  <td><?= e((string)$row['title']) ?></td>
                  <td class="text-end"><?= e((string)$solveCount) ?></td>
                  <td class="text-end"><?= e(number_format($solveRate, 1)) ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
