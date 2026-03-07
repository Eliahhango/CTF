<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();
$userId = sanitize_int($u['id'] ?? 0, 0, 1);

$points = user_points($userId);
$solved = solved_count($userId);

$stmt = db()->prepare('SELECT s.solved_at, c.id AS challenge_id, c.title, c.points FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=? ORDER BY s.solved_at DESC LIMIT 10');
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$totalChallenges = (int)db()->query('SELECT COUNT(*) FROM challenges WHERE is_active=1')->fetchColumn();
$remaining = max(0, $totalChallenges - $solved);
$completionPct = $totalChallenges > 0 ? (int)floor(($solved / $totalChallenges) * 100) : 0;

$rank = 0;
if (($u['role'] ?? '') === 'user') {
    $rankRows = db()->query("SELECT u.id, COALESCE(SUM(s.points_awarded),0) AS points, MAX(s.solved_at) AS last_solve FROM users u LEFT JOIN solves s ON s.user_id=u.id WHERE u.status='active' AND u.role='user' GROUP BY u.id ORDER BY points DESC, last_solve ASC")->fetchAll();
    $idx = 1;
    foreach ($rankRows as $row) {
        if ((int)$row['id'] === $userId) {
            $rank = $idx;
            break;
        }
        $idx++;
    }
}

$progressStmt = db()->prepare('SELECT solved_at, points_awarded FROM solves WHERE user_id=? ORDER BY solved_at ASC');
$progressStmt->execute([$userId]);
$progressRows = $progressStmt->fetchAll();
$progressLabels = [];
$progressValues = [];
$running = 0;
foreach ($progressRows as $row) {
    $running += (int)$row['points_awarded'];
    $progressLabels[] = (string)$row['solved_at'];
    $progressValues[] = $running;
}

$categoryStmt = db()->prepare('SELECT c.category, COUNT(*) AS cnt FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=? GROUP BY c.category ORDER BY cnt DESC, c.category ASC');
$categoryStmt->execute([$userId]);
$categoryRows = $categoryStmt->fetchAll();
$catLabels = array_map(static fn(array $row): string => (string)$row['category'], $categoryRows);
$catValues = array_map(static fn(array $row): int => (int)$row['cnt'], $categoryRows);

$nextStmt = db()->prepare(
    'SELECT id, title, category, points FROM challenges
     WHERE is_active=1 AND id NOT IN (SELECT challenge_id FROM solves WHERE user_id=?)
     ORDER BY points ASC LIMIT 1'
);
$nextStmt->execute([$userId]);
$nextChall = $nextStmt->fetch();

$actStmt = db()->query(
    'SELECT s.solved_at, u.username, u.id AS uid, c.title, c.id AS cid, c.category, s.points_awarded
     FROM solves s
     JOIN users u ON u.id = s.user_id
     JOIN challenges c ON c.id = s.challenge_id
     ORDER BY s.solved_at DESC
     LIMIT 10'
);
$activityFeed = $actStmt->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="page-title mb-0">Dashboard</h1>
    <p class="page-subtitle">Welcome back, @<?= e((string)($u['username'] ?? 'operator')) ?></p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if ($rank > 0): ?>
      <span class="badge bg-primary fs-6">Rank #<?= e((string)$rank) ?></span>
    <?php endif; ?>
    <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL) ?>/profile.php?username=<?= e(urlencode((string)($u['username'] ?? ''))) ?>">
      View Public Profile
    </a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:42px;height:42px;border-radius:10px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#2563eb;flex-shrink:0;">
            <i class="bi bi-lightning-charge-fill"></i>
          </div>
          <div>
            <div class="stat-card-label">Points</div>
            <div class="stat-card-value text-primary"><?= e((string)$points) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-solved">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:42px;height:42px;border-radius:10px;background:#ecfdf5;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#16a34a;flex-shrink:0;">
            <i class="bi bi-trophy-fill"></i>
          </div>
          <div>
            <div class="stat-card-label">Solved</div>
            <div class="stat-card-value text-success"><?= e((string)$solved) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-rank">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:42px;height:42px;border-radius:10px;background:#fffbeb;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#d97706;flex-shrink:0;">
            <i class="bi bi-bar-chart-fill"></i>
          </div>
          <div>
            <div class="stat-card-label">Rank</div>
            <div class="stat-card-value" style="color:#d97706;"><?= $rank > 0 ? e((string)$rank) : '--' ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-3 col-md-6">
    <div class="card stat-card-modern stat-remaining">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <div style="width:42px;height:42px;border-radius:10px;background:#fef2f2;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#dc2626;flex-shrink:0;">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div>
            <div class="stat-card-label">Remaining</div>
            <div class="stat-card-value text-danger"><?= e((string)$remaining) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <span class="fw-semibold">Progress</span>
      <span class="text-muted small"><?= e((string)$completionPct) ?>%</span>
    </div>
    <div class="progress mb-2" role="progressbar" aria-label="progress" aria-valuenow="<?= e((string)$completionPct) ?>" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-bar bg-primary" style="width: <?= e((string)$completionPct) ?>%"></div>
    </div>
    <small class="text-muted"><?= e((string)$solved) ?> of <?= e((string)$totalChallenges) ?> challenges solved</small>
  </div>
