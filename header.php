<?php
if (ob_get_level() === 0) {
  ob_start();
}

require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();
$flashes = flash_get_all();

$requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPage = strtolower(basename($requestPath));
if ($currentPage === '' || strpos($currentPage, '.php') === false) {
  $currentPage = 'index.php';
}

$announcementUnread = 0;
if ($u && ($u['status'] ?? '') === 'active') {
  try {
    $lastSeenTs = sanitize_int($_SESSION['last_seen_announcements'] ?? 0, 0, 0);
    if ($lastSeenTs > 0) {
      $cutoff = date('Y-m-d H:i:s', $lastSeenTs);
      $annStmt = db()->prepare('SELECT COUNT(*) FROM announcements WHERE created_at > ?');
      $annStmt->execute([$cutoff]);
      $announcementUnread = (int)$annStmt->fetchColumn();
    } else {
      $announcementUnread = (int)db()->query('SELECT COUNT(*) FROM announcements')->fetchColumn();
    }
  } catch (Throwable $e) {
    $announcementUnread = 0;
    if (function_exists('app_log_error')) {
      app_log_error('announcement unread count failed', ['error' => $e->getMessage()]);
    }
  }
}

$userProfileUrl = '';
if ($u && isset($u['username'])) {
  $userProfileUrl = BASE_URL . '/profile.php?username=' . urlencode((string)$u['username']);
}

$navItems = [
  ['label' => 'Info', 'path' => '/info.php'],
];
if ($u && ($u['status'] ?? '') === 'active') {
  $navItems[] = ['label' => 'Dashboard', 'path' => '/dashboard.php'];
  $navItems[] = ['label' => 'Announcements', 'path' => '/announcements.php', 'id' => 'navAnnouncements'];
  if (challenges_window_open()) {
    $navItems[] = ['label' => 'Challenges', 'path' => '/challenges.php'];
  }
  $navItems[] = ['label' => 'Leaderboard', 'path' => '/leaderboard.php'];
}
if ($u && ($u['role'] ?? '') === 'admin') {
  $navItems[] = ['label' => 'Admin', 'path' => '/admin.php'];
}

$now = time();
$startTs = strtotime(CHALLENGES_OPEN_AT);
$endTs = strtotime(CHALLENGES_CLOSE_AT);

