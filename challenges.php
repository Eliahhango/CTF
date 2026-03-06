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

/* Build category list for filter dropdown */
$categories = array_values(array_unique(array_map(fn($c) => (string)$c['category'], $challs)));
sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

include __DIR__ . '/header.php';
?>

<div class="challenge-controls">
  <div>
    <h2 class="h4 mb-1">Challenges</h2>
    <div class="text-muted small">Filter by category and open challenge terminals.</div>
  </div>

  <div class="d-flex gap-2 align-items-center">
    <select id="catFilter" class="form-select form-select-sm" style="max-width: 240px;">
      <option value="__all__">All Categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>

    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/leaderboard.php">./leaderboard</a>
  </div>
</div>

<div class="challenge-grid" id="challengeGrid">
  <?php foreach ($challs as $c): ?>
    <?php
      $cid = (int)$c['id'];
      $solved = isset($solved_ids[$cid]);
      $locked = false;

      $cat = strtolower(trim((string)$c['category']));
      if ($cat === 'web') {
        $catClass = 'cat-web';
      } elseif ($cat === 'forensics') {
        $catClass = 'cat-forensics';
      } elseif ($cat === 'crypto') {
        $catClass = 'cat-crypto';
      } elseif ($cat === 'pwn') {
        $catClass = 'cat-pwn';
      } else {
        $catClass = 'cat-default';
      }

      if ($locked) {
        $statusClass = 'status-locked';
        $statusLabel = '[LOCKED]';
      } elseif ($solved) {
        $statusClass = 'status-solved';
        $statusLabel = '[SOLVED ✓]';
      } else {
        $statusClass = 'status-open';
        $statusLabel = '[OPEN]';
      }
    ?>

    <article class="challenge-card" data-category="<?= e((string)$c['category']) ?>" data-points="<?= e((string)$c['points']) ?>">
      <div class="terminal-window-head d-flex justify-content-between align-items-center">
        <div>
          <span class="dot-red"></span>
          <span class="dot-amber"></span>
          <span class="dot-green"></span>
        </div>
        <span class="cat-tag <?= e($catClass) ?>"><?= e($c['category']) ?></span>
      </div>

      <h3 class="challenge-title"><?= e($c['title']) ?></h3>
      <div class="challenge-points">[<?= e((string)$c['points']) ?> pts]</div>

      <div class="d-flex justify-content-between align-items-center gap-2">
        <span class="status-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
        <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/challenge.php?id=<?= e((string)$cid) ?>">./open</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php if (!$challs): ?>
  <div class="alert alert-info mt-3 mb-0">No active challenges available.</div>
<?php endif; ?>

<script>
(function () {
  const sel = document.getElementById('catFilter');
  if (!sel) return;

  const cards = Array.from(document.querySelectorAll('#challengeGrid .challenge-card'));

  function applyFilter() {
    const value = sel.value;
    cards.forEach((card) => {
      const cat = card.getAttribute('data-category') || '';
      card.style.display = (value === '__all__' || cat === value) ? '' : 'none';
    });
  }

  sel.addEventListener('change', applyFilter);
  applyFilter();
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
