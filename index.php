<?php
require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();

if ($u && ($u['status'] ?? '') === 'active') redirect('/dashboard.php');
if ($u && ($u['status'] ?? '') !== 'active') redirect('/pending.php');

$stats = db()->query("\n  SELECT\n    (SELECT COUNT(*) FROM challenges WHERE is_active=1) AS challenges_count,\n    (SELECT COUNT(*) FROM users WHERE role='user' AND status='active') AS users_count,\n    (SELECT COUNT(*) FROM solves) AS solves_count\n")->fetch();

include __DIR__ . '/header.php';
?>

<style>
  .landing-page .landing-override {
    margin-left: calc(-50vw + 50%);
    margin-right: calc(-50vw + 50%);
  }

  .landing-page .landing-hero {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #0f172a 100%);
  }

  .landing-page .landing-hero::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image:
      linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
      linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
  }

  .landing-page .landing-hero .container {
    position: relative;
    z-index: 1;
    padding-top: 4.25rem;
    padding-bottom: 4.25rem;
  }

  .landing-page .live-pill {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    background: rgba(15, 23, 42, .28);
    border: 1px solid rgba(148, 163, 184, .35);
    border-radius: 999px;
    color: #cbd5e1;
    font-size: .82rem;
    padding: .4rem .8rem;
    margin-bottom: 1rem;
  }

  .landing-page .live-dot {
    width: .5rem;
    height: .5rem;
    border-radius: 999px;
    background: #22c55e;
    box-shadow: 0 0 0 rgba(34, 197, 94, .45);
    animation: landingPulse 1.8s infinite;
  }

  @keyframes landingPulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, .55); }
    70% { box-shadow: 0 0 0 12px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
  }

  .landing-page .hero-title {
    color: #ffffff;
    font-size: clamp(2rem, 4vw, 3.2rem);
    font-weight: 800;
    line-height: 1.15;
    margin-bottom: .9rem;
  }

  .landing-page .hero-subtitle {
    color: #94a3b8;
    font-size: 1rem;
    margin-bottom: 1.35rem;
    max-width: 760px;
  }

  .landing-page .hero-points {
    list-style: none;
    padding: 0;
    margin: 0 0 1.35rem 0;
    display: grid;
    gap: .6rem;
  }

  .landing-page .hero-points li {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
    color: #cbd5e1;
  }

  .landing-page .hero-points i {
    color: #60a5fa;
    margin-top: 2px;
  }

  .landing-page .hero-cta {
    display: flex;
    flex-wrap: wrap;
    gap: .65rem;
    margin-bottom: .65rem;
  }

  .landing-page .btn-hero-primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    padding: .72rem 1.4rem;
    font-weight: 600;
  }

  .landing-page .btn-hero-primary:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #fff;
  }

  .landing-page .btn-hero-outline {
    border: 1px solid rgba(255, 255, 255, .75);
    color: #fff;
    padding: .72rem 1.4rem;
    font-weight: 600;
  }

  .landing-page .btn-hero-outline:hover {
    background: #fff;
    color: #0f172a;
  }

  .landing-page .hero-note {
    color: #94a3b8;
    font-size: .86rem;
  }

  .landing-page .glass-card {
    background: rgba(255, 255, 255, .06);
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 12px;
    padding: 1.5rem;
    backdrop-filter: blur(5px);
  }

  .landing-page .glass-card h2 {
    color: #fff;
    margin-bottom: .95rem;
    font-size: 1.1rem;
  }

  .landing-page .hero-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .6rem;
    margin-bottom: .95rem;
  }

  .landing-page .hero-stat-box {
    background: rgba(15, 23, 42, .45);
    border: 1px solid rgba(148, 163, 184, .25);
    border-radius: 10px;
    padding: .75rem .55rem;
    text-align: center;
  }

  .landing-page .hero-stat-value {
    display: block;
    color: #60a5fa;
    font-size: 1.28rem;
    font-weight: 800;
    line-height: 1.1;
  }

  .landing-page .hero-stat-label {
    display: block;
    color: #94a3b8;
    font-size: .78rem;
    margin-top: .2rem;
  }

  .landing-page .category-pills {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    margin-bottom: 1rem;
  }

  .landing-page .hero-badge {
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
    padding: .35rem .62rem;
  }

  .landing-page .hero-badge.web { background: rgba(14, 165, 233, .22); color: #7dd3fc; }
  .landing-page .hero-badge.crypto { background: rgba(139, 92, 246, .22); color: #c4b5fd; }
  .landing-page .hero-badge.forensics { background: rgba(245, 158, 11, .22); color: #fcd34d; }
  .landing-page .hero-badge.pwn { background: rgba(239, 68, 68, .22); color: #fca5a5; }
  .landing-page .hero-badge.linux { background: rgba(34, 197, 94, .22); color: #86efac; }

  .landing-page .hero-terminal {
    background: rgba(2, 6, 23, .5);
    border: 1px solid rgba(148, 163, 184, .3);
    border-radius: 10px;
    padding: .85rem;
    color: #cbd5e1;
    font-family: Consolas, "Courier New", monospace;
    font-size: .8rem;
    line-height: 1.55;
    margin: 0;
  }

  .landing-page .why-section {
    background: #f8fafc;
    padding: 3rem 0;
  }

  .landing-page .section-title {
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800;
    text-align: center;
    margin-bottom: .4rem;
    color: #0f172a;
  }

  .landing-page .section-subtitle {
    color: #64748b;
    text-align: center;
    margin-bottom: 1.6rem;
  }

  .landing-page .feature-card {
    background: #fff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
    padding: 1.5rem;
    height: 100%;
  }

  .landing-page .feature-icon {
    font-size: 2rem;
    margin-bottom: .75rem;
    display: inline-block;
  }

  .landing-page .feature-icon.blue { color: #2563eb; }
  .landing-page .feature-icon.amber { color: #d97706; }
  .landing-page .feature-icon.green { color: #16a34a; }

  .landing-page .feature-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: .5rem;
  }

  .landing-page .feature-text {
    color: #475569;
    margin-bottom: 0;
  }

  .landing-page .learn-section {
    background: #0f172a;
    padding: 3rem 0;
  }

  .landing-page .learn-title {
    color: #fff;
    text-align: center;
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 800;
    margin-bottom: 1.4rem;
  }

  .landing-page .category-grid {
    display: flex;
    flex-wrap: wrap;
    gap: .9rem;
  }

  .landing-page .learn-card {
    flex: 1 1 220px;
    background: #111d34;
    border: 1px solid rgba(148, 163, 184, .2);
    border-left: 4px solid #0ea5e9;
    border-radius: 10px;
    padding: .95rem;
    color: #cbd5e1;
  }

  .landing-page .learn-card h3 {
    color: #fff;
    font-size: 1rem;
    margin-bottom: .35rem;
  }

  .landing-page .learn-card p {
    color: #94a3b8;
    font-size: .88rem;
    margin: 0;
  }

  .landing-page .learn-card.web { border-left-color: #0ea5e9; }
  .landing-page .learn-card.crypto { border-left-color: #8b5cf6; }
  .landing-page .learn-card.forensics { border-left-color: #f59e0b; }
  .landing-page .learn-card.pwn { border-left-color: #ef4444; }
  .landing-page .learn-card.linux { border-left-color: #22c55e; }

  .landing-page .cta-section {
    background: linear-gradient(120deg, #1d4ed8 0%, #2563eb 100%);
    padding: 2.8rem 0;
    text-align: center;
  }

  .landing-page .cta-title {
    color: #fff;
    font-size: clamp(1.45rem, 3vw, 2rem);
    font-weight: 800;
    margin-bottom: .4rem;
  }

  .landing-page .cta-subtitle {
    color: #bfdbfe;
    margin-bottom: 1.1rem;
  }

  .landing-page .cta-actions {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: .65rem;
  }

  .landing-page .btn-cta-outline {
    color: #fff;
    border: 1px solid rgba(255, 255, 255, .78);
    padding: .65rem 1.35rem;
    font-weight: 600;
  }

  .landing-page .btn-cta-outline:hover {
    background: rgba(255, 255, 255, .14);
    color: #fff;
  }

  @media (max-width: 991.98px) {
    .landing-page .hero-stats {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 575.98px) {
    .stat-box { padding: .6rem .5rem; }
    .stat-box-value { font-size: 1.1rem; }
    .landing-page .hero-title { font-size: clamp(1.7rem, 6vw, 2.4rem); }
    .landing-page .hero-subtitle { font-size: .92rem; }
  }

  @media (max-width: 575.98px) {
    .landing-page .landing-override { margin-left: -1rem; margin-right: -1rem; }
  }
</style>

<div class="landing-page">
  <section class="landing-hero landing-override">
    <div class="container">
      <div class="row align-items-center g-4">
        <div class="col-12 col-lg-7">
          <span class="live-pill"><span class="live-dot"></span> Live Platform · Dar es Salaam</span>

          <h1 class="hero-title">Master the Art of <span style="color:#60a5fa">Ethical Hacking</span></h1>
          <p class="hero-subtitle">
            Join Cyber Club DIT's training platform. Solve real-world challenges in web exploitation, cryptography,
            forensics, binary exploitation, and Linux - and climb the leaderboard.
          </p>

          <ul class="hero-points">
            <li><i class="bi bi-shield-check"></i><span>Hands-on challenges designed by industry practitioners</span></li>
            <li><i class="bi bi-lightning-charge"></i><span>Real-world attack and defense techniques</span></li>
            <li><i class="bi bi-trophy"></i><span>Track your growth and compete with peers</span></li>
          </ul>

          <div class="hero-cta">
            <a class="btn btn-hero-primary btn-lg" href="<?= e(BASE_URL) ?>/register.php">Register Now</a>
            <a class="btn btn-hero-outline btn-lg" href="<?= e(BASE_URL) ?>/info.php">View Info</a>
          </div>
          <div class="hero-note">Account requires instructor approval</div>
        </div>

        <div class="col-12 col-lg-5">
          <div class="glass-card">
            <h2>Platform Stats</h2>
            <div class="hero-stats">
              <div class="hero-stat-box">
                <span class="hero-stat-value"><?= e((string)($stats['challenges_count'] ?? 0)) ?></span>
                <span class="hero-stat-label">Challenges</span>
              </div>
              <div class="hero-stat-box">
                <span class="hero-stat-value"><?= e((string)($stats['users_count'] ?? 0)) ?></span>
                <span class="hero-stat-label">Players</span>
              </div>
              <div class="hero-stat-box">
                <span class="hero-stat-value"><?= e((string)($stats['solves_count'] ?? 0)) ?></span>
                <span class="hero-stat-label">Solves</span>
              </div>
            </div>

            <div class="category-pills">
              <span class="hero-badge web">Web</span>
              <span class="hero-badge crypto">Crypto</span>
              <span class="hero-badge forensics">Forensics</span>
              <span class="hero-badge pwn">PWN</span>
              <span class="hero-badge linux">Linux</span>
            </div>

            <pre class="hero-terminal">$ whoami -> ctf_player
$ target -> learn_and_grow
$ status -> ready</pre>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="why-section landing-override">
    <div class="container">
      <h2 class="section-title">Why Capture The Flag?</h2>
      <p class="section-subtitle">CTF competitions are the fastest way to build real cybersecurity skills</p>

      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-4">
          <article class="feature-card">
            <i class="bi bi-shield-check feature-icon blue"></i>
            <h3 class="feature-title">Real Skills</h3>
            <p class="feature-text">
              Every challenge mirrors a real vulnerability or technique used by professionals. No theory - pure hands-on practice.
            </p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <article class="feature-card">
            <i class="bi bi-lightning-charge feature-icon amber"></i>
            <h3 class="feature-title">Rapid Growth</h3>
            <p class="feature-text">
              Solve progressively harder challenges. Each solve builds on the last. Track your improvement on the dashboard.
            </p>
          </article>
        </div>
        <div class="col-12 col-md-6 col-lg-4">
          <article class="feature-card">
            <i class="bi bi-trophy feature-icon green"></i>
            <h3 class="feature-title">Compete &amp; Learn</h3>
            <p class="feature-text">
              See how you rank against peers. First blood badges reward speed. The leaderboard keeps competition healthy.
            </p>
          </article>
        </div>
      </div>
    </div>
  </section>

  <section class="learn-section landing-override">
    <div class="container">
      <h2 class="learn-title">What Will You Learn?</h2>

      <div class="category-grid">
        <article class="learn-card web">
          <h3>Web</h3>
          <p>SQL injection, XSS, CSRF, auth bypass, SSRF</p>
        </article>
        <article class="learn-card crypto">
          <h3>Crypto</h3>
          <p>RSA, AES, hash cracking, encoding, classical ciphers</p>
        </article>
        <article class="learn-card forensics">
          <h3>Forensics</h3>
          <p>PCAP analysis, steganography, file carving, metadata</p>
        </article>
        <article class="learn-card pwn">
          <h3>PWN</h3>
          <p>Buffer overflow, ROP chains, heap exploitation, shellcode</p>
        </article>
        <article class="learn-card linux">
          <h3>Linux</h3>
          <p>Privilege escalation, SUID, cron abuse, bash scripting</p>
        </article>
      </div>
    </div>
  </section>

  <section class="cta-section landing-override">
    <div class="container">
      <h2 class="cta-title">Ready to start your cybersecurity journey?</h2>
      <p class="cta-subtitle">Register today and get approved by your instructor.</p>

      <div class="cta-actions">
        <a class="btn btn-cta-outline btn-lg" href="<?= e(BASE_URL) ?>/register.php">Register Now</a>
        <a class="btn btn-cta-outline btn-lg" href="<?= e(BASE_URL) ?>/info.php">Learn More</a>
      </div>
    </div>
  </section>
</div>

<?php include __DIR__ . '/footer.php'; ?>