if ($now < $startTs) {
  $secondsLeft = $startTs - $now;
  $mode = 'before';
} elseif ($now < $endTs) {
  $secondsLeft = $endTs - $now;
  $mode = 'running';
} else {
  $secondsLeft = 0;
  $mode = 'ended';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>

  <link rel="icon" href="<?= e(BASE_URL) ?>/favicon.php" type="image/svg+xml">
  <link rel="shortcut icon" href="<?= e(BASE_URL) ?>/favicon.php" type="image/x-icon">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    :root {
      --bg-page: #f8f9fa;
      --bg-surface: #ffffff;
      --text-main: #1e293b;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --nav-bg: #1a1f2e;
      --nav-link: #94a3b8;
      --primary: #2563eb;
      --primary-hover: #1d4ed8;
      --success: #16a34a;
      --danger: #dc2626;
      --warning: #d97706;
    }

    * { box-sizing: border-box; }

    html { height: 100%; }

    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: var(--bg-page);
      color: var(--text-main);
      font-family: 'Inter', 'Poppins', 'Segoe UI', Arial, sans-serif;
      font-size: 14px;
      line-height: 1.5;
    }

    a {
      color: var(--primary);
      text-decoration: none;
    }

    a:hover { color: var(--primary-hover); }

    h1, h2, h3, h4, h5, h6 {
      font-family: 'Inter', 'Poppins', 'Segoe UI', Arial, sans-serif;
      color: var(--text-main);
      font-weight: 700;
      margin-bottom: .5rem;
    }

    .app-navbar {
      background: var(--nav-bg);
      position: sticky;
      top: 0;
      z-index: 1200;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
    }

    .app-navbar .navbar { min-height: 60px; }

    .app-brand {
      color: #ffffff;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: .02em;
    }

    .app-brand:hover { color: #ffffff; }

    .app-nav-link {
      color: var(--nav-link) !important;
      font-weight: 500;
      padding: .8rem .7rem !important;
      border-bottom: 2px solid transparent;
      transition: color .2s ease, border-color .2s ease;
    }

    .app-nav-link:hover { color: #ffffff !important; }

    .app-nav-link.active {
      color: #ffffff !important;
      border-bottom-color: var(--primary);
    }

    .announcement-badge {
      font-size: .64rem;
      line-height: 1;
      vertical-align: middle;
    }

    .nav-user-toggle {
      min-height: 34px;
      display: inline-flex;
      align-items: center;
    }

    .user-badge {
      display: inline-flex;
      align-items: center;
      padding: .38rem .65rem;
      border-radius: 999px;
      background: rgba(255, 255, 255, .12);
      color: #e2e8f0;
      font-size: .82rem;
      font-weight: 600;
    }

    .countdown-pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .34rem .62rem;
      border-radius: 999px;
      font-size: .78rem;
      font-weight: 600;
      line-height: 1;
      border: 1px solid transparent;
      background: #f8fafc;
      white-space: nowrap;
    }

    .countdown-before {
      color: #92400e;
      border-color: #fcd34d;
      background: #fffbeb;
    }

    .countdown-live {
      color: #166534;
      border-color: #86efac;
      background: #f0fdf4;
    }

    .countdown-ended {
      color: #991b1b;
      border-color: #fca5a5;
      background: #fef2f2;
    }

    .flash-stack {
      position: fixed;
      top: 76px;
      right: 16px;
      width: min(380px, calc(100vw - 24px));
      z-index: 1300;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .flash-alert {
      margin: 0;
      background: #ffffff;
      border: 1px solid var(--border);
      border-left: 4px solid #94a3b8;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      border-radius: 8px;
      opacity: 0;
      transform: translateX(16px);
      transition: opacity .25s ease, transform .25s ease;
    }

    .flash-alert.show {
      opacity: 1;
      transform: translateX(0);
    }

    .flash-alert.flash-success { border-left-color: var(--success); }
    .flash-alert.flash-danger { border-left-color: var(--danger); }
    .flash-alert.flash-warning { border-left-color: var(--warning); }
    .flash-alert.flash-info { border-left-color: var(--primary); }

    main.container {
      padding-top: 1.25rem;
      padding-bottom: 1.5rem;
      flex: 1 0 auto;
    }

    .page-title {
      font-size: clamp(1.6rem, 3vw, 2rem);
      font-weight: 800;
      margin-bottom: .35rem;
      color: #0f172a;
    }

    .page-subtitle {
      color: var(--text-muted);
      margin-bottom: 0;
    }

    .card,
    .panel-card {
      background: #ffffff !important;
      border: 1px solid var(--border) !important;
      border-radius: 8px !important;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
      color: var(--text-main);
    }

    .table {
      --bs-table-bg: #ffffff;
      --bs-table-color: var(--text-main);
      --bs-table-border-color: var(--border);
      font-size: 13px;
      margin-bottom: 0;
    }

    .table thead th {
      background: #f8fafc;
      border-bottom: 1px solid var(--border);
      font-weight: 600;
      color: #334155;
      padding: .72rem .7rem;
    }

    .table tbody td {
      padding: .72rem .7rem;
      vertical-align: middle;
      color: var(--text-main);
    }

    .btn {
      border-radius: 8px;
      font-size: .88rem;
      font-weight: 600;
      min-height: 40px;
    }

    .btn-primary,
    .auth-submit,
    .btn-blue {
      background: var(--primary);
      border-color: var(--primary);
      color: #ffffff;
    }

    .btn-primary:hover,
    .auth-submit:hover,
    .btn-blue:hover {
      background: var(--primary-hover);
      border-color: var(--primary-hover);
      color: #ffffff;
    }

    .btn-outline-primary {
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-outline-primary:hover {
      color: #ffffff;
      background: var(--primary);
      border-color: var(--primary);
    }

    .form-label,
    label {
      font-weight: 600;
      color: #334155;
      margin-bottom: .35rem;
    }

    .form-control,
    .form-select,
    textarea {
      border: 1px solid #cbd5e1;
      border-radius: 8px;
      min-height: 40px;
      font-size: .9rem;
      color: var(--text-main);
      background: #ffffff;
    }

    .form-control:focus,
    .form-select:focus,
    textarea:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 .2rem rgba(37, 99, 235, 0.15);
    }

    .badge {
      font-size: .72rem;
      font-weight: 600;
      border-radius: 999px;
      padding: .33rem .55rem;
    }

    .auth-page {
      min-height: calc(100vh - 150px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem 0;
    }

    .auth-card {
      width: 100%;
      max-width: 440px;
      margin: 0 auto;
    }

    .landing-hero {
      padding: 1.4rem 0 1.8rem;
    }

    .landing-heading {
      font-size: clamp(2rem, 4.4vw, 2.6rem);
      line-height: 1.2;
      color: #0f172a;
      margin-bottom: .9rem;
      font-weight: 800;
    }

    .landing-subtitle {
      color: #64748b;
      max-width: 56ch;
      font-size: 1rem;
      margin-bottom: 1.2rem;
    }

    .stats-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: .75rem;
    }

    .stat-box {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: .85rem .7rem;
      text-align: center;
    }

    .stat-box-value {
      display: block;
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--primary);
      line-height: 1;
    }

    .stat-box-label {
      display: block;
      margin-top: .35rem;
      font-size: .78rem;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .03em;
    }

    .stat-card-modern {
      border-top: 4px solid var(--primary) !important;
    }

    .stat-card-modern.stat-solved { border-top-color: var(--success) !important; }
    .stat-card-modern.stat-rank { border-top-color: var(--warning) !important; }
    .stat-card-modern.stat-remaining { border-top-color: var(--danger) !important; }

    .stat-card-label {
      color: #64748b;
      font-size: .8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .02em;
      margin-bottom: .28rem;
    }

    .stat-card-value {
      font-size: clamp(1.35rem, 3vw, 1.75rem);
      line-height: 1.1;
      font-weight: 800;
      color: #0f172a;
    }

    .challenge-search {
      max-width: 320px;
      min-width: 210px;
    }

    .challenge-card {
      position: relative;
      overflow: hidden;
      height: 100%;
    }

    .challenge-category-strip {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
    }

    .cat-strip-web { background: #0ea5e9; }
    .cat-strip-crypto { background: #8b5cf6; }
    .cat-strip-forensics { background: #f59e0b; }
    .cat-strip-pwn { background: #ef4444; }
    .cat-strip-linux { background: #22c55e; }
    .cat-strip-default { background: #94a3b8; }

    .first-blood-badge {
      position: absolute;
      top: .6rem;
      right: .75rem;
      background: #fef3c7;
      border: 1px solid #fcd34d;
      color: #92400e;
      border-radius: 999px;
      padding: .15rem .48rem;
      font-size: .68rem;
      font-weight: 600;
      max-width: 70%;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .challenge-title {
      font-size: 1rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: .35rem;
    }

    .challenge-meta-text {
      color: #64748b;
      font-size: .78rem;
    }

    .challenge-description {
      color: #334155;
      line-height: 1.65;
    }

    .chart-shell {
      border: 1px solid var(--border);
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
      padding: .85rem;
      min-height: 280px;
    }

    .chart-shell canvas {
      width: 100% !important;
      height: 240px !important;
    }

    .status-screen {
      min-height: calc(100vh - 160px);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem 0;
    }

    .status-box {
      width: 100%;
      max-width: 700px;
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      padding: 2rem 1.25rem;
      text-align: center;
    }

    .status-title {
      font-size: clamp(1.7rem, 5vw, 2.4rem);
      margin-bottom: .6rem;
      font-weight: 800;
      color: #0f172a;
    }

    .status-sub {
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-size: .78rem;
      margin-bottom: .6rem;
    }

    .status-pending { border-top: 4px solid var(--warning); }
    .status-banned { border-top: 4px solid var(--danger); }
    .status-403 { border-top: 4px solid var(--danger); }

    .ops-footer {
      border-top: 1px solid var(--border);
      background: #ffffff;
      padding: .8rem 0;
      margin-top: 0;
      flex-shrink: 0;
      color: #64748b;
      font-size: .86rem;
    }

    .rank-first {
      border-left: 4px solid #f59e0b;
      background: #fff7ed;
    }

    .rank-current {
      border-left: 4px solid var(--primary);
      background: #eff6ff;
    }

    .points-cell {
      color: var(--primary);
      font-weight: 700;
      text-align: right;
    }

    .leaderboard-table thead th {
      background: #f1f5f9;
    }

    .text-muted-custom {
      color: #64748b !important;
    }

    .section-head {
      font-size: 1.15rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: .75rem;
    }

    /* Legacy admin utility classes */
    .term-block {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-left: 4px solid #2563eb;
      border-radius: 8px;
      padding: 1rem 1.25rem;
    }

    .prompt-label {
      font-weight: 600;
      color: #334155;
      margin-bottom: .35rem;
      display: block;
    }

    .score-user { color: #2563eb; font-weight: 600; }
    .score-points { color: #2563eb; font-weight: 700; text-align: right; }

    .btn-green { background: #16a34a; border-color: #16a34a; color: #fff; }
    .btn-green:hover { background: #15803d; border-color: #15803d; color: #fff; }
    .btn-red { background: transparent; color: #dc2626; border: 1px solid #dc2626; }
    .btn-red:hover { background: #dc2626; color: #fff; }
    .btn-amber { background: transparent; color: #d97706; border: 1px solid #d97706; }
    .btn-amber:hover { background: #d97706; color: #fff; }
    .btn-cyan { background: transparent; color: #0891b2; border: 1px solid #0891b2; }
    .btn-cyan:hover { background: #0891b2; color: #fff; }

    @media (max-width: 991.98px) {
      .navbar-nav .app-nav-link {
        border-bottom: none;
      }

      .navbar-nav .app-nav-link.active {
        color: #ffffff !important;
      }

      .stats-grid-3 {
        grid-template-columns: 1fr;
      }

      .challenge-search {
        max-width: 100%;
        width: 100%;
      }
    }

    @media (max-width: 767.98px) {
      .flash-stack {
        top: 68px;
        right: 10px;
      }
    }
  </style>
</head>

<body>
<header class="app-navbar">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
      <a class="navbar-brand app-brand" href="<?= e(BASE_URL) ?>/index.php"><?= e(APP_NAME) ?></a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#appNav" aria-controls="appNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="appNav">
        <ul class="navbar-nav ms-lg-4 me-auto">
          <?php foreach ($navItems as $item): ?>
            <?php $isActive = ($currentPage === basename($item['path'])); ?>
            <li class="nav-item">
              <a
                class="nav-link app-nav-link<?= $isActive ? ' active' : '' ?>"
                href="<?= e(BASE_URL) . e($item['path']) ?>"
                <?= isset($item['id']) ? 'id="' . e((string)$item['id']) . '"' : '' ?>
              >
                <?= e($item['label']) ?>
                <?php if (($item['id'] ?? '') === 'navAnnouncements'): ?>
                  <span
                    id="announcementsUnreadBadge"
                    class="badge text-bg-danger ms-1 announcement-badge<?= $announcementUnread > 0 ? '' : ' d-none' ?>"
                  >
                    <?= e((string)$announcementUnread) ?>
                  </span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-lg-end pt-2 pt-lg-0">
          <?php if ($mode === 'before'): ?>
            <span class="countdown-pill countdown-before" id="challengeCountdown" data-mode="before" data-seconds="<?= e((string)$secondsLeft) ?>">
              OPENS IN <span id="cdText">--</span>
            </span>
          <?php elseif ($mode === 'running'): ?>
            <span class="countdown-pill countdown-live" id="challengeCountdown" data-mode="running" data-seconds="<?= e((string)$secondsLeft) ?>">
              LIVE <span id="cdText">--</span>
            </span>
          <?php else: ?>
            <span class="countdown-pill countdown-ended">ENDED</span>
          <?php endif; ?>

          <?php if ($u): ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-light dropdown-toggle nav-user-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                @<?= e((string)$u['username']) ?>
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">@<?= e((string)$u['username']) ?></h6></li>
                <li><a class="dropdown-item" href="<?= e($userProfileUrl) ?>">My Profile</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/settings.php">Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= e(BASE_URL) ?>/logout.php">Logout</a></li>
              </ul>
            </div>
          <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/login.php">Login</a>
            <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>/register.php">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
</header>

<?php if ($flashes): ?>
  <div class="flash-stack" id="flashStack">
    <?php foreach ($flashes as $f): ?>
      <?php
        $flashType = strtolower((string)($f['type'] ?? 'info'));
        $flashClass = 'flash-info';
        if ($flashType === 'success') {
          $flashClass = 'flash-success';
        } elseif ($flashType === 'danger' || $flashType === 'error') {
          $flashClass = 'flash-danger';
        } elseif ($flashType === 'warning') {
          $flashClass = 'flash-warning';
        }
      ?>
      <div class="alert flash-alert <?= e($flashClass) ?>" role="alert">
        <?= e((string)($f['msg'] ?? '')) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  const countdown = document.getElementById('challengeCountdown');
  const cdText = document.getElementById('cdText');
  const announcementsApiUrl = <?= json_encode(
    ($u && ($u['status'] ?? '') === 'active') ? (BASE_URL . '/api/announcements_count.php') : '',
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
  ) ?>;
  const announcementsBadge = document.getElementById('announcementsUnreadBadge');

  function formatDuration(totalSeconds) {
    const sec = Math.max(0, totalSeconds);
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;

    if (d > 0) {
      return `${d}d ${h}h`;
    }
    if (h > 0) {
      return `${h}h ${m}m`;
    }
    if (m > 0) {
      return `${m}m ${s}s`;
    }
    return `${s}s`;
  }

  if (countdown && cdText) {
    let seconds = parseInt(countdown.dataset.seconds || '0', 10);

    function tick() {
      cdText.textContent = formatDuration(seconds);

      if (seconds <= 0) {
        setTimeout(() => location.reload(), 900);
        return;
      }

      seconds -= 1;
      setTimeout(tick, 1000);
    }

    tick();
  }

  function updateAnnouncementsBadge(count) {
    if (!announcementsBadge) return;

    const safeCount = Number.isFinite(count) ? Math.max(0, Math.floor(count)) : 0;
    announcementsBadge.textContent = String(safeCount);
    announcementsBadge.classList.toggle('d-none', safeCount <= 0);
  }

  function pollAnnouncementCount() {
    if (!announcementsApiUrl || !window.fetch) return;

    fetch(announcementsApiUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Announcement count request failed');
        }
        return response.json();
      })
      .then((payload) => {
        if (!payload || typeof payload.count === 'undefined') return;
        const parsed = parseInt(String(payload.count), 10);
        updateAnnouncementsBadge(Number.isNaN(parsed) ? 0 : parsed);
      })
      .catch(() => {
        // Ignore transient polling failures.
      });
  }

  if (announcementsApiUrl) {
    setInterval(pollAnnouncementCount, 60000);
  }

  const flashItems = Array.from(document.querySelectorAll('.flash-alert'));
  flashItems.forEach((item, index) => {
    setTimeout(() => {
      item.classList.add('show');
    }, 70 + (index * 80));

    setTimeout(() => {
      item.classList.remove('show');
      setTimeout(() => item.remove(), 280);
    }, 4600 + (index * 200));
  });
})();
</script>

<main class="container">
