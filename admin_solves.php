<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$rows = db()->query("
SELECT s.solved_at, u.username, c.title, s.points_awarded
FROM solves s
JOIN users u ON u.id=s.user_id
JOIN challenges c ON c.id=s.challenge_id
ORDER BY s.solved_at DESC
LIMIT 300
")->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">root@admin-solves:~</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 class="h4 mb-2">Solves Log</h2>
        <p class="small muted-cyber mb-0">Latest 300 solve events across all active users.</p>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">./back</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th style="width: 190px;">Time</th>
            <th>User</th>
            <th>Challenge</th>
            <th style="width: 90px;" class="text-end">Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="small"><?= e($r['solved_at']) ?></td>
              <td class="fw-semibold"><?= e($r['username']) ?></td>
              <td><?= e($r['title']) ?></td>
              <td class="text-end fw-bold"><?= e((string)$r['points_awarded']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
