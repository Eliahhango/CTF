<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_window_open()) {
    http_response_code(403);
    redirect('/403.php');
}

$u = current_user();

$challs = db()->query("SELECT id,title,category,points FROM challenges WHERE is_active=1 ORDER BY points ASC, id ASC")->fetchAll();

$stmt = db()->prepare("SELECT challenge_id FROM solves WHERE user_id=?");
$stmt->execute([(int)$u['id']]);
$solved_ids = array_flip(array_map(fn($x)=>(int)$x['challenge_id'], $stmt->fetchAll()));

$categories = array_values(array_unique(array_map(fn($c) => (string)$c['category'], $challs)));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

include __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h2 class="h5 mb-1">Challenges</h2>
    <div class="small text-muted">Top operators classify targets before exploitation.</div>
  </div>
  <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/leaderboard.php">[ LEADERBOARD ]</a>
</div>

<div class="filter-tabs" id="challengeTabs">
  <button type="button" class="filter-tab active" data-filter="all">[ ALL ]</button>
  <button type="button" class="filter-tab" data-filter="web">[ WEB ]</button>
  <button type="button" class="filter-tab" data-filter="crypto">[ CRYPTO ]</button>
  <button type="button" class="filter-tab" data-filter="forensics">[ FORENSICS ]</button>
  <button type="button" class="filter-tab" data-filter="pwn">[ PWN ]</button>
  <button type="button" class="filter-tab" data-filter="linux">[ LINUX ]</button>
</div>

<div class="challenge-grid" id="challengeGrid">
  <?php foreach ($challs as $c): ?>
    <?php
      $cid = (int)$c['id'];
      $solved = isset($solved_ids[$cid]);
      $locked = false;
      $catRaw = strtolower(trim((string)$c['category']));

      if (strpos($catRaw, 'web') !== false) {
        $catKey = 'web';
      } elseif (strpos($catRaw, 'crypto') !== false) {
        $catKey = 'crypto';
      } elseif (strpos($catRaw, 'forensic') !== false) {
        $catKey = 'forensics';
      } elseif (strpos($catRaw, 'pwn') !== false) {
        $catKey = 'pwn';
      } elseif (strpos($catRaw, 'linux') !== false) {
        $catKey = 'linux';
      } else {
        $catKey = 'default';
      }

      $stripClass = 'strip-' . $catKey;
      $catClass = 'cat-' . $catKey;
    ?>

    <article class="challenge-item" data-category="<?= e($catKey) ?>">
      <div class="challenge-strip <?= e($stripClass) ?>"></div>

      <div class="d-flex justify-content-between align-items-center">
        <span class="small text-muted terminal-mono">TARGET</span>
        <span class="challenge-cat <?= e($catClass) ?>"><?= e($c['category']) ?></span>
      </div>

      <h3 class="challenge-title"><?= e($c['title']) ?></h3>
      <div class="challenge-points">[ <?= e((string)$c['points']) ?> ]</div>

      <div class="d-flex justify-content-between align-items-center gap-2">
        <?php if ($locked): ?>
          <span class="challenge-status status-locked">[ LOCKED ]</span>
        <?php elseif ($solved): ?>
          <span class="challenge-status status-solved">[ PWNED &#10003; ]</span>
        <?php else: ?>
          <span class="challenge-status status-open">[ OPEN ]</span>
        <?php endif; ?>

        <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$cid) ?>">OPEN</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!$challs): ?>
  <div class="alert alert-info mt-3 mb-0">No active challenges available.</div>
<?php endif; ?>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('#challengeTabs .filter-tab'));
  const cards = Array.from(document.querySelectorAll('#challengeGrid .challenge-item'));

  if (!tabs.length) return;

  function applyFilter(filterKey) {
    cards.forEach((card) => {
      const key = card.getAttribute('data-category') || 'default';
      card.style.display = (filterKey === 'all' || key === filterKey) ? '' : 'none';
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((x) => x.classList.remove('active'));
      tab.classList.add('active');
      applyFilter(tab.dataset.filter || 'all');
    });
  });

  applyFilter('all');
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
