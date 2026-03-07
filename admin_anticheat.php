<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_admin();

$pdo = db();
$admin = current_user();
$adminId = sanitize_int($admin['id'] ?? 0, 0, 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    require_admin_write_access();

    $action = sanitize_str($_POST['action'] ?? '', 40);

    if ($action === 'mark_reviewed') {
        $alertId = sanitize_int($_POST['alert_id'] ?? 0, 0, 1);
        $viewUid = sanitize_int($_POST['view_user'] ?? 0, 0, 1);

        if ($alertId > 0 && $adminId > 0) {
            $stmt = $pdo->prepare('UPDATE cheat_alerts SET reviewed=1, reviewed_by=?, reviewed_at=NOW() WHERE id=?');
            $stmt->execute([$adminId, $alertId]);
            log_admin_action('mark_alert_reviewed', 'cheat_alert', $alertId, '');
        }

        if ($viewUid > 0) {
            redirect('/admin_anticheat.php?view_user=' . $viewUid);
        }

        redirect('/admin_anticheat.php');
    }

    if ($action === 'mark_all_reviewed') {
        $targetUid = sanitize_int($_POST['target_uid'] ?? 0, 0, 1);
        if ($targetUid > 0 && $adminId > 0) {
            $stmt = $pdo->prepare('UPDATE cheat_alerts SET reviewed=1, reviewed_by=?, reviewed_at=NOW() WHERE user_id=? AND reviewed=0');
            $stmt->execute([$adminId, $targetUid]);
            log_admin_action('mark_all_alerts_reviewed', 'user', $targetUid, '');
        }
        redirect('/admin_anticheat.php');
    }

    if ($action === 'ban_user') {
        $targetUid = sanitize_int($_POST['target_uid'] ?? 0, 0, 1);
        if ($targetUid > 0) {
            $stmt = $pdo->prepare("UPDATE users SET status='banned' WHERE id=? AND role='user'");
            $stmt->execute([$targetUid]);
            log_admin_action('ban_from_anticheat', 'user', $targetUid, 'Banned via anti-cheat panel');
            flash_set('success', 'User banned.');
        }
        redirect('/admin_anticheat.php');
    }
}

$unreviewedCount = (int)$pdo->query('SELECT COUNT(*) FROM cheat_alerts WHERE reviewed=0')->fetchColumn();
$highSeverityCount = (int)$pdo->query("SELECT COUNT(*) FROM cheat_alerts WHERE severity='high' AND reviewed=0")->fetchColumn();
$flaggedUsersCount = (int)$pdo->query('SELECT COUNT(DISTINCT user_id) FROM cheat_alerts WHERE reviewed=0')->fetchColumn();
$reviewedTodayCount = (int)$pdo->query('SELECT COUNT(*) FROM cheat_alerts WHERE reviewed=1 AND reviewed_at >= CURDATE()')->fetchColumn();

