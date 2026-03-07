<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

/**
 * Upload all files from a challenge attachment input field.
 *
 * @return array{uploaded:int,failed:int}
 */
function process_challenge_attachment_batch(int $challengeId, ?array $uploadField, string $context): array
{
    if ($uploadField === null) {
        return ['uploaded' => 0, 'failed' => 0];
    }

    $uploaded = 0;
    $failed = 0;
    $entries = normalize_uploaded_file_entries($uploadField);

    foreach ($entries as $entry) {
        $error = (int)($entry['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        try {
            handle_challenge_upload($challengeId, $entry);
            $uploaded++;
        } catch (Throwable $e) {
            $failed++;
            app_log_error('challenge file upload failed', [
                'context' => $context,
                'challenge_id' => $challengeId,
                'filename' => sanitize_str((string)($entry['name'] ?? ''), 255),
                'error' => $e->getMessage(),
            ]);
        }
    }

    return ['uploaded' => $uploaded, 'failed' => $failed];
}

/**
 * Parse submitted hint text/cost fields into normalized rows.
 *
 * @return array<int,array{content:string,cost:int,sort_order:int}>
 */
function parse_submitted_hints(mixed $textsRaw, mixed $costsRaw): array
{
    $texts = is_array($textsRaw) ? $textsRaw : [];
    $costs = is_array($costsRaw) ? $costsRaw : [];

    $max = max(count($texts), count($costs));
    $rows = [];
    $order = 0;

    for ($i = 0; $i < $max; $i++) {
        $content = sanitize_str($texts[$i] ?? '', 20000);
        $cost = sanitize_int($costs[$i] ?? 0, 0, 0);

        if ($content === '') {
            continue;
        }

        $rows[] = [
            'content' => $content,
            'cost' => max(0, $cost),
            'sort_order' => $order++,
        ];
    }

    return $rows;
}

/**
 * Insert hint rows for a challenge.
 *
 * @param array<int,array{content:string,cost:int,sort_order:int}> $hintRows
 */
function insert_hints_for_challenge(PDO $pdo, int $challengeId, array $hintRows): void
{
    if ($challengeId <= 0 || $hintRows === []) {
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO hints (challenge_id, content, cost, sort_order) VALUES (?,?,?,?)');
    foreach ($hintRows as $row) {
        $stmt->execute([$challengeId, $row['content'], $row['cost'], $row['sort_order']]);
    }
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 30);

    if ($action === 'remove_file') {
        $fileId = sanitize_int($_POST['file_id'] ?? 0, 0, 1);
        if ($fileId <= 0) {
            flash_set('danger', 'Invalid file selection.');
            redirect('/admin_challenges.php');
        }

        try {
            delete_challenge_file($fileId);
            log_admin_action('remove_challenge_file', 'challenge_file', $fileId);
            flash_set('success', 'Attachment removed.');
        } catch (Throwable $e) {
            app_log_error('challenge file removal failed', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            flash_set('danger', 'Could not remove attachment.');
        }

        redirect('/admin_challenges.php');
    }

    if ($action === 'update_hints') {
        $challengeId = sanitize_int($_POST['id'] ?? 0, 0, 1);
        if ($challengeId <= 0) {
            flash_set('danger', 'Invalid challenge for hints update.');
            redirect('/admin_challenges.php');
        }

        $hintRows = parse_submitted_hints($_POST['hint_text'] ?? [], $_POST['hint_cost'] ?? []);

        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM hints WHERE challenge_id=?')->execute([$challengeId]);
            insert_hints_for_challenge($pdo, $challengeId, $hintRows);
            $pdo->commit();
            log_admin_action('update_challenge_hints', 'challenge', $challengeId, 'hint_count=' . (string)count($hintRows));
            flash_set('success', 'Hints updated.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            app_log_error('challenge hints update failed', [
                'challenge_id' => $challengeId,
                'error' => $e->getMessage(),
            ]);
            flash_set('danger', 'Could not update hints.');
        }

        redirect('/admin_challenges.php');
    }

    if ($action === 'create') {
        $title = sanitize_str($_POST['title'] ?? '', 120);
        $category = sanitize_str($_POST['category'] ?? '', 60);
        $points = sanitize_int($_POST['points'] ?? 0, 0, 1);
        $initialPoints = sanitize_int($_POST['initial_points'] ?? 500, 500, 1);
        $floorPoints = sanitize_int($_POST['floor_points'] ?? 100, 100, 1);
        $decaySolves = sanitize_int($_POST['decay_solves'] ?? 50, 50, 1);
        $scoringTypeRaw = strtolower(sanitize_str($_POST['scoring_type'] ?? 'static', 20));
        $scoringType = in_array($scoringTypeRaw, ['static', 'dynamic'], true) ? $scoringTypeRaw : 'static';
        $description = sanitize_str($_POST['description'] ?? '', 20000);
        $flag = sanitize_str($_POST['flag'] ?? '', 255);

        if ($title === '' || $category === '' || $points <= 0 || $description === '' || $flag === '') {
            flash_set('danger', 'All fields required.');
            redirect('/admin_challenges.php');
        }

        $initialPoints = max(1, $initialPoints);
        $floorPoints = max(1, $floorPoints);
        $decaySolves = max(1, $decaySolves);
        if ($initialPoints < $floorPoints) {
            $initialPoints = $floorPoints;
        }
        if ($scoringType === 'dynamic') {
            $points = $initialPoints;
        }

        $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO challenges (title,category,points,initial_points,floor_points,decay_solves,scoring_type,description,flag_hash,is_active,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,1,NOW())'
        );
        $stmt->execute([$title, $category, $points, $initialPoints, $floorPoints, $decaySolves, $scoringType, $description, $flag_hash]);
        $challengeId = (int)$pdo->lastInsertId();

        $hintRows = parse_submitted_hints($_POST['hint_text'] ?? [], $_POST['hint_cost'] ?? []);
        insert_hints_for_challenge($pdo, $challengeId, $hintRows);

        $uploadSummary = process_challenge_attachment_batch($challengeId, $_FILES['attachments'] ?? null, 'create');
        $message = 'Challenge created.';

        if ($uploadSummary['uploaded'] > 0) {
            $message .= ' ' . $uploadSummary['uploaded'] . ' attachment(s) uploaded.';
        }

        log_admin_action(
            'create_challenge',
            'challenge',
            $challengeId,
            'title=' . $title . '; category=' . $category . '; scoring=' . $scoringType
        );

        flash_set('success', $message);

        if ($uploadSummary['failed'] > 0) {
            flash_set('warning', $uploadSummary['failed'] . ' attachment(s) failed to upload.');
        }

        redirect('/admin_challenges.php');
    }

    if ($action === 'toggle') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE challenges SET is_active=1-is_active WHERE id=?');
            $stmt->execute([$id]);
            log_admin_action('toggle_challenge_status', 'challenge', $id, 'affected=' . (string)$stmt->rowCount());
        }
        redirect('/admin_challenges.php');
    }

    if ($action === 'delete') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE challenges SET is_active=0, title=CONCAT('[DELETED] ', title) WHERE id=?");
            $stmt->execute([$id]);
            log_admin_action('delete_challenge', 'challenge', $id, 'affected=' . (string)$stmt->rowCount());
        }

        flash_set('warning', 'Challenge deactivated.');
        redirect('/admin_challenges.php');
    }

    if ($action === 'update') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        $title = sanitize_str($_POST['title'] ?? '', 120);
        $category = sanitize_str($_POST['category'] ?? '', 60);
        $points = sanitize_int($_POST['points'] ?? 0, 0, 1);
        $initialPoints = sanitize_int($_POST['initial_points'] ?? 500, 500, 1);
        $floorPoints = sanitize_int($_POST['floor_points'] ?? 100, 100, 1);
        $decaySolves = sanitize_int($_POST['decay_solves'] ?? 50, 50, 1);
        $scoringTypeRaw = strtolower(sanitize_str($_POST['scoring_type'] ?? 'static', 20));
        $scoringType = in_array($scoringTypeRaw, ['static', 'dynamic'], true) ? $scoringTypeRaw : 'static';
        $description = sanitize_str($_POST['description'] ?? '', 20000);
        $flag = sanitize_str($_POST['flag'] ?? '', 255);

        if ($id <= 0 || $title === '' || $category === '' || $points <= 0 || $description === '') {
            flash_set('danger', 'Invalid update.');
            redirect('/admin_challenges.php');
        }

        $initialPoints = max(1, $initialPoints);
        $floorPoints = max(1, $floorPoints);
        $decaySolves = max(1, $decaySolves);
        if ($initialPoints < $floorPoints) {
            $initialPoints = $floorPoints;
        }
        if ($scoringType === 'dynamic') {
            $points = $initialPoints;
        }

        if ($flag !== '') {
            $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'UPDATE challenges
                 SET title=?,category=?,points=?,initial_points=?,floor_points=?,decay_solves=?,scoring_type=?,description=?,flag_hash=?
                 WHERE id=?'
            );
            $stmt->execute([$title, $category, $points, $initialPoints, $floorPoints, $decaySolves, $scoringType, $description, $flag_hash, $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE challenges
                 SET title=?,category=?,points=?,initial_points=?,floor_points=?,decay_solves=?,scoring_type=?,description=?
                 WHERE id=?'
            );
            $stmt->execute([$title, $category, $points, $initialPoints, $floorPoints, $decaySolves, $scoringType, $description, $id]);
        }

        $uploadSummary = process_challenge_attachment_batch($id, $_FILES['attachments_edit'] ?? null, 'update');
        flash_set('success', 'Challenge updated.');

        if ($uploadSummary['uploaded'] > 0) {
            flash_set('success', $uploadSummary['uploaded'] . ' new attachment(s) uploaded.');
        }

        if ($uploadSummary['failed'] > 0) {
            flash_set('warning', $uploadSummary['failed'] . ' attachment(s) failed to upload.');
        }

        log_admin_action(
            'update_challenge',
            'challenge',
            $id,
            'title=' . $title . '; category=' . $category . '; scoring=' . $scoringType
        );

        redirect('/admin_challenges.php');
    }
}

