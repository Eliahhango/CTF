</main>

<footer class="ops-footer">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <div>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?></div>
    <div class="text-muted-custom">Professional Capture The Flag Platform</div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Live feed toast container -->
<div id="lf-wrap" aria-live="polite" style="
  position:fixed;bottom:1.25rem;right:1.25rem;z-index:1500;
  width:min(340px,calc(100vw - 2rem));
  display:flex;flex-direction:column-reverse;gap:.5rem;
  pointer-events:none;
"></div>

<style>
.lf-toast {
  background:#fff;border:1px solid #e2e8f0;border-radius:10px;
  padding:.7rem 1rem;box-shadow:0 4px 18px rgba(15,23,42,.12);
  pointer-events:auto;font-size:.86rem;color:#1e293b;
  display:flex;align-items:center;gap:.6rem;
  opacity:0;transform:translateY(6px);
  transition:opacity .25s ease,transform .25s ease;
}
.lf-toast.show { opacity:1; transform:translateY(0); }
.lf-dot { width:9px;height:9px;border-radius:50%;flex-shrink:0; }
</style>

<script>
(function(){
  const loggedIn = <?= json_encode(
    session_status() === PHP_SESSION_ACTIVE &&
    isset($_SESSION['user']) &&
    (($_SESSION['user']['status'] ?? '') === 'active')
  ) ?>;
  if (!loggedIn) return;

  const feedUrl = <?= json_encode(rtrim(BASE_URL, '/') . '/api/live_feed.php') ?>;
  const wrap    = document.getElementById('lf-wrap');
  const catColors = {
    web:'#0ea5e9', crypto:'#8b5cf6', forensics:'#f59e0b',
    pwn:'#ef4444', linux:'#22c55e'
  };

  let lastTs = new Date(Date.now() - 30000).toISOString().slice(0,19).replace('T',' ');
  let started = false;

  function showToast(html, ms = 5500) {
    const t = document.createElement('div');
    t.className = 'lf-toast';
    t.innerHTML = html;
    wrap.appendChild(t);
    requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('show')));
    setTimeout(() => {
      t.classList.remove('show');
      setTimeout(() => t.remove(), 300);
    }, ms);
  }

  function poll() {
    fetch(feedUrl + '?since=' + encodeURIComponent(lastTs), {
      credentials:'same-origin', cache:'no-store',
      headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}
    })
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      if (!data) return;
      lastTs = data.ts;

      (data.solves || []).forEach(s => {
        const c = catColors[(s.category||'').toLowerCase()] || '#2563eb';
        showToast(
          '<span class="lf-dot" style="background:' + c + '"></span>' +
          '<span><strong>@' + s.username + '</strong> solved <strong>' + s.title + '</strong>' +
          ' <span style="color:#64748b">+' + s.points_awarded + ' pts</span></span>'
        );
      });

      (data.announcements || []).forEach(a => {
        showToast(
          '<i class="bi bi-megaphone-fill" style="color:#2563eb;flex-shrink:0;"></i>' +
          '<span><strong>Announcement:</strong> ' + a.title + '</span>',
          8000
        );
      });
    })
    .catch(() => {});
  }

  // First poll after 15s (don't spam on page load), then every 30s
  setTimeout(function startPoll(){
    if (!started) { started = true; poll(); setInterval(poll, 30000); }
  }, 15000);
})();
</script>

</body>
</html>
