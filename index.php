<?php
require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();

if ($u && ($u['status'] ?? '') === 'active') redirect('/dashboard.php');
if ($u && ($u['status'] ?? '') !== 'active') redirect('/pending.php');

$stats = db()->query("\n  SELECT\n    (SELECT COUNT(*) FROM challenges WHERE is_active=1) AS challenges_count,\n    (SELECT COUNT(*) FROM users WHERE role='user' AND status='active') AS users_count,\n    (SELECT COUNT(*) FROM solves) AS solves_count\n")->fetch();

include __DIR__ . '/header.php';
?>

<section class="landing-hero">
  <div class="row g-4 align-items-center">
    <div class="col-lg-7">
      <h1 class="landing-heading">Cyber Club DIT CTF Platform</h1>
      <p class="landing-subtitle">
        A secure and modern training environment for web, crypto, forensics, pwn, and Linux challenges. Track progress, compete on the leaderboard, and sharpen real-world skills.
      </p>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary px-4" href="<?= e(BASE_URL) ?>/register.php">Register</a>
        <a class="btn btn-outline-primary px-4" href="<?= e(BASE_URL) ?>/login.php">Login</a>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-body p-4">
          <h2 class="h5 mb-3">Live Platform Stats</h2>
          <div class="stats-grid-3">
            <div class="stat-box">
              <span class="stat-box-value"><?= e((string)($stats['challenges_count'] ?? 0)) ?></span>
              <span class="stat-box-label">Challenges</span>
            </div>
            <div class="stat-box">
              <span class="stat-box-value"><?= e((string)($stats['users_count'] ?? 0)) ?></span>
              <span class="stat-box-label">Players</span>
            </div>
            <div class="stat-box">
              <span class="stat-box-value"><?= e((string)($stats['solves_count'] ?? 0)) ?></span>
              <span class="stat-box-label">Solves</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
