<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();
$sort = sanitize_str($_GET['sort'] ?? 'created_desc', 20);
if (!in_array($sort, ['created_desc', 'points_desc', 'points_asc'], true)) {
    $sort = 'created_desc';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 40);
    $uid = sanitize_int($_POST['user_id'] ?? 0, 0, 1);

    if ($action === 'approve_all_pending') {
        $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE status='pending' AND role='user'");
        $stmt->execute();
        $count = (int)$stmt->rowCount();
        log_admin_action('approve_all_pending', 'user', null, 'count=' . $count);
        flash_set('success', 'Bulk approved ' . (string)$count . ' pending users.');
        redirect('/admin_users.php');
    }

    if ($uid > 0) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'");
            $stmt->execute([$uid]);
            log_admin_action('approve_user', 'user', $uid, 'affected=' . (string)$stmt->rowCount());
            flash_set('success', 'User approved.');
        } elseif ($action === 'ban') {
            $stmt = $pdo->prepare("UPDATE users SET status='banned' WHERE id=? AND role='user'");
            $stmt->execute([$uid]);
            log_admin_action('ban_user', 'user', $uid, 'affected=' . (string)$stmt->rowCount());
            flash_set('warning', 'User banned.');
        } elseif ($action === 'unban') {
            $stmt = $pdo->prepare("UPDATE users SET status='active' WHERE id=? AND role='user'");
            $stmt->execute([$uid]);
            log_admin_action('unban_user', 'user', $uid, 'affected=' . (string)$stmt->rowCount());
            flash_set('success', 'User unbanned.');
        }
    }

    redirect('/admin_users.php');
}

$orderBy = 'u.created_at DESC';
if ($sort === 'points_desc') {
    $orderBy = 'total_points DESC, u.created_at DESC';
} elseif ($sort === 'points_asc') {
    $orderBy = 'total_points ASC, u.created_at DESC';
}

try {
    $users = $pdo->query(
        "SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at,
            COALESCE(sv.solve_count, 0) AS solve_count,
            (COALESCE(sv.solve_points, 0) - COALESCE(hd.points_deducted, 0)) AS total_points
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS solve_count, SUM(points_awarded) AS solve_points
            FROM solves
            GROUP BY user_id
         ) sv ON sv.user_id = u.id
         LEFT JOIN (
            SELECT user_id, SUM(points_deducted) AS points_deducted
            FROM hint_deductions
            GROUP BY user_id
         ) hd ON hd.user_id = u.id
         ORDER BY $orderBy"
    )->fetchAll();
} catch (Throwable $e) {
    $users = $pdo->query(
        "SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at,
            COALESCE(sv.solve_count, 0) AS solve_count,
            COALESCE(sv.solve_points, 0) AS total_points
         FROM users u
         LEFT JOIN (
            SELECT user_id, COUNT(*) AS solve_count, SUM(points_awarded) AS solve_points
            FROM solves
            GROUP BY user_id
         ) sv ON sv.user_id = u.id
         ORDER BY $orderBy"
    )->fetchAll();
}

$pointsSortNext = ($sort === 'points_desc') ? 'points_asc' : 'points_desc';
$pointsSortMarker = '';
if ($sort === 'points_desc') {
    $pointsSortMarker = '▼';
} elseif ($sort === 'points_asc') {
    $pointsSortMarker = '▲';
}

include __DIR__ . '/header.php';
?>

<div class="term-block mb-3">
  <h2 class="section-head mb-2">User Management</h2>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <label class="form-label mb-0" for="userSearchInput">Search Users</label>
      <input
        id="userSearchInput"
        type="text"
        class="form-control form-control-sm"
        style="max-width: 280px;"
        placeholder="Filter by username or email"
        autocomplete="off"
      >
    </div>

    <div class="table-responsive">
      <table class="table align-middle" id="adminUsersTable">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th style="width: 100px;">Role</th>
            <th style="width: 120px;">Status</th>
            <th style="width: 90px;" class="text-end">Solves</th>
            <th style="width: 120px;" class="text-end">
              <a class="text-decoration-none" href="<?= e(BASE_URL) ?>/admin_users.php?sort=<?= e($pointsSortNext) ?>">
                Points <?= e($pointsSortMarker) ?>
              </a>
            </th>
            <th style="width: 190px;">Created</th>
            <th style="width: 180px;" class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $x): ?>
            <?php $searchText = mb_strtolower((string)$x['username'] . ' ' . (string)$x['email']); ?>
            <tr>
              <td class="score-user" data-search="<?= e($searchText) ?>">@<?= e((string)$x['username']) ?></td>
              <td><?= e((string)$x['email']) ?></td>
              <td><?= e((string)$x['role']) ?></td>
              <td>
                <span class="badge text-bg-<?= ($x['status'] === 'active') ? 'success' : (($x['status'] === 'pending') ? 'warning' : 'danger') ?>">
                  <?= e((string)$x['status']) ?>
                </span>
              </td>
              <td class="text-end"><?= e((string)$x['solve_count']) ?></td>
              <td class="text-end score-points"><?= e((string)$x['total_points']) ?></td>
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

<script>
(function () {
  const input = document.getElementById('userSearchInput');
  const table = document.getElementById('adminUsersTable');
  if (!input || !table) return;

  const rows = Array.from(table.querySelectorAll('tbody tr'));
  input.addEventListener('input', function () {
    const term = input.value.trim().toLowerCase();
    rows.forEach((row) => {
      const sourceCell = row.querySelector('td[data-search]');
      const sourceText = sourceCell ? sourceCell.getAttribute('data-search') || '' : '';
      row.style.display = (term === '' || sourceText.includes(term)) ? '' : 'none';
    });
  });
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
