<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();
$u = current_user();
$adminId = sanitize_int($u['id'] ?? 0, 0, 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 30);

    if ($action === 'create') {
        $title = sanitize_str($_POST['title'] ?? '', 200);
        $body = sanitize_str($_POST['body'] ?? '', 20000);
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

        if ($title === '' || $body === '' || $adminId <= 0) {
            flash_set('danger', 'Title and body are required.');
            redirect('/admin_announcements.php');
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO announcements (title, body, is_pinned, created_by, created_at)
                 VALUES (?,?,?,?,NOW())'
            );
            $stmt->execute([$title, $body, $isPinned, $adminId]);
            $announcementId = (int)$pdo->lastInsertId();
            log_admin_action('post_announcement', 'announcement', $announcementId, 'title=' . $title . '; pinned=' . (string)$isPinned);

            flash_set('success', 'Announcement published.');
        } catch (Throwable $e) {
            flash_set('warning', 'Announcements table is not available yet. Run DB migrations first.');
        }
        redirect('/admin_announcements.php');
    }

    if ($action === 'delete') {
        $id = sanitize_int($_POST['id'] ?? 0, 0, 1);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id=?');
                $stmt->execute([$id]);
                log_admin_action('delete_announcement', 'announcement', $id, 'affected=' . (string)$stmt->rowCount());
                flash_set('warning', 'Announcement removed.');
            } catch (Throwable $e) {
                flash_set('warning', 'Announcements table is not available yet. Run DB migrations first.');
            }
        }
        redirect('/admin_announcements.php');
    }
}

try {
    $rows = $pdo->query(
        'SELECT a.id, a.title, a.body, a.is_pinned, a.created_at, u.username AS author
         FROM announcements a
         JOIN users u ON u.id = a.created_by
         ORDER BY a.is_pinned DESC, a.created_at DESC, a.id DESC'
    )->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <h1 class="page-title mb-0">Manage Announcements</h1>
    <p class="page-subtitle">Create platform-wide updates for participants</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Create Announcement</h2>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <div class="mb-3">
            <label class="form-label" for="ann_title">Title</label>
            <input id="ann_title" class="form-control" name="title" maxlength="200" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="ann_body">Body</label>
            <textarea id="ann_body" class="form-control" name="body" rows="7" required></textarea>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="ann_pinned" name="is_pinned" value="1">
            <label class="form-check-label" for="ann_pinned">Pin this announcement</label>
          </div>

          <button class="btn btn-primary" type="submit">Publish</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h2 class="h5 mb-3">Announcement History</h2>

        <?php if (!$rows): ?>
          <div class="alert alert-info mb-0">No announcements yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Title</th>
                  <th style="width: 120px;">Pinned</th>
                  <th style="width: 180px;">Created</th>
                  <th style="width: 120px;">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= e((string)$row['title']) ?></div>
                      <div class="text-muted small">by @<?= e((string)$row['author']) ?></div>
                    </td>
                    <td>
                      <span class="badge text-bg-<?= ((int)$row['is_pinned'] === 1) ? 'warning' : 'secondary' ?>">
                        <?= ((int)$row['is_pinned'] === 1) ? 'Pinned' : 'No' ?>
                      </span>
                    </td>
                    <td class="text-muted small"><?= e((string)$row['created_at']) ?></td>
                    <td>
                      <form method="post" onsubmit="return confirm('Delete this announcement?')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e((string)$row['id']) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
