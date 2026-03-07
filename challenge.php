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

$stmt = db()->prepare('SELECT id,title,category,points,description FROM challenges WHERE id=? AND is_active=1');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) {
    flash_set('danger', 'Challenge not found.');
    redirect('/challenges.php');
}

$stmt2 = db()->prepare('SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1');
$stmt2->execute([sanitize_int($u['id'] ?? 0), $id]);
$solved = (bool)$stmt2->fetchColumn();

$files = get_challenge_files($id);

$attemptsStmt = db()->prepare('SELECT COUNT(*) FROM solves WHERE challenge_id=?');
$attemptsStmt->execute([$id]);
$attemptsCount = (int)$attemptsStmt->fetchColumn();

$firstBloodStmt = db()->prepare('SELECT u.username FROM solves s JOIN users u ON u.id=s.user_id WHERE s.challenge_id=? ORDER BY s.solved_at ASC LIMIT 1');
$firstBloodStmt->execute([$id]);
$firstBlood = $firstBloodStmt->fetchColumn();

$solversStmt = db()->prepare('SELECT u.username FROM solves s JOIN users u ON u.id=s.user_id WHERE s.challenge_id=? ORDER BY s.solved_at ASC');
$solversStmt->execute([$id]);
$solverRows = $solversStmt->fetchAll();
$solverNames = array_map(static fn(array $row): string => (string)$row['username'], $solverRows);

$hintText = '';
if (is_array($c) && array_key_exists('hint', $c) && trim((string)$c['hint']) !== '') {
    $hintText = trim((string)$c['hint']);
}

include __DIR__ . '/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <span class="badge bg-secondary"><?= e((string)$c['category']) ?></span>
          <span class="badge bg-primary"><?= e((string)$c['points']) ?> pts</span>
          <span class="badge <?= $solved ? 'bg-success' : 'text-bg-light border text-primary border-primary' ?>"><?= $solved ? 'Solved' : 'Open' ?></span>
        </div>

        <h1 class="page-title mb-3"><?= e((string)$c['title']) ?></h1>

        <div class="challenge-description mb-3" style="white-space: pre-wrap;">
          <?= linkify((string)$c['description']) ?>
        </div>

        <?php if ($hintText !== ''): ?>
          <div class="alert alert-warning mb-3" style="white-space: pre-wrap;">
            <strong>Hint:</strong><br>
            <?= linkify($hintText) ?>
          </div>
        <?php endif; ?>

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

        <?php if ($solved): ?>
          <div class="alert alert-success mb-3">You already solved this challenge.</div>
        <?php else: ?>
          <form method="post" action="<?= e(BASE_URL) ?>/submit_flag.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="challenge_id" value="<?= e((string)$id) ?>">

            <div class="mb-3">
              <label class="form-label" for="flag">Flag</label>
              <input id="flag" class="form-control" name="flag" placeholder="ccd{...}" required>
            </div>

            <button class="btn btn-primary w-100" type="submit">Submit</button>
          </form>
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
