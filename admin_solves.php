<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();
$perPage = 50;
$page = sanitize_int($_GET['page'] ?? 1, 1, 1);
$challengeId = sanitize_int($_GET['challenge_id'] ?? 0, 0, 0);
$userPrefix = sanitize_str($_GET['user'] ?? '', 50);
$viewParam = sanitize_str($_GET['view'] ?? 'correct', 20);
$view = ($viewParam === 'incorrect') ? 'incorrect' : 'correct';

$challengeOptions = $pdo->query('SELECT id, title FROM challenges ORDER BY title ASC')->fetchAll();

$filterConds = [];
$filterParams = [];

if ($challengeId > 0) {
    $filterConds[] = 'c.id = ?';
    $filterParams[] = $challengeId;
}

if ($userPrefix !== '') {
    $filterConds[] = 'u.username LIKE ?';
    $filterParams[] = $userPrefix . '%';
}

$correctWhere = $filterConds ? (' WHERE ' . implode(' AND ', $filterConds)) : '';
$incorrectWhere = ' WHERE sub.is_correct=0';
if ($filterConds) {
    $incorrectWhere .= ' AND ' . implode(' AND ', $filterConds);
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM solves s
     JOIN users u ON u.id = s.user_id
     JOIN challenges c ON c.id = s.challenge_id' . $correctWhere
);
$stmt->execute($filterParams);
$totalCorrect = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM submissions sub
     JOIN users u ON u.id = sub.user_id
     JOIN challenges c ON c.id = sub.challenge_id' . $incorrectWhere
);
$stmt->execute($filterParams);
$totalIncorrect = (int)$stmt->fetchColumn();

$totalRows = ($view === 'incorrect') ? $totalIncorrect : $totalCorrect;
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

if ($view === 'incorrect') {
    $sql =
        'SELECT sub.created_at AS event_time, u.username, c.title, sub.ip_addr
         FROM submissions sub
         JOIN users u ON u.id = sub.user_id
         JOIN challenges c ON c.id = sub.challenge_id' . $incorrectWhere .
        ' ORDER BY sub.created_at DESC
          LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filterParams);
    $rows = $stmt->fetchAll();
} else {
    $sql =
        'SELECT s.solved_at AS event_time, u.username, c.title, s.points_awarded
         FROM solves s
         JOIN users u ON u.id = s.user_id
         JOIN challenges c ON c.id = s.challenge_id' . $correctWhere .
        ' ORDER BY s.solved_at DESC
          LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($filterParams);
    $rows = $stmt->fetchAll();
}

$buildUrl = static function (array $overrides = []) use ($view, $challengeId, $userPrefix, $page): string {
    $params = [
        'view' => $view,
        'challenge_id' => $challengeId,
        'user' => $userPrefix,
        'page' => $page,
    ];

    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }

    if ((int)($params['challenge_id'] ?? 0) <= 0) {
        unset($params['challenge_id']);
    }
    if ((string)($params['user'] ?? '') === '') {
        unset($params['user']);
    }
    if ((int)($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }

    return BASE_URL . '/admin_solves.php' . ($params ? ('?' . http_build_query($params)) : '');
};

include __DIR__ . '/header.php';
?>

<div class="term-block mb-3">
  <h2 class="section-head mb-2">Solves Log</h2>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="small text-muted">Paginated event stream with correct and incorrect submission views.</span>
    <div class="d-flex align-items-center gap-2">
      <label class="small text-muted d-inline-flex align-items-center gap-1 mb-0">
        <input id="autoRefreshSolves" type="checkbox" class="form-check-input mt-0"> auto-refresh 30s
      </label>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="view" value="<?= e($view) ?>">

      <div class="col-lg-4">
        <label class="form-label" for="challengeFilter">Challenge</label>
        <select id="challengeFilter" name="challenge_id" class="form-select">
          <option value="0">All Challenges</option>
          <?php foreach ($challengeOptions as $challenge): ?>
            <option value="<?= e((string)$challenge['id']) ?>" <?= ((int)$challenge['id'] === $challengeId) ? 'selected' : '' ?>>
              <?= e((string)$challenge['title']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-lg-4">
        <label class="form-label" for="userFilter">User Prefix</label>
        <input
          id="userFilter"
          class="form-control"
          name="user"
          value="<?= e($userPrefix) ?>"
          placeholder="e.g. ali"
          autocomplete="off"
        >
      </div>

      <div class="col-lg-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Apply Filters</button>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/admin_solves.php?view=<?= e($view) ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= ($view === 'correct') ? 'active' : '' ?>" href="<?= e($buildUrl(['view' => 'correct', 'page' => 1])) ?>">
      Correct Solves (<?= e((string)$totalCorrect) ?>)
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= ($view === 'incorrect') ? 'active' : '' ?>" href="<?= e($buildUrl(['view' => 'incorrect', 'page' => 1])) ?>">
      Incorrect Submissions (<?= e((string)$totalIncorrect) ?>)
    </a>
  </li>
</ul>

<div class="card">
  <div class="card-body">
    <?php if (!$rows): ?>
      <div class="alert alert-info mb-0">No records match your current filters.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th style="width: 200px;">Time</th>
              <th>User</th>
              <th>Challenge</th>
              <?php if ($view === 'incorrect'): ?>
                <th style="width: 150px;">IP Address</th>
              <?php else: ?>
                <th style="width: 110px;" class="text-end">Points</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= e((string)$r['event_time']) ?></td>
                <td class="score-user">
                  <a href="<?= e(BASE_URL) ?>/profile.php?username=<?= e(urlencode((string)$r['username'])) ?>">
                    @<?= e((string)$r['username']) ?>
                  </a>
                </td>
                <td><?= e((string)$r['title']) ?></td>
                <?php if ($view === 'incorrect'): ?>
                  <td><?= e((string)$r['ip_addr']) ?></td>
                <?php else: ?>
                  <td class="text-end score-points"><?= e((string)$r['points_awarded']) ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        ?>
        <nav aria-label="Solves pagination" class="mt-3">
          <ul class="pagination mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= e($buildUrl(['page' => $page - 1])) ?>">Previous</a>
            </li>

            <?php for ($p = $start; $p <= $end; $p++): ?>
              <li class="page-item <?= ($p === $page) ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($buildUrl(['page' => $p])) ?>"><?= e((string)$p) ?></a>
              </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= e($buildUrl(['page' => $page + 1])) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
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
