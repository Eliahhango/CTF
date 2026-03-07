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

    if ($action === 'create') {
        $title = sanitize_str($_POST['title'] ?? '', 120);
        $category = sanitize_str($_POST['category'] ?? '', 60);
        $points = sanitize_int($_POST['points'] ?? 0, 0, 1);
        $description = sanitize_str($_POST['description'] ?? '', 20000);
        $flag = sanitize_str($_POST['flag'] ?? '', 255);

        if ($title === '' || $category === '' || $points <= 0 || $description === '' || $flag === '') {
            flash_set('danger', 'All fields required.');
            redirect('/admin_challenges.php');
        }

        $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO challenges (title,category,points,description,flag_hash,is_active,created_at)
             VALUES (?,?,?,?,?,1,NOW())'
        );
        $stmt->execute([$title, $category, $points, $description, $flag_hash]);
        $challengeId = (int)$pdo->lastInsertId();

        $uploadSummary = process_challenge_attachment_batch($challengeId, $_FILES['attachments'] ?? null, 'create');
        $message = 'Challenge created.';

        if ($uploadSummary['uploaded'] > 0) {
            $message .= ' ' . $uploadSummary['uploaded'] . ' attachment(s) uploaded.';
        }

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
        }
        redirect('/admin_challenges.php');
    }

    if ($action === 'delete') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE challenges SET is_active=0, title=CONCAT('[DELETED] ', title) WHERE id=?");
            $stmt->execute([$id]);
        }

        flash_set('warning', 'Challenge deactivated.');
        redirect('/admin_challenges.php');
    }

    if ($action === 'update') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        $title = sanitize_str($_POST['title'] ?? '', 120);
        $category = sanitize_str($_POST['category'] ?? '', 60);
        $points = sanitize_int($_POST['points'] ?? 0, 0, 1);
        $description = sanitize_str($_POST['description'] ?? '', 20000);
        $flag = sanitize_str($_POST['flag'] ?? '', 255);

        if ($id <= 0 || $title === '' || $category === '' || $points <= 0 || $description === '') {
            flash_set('danger', 'Invalid update.');
            redirect('/admin_challenges.php');
        }

        if ($flag !== '') {
            $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE challenges SET title=?,category=?,points=?,description=?,flag_hash=? WHERE id=?');
            $stmt->execute([$title, $category, $points, $description, $flag_hash, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE challenges SET title=?,category=?,points=?,description=? WHERE id=?');
            $stmt->execute([$title, $category, $points, $description, $id]);
        }

        $uploadSummary = process_challenge_attachment_batch($id, $_FILES['attachments_edit'] ?? null, 'update');
        flash_set('success', 'Challenge updated.');

        if ($uploadSummary['uploaded'] > 0) {
            flash_set('success', $uploadSummary['uploaded'] . ' new attachment(s) uploaded.');
        }

        if ($uploadSummary['failed'] > 0) {
            flash_set('warning', $uploadSummary['failed'] . ' attachment(s) failed to upload.');
        }

        redirect('/admin_challenges.php');
    }
}

$challs = $pdo->query('SELECT id,title,category,points,description,is_active,created_at FROM challenges ORDER BY created_at DESC')->fetchAll();

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

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="section-head mb-2">Challenge Control</h2>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="small text-muted">Create, edit, activate, and retire challenge entries.</span>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="section-head">Create Challenge</h3>
        <form method="post" enctype="multipart/form-data">
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
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="6" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Flag</label>
            <input class="form-control" name="flag" required placeholder="ccd{...}">
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
        <h3 class="section-head">Challenge Table</h3>
        <div class="table-responsive">
          <table class="table align-middle">
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
                <tr>
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
          <div class="modal fade" id="edit<?= e((string)$c['id']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Edit #<?= e((string)$c['id']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <form method="post" enctype="multipart/form-data" class="mb-3">
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

<?php include __DIR__ . '/footer.php'; ?>
