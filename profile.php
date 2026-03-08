<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();

$username = sanitize_str($_GET['username'] ?? '', 50);
$pdo = db();

$userStmt = $pdo->prepare(
    "SELECT id, username, role, status, created_at
     FROM users
     WHERE username=?
     LIMIT 1"
);
$userStmt->execute([$username]);
$profileUser = $userStmt->fetch();

if (
    $username === ''
    || !$profileUser
    || ($profileUser['role'] ?? '') !== 'user'
    || ($profileUser['status'] ?? '') !== 'active'
) {
    http_response_code(404);
    include __DIR__ . '/header.php';
    ?>
    <div class="card">
      <div class="card-body">
        <h1 class="page-title mb-2">Profile Not Found</h1>
        <p class="page-subtitle mb-3">The requested user profile could not be found.</p>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/index.php">Back Home</a>
      </div>
    </div>
    <?php
    include __DIR__ . '/footer.php';
    exit;
}

$profileUserId = (int)$profileUser['id'];
$currentUser = current_user();
$isYou = is_array($currentUser)
    && isset($currentUser['username'])
    && strcasecmp((string)$currentUser['username'], (string)$profileUser['username']) === 0;

$cutoff = scoreboard_cutoff_datetime();
$joinCondition = 'ON s.user_id=u.id';
$rankParams = [];
if ($cutoff !== null) {
    $joinCondition .= ' AND s.solved_at <= ?';
    $rankParams[] = $cutoff;
}

$rankSql = "SELECT u.id, COALESCE(SUM(s.points_awarded),0) AS points, COUNT(s.id) AS solves, MAX(s.solved_at) AS last_solve FROM users u LEFT JOIN solves s {$joinCondition} WHERE u.status='active' AND u.role='user' GROUP BY u.id ORDER BY points DESC, last_solve ASC";
$rankStmt = $pdo->prepare($rankSql);
$rankStmt->execute($rankParams);
$rankRows = $rankStmt->fetchAll();

$rank = 0;
$totalPoints = 0;
$totalSolves = 0;
$pos = 1;
foreach ($rankRows as $row) {
    if ((int)$row['id'] === $profileUserId) {
        $rank = $pos;
        $totalPoints = (int)$row['points'];
        $totalSolves = (int)$row['solves'];
        break;
    }
    $pos++;
}

$solveSql = "SELECT c.id AS challenge_id, c.title, c.category, s.points_awarded, s.solved_at FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=?";
$solveParams = [$profileUserId];
if ($cutoff !== null) {
    $solveSql .= ' AND s.solved_at <= ?';
    $solveParams[] = $cutoff;
}
$solveSql .= ' ORDER BY s.solved_at DESC';
$solvesStmt = $pdo->prepare($solveSql);
$solvesStmt->execute($solveParams);
$solvedRows = $solvesStmt->fetchAll();

$categorySql = "SELECT c.category, COUNT(*) AS solve_count FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=?";
$categoryParams = [$profileUserId];
if ($cutoff !== null) {
    $categorySql .= ' AND s.solved_at <= ?';
    $categoryParams[] = $cutoff;
}
$categorySql .= ' GROUP BY c.category ORDER BY solve_count DESC, c.category ASC';
$categoryStmt = $pdo->prepare($categorySql);
$categoryStmt->execute($categoryParams);
$categoryRows = $categoryStmt->fetchAll();

$joinedTs = strtotime((string)$profileUser['created_at']);
$joinedLabel = $joinedTs ? 'Joined ' . date('F Y', $joinedTs) : 'Joined -';

$categoryColors = [
    'web' => '#0ea5e9',
    'crypto' => '#8b5cf6',
    'forensics' => '#f59e0b',
    'pwn' => '#ef4444',
    'linux' => '#22c55e',
    'default' => '#94a3b8',
];
$chartLabels = [];
$chartValues = [];
$chartColors = [];
foreach ($categoryRows as $row) {
    $label = (string)$row['category'];
    $key = category_key($label);
    $chartLabels[] = $label;
    $chartValues[] = (int)$row['solve_count'];
    $chartColors[] = $categoryColors[$key] ?? $categoryColors['default'];
}

include __DIR__ . '/header.php';
?>

<style>
.profile-banner {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  margin-left: calc(-50vw + 50%);
  margin-right: calc(-50vw + 50%);
  padding: 2.5rem 0;
  margin-bottom: 1.5rem;
}
.profile-banner .container { position: relative; }
.profile-avatar {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg,#2563eb,#7c3aed);
  display: flex; align-items: center; justify-content: center;
  font-size: 2rem; font-weight: 800; color: #fff;
  flex-shrink: 0;
}
.profile-stat-pill {
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 999px;
  color: #fff;
  font-size: .82rem;
  padding: .3rem .85rem;
  display: inline-block;
}
.cat-progress-bar {
  height: 6px; border-radius: 3px; background: #e2e8f0;
  overflow: hidden; margin-top: .3rem;
}
.cat-progress-fill { height: 100%; border-radius: 3px; }
</style>

