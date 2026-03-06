<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();
if (!challenges_window_open()) {
    http_response_code(403);
    redirect('/403.php');
}


$u = current_user();
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) redirect('/challenges.php');

$stmt = db()->prepare("SELECT id,title,category,points,description FROM challenges WHERE id=? AND is_active=1");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { flash_set('danger','Challenge not found.'); redirect('/challenges.php'); }

$stmt2 = db()->prepare("SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1");
$stmt2->execute([(int)$u['id'],$id]);
$solved = (bool)$stmt2->fetchColumn();

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

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">challenge@node-<?= e((string)$id) ?>:~</span>
    </div>

    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
      <div>
        <h2 class="h4 mb-2"><?= e($c['title']) ?></h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span class="cat-tag <?= e($catClass) ?>"><?= e($c['category']) ?></span>
          <span class="challenge-points mb-0">[<?= e((string)$c['points']) ?> pts]</span>
        </div>
      </div>

      <span class="status-badge <?= $solved ? 'status-solved' : 'status-open' ?>">
        <?= $solved ? '[SOLVED ?]' : '[OPEN]' ?>
      </span>
    </div>

    <hr>

    <div class="terminal-block" style="white-space: pre-wrap;">
      <?= linkify($c['description']) ?>
      <span class="terminal-cursor">_</span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h3 class="h6 mb-3">Submit Flag</h3>

    <?php if ($solved): ?>
      <div class="alert alert-success mb-0">You already solved this challenge.</div>
    <?php else: ?>
      <form method="post" action="<?= e(BASE_URL) ?>/submit_flag.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="challenge_id" value="<?= e((string)$id) ?>">

        <div class="input-group">
          <input class="form-control" name="flag" placeholder="ccd{...}" required>
          <button class="btn btn-primary" type="submit">Submit</button>
        </div>

        <div class="form-text">Flags are case-sensitive.</div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
