<?php
require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();

if ($u && ($u['status'] ?? '') === 'active') redirect('/dashboard.php');
if ($u && ($u['status'] ?? '') !== 'active') redirect('/pending.php');

include __DIR__ . '/header.php';
?>

<section class="hero-terminal">
  <div class="w-100">
    <div class="row justify-content-center">
      <div class="col-xl-10">
        <div class="card p-4 p-lg-5">
          <div class="terminal-window-head">
            <span class="dot-red"></span>
            <span class="dot-amber"></span>
            <span class="dot-green"></span>
            <span class="small muted-cyber ms-2">root@ccd-core:~</span>
          </div>

<pre class="ascii-banner">  ____ ____  ____  _____ ____       ____ _     _   _ ____
 / ___/ ___||  _ \| ____|  _ \     / ___| |   | | | | __ )
| |  | |    | | | |  _| | |_) |   | |   | |   | | | |  _ \
| |__| |___ | |_| | |___|  _ < _  | |___| |___| |_| | |_) |
 \____\____||____/|_____|_| \_(_)  \____|_____|\___/|____/
      ____ ___ _____   ____ ___  __  __ ____  _     _____
     / ___|_ _|_   _| / ___/ _ \|  \/  |  _ \| |   | ____|
    | |    | |  | |  | |  | | | | |\/| | |_) | |   |  _|
    | |___ | |  | |  | |__| |_| | |  | |  __/| |___| |___
     \____|___| |_|   \____\___/|_|  |_|_|   |_____|_____|</pre>

          <p class="mb-3 mt-4 text-uppercase small">Operation Brief</p>
          <p class="mb-4">
            <span id="heroTypewriter" class="typewriter"></span>
          </p>

          <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-command" onclick="location.href='<?= e(BASE_URL) ?>/register.php'">
              <span class="prompt">$</span> ./register --join-club
            </button>
            <button type="button" class="btn btn-command" onclick="location.href='<?= e(BASE_URL) ?>/login.php'">
              <span class="prompt">$</span> ./login --resume-session
            </button>
          </div>

          <p class="small muted-cyber mt-4 mb-0">
            Access is gated by instructor approval. Stand by for activation.
            <span class="terminal-cursor">_</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const el = document.getElementById('heroTypewriter');
  if (!el) return;

  const lines = [
    'Initializing offensive and defensive challenge pipeline...',
    'Train like a competitive CTF operator: Web | Forensics | Crypto | PWN',
    'Submit flags. Gain points. Climb the board.'
  ];

  let lineIndex = 0;
  let charIndex = 0;
  let deleting = false;

  function tick() {
    const line = lines[lineIndex];

    if (!deleting) {
      charIndex += 1;
      el.textContent = line.slice(0, charIndex);
      if (charIndex >= line.length) {
        deleting = true;
        setTimeout(tick, 1000);
        return;
      }
      setTimeout(tick, 42);
      return;
    }

    charIndex -= 1;
    el.textContent = line.slice(0, Math.max(charIndex, 0));

    if (charIndex <= 0) {
      deleting = false;
      lineIndex = (lineIndex + 1) % lines.length;
      setTimeout(tick, 260);
      return;
    }

    setTimeout(tick, 24);
  }

  tick();
})();
</script>

<?php include __DIR__ . '/footer.php'; ?>
