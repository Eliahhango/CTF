<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();
require_active_user();

$stmt = db()->query(
    'SELECT a.id, a.title, a.body, a.is_pinned, a.created_at, u.username AS author
     FROM announcements a
     JOIN users u ON u.id = a.created_by
     ORDER BY a.is_pinned DESC, a.created_at DESC, a.id DESC'
);
$announcements = $stmt->fetchAll();

$_SESSION['last_seen_announcements'] = time();

include __DIR__ . '/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="page-title mb-0">Announcements</h1>
    <p class="page-subtitle">Latest platform updates and notices</p>
  </div>
</div>

<?php if (!$announcements): ?>
  <div class="card">
    <div class="card-body">
      <div class="alert alert-info mb-0">No announcements yet.</div>
    </div>
  </div>
<?php else: ?>
  <div class="vstack gap-3">
    <?php foreach ($announcements as $announcement): ?>
      <article class="card">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h3 class="h5 mb-0"><?= e((string)$announcement['title']) ?></h3>
            <?php if ((int)$announcement['is_pinned'] === 1): ?>
              <span class="badge text-bg-warning">Pinned</span>
            <?php endif; ?>
          </div>

          <div class="challenge-description mb-3" style="white-space: normal;">
            <?= nl2br(linkify((string)$announcement['body']), false) ?>
          </div>

          <div class="text-muted small">
            <?= e((string)$announcement['created_at']) ?> by @<?= e((string)$announcement['author']) ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
