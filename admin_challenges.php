<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $action = $_POST['action'] ?? '';

  if ($action==='create') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $points = (int)($_POST['points'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $flag = trim($_POST['flag'] ?? '');
    if ($title===''||$category===''||$points<=0||$description===''||$flag==='') { flash_set('danger','All fields required.'); redirect('/admin_challenges.php'); }
    $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO challenges (title,category,points,description,flag_hash,is_active,created_at) VALUES (?,?,?,?,?,1,NOW())")
        ->execute([$title,$category,$points,$description,$flag_hash]);
    flash_set('success','Challenge created.');
    redirect('/admin_challenges.php');
  }

  if ($action==='toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) $pdo->prepare("UPDATE challenges SET is_active=1-is_active WHERE id=?")->execute([$id]);
    redirect('/admin_challenges.php');
  }

  if ($action==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) $pdo->prepare("UPDATE challenges SET is_active=0, title=CONCAT('[DELETED] ', title) WHERE id=?")->execute([$id]);
    flash_set('warning','Challenge deactivated.');
    redirect('/admin_challenges.php');
  }

  if ($action==='update') {
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $points = (int)($_POST['points'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $flag = trim($_POST['flag'] ?? '');
    if ($id<=0||$title===''||$category===''||$points<=0||$description==='') { flash_set('danger','Invalid update.'); redirect('/admin_challenges.php'); }

    if ($flag!=='') {
      $flag_hash = password_hash($flag, PASSWORD_DEFAULT);
      $pdo->prepare("UPDATE challenges SET title=?,category=?,points=?,description=?,flag_hash=? WHERE id=?")
          ->execute([$title,$category,$points,$description,$flag_hash,$id]);
    } else {
      $pdo->prepare("UPDATE challenges SET title=?,category=?,points=?,description=? WHERE id=?")
          ->execute([$title,$category,$points,$description,$id]);
    }
    flash_set('success','Challenge updated.');
    redirect('/admin_challenges.php');
  }
}

$challs = $pdo->query("SELECT id,title,category,points,is_active,created_at FROM challenges ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">root@admin-challenges:~</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 class="h4 mb-2">Manage Challenges</h2>
        <p class="small muted-cyber mb-0">Create, edit, activate, or deactivate challenge records.</p>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">./back</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Create Challenge</h3>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input type="hidden" name="action" value="create">

          <div class="mb-2">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Category</label>
            <input class="form-control" name="category" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Points</label>
            <input class="form-control" name="points" type="number" min="1" value="100" required>
          </div>

          <div class="mb-2">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="6" required></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Flag (hashed)</label>
            <input class="form-control" name="flag" required placeholder="ccd{...}">
          </div>

          <button class="btn btn-warning" type="submit">Create</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Existing Challenges</h3>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
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
                  <td><?= e($c['title']) ?></td>
                  <td><?= e($c['category']) ?></td>
                  <td><?= e((string)$c['points']) ?></td>
                  <td>
                    <span class="badge text-bg-<?= $c['is_active']?'success':'secondary' ?>">
                      <?= $c['is_active']?'active':'inactive' ?>
                    </span>
                  </td>
                  <td class="text-nowrap">
                    <form method="post" class="d-inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">Toggle</button>
                    </form>

                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#edit<?= e((string)$c['id']) ?>">Edit</button>

                    <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this challenge?')">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= e((string)$c['id']) ?>">
                      <button class="btn btn-sm btn-danger" type="submit">Deactivate</button>
                    </form>
                  </td>
                </tr>

                <div class="modal fade" id="edit<?= e((string)$c['id']) ?>" tabindex="-1" aria-hidden="true">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit #<?= e((string)$c['id']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <?php $st = db()->prepare("SELECT * FROM challenges WHERE id=?"); $st->execute([(int)$c['id']]); $full = $st->fetch(); ?>
                        <form method="post">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="id" value="<?= e((string)$full['id']) ?>">

                          <div class="mb-2">
                            <label class="form-label">Title</label>
                            <input class="form-control" name="title" value="<?= e($full['title']) ?>" required>
                          </div>

                          <div class="mb-2">
                            <label class="form-label">Category</label>
                            <input class="form-control" name="category" value="<?= e($full['category']) ?>" required>
                          </div>

                          <div class="mb-2">
                            <label class="form-label">Points</label>
                            <input class="form-control" name="points" type="number" min="1" value="<?= e((string)$full['points']) ?>" required>
                          </div>

                          <div class="mb-2">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="8" required><?= e($full['description']) ?></textarea>
                          </div>

                          <div class="mb-3">
                            <label class="form-label">New Flag (optional)</label>
                            <input class="form-control" name="flag" placeholder="leave empty to keep">
                          </div>

                          <button class="btn btn-primary" type="submit">Save</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
