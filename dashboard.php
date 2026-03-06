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

$barWidth = 12;
$filled = min($barWidth, max(0, (int)$solved));
$empty = $barWidth - $filled;
$progressBar = '[' . str_repeat('&#9608;', $filled) . str_repeat('&#9617;', $empty) . ']';

include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">ops@dashboard:~</span>
    </div>

    <h2 class="h4 mb-2">Dashboard</h2>
    <p class="small muted-cyber mb-0">
      Session owner: <?= e($u['username'] ?? 'user') ?> | Status: <?= e($u['status'] ?? 'active') ?>
    </p>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="stat-box">
      <div class="stat-label">Total Points</div>
      <div class="stat-value"><?= e((string)$points) ?></div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="stat-box">
      <div class="stat-label">Challenges Solved</div>
      <div class="stat-value"><?= e((string)$solved) ?></div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="stat-box">
      <div class="stat-label">Progress Buffer</div>
      <p class="ascii-progress mb-1"><?= $progressBar ?></p>
      <div class="small muted-cyber">Auto-scales at 12 blocks</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Control</h3>
        <div class="d-grid gap-2">
          <a href="<?= e(BASE_URL) ?>/challenges.php" class="btn btn-primary">./challenges</a>
          <a href="<?= e(BASE_URL) ?>/leaderboard.php" class="btn btn-outline-secondary">./leaderboard</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h3 class="h6 mb-0">Recent Solves</h3>
          <span class="small muted-cyber">latest 10 entries</span>
        </div>

        <?php if (!$recent): ?>
          <div class="alert alert-info mb-0">No solves yet. Start with low-point challenges.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th style="width: 190px;">Time</th>
                  <th>Challenge</th>
                  <th style="width: 90px;" class="text-end">Points</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent as $r): ?>
                  <tr>
                    <td class="small"><?= e($r['solved_at']) ?></td>
                    <td class="fw-semibold"><?= e($r['title']) ?></td>
                    <td class="text-end fw-bold"><?= e((string)$r['points']) ?></td>
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

<?php include __DIR__ . '/footer.php'; ?>
