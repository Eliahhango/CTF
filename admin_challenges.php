<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 30);

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
        $stmt = $pdo->prepare('INSERT INTO challenges (title,category,points,description,flag_hash,is_active,created_at) VALUES (?,?,?,?,?,1,NOW())');
        $stmt->execute([$title, $category, $points, $description, $flag_hash]);

        flash_set('success', 'Challenge created.');
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

        flash_set('success', 'Challenge updated.');
        redirect('/admin_challenges.php');
    }
}

$challs = $pdo->query('SELECT id,title,category,points,description,is_active,created_at FROM challenges ORDER BY created_at DESC')->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="term-block mb-3">
  <h2 class="section-head mb-2">// CHALLENGE_CONTROL</h2>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="small text-muted">Create, edit, activate, and retire challenge entries.</span>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="term-block h-100">
      <h3 class="section-head">// CREATE_CHALLENGE</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">

        <div class="mb-3">
          <label class="prompt-label">Title</label>
          <input class="form-control" name="title" required>
        </div>

        <div class="mb-3">
          <label class="prompt-label">Category</label>
          <input class="form-control" name="category" required>
        </div>

        <div class="mb-3">
          <label class="prompt-label">Points</label>
          <input class="form-control" name="points" type="number" min="1" value="100" required>
        </div>

        <div class="mb-3">
          <label class="prompt-label">Description</label>
          <textarea class="form-control" name="description" rows="6" required></textarea>
        </div>

        <div class="mb-3">
          <label class="prompt-label">Flag</label>
          <input class="form-control" name="flag" required placeholder="ccd{...}">
        </div>

        <button class="btn btn-amber" type="submit">Create</button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="section-head">// CHALLENGE_TABLE</h3>
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
                      <button class="btn btn-sm btn-cyan" type="submit">Toggle</button>
                    </form>

                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit<?= e((string)$c['id']) ?>">Edit</button>

                    <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this challenge?')">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <button class="btn btn-sm btn-red" type="submit">Deactivate</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php foreach ($challs as $c): ?>
          <div class="modal fade" id="edit<?= e((string)$c['id']) ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Edit #<?= e((string)$c['id']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">

                    <div class="mb-3">
                      <label class="prompt-label">Title</label>
                      <input class="form-control" name="title" value="<?= e((string)$c['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="prompt-label">Category</label>
                      <input class="form-control" name="category" value="<?= e((string)$c['category']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="prompt-label">Points</label>
                      <input class="form-control" name="points" type="number" min="1" value="<?= e((string)$c['points']) ?>" required>
                    </div>

                    <div class="mb-3">
                      <label class="prompt-label">Description</label>
                      <textarea class="form-control" name="description" rows="8" required><?= e((string)$c['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                      <label class="prompt-label">New Flag (optional)</label>
                      <input class="form-control" name="flag" placeholder="leave empty to keep">
                    </div>

                    <button class="btn btn-green" type="submit">Save</button>
                  </form>
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