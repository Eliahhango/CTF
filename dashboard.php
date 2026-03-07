<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$u = current_user();
$points = user_points((int)$u['id']);
$solved = solved_count((int)$u['id']);

$stmt = db()->prepare("SELECT s.solved_at, c.title, c.points FROM solves s JOIN challenges c ON c.id=s.challenge_id WHERE s.user_id=? ORDER BY s.solved_at DESC LIMIT 10");
$stmt->execute([(int)$u['id']]);
$recent = $stmt->fetchAll();

$totalChallenges = (int)db()->query("SELECT COUNT(*) FROM challenges WHERE is_active=1")->fetchColumn();
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
    if ((int)$row['id'] === (int)$u['id']) {
      $rank = $idx;
      break;
    }
    $idx++;
  }
}

include __DIR__ . '/header.php';
?>

<div class="dashboard-header box-glow">
  <h2 class="operator-name">OPERATOR: @<?= e($u['username'] ?? 'unknown') ?></h2>
  <div class="operator-meta">
    <span class="rank-badge glow-amber">RANK: <?= $rank > 0 ? '#' . e((string)$rank) : 'N/A' ?></span>
    <span class="point-badge glow-green">POINTS: <?= e((string)$points) ?></span>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card stat-points">
    <div class="label">Points</div>
    <div class="value glow-green"><?= e((string)$points) ?></div>
  </div>

  <div class="stat-card stat-solved">
    <div class="label">Solved</div>
    <div class="value glow-cyan"><?= e((string)$solved) ?></div>
  </div>

  <div class="stat-card stat-rank">
    <div class="label">Rank</div>
    <div class="value glow-amber"><?= $rank > 0 ? e((string)$rank) : '--' ?></div>
  </div>

  <div class="stat-card stat-remain">
    <div class="label">Remaining</div>
    <div class="value"><?= e((string)$remaining) ?></div>
  </div>
</div>

<div class="progress-shell">
  <div class="progress-label">Completion</div>
  <div class="progress-line"><?= $asciiBar ?> <?= e((string)$completionPct) ?>%</div>
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
                <td><?= e($r['solved_at']) ?></td>
                <td><?= e($r['title']) ?></td>
                <td class="text-end"><?= e((string)$r['points']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
