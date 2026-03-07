<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$rows = db()->query(
    'SELECT aal.id, aal.action, aal.target_type, aal.target_id, aal.details, aal.ip_addr, aal.created_at, u.username
     FROM admin_audit_log aal
     JOIN users u ON u.id = aal.admin_id
     ORDER BY aal.created_at DESC, aal.id DESC
     LIMIT 500'
)->fetchAll();

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h1 class="page-title mb-0">Admin Audit Log</h1>
      <p class="page-subtitle mb-0">Last 500 recorded admin actions</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <?php if (!$rows): ?>
      <div class="alert alert-info mb-0">No audit entries found.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th style="width: 190px;">Time</th>
              <th style="width: 130px;">Admin</th>
              <th style="width: 160px;">Action</th>
              <th style="width: 170px;">Target</th>
              <th>Details</th>
              <th style="width: 150px;">IP</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
              $targetLabel = (string)$row['target_type'];
              if ($row['target_id'] !== null) {
                  $targetLabel .= ' #' . (string)$row['target_id'];
              }
              ?>
              <tr>
                <td><?= e((string)$row['created_at']) ?></td>
                <td class="score-user">@<?= e((string)$row['username']) ?></td>
                <td><?= e((string)$row['action']) ?></td>
                <td><?= e($targetLabel) ?></td>
                <td class="text-muted small"><?= e((string)$row['details']) ?></td>
                <td><?= e((string)$row['ip_addr']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
