<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$sql = "
SELECT u.username,
       COALESCE(SUM(s.points_awarded),0) AS points,
       COUNT(s.id) AS solves,
       MAX(s.solved_at) AS last_solve
FROM users u
LEFT JOIN solves s ON s.user_id=u.id
WHERE u.status='active' and u.role='user'
GROUP BY u.id
ORDER BY points DESC, last_solve ASC
LIMIT 100
";
$rows = db()->query($sql)->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">rank@scoreboard:~</span>
    </div>

    <h2 class="h4 mb-2">Leaderboard</h2>
    <p class="small muted-cyber mb-0">
      Top operators sorted by score. Tie-breaker: earliest final solve timestamp.
    </p>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
      <h3 class="h6 mb-0">Top Process List</h3>
      <span class="small muted-cyber"><?= e((string)count($rows)) ?> active users</span>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle leaderboard-terminal">
        <thead>
          <tr>
            <th style="width: 78px;">Rank</th>
            <th>User</th>
            <th style="width: 120px;" class="text-end">Points</th>
            <th style="width: 100px;" class="text-end">Solves</th>
            <th style="width: 210px;" class="text-end">Last Solve</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; foreach ($rows as $r): ?>
            <?php
              $rank = $i++;
              if ($rank === 1) {
                $rankClass = 'rank-1';
              } elseif ($rank === 2) {
                $rankClass = 'rank-2';
              } elseif ($rank === 3) {
                $rankClass = 'rank-3';
              } else {
                $rankClass = '';
              }
            ?>
            <tr>
              <td>
                <span class="rank-chip <?= e($rankClass) ?>"><?= e((string)$rank) ?></span>
              </td>
              <td class="fw-semibold"><?= e($r['username']) ?></td>
              <td class="text-end fw-bold"><?= e((string)$r['points']) ?></td>
              <td class="text-end"><?= e((string)$r['solves']) ?></td>
              <td class="text-end small"><?= e($r['last_solve'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$rows): ?>
      <div class="alert alert-info mt-3 mb-0">No ranked users yet.</div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
