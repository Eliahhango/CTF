<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$rows = db()->query('SELECT s.solved_at, u.username, c.title, s.points_awarded FROM solves s JOIN users u ON u.id=s.user_id JOIN challenges c ON c.id=s.challenge_id ORDER BY s.solved_at DESC LIMIT 300')->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="term-block mb-3">
  <h2 class="section-head mb-2">// SOLVES_LOG</h2>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="small text-muted">Latest 300 solve events across active operators.</span>
    <div class="d-flex align-items-center gap-2">
      <label class="small text-muted d-inline-flex align-items-center gap-1 mb-0">
        <input id="autoRefreshSolves" type="checkbox" class="form-check-input mt-0"> auto-refresh 30s
      </label>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th style="width: 200px;">Time</th>
            <th>User</th>
            <th>Challenge</th>
            <th style="width: 110px;" class="text-end">Points</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e((string)$r['solved_at']) ?></td>
              <td class="score-user">@<?= e((string)$r['username']) ?></td>
              <td><?= e((string)$r['title']) ?></td>
              <td class="text-end score-points"><?= e((string)$r['points_awarded']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  const key = 'admin_solves_auto_refresh';
  const checkbox = document.getElementById('autoRefreshSolves');
  if (!checkbox) return;

  checkbox.checked = localStorage.getItem(key) === '1';

  let timer = null;
  function updateTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
    if (checkbox.checked) {
      timer = setInterval(function () { location.reload(); }, 30000);
    }
  }

  checkbox.addEventListener('change', function () {
    localStorage.setItem(key, checkbox.checked ? '1' : '0');
    updateTimer();
  });

  updateTimer();
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>