$flaggedUsers = $pdo->query(
    'SELECT u.id, u.username,
      COUNT(ca.id) AS alert_count,
      SUM(CASE WHEN ca.severity=\'high\' THEN 1 ELSE 0 END) AS high_count,
      MAX(ca.created_at) AS last_alert
     FROM cheat_alerts ca
     JOIN users u ON u.id = ca.user_id
     WHERE ca.reviewed = 0
     GROUP BY u.id
     ORDER BY high_count DESC, alert_count DESC, last_alert DESC'
)->fetchAll();

$viewUid = sanitize_int($_GET['view_user'] ?? 0, 0, 1);
$viewUser = null;
$viewUserAlerts = [];
$viewUserTimeline = [];
$viewUserPoints = 0;
$viewUserSolves = 0;

if ($viewUid > 0) {
    $userStmt = $pdo->prepare('SELECT id, username, created_at FROM users WHERE id=? LIMIT 1');
    $userStmt->execute([$viewUid]);
    $viewUser = $userStmt->fetch();

    if ($viewUser) {
        $viewUserPoints = user_points($viewUid);
        $viewUserSolves = solved_count($viewUid);

        $alertStmt = $pdo->prepare(
            'SELECT ca.id, ca.challenge_id, c.title AS challenge_title, ca.reason, ca.severity, ca.detail, ca.reviewed, ca.created_at
             FROM cheat_alerts ca
             LEFT JOIN challenges c ON c.id = ca.challenge_id
             WHERE ca.user_id=?
             ORDER BY ca.reviewed ASC, ca.created_at DESC, ca.id DESC'
        );
        $alertStmt->execute([$viewUid]);
        $viewUserAlerts = $alertStmt->fetchAll();

        $timelineStmt = $pdo->prepare(
            'SELECT s.solved_at, c.title AS challenge_title, s.points_awarded
             FROM solves s
             JOIN challenges c ON c.id = s.challenge_id
             WHERE s.user_id=?
             ORDER BY s.solved_at ASC'
        );
        $timelineStmt->execute([$viewUid]);
        $viewUserTimeline = $timelineStmt->fetchAll();
    }
}

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h1 class="page-title mb-0">Anti-Cheat Monitor</h1>
      <p class="page-subtitle mb-0">Flag-sharing and suspicious solve-pattern detection dashboard</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/admin.php">Back</a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:1.8rem;font-weight:800;color:#2563eb"><?= e((string)$unreviewedCount) ?></div>
        <div class="text-muted small">Unreviewed Alerts</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:1.8rem;font-weight:800;color:#dc2626"><?= e((string)$highSeverityCount) ?></div>
        <div class="text-muted small">High Severity</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:1.8rem;font-weight:800;color:#d97706"><?= e((string)$flaggedUsersCount) ?></div>
        <div class="text-muted small">Flagged Users</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card">
      <div class="card-body text-center">
        <div style="font-size:1.8rem;font-weight:800;color:#16a34a"><?= e((string)$reviewedTodayCount) ?></div>
        <div class="text-muted small">Reviewed Today</div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <h2 class="h5 mb-3">Flagged Users</h2>

    <?php if (!$flaggedUsers): ?>
      <div class="alert alert-success mb-0">No unreviewed anti-cheat alerts right now.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>Username</th>
              <th style="width: 90px;" class="text-end">Alerts</th>
              <th style="width: 90px;" class="text-end">High</th>
              <th style="width: 180px;">Last Flagged</th>
              <th style="width: 120px;" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($flaggedUsers as $row): ?>
              <tr>
                <td>
                  <a href="<?= e(BASE_URL) ?>/profile.php?username=<?= e(urlencode((string)$row['username'])) ?>">
                    @<?= e((string)$row['username']) ?>
                  </a>
                </td>
                <td class="text-end"><?= e((string)$row['alert_count']) ?></td>
                <td class="text-end">
                  <?php $highCount = (int)$row['high_count']; ?>
                  <?php if ($highCount > 0): ?>
                    <span class="badge bg-danger"><?= e((string)$highCount) ?></span>
                  <?php else: ?>
                    <span class="badge bg-secondary">0</span>
                  <?php endif; ?>
                </td>
                <td><?= e((string)$row['last_alert']) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/admin_anticheat.php?view_user=<?= e((string)$row['id']) ?>">Review</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($viewUid > 0): ?>
  <?php if (!$viewUser): ?>
    <div class="alert alert-warning">Requested user was not found.</div>
  <?php else: ?>
    <div class="card mb-3">
      <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
          <h2 class="h5 mb-1">User Review: @<?= e((string)$viewUser['username']) ?></h2>
          <div class="small text-muted">Joined: <?= e((string)$viewUser['created_at']) ?></div>
          <div class="small text-muted">Points: <?= e((string)$viewUserPoints) ?> | Solves: <?= e((string)$viewUserSolves) ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" class="d-inline">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="mark_all_reviewed">
            <input type="hidden" name="target_uid" value="<?= e((string)$viewUid) ?>">
            <button class="btn btn-sm btn-outline-success" type="submit">Mark All Reviewed</button>
          </form>

          <form method="post" class="d-inline" onsubmit="return confirm('Ban this user?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="ban_user">
            <input type="hidden" name="target_uid" value="<?= e((string)$viewUid) ?>">
            <button class="btn btn-sm btn-danger" type="submit">Ban User</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h3 class="h6 mb-3">Cheat Alerts</h3>

        <?php if (!$viewUserAlerts): ?>
          <div class="alert alert-info mb-0">No alerts recorded for this user.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Challenge</th>
                  <th>Reason</th>
                  <th>Severity</th>
                  <th>Detail</th>
                  <th style="width: 170px;">Time</th>
                  <th style="width: 140px;" class="text-end">Mark Reviewed</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($viewUserAlerts as $alert): ?>
                  <?php
                  $reasonText = ucwords(str_replace('_', ' ', (string)$alert['reason']));
                  $severity = (string)$alert['severity'];
                  $sevClass = $severity === 'high' ? 'danger' : ($severity === 'medium' ? 'warning' : 'secondary');
                  ?>
                  <tr>
                    <td><?= e((string)($alert['challenge_title'] ?? ('#' . (string)$alert['challenge_id']))) ?></td>
                    <td><?= e($reasonText) ?></td>
                    <td><span class="badge text-bg-<?= e($sevClass) ?>"><?= e($severity) ?></span></td>
                    <td class="small text-muted"><?= e((string)$alert['detail']) ?></td>
                    <td><?= e((string)$alert['created_at']) ?></td>
                    <td class="text-end">
                      <?php if ((int)$alert['reviewed'] === 1): ?>
                        <span class="badge bg-success">Reviewed</span>
                      <?php else: ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                          <input type="hidden" name="action" value="mark_reviewed">
                          <input type="hidden" name="alert_id" value="<?= e((string)$alert['id']) ?>">
                          <input type="hidden" name="view_user" value="<?= e((string)$viewUid) ?>">
                          <button class="btn btn-sm btn-outline-primary" type="submit">Mark</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-3">Solve Timeline</h3>

        <?php if (!$viewUserTimeline): ?>
          <div class="alert alert-info mb-0">No solves recorded for this user.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th style="width: 190px;">Time</th>
                  <th>Challenge</th>
                  <th style="width: 110px;" class="text-end">Points</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($viewUserTimeline as $solve): ?>
                  <tr>
                    <td><?= e((string)$solve['solved_at']) ?></td>
                    <td><?= e((string)$solve['challenge_title']) ?></td>
                    <td class="text-end score-points"><?= e((string)$solve['points_awarded']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
