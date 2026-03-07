<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

if (!challenges_are_open() || !challenges_window_open()) {
    redirect('/403.php');
}

$u = current_user();
$id = sanitize_int($_GET['id'] ?? 0, 0, 1);
if ($id <= 0) {
    redirect('/challenges.php');
}

$stmt = db()->prepare(
    'SELECT id,title,category,points,initial_points,floor_points,decay_solves,scoring_type,max_attempts,flag_type,description
     FROM challenges
     WHERE id=? AND is_active=1'
);
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash_set('danger', 'Challenge not found.');
    redirect('/challenges.php');
}

$stmt2 = db()->prepare('SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1');
$stmt2->execute([sanitize_int($u['id'] ?? 0), $id]);
$solved = (bool)$stmt2->fetchColumn();
$userId = sanitize_int($u['id'] ?? 0, 0, 1);

$maxAttempts = (int)($c['max_attempts'] ?? 0);
$wrongCount = 0;
if ($maxAttempts > 0) {
    $wStmt = db()->prepare('SELECT COUNT(*) FROM submissions WHERE user_id=? AND challenge_id=? AND is_correct=0');
    $wStmt->execute([$userId, $id]);
    $wrongCount = (int)$wStmt->fetchColumn();
}
$attemptsLeft = $maxAttempts > 0 ? max(0, $maxAttempts - $wrongCount) : -1;
$attsExhausted = $maxAttempts > 0 && $attemptsLeft === 0;
$flagType = (string)($c['flag_type'] ?? 'static');

$files = get_challenge_files($id);

$attemptsStmt = db()->prepare('SELECT COUNT(*) FROM solves WHERE challenge_id=?');
$attemptsStmt->execute([$id]);
$attemptsCount = (int)$attemptsStmt->fetchColumn();

$scoringType = (string)($c['scoring_type'] ?? 'static');
$displayPoints = (int)($c['points'] ?? 0);
if ($scoringType === 'dynamic') {
    $displayPoints = calculate_dynamic_points(
        (int)($c['initial_points'] ?? 500),
        (int)($c['floor_points'] ?? 100),
        (int)($c['decay_solves'] ?? 50),
        $attemptsCount + 1
    );
}

$hintsStmt = db()->prepare(
    'SELECT h.id, h.content, h.cost, h.sort_order, hu.id AS unlock_id
     FROM hints h
     LEFT JOIN hint_unlocks hu ON hu.hint_id = h.id AND hu.user_id = ?
     WHERE h.challenge_id = ?
     ORDER BY h.sort_order ASC, h.id ASC'
);
$hintsStmt->execute([$userId, $id]);
$hintRows = $hintsStmt->fetchAll();
$netPoints = user_points($userId);

$firstBloodStmt = db()->prepare('SELECT u.username FROM solves s JOIN users u ON u.id=s.user_id WHERE s.challenge_id=? ORDER BY s.solved_at ASC LIMIT 1');
$firstBloodStmt->execute([$id]);
$firstBlood = $firstBloodStmt->fetchColumn();

$solversStmt = db()->prepare('SELECT u.username FROM solves s JOIN users u ON u.id=s.user_id WHERE s.challenge_id=? ORDER BY s.solved_at ASC');
$solversStmt->execute([$id]);
$solverRows = $solversStmt->fetchAll();
$solverNames = array_map(static fn(array $row): string => (string)$row['username'], $solverRows);

$wrongStmt = db()->prepare('SELECT COUNT(*) FROM submissions WHERE user_id=? AND challenge_id=? AND is_correct=0');
$wrongStmt->execute([$userId, $id]);
$wrongAttempts = (int)$wrongStmt->fetchColumn();

if (!function_exists('render_challenge_desc')) {
    function render_challenge_desc(string $raw): string
    {
        $s = htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace('/```(.*?)```/s', '<pre class="bg-dark text-success p-3 rounded overflow-auto"><code>$1</code></pre>', $s) ?? $s;
        $s = preg_replace('/`([^`]+)`/', '<code class="bg-light px-1 rounded border" style="font-size:.88em;">$1</code>', $s) ?? $s;
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
        $s = nl2br($s);
        $s = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            static fn($m) => '<a href="' . htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener">' . $m[1] . '</a>',
            $s
        ) ?? $s;
        return $s;
    }
}

