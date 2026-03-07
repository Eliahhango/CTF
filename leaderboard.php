<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();
$page = sanitize_int($_GET['page'] ?? 1, 1, 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;
$cutoff = scoreboard_cutoff_datetime();

$totalPlayers = (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='user'")->fetchColumn();
$totalPages = max(1, (int)ceil($totalPlayers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$joinCondition = 'ON s.user_id=u.id';
$params = [];
if ($cutoff !== null) {
    $joinCondition .= ' AND s.solved_at <= ?';
    $params[] = $cutoff;
}

$sql = "SELECT u.id, u.username, COALESCE(SUM(s.points_awarded),0) AS points, COUNT(s.id) AS solves, MAX(s.solved_at) AS last_solve FROM users u LEFT JOIN solves s {$joinCondition} WHERE u.status='active' AND u.role='user' GROUP BY u.id ORDER BY points DESC, last_solve ASC LIMIT {$perPage} OFFSET {$offset}";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$topSql = "SELECT u.id, u.username FROM users u LEFT JOIN solves s {$joinCondition} WHERE u.status='active' AND u.role='user' GROUP BY u.id ORDER BY COALESCE(SUM(s.points_awarded),0) DESC, MAX(s.solved_at) ASC LIMIT 5";
$topStmt = db()->prepare($topSql);
$topStmt->execute($params);
$topUsers = $topStmt->fetchAll();

$topIds = array_map(static fn(array $r): int => (int)$r['id'], $topUsers);
$graphLabels = [];
$graphDatasets = [];

if ($topIds) {
    $inClause = implode(',', array_fill(0, count($topIds), '?'));
    $graphSql = "SELECT user_id, solved_at, points_awarded FROM solves WHERE user_id IN ({$inClause})";
    $graphParams = $topIds;

    if ($cutoff !== null) {
        $graphSql .= ' AND solved_at <= ?';
        $graphParams[] = $cutoff;
    }

    $graphSql .= ' ORDER BY solved_at ASC, id ASC';
    $graphStmt = db()->prepare($graphSql);
    $graphStmt->execute($graphParams);
    $graphRows = $graphStmt->fetchAll();

    $rowsByTs = [];
    foreach ($graphRows as $gr) {
        $ts = (string)$gr['solved_at'];
        $rowsByTs[$ts][] = [
            'user_id' => (int)$gr['user_id'],
            'points' => (int)$gr['points_awarded'],
        ];
    }

    $graphLabels = array_keys($rowsByTs);
    sort($graphLabels, SORT_STRING);

    foreach ($topUsers as $tu) {
        $uid = (int)$tu['id'];
        $running = [];
        foreach ($topUsers as $initUser) {
            $running[(int)$initUser['id']] = 0;
        }

        $series = [];
        foreach ($graphLabels as $label) {
            if (!empty($rowsByTs[$label])) {
                foreach ($rowsByTs[$label] as $event) {
                    $running[$event['user_id']] += $event['points'];
                }
            }
            $series[] = $running[$uid] ?? 0;
        }

        $graphDatasets[] = [
            'label' => '@' . (string)$tu['username'],
            'data' => $series,
        ];
    }
}

include __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="page-title mb-0">Leaderboard</h1>
    <p class="page-subtitle">
      Ranked by score<?= $cutoff ? ' (frozen at ' . e($cutoff) . ')' : '' ?>
    </p>
  </div>
  <button id="toggleScoreGraph" class="btn btn-outline-primary" type="button">Score Graph</button>
</div>

<div id="topScoreChartWrap" class="chart-shell mb-3" style="display:none;">
  <canvas id="topScoreChart"></canvas>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped align-middle leaderboard-table">
        <thead>
          <tr>
            <th style="width: 110px;">Rank</th>
            <th>User</th>
            <th style="width: 120px;" class="text-end">Points</th>
            <th style="width: 100px;" class="text-end">Solves</th>
            <th style="width: 180px;" class="text-end">Last Solve</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = $offset + 1; foreach ($rows as $r): ?>
            <?php
              $rank = $i++;
              $isCurrent = strtolower((string)($u['username'] ?? '')) === strtolower((string)$r['username']);
              $rowClasses = [];
              if ($rank === 1) {
                $rowClasses[] = 'rank-first';
              }
              if ($isCurrent) {
                $rowClasses[] = 'rank-current';
              }
            ?>
            <tr class="<?= e(implode(' ', $rowClasses)) ?>">
              <td class="fw-semibold">#<?= e((string)$rank) ?></td>
              <td>
                <a href="<?= e(BASE_URL) ?>/profile.php?username=<?= e(urlencode((string)$r['username'])) ?>">
                  @<?= e((string)$r['username']) ?>
                </a>
              </td>
              <td class="points-cell"><?= e((string)$r['points']) ?></td>
              <td class="text-end"><?= e((string)$r['solves']) ?></td>
              <td class="text-end text-muted"><?= e((string)($r['last_solve'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$rows): ?>
      <div class="alert alert-info mb-0 mt-2">No ranked users yet.</div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
      <small class="text-muted">Page <?= e((string)$page) ?> of <?= e((string)$totalPages) ?></small>

      <nav aria-label="Leaderboard pages">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)max(1, $page - 1)) ?>">Previous</a>
          </li>

          <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($p = $start; $p <= $end; $p++):
          ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
              <a class="page-link" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)$p) ?>"><?= e((string)$p) ?></a>
            </li>
          <?php endfor; ?>

          <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)min($totalPages, $page + 1)) ?>">Next</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</div>

<script>
(function () {
  const toggle = document.getElementById('toggleScoreGraph');
  const wrap = document.getElementById('topScoreChartWrap');
  const canvas = document.getElementById('topScoreChart');
  if (!toggle || !wrap || !canvas || !window.Chart) return;

  const labels = <?= json_encode($graphLabels, JSON_UNESCAPED_UNICODE) ?>;
  const datasetsRaw = <?= json_encode($graphDatasets, JSON_UNESCAPED_UNICODE) ?>;

  let chart = null;
  const palette = ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#8b5cf6'];

  toggle.addEventListener('click', function () {
    const isOpen = wrap.style.display !== 'none';
    wrap.style.display = isOpen ? 'none' : 'block';

    if (isOpen || chart || !labels.length || !datasetsRaw.length) {
      return;
    }

    chart = new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: datasetsRaw.map((d, idx) => ({
          label: d.label,
          data: d.data,
          borderColor: palette[idx % palette.length],
          backgroundColor: 'transparent',
          tension: 0.25,
          pointRadius: 1.5,
        })),
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { color: '#334155' } } },
        scales: {
          x: { ticks: { color: '#64748b', maxTicksLimit: 8 }, grid: { color: '#e2e8f0' } },
          y: { ticks: { color: '#64748b' }, grid: { color: '#e2e8f0' } },
        },
      },
    });
  });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
