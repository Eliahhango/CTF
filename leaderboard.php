<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();

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

<div class="leader-header">
  <h2 class="leader-title glow-green">// LEADERBOARD</h2>
  <div class="leader-subtitle">TOP OPERATORS - RANKED BY SCORE</div>
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
        <?php $i = 1; foreach ($rows as $r): ?>
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
            <td><span class="score-user">@<?= e($r['username']) ?></span></td>
            <td class="text-end"><span class="score-points"><?= e((string)$r['points']) ?></span></td>
            <td class="text-end"><span class="score-solves"><?= e((string)$r['solves']) ?></span></td>
            <td class="text-end"><?= e($r['last_solve'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!$rows): ?>
    <div class="alert alert-info mt-3 mb-0">No ranked users yet.</div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>