<div class="profile-page">
  <div class="profile-banner">
    <div class="container">
      <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="profile-avatar">
          <?= e(strtoupper(substr((string)$profileUser['username'], 0, 1))) ?>
        </div>
        <div style="min-width:0;">
          <h1 style="color:#fff;font-size:1.7rem;font-weight:800;margin:0;word-break:break-all;">
            @<?= e((string)$profileUser['username']) ?>
            <?php if ($isYou): ?>
              <span class="badge bg-success ms-2" style="font-size:.6rem;vertical-align:middle;">You</span>
            <?php endif; ?>
          </h1>
          <div class="text-slate-400 mb-2" style="color:#94a3b8;font-size:.88rem;"><?= e($joinedLabel) ?></div>
          <div class="d-flex flex-wrap gap-2">
            <span class="profile-stat-pill">⚡ <?= e((string)$totalPoints) ?> pts</span>
            <span class="profile-stat-pill">✓ <?= e((string)$totalSolves) ?> solved</span>
            <span class="profile-stat-pill">🏆 Rank #<?= e((string)$rank) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-8">
      <div class="card h-100">
        <div class="card-body">
          <h2 class="h5 mb-3">Solved Challenges</h2>

          <?php if (!$solvedRows): ?>
            <div class="empty-state">
              <span class="empty-state-icon"><i class="bi bi-shield"></i></span>
              <div class="empty-state-title">No solves yet</div>
              <p class="empty-state-text">Start solving challenges to build your profile.</p>
              <?php if ($isYou): ?>
                <a href="<?= e(BASE_URL) ?>/challenges.php" class="btn btn-primary btn-sm mt-2">Browse Challenges</a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Challenge Title</th>
                    <th>Category</th>
                    <th class="text-end" style="width:100px;">Points</th>
                    <th style="width:180px;">Solved At</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($solvedRows as $row): ?>
                    <tr>
                      <td>
                        <a href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$row['challenge_id']) ?>">
                          <?= e((string)$row['title']) ?>
                        </a>
                      </td>
                      <td><?= e((string)$row['category']) ?></td>
                      <td class="text-end fw-semibold text-primary"><?= e((string)$row['points_awarded']) ?></td>
                      <td><?= e((string)$row['solved_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card mb-3">
        <div class="card-body">
          <h2 class="h6 fw-bold mb-3">Account Info</h2>
          <table class="table table-borderless table-sm mb-0">
            <tr><td class="text-muted ps-0">Username</td><td class="fw-semibold">@<?= e((string)$profileUser['username']) ?></td></tr>
            <tr><td class="text-muted ps-0">Joined</td><td><?= e($joinedLabel) ?></td></tr>
            <tr><td class="text-muted ps-0">Status</td><td><span class="badge bg-success">Active</span></td></tr>
          </table>
          <?php if ($isYou): ?>
            <a href="<?= e(BASE_URL) ?>/settings.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">
              Edit Settings
            </a>
          <?php endif; ?>
        </div>
      </div>

      <?php
        $catTotals = [];
        $catTotalRows = db()->query("SELECT category, COUNT(*) AS cnt FROM challenges WHERE is_active=1 GROUP BY category")->fetchAll();
        foreach ($catTotalRows as $ct) { $catTotals[strtolower((string)$ct['category'])] = (int)$ct['cnt']; }

        $catColorMap = ['web'=>'#0ea5e9','crypto'=>'#8b5cf6','forensics'=>'#f59e0b','pwn'=>'#ef4444','linux'=>'#22c55e','default'=>'#94a3b8'];
      ?>
      <?php if ($categoryRows): ?>
      <div class="card mb-3">
        <div class="card-body">
          <h2 class="h6 fw-bold mb-3">Category Progress</h2>
          <?php foreach ($categoryRows as $cr):
            $catLabel = (string)$cr['category'];
            $catK = strtolower($catLabel);
            $solved = (int)$cr['solve_count'];
            $total = (int)($catTotals[$catK] ?? $solved);
            $pct = $total > 0 ? min(100, round($solved / $total * 100)) : 100;
            $color = $catColorMap[$catK] ?? $catColorMap['default'];
          ?>
          <div class="mb-2">
            <div class="d-flex justify-content-between">
              <span class="small fw-semibold"><?= e($catLabel) ?></span>
              <span class="small text-muted"><?= e((string)$solved) ?>/<?= e((string)$total) ?></span>
            </div>
            <div class="cat-progress-bar">
              <div class="cat-progress-fill" style="width:<?= e((string)$pct) ?>%;background:<?= e($color) ?>;"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mt-3">
    <div class="card-body">
      <h2 class="h5 mb-3">Category Breakdown</h2>
      <?php if (!$chartLabels): ?>
        <div class="alert alert-info mb-0">No category data available yet.</div>
      <?php else: ?>
        <div class="chart-shell">
          <canvas id="profileCategoryChart"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div><!-- /.profile-page -->

<script>
(function () {
  if (!window.Chart) return;
  const canvas = document.getElementById('profileCategoryChart');
  if (!canvas) return;

  const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
  const values = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;
  const colors = <?= json_encode($chartColors, JSON_UNESCAPED_UNICODE) ?>;

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: 'Solves',
        data: values,
        backgroundColor: colors,
        borderRadius: 6,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          beginAtZero: true,
          ticks: { color: '#64748b', precision: 0 },
          grid: { color: '#e2e8f0' },
        },
        y: {
          ticks: { color: '#334155' },
          grid: { color: '#f1f5f9' },
        },
      },
    },
  });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
