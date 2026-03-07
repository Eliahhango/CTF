<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_window_open()) {
    redirect('/403.php');
}

$u = current_user();

$challs = db()->query("SELECT id,title,category,points FROM challenges WHERE is_active=1 ORDER BY points ASC, id ASC")->fetchAll();

$stmt = db()->prepare("SELECT challenge_id FROM solves WHERE user_id=?");
$stmt->execute([sanitize_int($u['id'] ?? 0)]);
$solved_ids = array_flip(array_map(static fn($x): int => (int)$x['challenge_id'], $stmt->fetchAll()));

$statsRows = db()->query("SELECT s.challenge_id, COUNT(*) AS solve_count, SUBSTRING_INDEX(GROUP_CONCAT(u.username ORDER BY s.solved_at ASC SEPARATOR ','), ',', 1) AS first_blood FROM solves s JOIN users u ON u.id=s.user_id GROUP BY s.challenge_id")->fetchAll();
$challengeStats = [];
foreach ($statsRows as $row) {
    $challengeStats[(int)$row['challenge_id']] = [
        'solve_count' => (int)$row['solve_count'],
        'first_blood' => (string)($row['first_blood'] ?? ''),
    ];
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
      $solved = isset($solved_ids[$cid]);
      $catKey = category_key((string)$c['category']);
      $stats = $challengeStats[$cid] ?? ['solve_count' => 0, 'first_blood' => ''];
      $firstBlood = (string)$stats['first_blood'];
      $solveCount = (int)$stats['solve_count'];
    ?>

    <div class="col-lg-4 col-md-6" data-category="<?= e($catKey) ?>" data-title="<?= e(strtolower((string)$c['title'])) ?>">
      <article class="card challenge-card">
        <div class="challenge-category-strip cat-strip-<?= e($catKey) ?>"></div>

        <?php if ($firstBlood !== ''): ?>
          <span class="first-blood-badge">First Blood @<?= e($firstBlood) ?></span>
        <?php endif; ?>

        <div class="card-body pt-3">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <span class="badge bg-secondary"><?= e((string)$c['category']) ?></span>
            <span class="badge bg-primary"><?= e((string)$c['points']) ?> pts</span>
          </div>

          <h3 class="challenge-title"><?= e((string)$c['title']) ?></h3>
          <p class="challenge-meta-text mb-3"><?= e((string)$solveCount) ?> solves</p>

          <div class="d-flex justify-content-between align-items-center gap-2">
            <?php if ($solved): ?>
              <span class="badge bg-success">Solved</span>
            <?php else: ?>
              <span class="badge text-bg-light border text-primary border-primary">Open</span>
            <?php endif; ?>

            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$cid) ?>">Open</a>
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

<?php include __DIR__ . '/footer.php'; ?>
