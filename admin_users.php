<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 40);
    $uid = sanitize_int($_POST['user_id'] ?? 0, 0, 1);

    if ($action === 'approve_all_pending') {
        $count = db()->exec("UPDATE users SET status='active' WHERE status='pending' AND role='user'");
        flash_set('success', 'Bulk approved ' . (string)$count . ' pending users.');
        redirect('/admin_users.php');
    }

    if ($uid > 0) {
        if ($action === 'approve') {
            db()->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'")->execute([$uid]);
            flash_set('success', 'User approved.');
        } elseif ($action === 'ban') {
            db()->prepare("UPDATE users SET status='banned' WHERE id=? AND role='user'")->execute([$uid]);
            flash_set('warning', 'User banned.');
        } elseif ($action === 'unban') {
            db()->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'")->execute([$uid]);
            flash_set('success', 'User unbanned.');
        }
    }

    redirect('/admin_users.php');
}

$users = db()->query("SELECT id,username,email,role,status,created_at FROM users ORDER BY created_at DESC")->fetchAll();
include __DIR__ . '/header.php';
?>

<div class="term-block mb-3">
  <h2 class="section-head mb-2">// USER_MANAGEMENT</h2>
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span class="small text-muted">Approve, ban, or restore operator accounts.</span>
    <div class="d-flex gap-2 align-items-center">
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-sm btn-amber" name="action" value="approve_all_pending" type="submit" onclick="return confirm('Approve all pending users?');">Bulk Approve Pending</button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th style="width: 100px;">Role</th>
            <th style="width: 120px;">Status</th>
            <th style="width: 190px;">Created</th>
            <th style="width: 180px;" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $x): ?>
            <tr>
              <td class="score-user">@<?= e((string)$x['username']) ?></td>
              <td><?= e((string)$x['email']) ?></td>
              <td><?= e((string)$x['role']) ?></td>
              <td>
                <span class="badge text-bg-<?= ($x['status'] === 'active') ? 'success' : (($x['status'] === 'pending') ? 'warning' : 'danger') ?>">
                  <?= e((string)$x['status']) ?>
                </span>
              </td>
              <td><?= e((string)$x['created_at']) ?></td>
              <td class="text-end">
                <?php if (($x['role'] ?? '') === 'admin'): ?>
                  <span class="text-muted">N/A</span>
                <?php else: ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= e((string)$x['id']) ?>">

                    <?php if (($x['status'] ?? '') === 'pending'): ?>
                      <button class="btn btn-sm btn-green" name="action" value="approve">Approve</button>
                    <?php elseif (($x['status'] ?? '') === 'active'): ?>
                      <button class="btn btn-sm btn-red" name="action" value="ban">Ban</button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-cyan" name="action" value="unban">Unban</button>
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