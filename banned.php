<?php
require_once __DIR__ . '/helpers.php';
start_session();
require_login();
include __DIR__ . '/header.php';
?>

<section class="status-screen">
  <div class="status-box status-banned">
    <pre class="status-ascii">  /!\\
 ( x_x )
  /_|_\\</pre>
    <h1 class="status-title">[ ACCESS DENIED ]</h1>
    <p class="status-sub mb-2">THIS ACCOUNT HAS BEEN TERMINATED</p>
    <p class="small text-muted mb-0">Contact the instructor if you believe this action was applied in error.</p>
  </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