</div>

<?php if ($nextChall): ?>
  <div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3 py-3">
      <i class="bi bi-arrow-right-circle-fill text-primary fs-3 flex-shrink-0"></i>
      <div class="flex-grow-1">
        <div class="fw-bold text-primary" style="font-size:.8rem;letter-spacing:.05em;text-transform:uppercase;">
          Suggested Next
        </div>
        <div class="fw-semibold"><?= e((string)$nextChall['title']) ?></div>
        <div class="text-muted small"><?= e((string)$nextChall['category']) ?> · <?= e((string)$nextChall['points']) ?> pts</div>
      </div>
      <a href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$nextChall['id']) ?>"
         class="btn btn-sm btn-primary flex-shrink-0">Go →</a>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="chart-shell">
      <div class="fw-semibold mb-2">Score Over Time</div>
      <canvas id="scoreProgressChart"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="chart-shell">
      <div class="fw-semibold mb-2">Solved by Category</div>
      <canvas id="categoryBreakdownChart"></canvas>
    </div>
  </div>
</div>

<?php if ($activityFeed): ?>
<div class="card mb-3">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="h5 mb-0">Platform Activity</h2>
      <span class="badge bg-success" style="font-size:.7rem;">
        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#fff;margin-right:4px;
          animation:livePulse 1.5s infinite;"></span>LIVE
      </span>
    </div>
    <style>@keyframes livePulse{0%,100%{opacity:1}50%{opacity:.4}}</style>
    <div class="vstack gap-0">
      <?php foreach ($activityFeed as $i => $a):
        $catK = strtolower((string)$a['category']);
        $catColors = ['web'=>'#0ea5e9','crypto'=>'#8b5cf6','forensics'=>'#f59e0b','pwn'=>'#ef4444','linux'=>'#22c55e'];
        $dotColor = $catColors[$catK] ?? '#94a3b8';
        $isLast = $i === count($activityFeed) - 1;
      ?>
      <div class="d-flex align-items-center gap-3 py-2 <?= !$isLast ? 'border-bottom' : '' ?>">
        <span style="width:9px;height:9px;border-radius:50%;background:<?= e($dotColor) ?>;flex-shrink:0;"></span>
        <div class="flex-grow-1 text-truncate">
          <a href="<?= e(BASE_URL) ?>/profile.php?username=<?= e(urlencode((string)$a['username'])) ?>" class="fw-semibold">
            @<?= e((string)$a['username']) ?>
          </a>
          <span class="text-muted"> solved </span>
          <a href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$a['cid']) ?>">
            <?= e((string)$a['title']) ?>
          </a>
        </div>
        <span class="text-success fw-semibold small flex-shrink-0">+<?= e((string)$a['points_awarded']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <h2 class="h5 mb-3">Recent Solves</h2>

    <?php if (!$recent): ?>
      <div class="alert alert-info mb-0">No solves yet. Open a challenge and submit your first flag.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th style="width:72px;">Status</th>
              <th style="width:190px;">Time</th>
              <th>Challenge</th>
              <th style="width:100px;" class="text-end">Points</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><span class="badge bg-success">Solved</span></td>
                <td><?= e((string)$r['solved_at']) ?></td>
                <td>
                  <a href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$r['challenge_id']) ?>">
                    <?= e((string)$r['title']) ?>
                  </a>
                </td>
                <td class="text-end fw-semibold text-primary"><?= e((string)$r['points']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  if (!window.Chart) return;

  const progressLabels = <?= json_encode($progressLabels, JSON_UNESCAPED_UNICODE) ?>;
  const progressValues = <?= json_encode($progressValues, JSON_UNESCAPED_UNICODE) ?>;
  const categoryLabels = <?= json_encode($catLabels, JSON_UNESCAPED_UNICODE) ?>;
  const categoryValues = <?= json_encode($catValues, JSON_UNESCAPED_UNICODE) ?>;

  const progressCtx = document.getElementById('scoreProgressChart');
  if (progressCtx) {
    new Chart(progressCtx, {
      type: 'line',
      data: {
        labels: progressLabels,
        datasets: [{
          label: 'Points',
          data: progressValues,
          borderColor: '#2563eb',
          backgroundColor: 'rgba(37, 99, 235, 0.12)',
          tension: 0.35,
          pointRadius: 2,
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#334155' } } },
        scales: {
          x: { ticks: { color: '#64748b', maxTicksLimit: 6 }, grid: { color: '#e2e8f0' } },
          y: { ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
        },
      },
    });
  }

  const categoryCtx = document.getElementById('categoryBreakdownChart');
  if (categoryCtx) {
    new Chart(categoryCtx, {
      type: 'doughnut',
      data: {
        labels: categoryLabels,
        datasets: [{
          data: categoryValues,
          backgroundColor: ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#8b5cf6', '#0ea5e9'],
          borderColor: '#ffffff',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#334155', boxWidth: 12 },
          },
        },
      },
    });
  }
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
