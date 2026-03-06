<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_validate();
  $action = $_POST['action'] ?? '';
  $uid = (int)($_POST['user_id'] ?? 0);

  if ($uid>0) {
    if ($action==='approve') { db()->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'")->execute([$uid]); flash_set('success','User approved.'); }
    elseif ($action==='ban') { db()->prepare("UPDATE users SET status='banned' WHERE id=? AND role='user'")->execute([$uid]); flash_set('warning','User banned.'); }
    elseif ($action==='unban') { db()->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'")->execute([$uid]); flash_set('success','User unbanned.'); }
  }
  redirect('/admin_users.php');
}

$users = db()->query("SELECT id,username,email,role,status,created_at FROM users ORDER BY created_at DESC")->fetchAll();
include __DIR__ . '/header.php';
?>

<div class="card mb-4">
  <div class="card-body">
    <div class="terminal-window-head mb-3">
      <span class="dot-red"></span>
      <span class="dot-amber"></span>
      <span class="dot-green"></span>
      <span class="small muted-cyber ms-2">root@admin-users:~</span>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <h2 class="h4 mb-2">Manage Users</h2>
        <p class="small muted-cyber mb-0">Approve pending users, ban accounts, or restore access.</p>
      </div>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">./back</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
      <h3 class="h6 mb-0">User Table</h3>
      <span class="small muted-cyber"><?= e((string)count($users)) ?> records</span>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th style="width: 100px;">Role</th>
            <th style="width: 120px;">Status</th>
            <th style="width: 190px;">Created</th>
            <th style="width: 160px;" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $x): ?>
            <tr>
              <td class="fw-semibold"><?= e($x['username']) ?></td>
              <td class="small"><?= e($x['email']) ?></td>
              <td><?= e($x['role']) ?></td>
              <td>
                <span class="badge text-bg-<?= $x['status']==='active'?'success':($x['status']==='pending'?'warning':'danger') ?>">
                  <?= e($x['status']) ?>
                </span>
              </td>
              <td class="small"><?= e($x['created_at']) ?></td>
              <td class="text-end">
                <?php if ($x['role']==='admin'): ?>
                  <span class="text-muted">N/A</span>
                <?php else: ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= e((string)$x['id']) ?>">

                    <?php if ($x['status']==='pending'): ?>
                      <button class="btn btn-sm btn-primary" name="action" value="approve">Approve</button>
                    <?php elseif ($x['status']==='active'): ?>
                      <button class="btn btn-sm btn-danger" name="action" value="ban">Ban</button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-success" name="action" value="unban">Unban</button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
