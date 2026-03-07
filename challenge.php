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

$attemptsStmt = db()->prepare("SELECT COUNT(*) FROM solves WHERE challenge_id=?");
$attemptsStmt->execute([$id]);
$attemptsCount = (int)$attemptsStmt->fetchColumn();

$firstBloodStmt = db()->prepare("SELECT u.username FROM solves s JOIN users u ON u.id=s.user_id WHERE s.challenge_id=? ORDER BY s.solved_at ASC LIMIT 1");
$firstBloodStmt->execute([$id]);
$firstBlood = $firstBloodStmt->fetchColumn();

$hintText = '';
if (is_array($c) && array_key_exists('hint', $c) && trim((string)$c['hint']) !== '') {
  $hintText = trim((string)$c['hint']);
}

include __DIR__ . '/header.php';
?>

<div class="challenge-layout">
  <div>
    <div class="card mb-3">
      <div class="card-body">
        <div class="challenge-meta">
          <span class="challenge-cat cat-default"><?= e($c['category']) ?></span>
          <span class="challenge-points mb-0">[ <?= e((string)$c['points']) ?> ]</span>
          <span class="challenge-status <?= $solved ? 'status-solved' : 'status-open' ?>"><?= $solved ? '[ PWNED &#10003; ]' : '[ OPEN ]' ?></span>
        </div>

        <h1 class="h4"><?= e($c['title']) ?></h1>

        <div class="term-block challenge-description" style="white-space: pre-wrap;">
          <?= linkify($c['description']) ?>
        </div>

        <?php if ($hintText !== ''): ?>
          <details class="term-block hint-block">
            <summary>HINTS</summary>
            <div style="white-space: pre-wrap;"><?= linkify($hintText) ?></div>
          </details>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <aside>
    <div class="submit-panel box-glow">
      <div class="submit-title">// SUBMIT FLAG</div>

      <?php if ($solved): ?>
        <div class="alert alert-success mb-3">You already solved this challenge.</div>
      <?php else: ?>
        <form method="post" action="<?= e(BASE_URL) ?>/submit_flag.php">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="challenge_id" value="<?= e((string)$id) ?>">

          <div class="mb-3">
            <label class="prompt-label" for="flag">Flag</label>
            <input id="flag" class="form-control" name="flag" placeholder="ccd{...}" required>
          </div>

          <button class="btn auth-submit w-100" type="submit">Submit</button>
        </form>
      <?php endif; ?>

      <div class="submit-meta">
        ATTEMPTS: <?= e((string)$attemptsCount) ?> | FIRST BLOOD: <?= $firstBlood ? '@' . e((string)$firstBlood) : 'N/A' ?>
      </div>
    </div>
  </aside>
</div>

<?php include __DIR__ . '/footer.php'; ?>
