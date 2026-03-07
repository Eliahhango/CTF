<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();

if (!MAINTENANCE_MODE) {
    redirect('/index.php');
}

$u = current_user();
if ($u && (($u['role'] ?? '') === 'admin')) {
    redirect('/admin.php');
}

include __DIR__ . '/header.php';
?>

<section class="status-screen">
  <div class="status-box status-pending box-glow">
    <h1 class="status-title">[ MAINTENANCE ]</h1>
    <p class="status-sub mb-2">SYSTEM HARDENING IN PROGRESS</p>
    <p class="small text-muted mb-1">The platform is temporarily unavailable for non-admin users.</p>
    <p class="small text-muted mb-0">Please check back shortly.</p>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>