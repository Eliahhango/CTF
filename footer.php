</main>

<footer class="terminal-footer">
  <div class="container py-4">
    <div class="row g-3">
      <div class="col-lg-5">
        <div class="terminal-badge mb-2">
          <i class="bi bi-broadcast"></i>
          Cyber Club DIT CTF
        </div>
        <p class="small muted-cyber mb-0">
          Live cyber operations training platform focused on Linux, web exploitation, and defensive thinking.
        </p>
      </div>

      <div class="col-lg-4">
        <div class="footer-title">Quick Commands</div>
        <a class="footer-link" href="<?= e(BASE_URL) ?>/index.php"><i class="bi bi-chevron-right"></i>./index</a>
        <a class="footer-link" href="<?= e(BASE_URL) ?>/dashboard.php"><i class="bi bi-chevron-right"></i>./dashboard</a>
        <a class="footer-link" href="<?= e(BASE_URL) ?>/challenges.php"><i class="bi bi-chevron-right"></i>./challenges</a>
        <a class="footer-link" href="<?= e(BASE_URL) ?>/leaderboard.php"><i class="bi bi-chevron-right"></i>./leaderboard</a>
      </div>

      <div class="col-lg-3">
        <div class="footer-title">Mission</div>
        <p class="small mb-1">Hack to Secure the World.</p>
        <p class="small footer-muted mb-0">CTF Platform v1.0</p>
      </div>
    </div>

    <hr class="my-3">

    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small">
      <div>&copy; <?= date('Y') ?> Cyber Club DIT</div>
      <div class="footer-muted">Powered by Linux, PHP, and MariaDB</div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
  const el = document.getElementById('challengeCountdown');
  const text = document.getElementById('cdText');
  if (!el || !text) return;

  let seconds = parseInt(el.dataset.seconds || '0', 10);

  function pad(n) {
    return String(n).padStart(2, '0');
  }

  function formatTime(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    return `${pad(h)}:${pad(m)}:${pad(s)}`;
  }

  function tick() {
    if (seconds <= 0) {
      location.reload();
      return;
    }

    text.textContent = formatTime(seconds);
    seconds -= 1;
    setTimeout(tick, 1000);
  }

  text.textContent = formatTime(seconds);
  setTimeout(tick, 1000);
})();
</script>

</body>
</html>
