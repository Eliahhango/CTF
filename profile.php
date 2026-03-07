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

$solveSql = "SELECT c.title, c.category, s.points_awarded, s.solved_at FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=?";
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

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="page-title mb-0">Public Profile</h1>
    <p class="page-subtitle">Player stats and solve history</p>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h2 class="h3 mb-2">@<?= e((string)$profileUser['username']) ?></h2>
        <p class="text-muted mb-3"><?= e($joinedLabel) ?></p>

        <div class="mb-3">
          <span class="badge bg-primary fs-6">#<?= e((string)$rank) ?></span>
        </div>

        <div class="mb-3">
          <div class="text-muted small">Total Points</div>
          <div class="display-6 fw-bold text-primary"><?= e((string)$totalPoints) ?></div>
        </div>

        <div class="mb-0">
          <div class="text-muted small">Total Solves</div>
          <div class="h3 mb-0"><?= e((string)$totalSolves) ?></div>
        </div>

        <?php if ($isYou): ?>
          <div class="alert alert-info mt-3 mb-0">This is you.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Solved Challenges</h2>

        <?php if (!$solvedRows): ?>
          <div class="alert alert-info mb-0">No solved challenges yet.</div>
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
                    <td><?= e((string)$row['title']) ?></td>
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
</div>

<div class="card">
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
