<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();
$userId = sanitize_int($u['id'] ?? 0, 0, 1);

$points = user_points($userId);
$solved = solved_count($userId);

$stmt = db()->prepare('SELECT s.solved_at, c.title, c.points FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=? ORDER BY s.solved_at DESC LIMIT 10');
$stmt->execute([$userId]);
$recent = $stmt->fetchAll();

$totalChallenges = (int)db()->query('SELECT COUNT(*) FROM challenges WHERE is_active=1')->fetchColumn();
$remaining = max(0, $totalChallenges - $solved);
$completionPct = $totalChallenges > 0 ? (int)floor(($solved / $totalChallenges) * 100) : 0;
$slots = 16;
$filled = min($slots, max(0, (int)round(($completionPct / 100) * $slots)));
$asciiBar = '[' . str_repeat('&#9608;', $filled) . str_repeat('&#9617;', $slots - $filled) . ']';

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

include __DIR__ . '/header.php';
?>

<div class="dashboard-header box-glow">
  <h2 class="operator-name">OPERATOR: @<?= e((string)($u['username'] ?? 'unknown')) ?></h2>
  <div class="operator-meta">
    <span class="rank-badge glow-amber">RANK: <?= $rank > 0 ? '#' . e((string)$rank) : 'N/A' ?></span>
    <span class="point-badge glow-green">POINTS: <?= e((string)$points) ?></span>
  </div>
</div>

<div class="stats-grid">
  <?= render_stat_card('Points', (string)$points, 'stat-points', 'glow-green') ?>
  <?= render_stat_card('Solved', (string)$solved, 'stat-solved', 'glow-cyan') ?>
  <?= render_stat_card('Rank', $rank > 0 ? (string)$rank : '--', 'stat-rank', 'glow-amber') ?>
  <?= render_stat_card('Remaining', (string)$remaining, 'stat-remain') ?>
</div>

<div class="progress-shell">
  <div class="progress-label">Completion</div>
  <div class="progress-line"><?= $asciiBar ?> <?= e((string)$completionPct) ?>%</div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="chart-shell">
      <div class="progress-label mb-2">Score Progression</div>
      <canvas id="scoreProgressChart"></canvas>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="chart-shell">
      <div class="progress-label mb-2">Solved by Category</div>
      <canvas id="categoryBreakdownChart"></canvas>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h3 class="section-head">// RECENT_SOLVES</h3>

    <?php if (!$recent): ?>
      <div class="alert alert-info mb-0">No solves yet. Open a challenge and submit your first flag.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table recent-solve-table align-middle">
          <thead>
            <tr>
              <th style="width:72px;">State</th>
              <th style="width:190px;">Time</th>
              <th>Challenge</th>
              <th style="width:100px;" class="text-end">Points</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><span class="solve-yes">&#10003;</span></td>
                <td><?= e((string)$r['solved_at']) ?></td>
                <td><?= e((string)$r['title']) ?></td>
                <td class="text-end"><?= e((string)$r['points']) ?></td>
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
          borderColor: '#00ff88',
          backgroundColor: 'rgba(0,255,136,0.2)',
          tension: 0.35,
          pointRadius: 2,
          pointBackgroundColor: '#00d4ff',
          fill: true,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#c8dce8' } } },
        scales: {
          x: { ticks: { color: '#7fa0b5', maxTicksLimit: 6 }, grid: { color: 'rgba(0,255,136,0.08)' } },
          y: { ticks: { color: '#7fa0b5' }, grid: { color: 'rgba(0,255,136,0.08)' } },
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
          backgroundColor: ['#00ff88', '#00d4ff', '#ffaa00', '#ff3355', '#b060ff', '#7fa0b5'],
          borderColor: '#060b14',
          borderWidth: 1,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#c8dce8', boxWidth: 12 },
          },
        },
      },
    });
  }
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>