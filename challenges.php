<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_window_open()) {
    redirect('/403.php');
}

$u = current_user();

try {
    $challs = db()->query(
        "SELECT id,title,category,points,initial_points,floor_points,decay_solves,scoring_type,prerequisite_id
         FROM challenges
         WHERE is_active=1
         ORDER BY points ASC, id ASC"
    )->fetchAll();
} catch (Throwable $e) {
    // New columns may not exist yet - query with safe legacy aliases.
    $challs = db()->query(
        "SELECT id,title,category,points,points AS initial_points,100 AS floor_points,50 AS decay_solves,'static' AS scoring_type,NULL AS prerequisite_id
         FROM challenges
         WHERE is_active=1
         ORDER BY points ASC, id ASC"
    )->fetchAll();
}

$stmt = db()->prepare("SELECT challenge_id FROM solves WHERE user_id=?");
$stmt->execute([sanitize_int($u['id'] ?? 0)]);
$solved_ids = array_flip(array_map(static fn($x): int => (int)$x['challenge_id'], $stmt->fetchAll()));

$locked_ids = [];
foreach ($challs as $ch) {
    $prereq = (int)($ch['prerequisite_id'] ?? 0);
    if ($prereq > 0 && !isset($solved_ids[$prereq])) {
        $locked_ids[(int)$ch['id']] = true;
    }
}

$statsRows = db()->query("SELECT s.challenge_id, COUNT(*) AS solve_count, SUBSTRING_INDEX(GROUP_CONCAT(u.username ORDER BY s.solved_at ASC SEPARATOR ','), ',', 1) AS first_blood FROM solves s JOIN users u ON u.id=s.user_id GROUP BY s.challenge_id")->fetchAll();
$challengeStats = [];
foreach ($statsRows as $row) {
    $challengeStats[(int)$row['challenge_id']] = [
        'solve_count' => (int)$row['solve_count'],
        'first_blood' => (string)($row['first_blood'] ?? ''),
    ];
}
try {
    $totalPlayers = (int)db()->query("SELECT COUNT(*) FROM users WHERE status='active' AND role='user'")->fetchColumn();
} catch (Throwable $e) {
    $totalPlayers = 1;
}

$categories = [];
foreach ($challs as $challenge) {
    $categories[] = category_key((string)$challenge['category']);
}
$categories = array_values(array_unique($categories));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

include __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="page-title mb-0">Challenges</h1>
    <p class="page-subtitle">Browse challenge categories and start solving.</p>
  </div>
  <div class="d-flex align-items-center gap-2 challenge-search">
    <input id="challengeSearch" class="form-control" placeholder="Search challenges...">
    <div class="btn-group btn-group-sm" id="viewToggle">
      <button class="btn btn-outline-secondary active" data-view="grid" title="Grid">
        <i class="bi bi-grid-3x3-gap"></i>
      </button>
      <button class="btn btn-outline-secondary" data-view="list" title="List">
        <i class="bi bi-list-ul"></i>
      </button>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/leaderboard.php">Leaderboard</a>
  </div>
</div>

<ul class="nav nav-pills mb-3" id="challengeTabs">
  <li class="nav-item"><button type="button" class="nav-link active" data-filter="all">All</button></li>
  <?php foreach ($categories as $category): ?>
    <li class="nav-item"><button type="button" class="nav-link" data-filter="<?= e($category) ?>"><?= e(ucfirst($category)) ?></button></li>
  <?php endforeach; ?>
</ul>

