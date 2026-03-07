<?php
require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();

if ($u && ($u['status'] ?? '') === 'active') redirect('/dashboard.php');
if ($u && ($u['status'] ?? '') !== 'active') redirect('/pending.php');

include __DIR__ . '/header.php';
?>

<section class="landing-hero">
  <div class="container-fluid px-0">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <div class="landing-label">[ CAPTURE THE FLAG ]</div>

        <h1 class="landing-title">
          <span class="word-green glow-green">Elite</span>
          <span class="word-cyan glow-cyan">Cyber Ops</span>
          <span class="word-main">Terminal</span>
        </h1>

        <p class="landing-subtitle">
          Precision offensive and defensive training platform for disciplined CTF operators. Build skill, execute cleanly, and dominate the board.
        </p>

        <div class="cta-row">
          <button type="button" class="btn btn-register" onclick="location.href='<?= e(BASE_URL) ?>/register.php'">[ REGISTER NOW ]</button>
          <button type="button" class="btn btn-login" onclick="location.href='<?= e(BASE_URL) ?>/login.php'">[ LOGIN ]</button>
        </div>

        <p class="landing-note">Accounts require instructor approval.</p>
      </div>

      <div class="col-lg-6">
        <div class="terminal-panel term-block box-glow">
          <div class="terminal-window-dots">
            <span class="dot-red"></span>
            <span class="dot-amber"></span>
            <span class="dot-green"></span>
          </div>

          <div class="typewriter-wrap">
            <div class="terminal-line" id="landingTypewriter"></div>
          </div>

          <div class="stat-chip-row">
            <span class="stat-chip">XX CHALLENGES</span>
            <span class="stat-chip">XX PLAYERS</span>
            <span class="stat-chip">XX SOLVES</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const lines = [
    '$ whoami -> [your_handle]',
    '$ mission -> hack_to_secure_the_world',
    '$ categories -> web | crypto | forensics | pwn | linux',
    '$ status -> CHALLENGES_OPEN / waiting...',
    '$ tip -> always start with: ls -la'
  ];

  const el = document.getElementById('landingTypewriter');
  if (!el) return;

  let lineIndex = 0;
  let charIndex = 0;
  let deleting = false;

  function step() {
    const line = lines[lineIndex];

    if (!deleting) {
      charIndex += 1;
      el.textContent = line.slice(0, charIndex);
      if (charIndex >= line.length) {
        deleting = true;
        setTimeout(step, 1000);
        return;
      }
      setTimeout(step, 36);
      return;
    }

    charIndex -= 1;
    el.textContent = line.slice(0, Math.max(charIndex, 0));

    if (charIndex <= 0) {
      deleting = false;
      lineIndex = (lineIndex + 1) % lines.length;
      setTimeout(step, 250);
      return;
    }

    setTimeout(step, 18);
  }

  step();
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
