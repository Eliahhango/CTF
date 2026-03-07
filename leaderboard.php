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

    $running = [];
    foreach ($topUsers as $tu) {
        $running[(int)$tu['id']] = 0;
    }

    foreach ($topUsers as $tu) {
        $uid = (int)$tu['id'];
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

        // Reset running counters for next dataset build.
        foreach ($running as $k => $v) {
            $running[$k] = 0;
        }
    }
}

include __DIR__ . '/header.php';
?>

<div class="leader-header d-flex flex-wrap justify-content-between align-items-center gap-2">
  <div>
    <h2 class="leader-title glow-green">// LEADERBOARD</h2>
    <div class="leader-subtitle">
      TOP OPERATORS - RANKED BY SCORE<?= $cutoff ? ' (FROZEN @ ' . e($cutoff) . ')' : '' ?>
    </div>
  </div>
  <button id="toggleScoreGraph" class="btn btn-outline-secondary btn-sm" type="button">[ SCORE GRAPH ]</button>
</div>

<div id="topScoreChartWrap" class="chart-shell mb-3" style="display:none;">
  <canvas id="topScoreChart"></canvas>
</div>

<div class="scoreboard-wrap">
  <div class="table-responsive">
    <table class="score-table">
      <thead>
        <tr>
          <th style="width: 130px;">Rank</th>
          <th>User</th>
          <th style="width: 130px;" class="text-end">Points</th>
          <th style="width: 100px;" class="text-end">Solves</th>
          <th style="width: 210px;" class="text-end">Last Solve</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = $offset + 1; foreach ($rows as $r): ?>
          <?php
            $rank = $i++;
            $isCurrent = strtolower((string)($u['username'] ?? '')) === strtolower((string)$r['username']);
          ?>
          <tr class="<?= $isCurrent ? 'current-user-row' : '' ?>">
            <td>
              <?php if ($rank === 1): ?>
                <span class="rank-1">&#9654; #1</span>
              <?php elseif ($rank === 2): ?>
                <span class="rank-2">#2</span>
              <?php elseif ($rank === 3): ?>
                <span class="rank-3">#3</span>
              <?php else: ?>
                <span class="rank-rest">#<?= e((string)$rank) ?></span>
              <?php endif; ?>
            </td>
            <td><span class="score-user">@<?= e((string)$r['username']) ?></span></td>
            <td class="text-end"><span class="score-points"><?= e((string)$r['points']) ?></span></td>
            <td class="text-end"><span class="score-solves"><?= e((string)$r['solves']) ?></span></td>
            <td class="text-end"><?= e((string)($r['last_solve'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info mt-3 mb-0">No ranked users yet.</div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mt-3 gap-2 flex-wrap">
    <div class="small text-muted">Page <?= e((string)$page) ?> / <?= e((string)$totalPages) ?></div>
    <div class="d-flex gap-2 align-items-center">
      <?php if ($page > 1): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)($page - 1)) ?>">Prev</a>
      <?php endif; ?>

      <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($p = $start; $p <= $end; $p++):
      ?>
        <a class="btn btn-sm <?= $p === $page ? 'btn-green' : 'btn-outline-secondary' ?>" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)$p) ?>"><?= e((string)$p) ?></a>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/leaderboard.php?page=<?= e((string)($page + 1)) ?>">Next</a>
      <?php endif; ?>
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
  const palette = ['#00ff88', '#00d4ff', '#ffaa00', '#ff3355', '#b060ff'];

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
        plugins: {
          legend: { labels: { color: '#c8dce8' } },
        },
        scales: {
          x: { ticks: { color: '#7fa0b5', maxTicksLimit: 8 }, grid: { color: 'rgba(0,255,136,0.08)' } },
          y: { ticks: { color: '#7fa0b5' }, grid: { color: 'rgba(0,255,136,0.08)' } },
        },
      },
    });
  });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>