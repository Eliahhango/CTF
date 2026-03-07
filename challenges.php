<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_window_open()) {
    redirect('/403.php');
}

$u = current_user();
$challs = db()->query('SELECT id,title,category,points FROM challenges WHERE is_active=1 ORDER BY points ASC, id ASC')->fetchAll();

$stmt = db()->prepare('SELECT challenge_id FROM solves WHERE user_id=?');
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
    <h2 class="h5 mb-1">Challenges</h2>
    <div class="small text-muted">Top operators classify targets before exploitation.</div>
  </div>
  <div class="d-flex align-items-center gap-2 challenge-search">
    <input id="challengeSearch" class="form-control form-control-sm" placeholder="Search challenge... (press /)">
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/leaderboard.php">[ LEADERBOARD ]</a>
  </div>
</div>

<div class="filter-tabs" id="challengeTabs">
  <button type="button" class="filter-tab active" data-filter="all">[ ALL ]</button>
  <?php foreach ($categories as $category): ?>
    <button type="button" class="filter-tab" data-filter="<?= e($category) ?>">[ <?= e(strtoupper($category)) ?> ]</button>
  <?php endforeach; ?>
</div>

<div class="challenge-grid" id="challengeGrid">
  <?php foreach ($challs as $c): ?>
    <?php
      $cid = (int)$c['id'];
      $solved = isset($solved_ids[$cid]);
      $catKey = category_key((string)$c['category']);
      $stats = $challengeStats[$cid] ?? ['solve_count' => 0, 'first_blood' => ''];

      $cardData = [
        'id' => $cid,
        'title' => (string)$c['title'],
        'category' => (string)$c['category'],
        'points' => (int)$c['points'],
        'cat_key' => $catKey,
        'solve_count' => (int)$stats['solve_count'],
        'first_blood' => (string)$stats['first_blood'],
      ];

      echo render_challenge_card($cardData, $solved);
    ?>
  <?php endforeach; ?>
</div>

<?php if (!$challs): ?>
  <div class="alert alert-info mt-3 mb-0">No active challenges available.</div>
<?php endif; ?>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('#challengeTabs .filter-tab'));
  const cards = Array.from(document.querySelectorAll('#challengeGrid .challenge-item'));
  const searchInput = document.getElementById('challengeSearch');

  if (!tabs.length) return;

  function applyFilter() {
    const active = document.querySelector('#challengeTabs .filter-tab.active');
    const filterKey = (active && active.dataset.filter) ? active.dataset.filter : 'all';
    const q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();

    cards.forEach((card) => {
      const key = card.getAttribute('data-category') || 'default';
      const title = (card.getAttribute('data-title') || '').toLowerCase();
      const filterOk = (filterKey === 'all' || key === filterKey);
      const searchOk = (q === '' || title.indexOf(q) !== -1);
      card.style.display = (filterOk && searchOk) ? '' : 'none';
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((x) => x.classList.remove('active'));
      tab.classList.add('active');
      applyFilter();
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyFilter);
  }

  document.addEventListener('keydown', function (evt) {
    if (evt.key !== '/') return;

    const tag = (document.activeElement && document.activeElement.tagName) ? document.activeElement.tagName.toLowerCase() : '';
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