<div class="row g-3" id="challengeGrid">
  <?php foreach ($challs as $c): ?>
    <?php
      $cid = (int)$c['id'];
      $isSolved = isset($solved_ids[$cid]);
      $catKey = category_key((string)$c['category']);
      $isLocked = (bool)($locked_ids[$cid] ?? false);
      $stats = $challengeStats[$cid] ?? ['solve_count' => 0, 'first_blood' => ''];
      $firstBlood = (string)$stats['first_blood'];
      $solveCount = (int)$stats['solve_count'];
      $scoringType = (string)($c['scoring_type'] ?? 'static');
      $displayPoints = (int)$c['points'];
      if ($scoringType === 'dynamic') {
          $displayPoints = calculate_dynamic_points(
              (int)($c['initial_points'] ?? 500),
              (int)($c['floor_points'] ?? 100),
              (int)($c['decay_solves'] ?? 50),
              $solveCount + 1
          );
      }
      $pts = $displayPoints;
      $diffClass = $pts <= 150 ? 'diff-easy' : ($pts <= 350 ? 'diff-medium' : 'diff-hard');
      $diffKey = $pts <= 150 ? 'easy' : ($pts <= 350 ? 'medium' : 'hard');
      $filledDots = ['easy' => 1, 'medium' => 2, 'hard' => 3][$diffKey];
      $sc = (int)($challengeStats[$cid]['solve_count'] ?? 0);
      $pct = $totalPlayers > 0 ? min(100, (int)floor($sc / $totalPlayers * 100)) : 0;
    ?>

    <div class="col-lg-4 col-md-6" data-category="<?= e($catKey) ?>" data-title="<?= e(strtolower((string)$c['title'])) ?>">
      <article class="card challenge-card<?= $isSolved ? ' is-solved' : '' ?><?= $isLocked ? ' is-locked' : '' ?>"<?= $isLocked ? ' title="Solve prerequisite challenge first"' : '' ?>>
        <div class="challenge-category-strip cat-strip-<?= e($catKey) ?>"></div>

        <?php if ($firstBlood !== ''): ?>
          <span class="first-blood-badge">First Blood @<?= e($firstBlood) ?></span>
        <?php endif; ?>

        <div class="card-body pt-3">
          <?php if ($isSolved): ?>
            <div class="solved-tick"><i class="bi bi-check"></i></div>
          <?php endif; ?>

          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <span class="badge bg-secondary"><?= e((string)$c['category']) ?></span>
            <div class="text-end">
              <span class="badge bg-primary"><?= e((string)$pts) ?> pts</span>
              <span class="diff-dots <?= e($diffClass) ?>">
                <?php for ($d=1; $d<=3; $d++): ?>
                  <span class="diff-dot <?= $d <= $filledDots ? 'on' : '' ?>"></span>
                <?php endfor; ?>
              </span>
              <?php if ($scoringType === 'dynamic'): ?>
                <div class="text-muted small mt-1">decays with solves</div>
              <?php endif; ?>
            </div>
          </div>

          <h3 class="challenge-title"><?= e((string)$c['title']) ?></h3>
          <p class="challenge-meta-text mb-3"><?= e((string)$solveCount) ?> solves</p>

          <div class="d-flex justify-content-between align-items-center gap-2">
            <?php if ($isLocked): ?>
              <span class="badge bg-secondary" title="Solve prerequisite challenge first">🔒 Locked</span>
            <?php elseif ($isSolved): ?>
              <span class="badge bg-success">Solved</span>
            <?php else: ?>
              <span class="badge text-bg-light border text-primary border-primary">Open</span>
            <?php endif; ?>

            <?php if ($isLocked): ?>
              <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Solve prerequisite challenge first">Locked</button>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$cid) ?>">Open</a>
            <?php endif; ?>
          </div>

          <div class="mt-3 pt-2 border-top">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted" style="font-size:.73rem;"><?= e((string)$sc) ?> solves</span>
              <span class="text-muted" style="font-size:.73rem;"><?= e((string)$pct) ?>%</span>
            </div>
            <div class="progress" style="height:3px;">
              <div class="progress-bar <?= $isSolved ? 'bg-success' : 'bg-primary' ?>"
                   style="width:<?= e((string)$pct) ?>%"></div>
            </div>
          </div>
        </div>
      </article>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$challs): ?>
  <div class="alert alert-info mt-3 mb-0">No active challenges available.</div>
<?php endif; ?>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('#challengeTabs .nav-link'));
  const cols = Array.from(document.querySelectorAll('#challengeGrid > div[data-category]'));
  const searchInput = document.getElementById('challengeSearch');

  if (!tabs.length) return;

  function applyFilter() {
    const active = document.querySelector('#challengeTabs .nav-link.active');
    const filterKey = active ? (active.dataset.filter || 'all') : 'all';
    const query = ((searchInput && searchInput.value) ? searchInput.value : '').toLowerCase().trim();

    cols.forEach((col) => {
      const key = col.getAttribute('data-category') || 'default';
      const title = (col.getAttribute('data-title') || '').toLowerCase();
      const filterOk = (filterKey === 'all' || filterKey === key);
      const searchOk = (query === '' || title.indexOf(query) !== -1);
      col.style.display = (filterOk && searchOk) ? '' : 'none';
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((item) => item.classList.remove('active'));
      tab.classList.add('active');
      applyFilter();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyFilter);
  }

  document.addEventListener('keydown', function (evt) {
    if (evt.key !== '/') return;

    const tag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
    if (tag === 'input' || tag === 'textarea' || (document.activeElement && document.activeElement.isContentEditable)) {
      return;
    }

    if (searchInput) {
      evt.preventDefault();
      searchInput.focus();
      searchInput.select();
    }
  });

  applyFilter();
})();
</script>

<script>
(function(){
  const saved = localStorage.getItem('ctf_view') || 'grid';
  const grid  = document.getElementById('challengeGrid');
  const btns  = document.querySelectorAll('#viewToggle [data-view]');
  if (!grid || !btns.length) return;

  function setView(v) {
    localStorage.setItem('ctf_view', v);
    if (v === 'list') {
      grid.classList.add('list-view');
      document.querySelectorAll('#challengeGrid > div').forEach(c => {
        c.classList.remove('col-md-6','col-lg-4'); c.classList.add('col-12');
      });
    } else {
      grid.classList.remove('list-view');
      document.querySelectorAll('#challengeGrid > div').forEach(c => {
        c.classList.remove('col-12'); c.classList.add('col-md-6','col-lg-4');
      });
    }
    btns.forEach(b => b.classList.toggle('active', b.dataset.view === v));
  }

  btns.forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));
  setView(saved);
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