include __DIR__ . '/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body p-4">
        <?php
          $catKey = category_key((string)$c['category']);
          $catIconMap = ['web' => 'globe', 'crypto' => 'lock', 'forensics' => 'search', 'pwn' => 'terminal', 'linux' => 'terminal-fill'];
          $catIcon = $catIconMap[$catKey] ?? 'puzzle';
        ?>
        <div class="d-flex align-items-start gap-3 mb-3">
          <div class="cat-icon-box cat-<?= e($catKey) ?>">
            <i class="bi bi-<?= e($catIcon) ?>"></i>
          </div>
          <div class="flex-grow-1">
            <h1 class="page-title mb-1"><?= e((string)$c['title']) ?></h1>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <span class="badge bg-secondary"><?= e((string)$c['category']) ?></span>
              <span class="badge bg-primary"><?= e((string)$displayPoints) ?> pts</span>
              <span class="badge <?= $solved ? 'bg-success' : 'bg-light text-primary border border-primary' ?>">
                <?= $solved ? '✓ Solved' : 'Unsolved' ?>
              </span>
              <?php if ($firstBlood): ?>
                <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fcd34d;">
                  🩸 First Blood: @<?= e((string)$firstBlood) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($scoringType === 'dynamic'): ?>
          <p class="text-muted small mb-3">
            Current value updates as solves increase. Started at <?= e((string)$c['initial_points']) ?>, floor <?= e((string)$c['floor_points']) ?>.
          </p>
        <?php endif; ?>

        <div class="challenge-description mb-3">
          <?= render_challenge_desc((string)$c['description']) ?>
        </div>

        <div class="accordion" id="solverAccordion">
          <div class="accordion-item">
            <h2 class="accordion-header" id="solverHeading">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#solverCollapse" aria-expanded="false" aria-controls="solverCollapse">
                Solvers (<?= e((string)count($solverNames)) ?>)
              </button>
            </h2>
            <div id="solverCollapse" class="accordion-collapse collapse" aria-labelledby="solverHeading" data-bs-parent="#solverAccordion">
              <div class="accordion-body">
                <?php if (!$solverNames): ?>
                  <p class="text-muted mb-0">No solves recorded yet.</p>
                <?php else: ?>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($solverNames as $name): ?>
                      <span class="badge text-bg-light border">@<?= e($name) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Submit Flag</h2>

        <?php if ($files): ?>
          <div class="mb-3">
            <h3 class="h6 mb-2">Downloads</h3>
            <div class="vstack gap-2">
              <?php foreach ($files as $file): ?>
                <div class="d-flex align-items-center justify-content-between gap-2 border rounded p-2">
                  <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-file-earmark-arrow-down text-primary"></i>
                    <div>
                      <div class="fw-semibold"><?= e((string)$file['original_name']) ?></div>
                      <div class="text-muted small"><?= e(format_upload_size((int)$file['file_size'])) ?></div>
                    </div>
                  </div>
                  <a class="btn btn-sm btn-outline-primary"
                     href="<?= e(BASE_URL) ?>/download.php?file_id=<?= e((string)$file['id']) ?>&challenge_id=<?= e((string)$id) ?>">
                    Download
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <hr>
        <?php endif; ?>

        <?php if ($hintRows): ?>
          <div class="mb-3">
            <h3 class="h6 mb-2">Hints</h3>
            <div class="vstack gap-2">
              <?php foreach ($hintRows as $index => $hint): ?>
                <?php
                  $hintCost = max(0, (int)($hint['cost'] ?? 0));
                  $isUnlocked = $solved || !empty($hint['unlock_id']);
                  $canAfford = ($hintCost <= 0) || ($netPoints >= $hintCost);
                ?>
                <div class="border rounded p-2">
                  <?php if ($isUnlocked): ?>
                    <div class="alert alert-info mb-0" style="white-space: pre-wrap;">
                      <strong>Hint #<?= e((string)($index + 1)) ?>:</strong><br>
                      <?= nl2br(linkify((string)$hint['content']), false) ?>
                    </div>
                  <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                      <div class="text-muted small">
                        Hint #<?= e((string)($index + 1)) ?> <?= $hintCost > 0 ? ' - costs ' . e((string)$hintCost) . ' points' : ' - free' ?>
                      </div>
                      <form method="post" action="<?= e(BASE_URL) ?>/unlock_hint.php" class="m-0">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="hint_id" value="<?= e((string)$hint['id']) ?>">
                        <input type="hidden" name="challenge_id" value="<?= e((string)$id) ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit" <?= $canAfford ? '' : 'disabled' ?>>
                          <?= $hintCost > 0 ? 'Unlock hint for ' . e((string)$hintCost) . ' pts' : 'Unlock free hint' ?>
                        </button>
                      </form>
                    </div>
                    <?php if (!$canAfford): ?>
                      <div class="small text-danger mt-2">Insufficient points to unlock this hint.</div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <hr>
        <?php endif; ?>

        <?php if ($solved): ?>
          <div class="alert alert-success mb-3">You already solved this challenge.</div>
        <?php else: ?>
          <?php if ($attsExhausted): ?>
            <div class="alert alert-danger">
              <i class="bi bi-x-octagon"></i> You have used all <?= e((string)$maxAttempts) ?> attempts for this challenge.
            </div>
          <?php endif; ?>

          <form method="post" action="<?= e(BASE_URL) ?>/submit_flag.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="challenge_id" value="<?= e((string)$id) ?>">

            <div class="mb-3">
              <label class="form-label" for="flag">Flag</label>
              <input id="flag" class="form-control" name="flag" placeholder="ccd{...}" required>

              <?php if ($maxAttempts > 0 && !$attsExhausted): ?>
                <div class="d-flex align-items-center gap-2 mt-2">
                  <div class="progress flex-grow-1" style="height:5px;">
                    <div class="progress-bar <?= $attemptsLeft <= 2 ? 'bg-danger' : ($attemptsLeft <= 5 ? 'bg-warning' : 'bg-success') ?>"
                         style="width:<?= e((string)floor($attemptsLeft / $maxAttempts * 100)) ?>%"></div>
                  </div>
                  <small class="text-muted text-nowrap"><?= e((string)$attemptsLeft) ?> left</small>
                </div>
              <?php endif; ?>

              <?php if ($flagType === 'regex'): ?>
                <div class="form-text">⚙️ This challenge uses pattern-based flag matching.</div>
              <?php elseif ($flagType === 'case_insensitive'): ?>
                <div class="form-text">⚙️ Flag matching is case-insensitive.</div>
              <?php endif; ?>
            </div>

            <button class="btn btn-primary w-100" type="submit" <?= $attsExhausted ? 'disabled' : '' ?>>Submit</button>
          </form>

          <?php if ($wrongAttempts > 0): ?>
            <p class="text-danger small mt-2 mb-0">
              <i class="bi bi-x-circle"></i> <?= e((string)$wrongAttempts) ?> incorrect attempt(s)
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <hr>
        <p class="mb-1 text-muted small"><strong>Solves:</strong> <?= e((string)$attemptsCount) ?></p>
        <p class="mb-0 text-muted small"><strong>First Blood:</strong> <?= $firstBlood ? '@' . e((string)$firstBlood) : 'N/A' ?></p>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const flagInput = document.getElementById('flag');
  if (!flagInput) return;

  flagInput.addEventListener('paste', function () {
    setTimeout(function () {
      flagInput.value = (flagInput.value || '').trim();
    }, 0);
  });

  flagInput.addEventListener('blur', function () {
    flagInput.value = (flagInput.value || '').trim();
  });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