$challs = $pdo->query(
    'SELECT id,title,category,points,initial_points,floor_points,decay_solves,scoring_type,description,is_active,created_at
     FROM challenges
     ORDER BY created_at DESC'
)->fetchAll();

$challengeFilesById = [];
if ($challs) {
    $challengeIds = array_values(array_map(static fn(array $challenge): int => (int)$challenge['id'], $challs));
    $placeholders = implode(',', array_fill(0, count($challengeIds), '?'));
    $filesStmt = $pdo->prepare(
        "SELECT id, challenge_id, original_name, file_size, uploaded_at
         FROM challenge_files
         WHERE challenge_id IN ($placeholders)
         ORDER BY uploaded_at DESC, id DESC"
    );
    $filesStmt->execute($challengeIds);
    $fileRows = $filesStmt->fetchAll();

    foreach ($fileRows as $row) {
        $challengeId = (int)$row['challenge_id'];
        if (!isset($challengeFilesById[$challengeId])) {
            $challengeFilesById[$challengeId] = [];
        }
        $challengeFilesById[$challengeId][] = $row;
    }
}

$challengeHintsById = [];
if ($challs) {
    $challengeIds = array_values(array_map(static fn(array $challenge): int => (int)$challenge['id'], $challs));
    $placeholders = implode(',', array_fill(0, count($challengeIds), '?'));
    $hintsStmt = $pdo->prepare(
        "SELECT id, challenge_id, content, cost, sort_order
         FROM hints
         WHERE challenge_id IN ($placeholders)
         ORDER BY sort_order ASC, id ASC"
    );
    $hintsStmt->execute($challengeIds);
    $hintRows = $hintsStmt->fetchAll();

    foreach ($hintRows as $row) {
        $challengeId = (int)$row['challenge_id'];
        if (!isset($challengeHintsById[$challengeId])) {
            $challengeHintsById[$challengeId] = [];
        }
        $challengeHintsById[$challengeId][] = $row;
    }
}

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="section-head mb-2">Challenge Control</h2>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="small text-muted">Create, edit, activate, and retire challenge entries.</span>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL) ?>/admin_export_challenges.php">Export CSV</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="section-head">Create Challenge</h3>
        <form method="post" enctype="multipart/form-data" data-scoring-form>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <div class="mb-3">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Category</label>
            <input class="form-control" name="category" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Points</label>
            <input class="form-control" name="points" type="number" min="1" value="100" required>
          </div>

          <div class="mb-3">
            <label class="form-label d-block">Scoring Type</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="create_scoring_static" name="scoring_type" value="static" checked>
              <label class="form-check-label" for="create_scoring_static">Static</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" id="create_scoring_dynamic" name="scoring_type" value="dynamic">
              <label class="form-check-label" for="create_scoring_dynamic">Dynamic</label>
            </div>
          </div>

          <div class="dynamic-scoring-fields d-none">
            <div class="mb-3">
              <label class="form-label">Initial Points</label>
              <input class="form-control" name="initial_points" type="number" min="1" value="500">
            </div>

            <div class="mb-3">
              <label class="form-label">Floor Points</label>
              <input class="form-control" name="floor_points" type="number" min="1" value="100">
            </div>

            <div class="mb-3">
              <label class="form-label">Decay at N Solves</label>
              <input class="form-control" name="decay_solves" type="number" min="1" value="50">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="6" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Flag</label>
            <input class="form-control" name="flag" required placeholder="ccd{...}">
          </div>

          <div class="mb-3" data-hints-builder>
            <label class="form-label d-flex justify-content-between align-items-center">
              <span>Hints</span>
              <button type="button" class="btn btn-sm btn-outline-secondary add-hint-row">Add Hint</button>
            </label>
            <div class="vstack gap-2 hint-rows"></div>
            <div class="form-text">Each hint can have a point cost. Use 0 for free hints.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Attachments (optional)</label>
            <input class="form-control" type="file" name="attachments[]" multiple>
            <div class="form-text">
              Max <?= e((string)UPLOAD_MAX_MB) ?> MB per file. Allowed: <?= e(ALLOWED_EXTENSIONS) ?>
            </div>
          </div>

          <button class="btn btn-primary" type="submit">Create</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <h3 class="section-head mb-0">Challenge Table</h3>
          <input
            type="text"
            id="challengeFilterInput"
            class="form-control form-control-sm"
            style="max-width: 280px;"
            placeholder="Filter by title or category"
            autocomplete="off"
          >
        </div>
        <div class="table-responsive">
          <table class="table align-middle" id="challengeAdminTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Category</th>
                <th>Pts</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($challs as $c): ?>
                <?php $rowSearch = mb_strtolower((string)$c['title'] . ' ' . (string)$c['category']); ?>
                <tr data-search="<?= e($rowSearch) ?>">
                  <td><?= e((string)$c['id']) ?></td>
                  <td><?= e((string)$c['title']) ?></td>
                  <td><?= e((string)$c['category']) ?></td>
                  <td><?= e((string)$c['points']) ?></td>
                  <td>
                    <span class="badge text-bg-<?= $c['is_active'] ? 'success' : 'secondary' ?>"><?= $c['is_active'] ? 'active' : 'inactive' ?></span>
                  </td>
                  <td class="text-nowrap">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <button class="btn btn-sm btn-outline-primary" type="submit">Toggle</button>
                    </form>

                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= e((string)$c['id']) ?>">Edit</button>

                    <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this challenge?')">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php foreach ($challs as $c): ?>
          <?php $existingFiles = $challengeFilesById[(int)$c['id']] ?? []; ?>
          <?php $existingHints = $challengeHintsById[(int)$c['id']] ?? []; ?>
          <div class="modal fade" id="edit<?= e((string)$c['id']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Edit #<?= e((string)$c['id']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <form method="post" enctype="multipart/form-data" class="mb-3" data-scoring-form>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">

                    <div class="mb-3">
                      <label class="form-label">Title</label>
                      <input class="form-control" name="title" value="<?= e((string)$c['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Category</label>
                      <input class="form-control" name="category" value="<?= e((string)$c['category']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Points</label>
                      <input class="form-control" name="points" type="number" min="1" value="<?= e((string)$c['points']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label d-block">Scoring Type</label>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="edit_<?= e((string)$c['id']) ?>_scoring_static" name="scoring_type" value="static" <?= ($c['scoring_type'] ?? 'static') === 'static' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="edit_<?= e((string)$c['id']) ?>_scoring_static">Static</label>
                      </div>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" id="edit_<?= e((string)$c['id']) ?>_scoring_dynamic" name="scoring_type" value="dynamic" <?= ($c['scoring_type'] ?? 'static') === 'dynamic' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="edit_<?= e((string)$c['id']) ?>_scoring_dynamic">Dynamic</label>
                      </div>
                    </div>

                    <div class="dynamic-scoring-fields<?= ($c['scoring_type'] ?? 'static') === 'dynamic' ? '' : ' d-none' ?>">
                      <div class="mb-3">
                        <label class="form-label">Initial Points</label>
                        <input class="form-control" name="initial_points" type="number" min="1" value="<?= e((string)$c['initial_points']) ?>">
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Floor Points</label>
                        <input class="form-control" name="floor_points" type="number" min="1" value="<?= e((string)$c['floor_points']) ?>">
                      </div>

                      <div class="mb-3">
                        <label class="form-label">Decay at N Solves</label>
                        <input class="form-control" name="decay_solves" type="number" min="1" value="<?= e((string)$c['decay_solves']) ?>">
                      </div>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Description</label>
                      <textarea class="form-control" name="description" rows="8" required><?= e((string)$c['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                      <label class="form-label">New Flag (optional)</label>
                      <input class="form-control" name="flag" placeholder="leave empty to keep">
                    </div>

                    <div class="mb-3">
                      <label class="form-label">Add Attachments (optional)</label>
                      <input class="form-control" type="file" name="attachments_edit[]" multiple>
                    </div>

                    <button class="btn btn-primary" type="submit">Save</button>
                  </form>

                  <div class="border rounded p-3 mb-3">
                    <h6 class="mb-2">Hints</h6>
                    <form method="post" data-hints-builder>
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="update_hints">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">

                      <div class="vstack gap-2 hint-rows">
                        <?php foreach ($existingHints as $hint): ?>
                          <div class="border rounded p-2 hint-row">
                            <div class="mb-2">
                              <label class="form-label small mb-1">Hint text</label>
                              <textarea class="form-control" name="hint_text[]" rows="3"><?= e((string)$hint['content']) ?></textarea>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                              <div class="flex-grow-1">
                                <label class="form-label small mb-1">Cost (0=free)</label>
                                <input class="form-control" type="number" min="0" name="hint_cost[]" value="<?= e((string)$hint['cost']) ?>">
                              </div>
                              <button type="button" class="btn btn-sm btn-outline-danger remove-hint-row mt-4">Remove</button>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>

                      <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary add-hint-row">Add Hint</button>
                        <button class="btn btn-sm btn-primary" type="submit">Save Hints</button>
                      </div>
                    </form>
                  </div>

                  <div class="border rounded p-3">
                    <h6 class="mb-2">Existing Files</h6>
                    <?php if (!$existingFiles): ?>
                      <p class="text-muted mb-0">No attachments uploaded for this challenge.</p>
                    <?php else: ?>
                      <div class="vstack gap-2">
                        <?php foreach ($existingFiles as $file): ?>
                          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-2 border rounded">
                            <div>
                              <div class="fw-semibold"><?= e((string)$file['original_name']) ?></div>
                              <div class="text-muted small">
                                <?= e(format_upload_size((int)$file['file_size'])) ?>
                              </div>
                            </div>

                            <form method="post" onsubmit="return confirm('Remove this attachment?')">
                              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                              <input type="hidden" name="action" value="remove_file">
                              <input type="hidden" name="file_id" value="<?= e((string)$file['id']) ?>">
                              <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                            </form>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const forms = Array.from(document.querySelectorAll('form[data-scoring-form]'));
  forms.forEach((form) => {
    const radios = Array.from(form.querySelectorAll('input[name="scoring_type"]'));
    const dynamicFields = form.querySelector('.dynamic-scoring-fields');
    if (!radios.length || !dynamicFields) return;

    const sync = () => {
      const selected = radios.find((r) => r.checked);
      const isDynamic = selected && selected.value === 'dynamic';
      dynamicFields.classList.toggle('d-none', !isDynamic);
    };

    radios.forEach((radio) => radio.addEventListener('change', sync));
    sync();
  });

  function buildHintRow(text, cost) {
    const row = document.createElement('div');
    row.className = 'border rounded p-2 hint-row';
    row.innerHTML = `
      <div class="mb-2">
        <label class="form-label small mb-1">Hint text</label>
        <textarea class="form-control" name="hint_text[]" rows="3">${text || ''}</textarea>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="flex-grow-1">
          <label class="form-label small mb-1">Cost (0=free)</label>
          <input class="form-control" type="number" min="0" name="hint_cost[]" value="${Number.isFinite(cost) ? cost : 0}">
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger remove-hint-row mt-4">Remove</button>
      </div>
    `;
    return row;
  }

  const hintBuilders = Array.from(document.querySelectorAll('[data-hints-builder]'));
  hintBuilders.forEach((builder) => {
    const rowsWrap = builder.querySelector('.hint-rows');
    const addButtons = Array.from(builder.querySelectorAll('.add-hint-row'));
    if (!rowsWrap || !addButtons.length) return;

    function addHintRow(text = '', cost = 0) {
      rowsWrap.appendChild(buildHintRow(text, cost));
    }

    addButtons.forEach((btn) => {
      btn.addEventListener('click', () => addHintRow('', 0));
    });

    builder.addEventListener('click', (evt) => {
      const target = evt.target;
      if (!(target instanceof HTMLElement)) return;
      if (!target.classList.contains('remove-hint-row')) return;

      const row = target.closest('.hint-row');
      if (!row) return;
      row.remove();
    });

    if (rowsWrap.children.length === 0) {
      addHintRow('', 0);
    }
  });

  const challengeFilterInput = document.getElementById('challengeFilterInput');
  const challengeTable = document.getElementById('challengeAdminTable');
  if (challengeFilterInput && challengeTable) {
    const rows = Array.from(challengeTable.querySelectorAll('tbody tr'));
    challengeFilterInput.addEventListener('input', () => {
      const term = challengeFilterInput.value.trim().toLowerCase();
      rows.forEach((row) => {
        const source = row.getAttribute('data-search') || '';
        row.style.display = (term === '' || source.includes(term)) ? '' : 'none';
      });
    });
  }
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
