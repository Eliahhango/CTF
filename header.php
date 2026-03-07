<?php
require_once __DIR__ . '/helpers.php';
start_session();
$u = current_user();
$flashes = flash_get_all();

$requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentPage = strtolower(basename($requestPath));
if ($currentPage === '' || strpos($currentPage, '.php') === false) {
  $currentPage = 'index.php';
}

$navItems = [];
if ($u && ($u['status'] ?? '') === 'active') {
  $navItems[] = ['label' => 'dashboard', 'path' => '/dashboard.php'];
  if (challenges_window_open()) {
    $navItems[] = ['label' => 'challenges', 'path' => '/challenges.php'];
  }
  $navItems[] = ['label' => 'leaderboard', 'path' => '/leaderboard.php'];
}
if ($u && ($u['role'] ?? '') === 'admin') {
  $navItems[] = ['label' => 'admin', 'path' => '/admin.php'];
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

  <link rel="shortcut icon" href="<?= e(BASE_URL) ?>/assets/logo.png" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;600&family=Orbitron:wght@400;700;900&family=Share+Tech+Mono&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    :root {
      --bg-void: #02040a;
      --bg-deep: #060b14;
      --bg-card: #0a1220;
      --border: rgba(0,255,136,.12);
      --border-bright: rgba(0,255,136,.40);
      --green: #00ff88;
      --cyan: #00d4ff;
      --amber: #ffaa00;
      --red: #ff3355;
      --purple: #b060ff;
      --text-primary: #c8dce8;
      --text-dim: rgba(200,220,232,.50);
      --font-mono: 'Share Tech Mono', 'Courier New', monospace;
      --font-display: 'Orbitron', 'Segoe UI', sans-serif;
      --font-body: 'Exo 2', 'Segoe UI', sans-serif;
    }

    * { box-sizing: border-box; }
    html, body { min-height: 100%; }

    body {
      margin: 0;
      background: radial-gradient(circle at 12% 0%, rgba(0,212,255,.06), transparent 45%), var(--bg-deep);
      color: var(--text-primary);
      font-family: var(--font-body);
      cursor: crosshair;
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background-image:
        repeating-linear-gradient(to right, rgba(0,255,136,.025) 0 1px, transparent 1px 48px),
        repeating-linear-gradient(to bottom, rgba(0,255,136,.025) 0 1px, transparent 1px 48px);
      animation: gridDrift 60s linear infinite;
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 9999;
      pointer-events: none;
      background-image: repeating-linear-gradient(to bottom, transparent 0, transparent 2px, rgba(0,0,0,.08) 2px, rgba(0,0,0,.08) 3px);
    }

    @keyframes gridDrift {
      from { background-position: 0 0, 0 0; }
      to { background-position: 240px 120px, -120px 240px; }
    }

    header, main, footer, .flash-stack { position: relative; z-index: 2; }

    a, button, .btn, [role='button'], input[type='submit'], input[type='button'] { cursor: pointer; }
    a { color: var(--cyan); text-decoration: none; transition: color .2s ease; }
    a:hover { color: var(--green); }

    h1, h2, h3, h4, h5, h6 {
      font-family: var(--font-display);
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--text-primary);
      margin-bottom: .75rem;
    }

    .mono, .small, .form-text, code, .badge, .btn, .table, .terminal-mono,
    .text-muted, .text-light-emphasis { font-family: var(--font-mono) !important; }
    .text-muted, .text-light-emphasis, .form-text { color: var(--text-dim) !important; }

    .glow-green { text-shadow: 0 0 10px #00ff88, 0 0 25px rgba(0,255,136,.4); }
    .glow-cyan { text-shadow: 0 0 10px #00d4ff, 0 0 25px rgba(0,212,255,.4); }
    .glow-amber { text-shadow: 0 0 10px #ffaa00, 0 0 25px rgba(255,170,0,.4); }
    .box-glow { box-shadow: 0 0 0 1px var(--border-bright), 0 0 20px rgba(0,255,136,.08); }

    .term-block {
      background: rgba(0,0,0,.6);
      border: 1px solid var(--border);
      border-left: 3px solid var(--green);
      border-radius: 4px;
      padding: 16px 20px 16px 34px;
      font-family: var(--font-mono);
      font-size: .9rem;
      position: relative;
    }

    .term-block::before {
      content: '// ';
      color: var(--green);
      opacity: .5;
      position: absolute;
      left: 12px;
      top: 14px;
      font-family: var(--font-mono);
    }

    .blink::after { content: '_'; animation: blink .8s step-end infinite; color: var(--green); }
    @keyframes blink { 50% { opacity: 0; } }

    .dot-online {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #00ff88;
      box-shadow: 0 0 6px #00ff88;
      animation: pulse-dot 2s infinite;
      display: inline-block;
      margin-right: 6px;
    }

    @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.3} }

    .ops-topbar {
      position: sticky;
      top: 0;
      z-index: 1200;
      min-height: 56px;
      background: rgba(2,4,10,.92);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
    }

    .ops-bar { min-height: 56px; display: flex; align-items: center; gap: 1rem; padding: .35rem 0; flex-wrap: wrap; }
    .ops-brand { display: flex; flex-direction: column; gap: 2px; color: var(--green); min-width: 220px; line-height: 1.1; }
    .ops-brand-main { font-family: var(--font-display); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: .95rem; color: var(--green); display: inline-flex; align-items: center; }
    .ops-brand-prompt { margin-right: .25rem; animation: blink .8s step-end infinite; }
    .ops-brand-sub { font-family: var(--font-mono); font-size: .66rem; letter-spacing: .12em; color: var(--text-dim); text-transform: uppercase; display: inline-flex; align-items: center; }

    .ops-toggle {
      border: 1px solid var(--border-bright);
      background: rgba(0,255,136,.08);
      width: 44px;
      height: 44px;
      border-radius: 4px;
      margin-left: auto;
      display: grid;
      align-content: center;
      justify-content: center;
      gap: 5px;
      padding: 0;
    }

    .ops-toggle span { width: 18px; height: 2px; background: var(--green); display: block; }

    .ops-collapse { flex: 1; min-width: 0; }
    .ops-nav-shell { display: flex; align-items: center; justify-content: space-between; gap: 1rem; width: 100%; }
    .ops-links { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; min-height: 44px; }

    .ops-link {
      display: inline-flex;
      align-items: center;
      gap: .1rem;
      color: var(--text-dim);
      font-family: var(--font-mono);
      font-size: .82rem;
      letter-spacing: .08em;
      text-transform: lowercase;
      border-bottom: 2px solid transparent;
      padding: .35rem .5rem;
      min-height: 44px;
    }

    .ops-link .cmd-prefix { color: rgba(0,255,136,.45); margin-right: .08rem; }
    .ops-link:hover { color: var(--green); }
    .ops-link.active { color: var(--green); border-bottom-color: var(--green); text-shadow: 0 0 12px rgba(0,255,136,.45); }

    .ops-right { display: flex; align-items: center; justify-content: flex-end; gap: .65rem; flex-wrap: wrap; min-height: 44px; }

    .ops-countdown {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: .38rem .55rem;
      font-family: var(--font-mono);
      font-size: .77rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      min-height: 36px;
    }

    .ops-countdown-time { font-weight: 700; min-width: 112px; text-align: center; }
    .ops-before { border-color: rgba(255,170,0,.45); color: var(--amber); box-shadow: inset 0 0 0 1px rgba(255,170,0,.2); }
    .ops-live { border-color: rgba(0,255,136,.45); color: var(--green); box-shadow: inset 0 0 0 1px rgba(0,255,136,.2); }
    .ops-closed { border-color: rgba(255,51,85,.45); color: var(--red); box-shadow: inset 0 0 0 1px rgba(255,51,85,.2); }

    .ops-user { font-family: var(--font-mono); color: var(--cyan); letter-spacing: .06em; font-size: .82rem; text-transform: lowercase; min-height: 36px; display: inline-flex; align-items: center; }

    .ops-exit {
      border: 1px solid var(--red);
      color: var(--red);
      background: transparent;
      border-radius: 4px;
      font-family: var(--font-mono);
      font-size: .76rem;
      text-transform: uppercase;
      letter-spacing: .08em;
      padding: .43rem .62rem;
      min-height: 36px;
      display: inline-flex;
      align-items: center;
    }

    .ops-exit:hover { background: var(--red); color: #000; box-shadow: 0 0 14px rgba(255,51,85,.35); }

    .flash-stack {
      position: fixed;
      top: 72px;
      right: 16px;
      width: min(360px, calc(100vw - 24px));
      z-index: 9998;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    }

    .flash-item {
      opacity: 0;
      transform: translateX(26px);
      transition: opacity .35s ease, transform .35s ease;
      pointer-events: auto;
      border-left-width: 3px;
      margin: 0;
      background: rgba(2,4,10,.9);
      padding-top: 12px;
      padding-bottom: 12px;
    }

    .flash-item::before { top: 11px; }
    .flash-item.show { opacity: 1; transform: translateX(0); }
    .flash-item.hide { opacity: 0; transform: translateX(22px); }

    .flash-prefix { font-family: var(--font-mono); font-size: .77rem; letter-spacing: .1em; margin-bottom: .2rem; text-transform: uppercase; }
    .flash-body { color: var(--text-primary); font-size: .86rem; font-family: var(--font-body); line-height: 1.35; }
    .flash-success { border-left-color: var(--green); } .flash-success .flash-prefix { color: var(--green); }
    .flash-danger { border-left-color: var(--red); } .flash-danger .flash-prefix { color: var(--red); }
    .flash-warning { border-left-color: var(--amber); } .flash-warning .flash-prefix { color: var(--amber); }
    .flash-info { border-left-color: var(--cyan); } .flash-info .flash-prefix { color: var(--cyan); }

    .app-main { padding-top: 1.4rem; padding-bottom: 1.5rem; }

    .card, .panel-card {
      background: linear-gradient(180deg, rgba(10,18,32,.96), rgba(6,11,20,.95)) !important;
      border: 1px solid var(--border) !important;
      border-radius: 6px !important;
      color: var(--text-primary);
      box-shadow: inset 0 0 0 1px rgba(0,255,136,.05);
    }

    .table {
      --bs-table-bg: transparent;
      --bs-table-color: var(--text-primary);
      --bs-table-border-color: var(--border);
      color: var(--text-primary);
      margin-bottom: 0;
    }

    .table thead th {
      background: rgba(0,255,136,.06);
      color: var(--green);
      border-bottom: 1px solid var(--border-bright);
      text-transform: uppercase;
      letter-spacing: .1em;
      font-size: .76rem;
      padding: .78rem .7rem;
    }

    .table tbody tr { border-bottom: 1px solid var(--border); }
    .table tbody tr:nth-child(even) { background: rgba(0,255,136,.02); }
    .table tbody td { padding: .78rem .7rem; vertical-align: middle; font-family: var(--font-mono); font-size: .86rem; }
    .table tbody tr:hover { background: rgba(0,255,136,.06); }

    .form-label, label {
      font-family: var(--font-mono);
      font-size: .8rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--green);
      margin-bottom: .35rem;
    }

    .prompt-label::before { content: '> '; color: var(--green); }

    .form-control, .form-select, textarea {
      width: 100%;
      background: transparent;
      border: none;
      border-bottom: 1px solid var(--border-bright);
      color: var(--text-primary);
      border-radius: 0;
      padding: .52rem .2rem;
      box-shadow: none;
      font-family: var(--font-mono);
      font-size: .9rem;
      min-height: 44px;
    }

    .form-control::placeholder, textarea::placeholder { color: rgba(200,220,232,.42); }
    .form-select { padding-right: 1.7rem; background-color: transparent; }

    .form-control:focus, .form-select:focus, textarea:focus {
      color: var(--text-primary);
      background: transparent;
      border-color: var(--green);
      box-shadow: 0 2px 0 rgba(0,255,136,.3);
      outline: none;
    }

    .btn {
      border-radius: 3px;
      text-transform: uppercase;
      letter-spacing: .12em;
      font-family: var(--font-mono);
      font-size: .78rem;
      min-height: 44px;
      padding: .62rem .9rem;
      border-width: 1px;
    }

    .btn-primary, .btn-success, .auth-submit, .btn-green {
      background: var(--green);
      border-color: var(--green);
      color: #000;
      font-weight: 700;
      box-shadow: 0 0 14px rgba(0,255,136,.25);
    }

    .btn-primary:hover, .btn-success:hover, .auth-submit:hover, .btn-green:hover {
      color: #000;
      background: #27ff9f;
      border-color: #27ff9f;
      transform: translateY(-1px);
      box-shadow: 0 0 20px rgba(0,255,136,.4);
    }

    .btn-warning, .btn-amber { background: transparent; color: var(--amber); border-color: var(--amber); }
    .btn-warning:hover, .btn-amber:hover { background: rgba(255,170,0,.14); color: var(--amber); border-color: var(--amber); }
    .btn-danger, .btn-red { background: transparent; color: var(--red); border-color: var(--red); }
    .btn-danger:hover, .btn-red:hover { background: rgba(255,51,85,.14); color: var(--red); border-color: var(--red); }
    .btn-info, .btn-cyan { background: transparent; color: var(--cyan); border-color: var(--cyan); }
    .btn-info:hover, .btn-cyan:hover { background: rgba(0,212,255,.12); color: var(--cyan); border-color: var(--cyan); }

    .btn-outline-light, .btn-outline-secondary, .btn-secondary, .btn-dark,
    .btn-outline-primary, .btn-outline-warning, .btn-outline-danger, .btn-outline-success, .btn-outline-info {
      border-color: var(--border-bright);
      background: transparent;
      color: var(--text-primary);
    }

    .btn-outline-light:hover, .btn-outline-secondary:hover, .btn-secondary:hover, .btn-dark:hover,
    .btn-outline-primary:hover, .btn-outline-warning:hover, .btn-outline-danger:hover, .btn-outline-success:hover, .btn-outline-info:hover {
      border-color: var(--green);
      background: rgba(0,255,136,.08);
      color: var(--green);
    }
    .badge {
      border-radius: 3px;
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: .35rem .5rem;
      border: 1px solid currentColor;
      font-family: var(--font-mono);
      font-weight: 400;
    }

    .badge.text-bg-success { color: var(--green) !important; background: rgba(0,255,136,.08) !important; }
    .badge.text-bg-info { color: var(--cyan) !important; background: rgba(0,212,255,.08) !important; }
    .badge.text-bg-warning { color: var(--amber) !important; background: rgba(255,170,0,.1) !important; }
    .badge.text-bg-danger { color: var(--red) !important; background: rgba(255,51,85,.1) !important; }
    .badge.text-bg-secondary { color: var(--text-dim) !important; background: rgba(200,220,232,.08) !important; }

    .alert { border-radius: 4px; border: 1px solid var(--border); background: rgba(0,212,255,.08); color: var(--text-primary); }
    .alert-info { border-color: rgba(0,212,255,.45); }
    .alert-success { border-color: rgba(0,255,136,.45); background: rgba(0,255,136,.08); }
    .alert-warning { border-color: rgba(255,170,0,.45); background: rgba(255,170,0,.1); }
    .alert-danger { border-color: rgba(255,51,85,.45); background: rgba(255,51,85,.12); }

    .modal-content { background: var(--bg-card); border: 1px solid var(--border-bright); border-radius: 4px; }
    .modal-header, .modal-footer { border-color: var(--border); }
    .btn-close { filter: invert(1) grayscale(1); }

    .terminal-window-dots { display: inline-flex; align-items: center; gap: 6px; margin-bottom: .7rem; }
    .terminal-window-dots span { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
    .terminal-window-dots .dot-red { background: var(--red); }
    .terminal-window-dots .dot-amber { background: var(--amber); }
    .terminal-window-dots .dot-green { background: var(--green); }

    .landing-hero { min-height: calc(100vh - 120px); display: flex; align-items: center; padding: 1rem 0 2rem; }
    .landing-label { font-family: var(--font-mono); color: rgba(0,255,136,.65); letter-spacing: .3em; font-size: .72rem; margin-bottom: 1rem; text-transform: uppercase; }
    .landing-title { line-height: .96; margin-bottom: 1.1rem; }
    .landing-title span { display: block; font-size: clamp(2rem, 7vw, 4.3rem); font-weight: 900; }
    .landing-title .word-green { color: var(--green); text-shadow: 0 0 16px rgba(0,255,136,.4); }
    .landing-title .word-cyan { color: var(--cyan); text-shadow: 0 0 16px rgba(0,212,255,.32); }
    .landing-title .word-main { color: var(--text-primary); }
    .landing-subtitle { color: var(--text-dim); max-width: 60ch; margin-bottom: 1.3rem; font-weight: 300; font-size: 1rem; }
    .cta-row { display: flex; gap: .65rem; flex-wrap: wrap; margin-bottom: .75rem; }
    .btn-register { background: var(--green); color: #000; border: 1px solid var(--green); font-family: var(--font-mono); font-weight: 700; min-width: 170px; }
    .btn-register:hover { color: #000; transform: scale(1.02); box-shadow: 0 0 20px rgba(0,255,136,.45); }
    .btn-login { background: transparent; color: var(--cyan); border: 1px solid var(--cyan); font-family: var(--font-mono); min-width: 130px; }
    .btn-login:hover { color: var(--cyan); background: rgba(0,212,255,.08); }
    .landing-note { color: var(--text-dim); font-family: var(--font-mono); font-size: .78rem; }
    .terminal-panel { background: rgba(0,0,0,.58); border: 1px solid var(--border); border-radius: 4px; padding: 1rem; }
    .typewriter-wrap { min-height: 150px; font-family: var(--font-mono); color: var(--text-primary); font-size: .92rem; line-height: 1.5; }
    .terminal-line { color: var(--green); }
    .terminal-output { color: var(--text-primary); }
    .stat-chip-row { margin-top: .8rem; display: flex; gap: .45rem; flex-wrap: wrap; }
    .stat-chip { border: 1px solid var(--border); background: rgba(0,0,0,.4); color: var(--text-primary); font-family: var(--font-mono); font-size: .72rem; letter-spacing: .08em; padding: .35rem .5rem; border-radius: 3px; }

    .auth-screen { min-height: calc(100vh - 130px); display: flex; align-items: center; justify-content: center; padding: .8rem 0 1.6rem; }
    .auth-shell-wrap { width: 100%; max-width: 460px; border: 1px solid var(--border-bright); background: var(--bg-card); box-shadow: 0 0 40px rgba(0,255,136,.06); border-radius: 6px; padding: 40px; margin: 0 auto; }
    .auth-titlebar { font-family: var(--font-mono); font-size: .78rem; color: var(--text-dim); letter-spacing: .07em; margin-bottom: 1rem; }
    .auth-titlebar .balls { color: var(--green); margin-right: .35rem; }
    .auth-policy { height: 100%; min-height: 360px; }
    .auth-policy .policy-line { margin-bottom: .4rem; color: var(--text-primary); font-family: var(--font-mono); font-size: .85rem; }
    .auth-policy .policy-line .shell { color: var(--green); margin-right: .35rem; }

    .dashboard-header { padding: 1.1rem 1.2rem; border: 1px solid var(--border); border-left: 3px solid var(--green); background: rgba(0,0,0,.42); border-radius: 4px; margin-bottom: 1rem; }
    .operator-name { font-size: clamp(1.2rem, 3vw, 1.9rem); color: var(--text-primary); margin-bottom: .4rem; }
    .operator-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; font-family: var(--font-mono); font-size: .78rem; }
    .rank-badge { border: 1px solid rgba(255,170,0,.45); color: var(--amber); padding: .2rem .5rem; border-radius: 3px; font-family: var(--font-mono); letter-spacing: .08em; text-transform: uppercase; }
    .point-badge { color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,.45); }
    .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; margin-bottom: 1rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px; padding: .9rem; }
    .stat-card .label { color: var(--text-dim); font-size: .72rem; letter-spacing: .11em; text-transform: uppercase; font-family: var(--font-mono); margin-bottom: .4rem; }
    .stat-card .value { font-family: var(--font-display); font-size: clamp(1.4rem, 3.4vw, 1.9rem); font-weight: 700; line-height: 1; }
    .stat-points { border-left: 3px solid var(--green); } .stat-solved { border-left: 3px solid var(--cyan); } .stat-rank { border-left: 3px solid var(--amber); } .stat-remain { border-left: 3px solid var(--red); }
    .progress-shell { border: 1px solid var(--border); border-radius: 4px; background: rgba(0,0,0,.38); padding: .9rem; margin-bottom: 1rem; }
    .progress-label { color: var(--text-dim); font-family: var(--font-mono); letter-spacing: .12em; font-size: .72rem; text-transform: uppercase; margin-bottom: .4rem; }
    .progress-line { font-family: var(--font-mono); color: var(--green); font-size: .95rem; letter-spacing: .03em; }
    .recent-solve-table td, .recent-solve-table th { border: none !important; }
    .solve-yes { color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,.38); font-family: var(--font-mono); }
    .solve-no { color: rgba(200,220,232,.35); font-family: var(--font-mono); }

    .filter-tabs { display: flex; flex-wrap: wrap; gap: .42rem; margin-bottom: 1rem; }
    .filter-tab { border: 1px solid var(--border); background: rgba(0,0,0,.35); color: var(--text-dim); font-family: var(--font-mono); font-size: .78rem; letter-spacing: .09em; min-height: 44px; padding: .45rem .62rem; border-radius: 3px; text-transform: uppercase; }
    .filter-tab.active { color: var(--green); border-bottom: 2px solid var(--green); text-shadow: 0 0 10px rgba(0,255,136,.4); box-shadow: inset 0 0 0 1px rgba(0,255,136,.12); }
    .challenge-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
    .challenge-item { background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; padding: 1rem; position: relative; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .challenge-item:hover { border-color: var(--border-bright); box-shadow: 0 0 20px rgba(0,255,136,.08); transform: translateY(-2px); }
    .challenge-strip { position: absolute; left: 0; top: 0; right: 0; height: 3px; border-radius: 6px 6px 0 0; }
    .strip-web { background: var(--cyan); } .strip-crypto { background: var(--purple); } .strip-forensics { background: var(--amber); } .strip-pwn { background: var(--red); } .strip-linux { background: var(--green); } .strip-default { background: rgba(200,220,232,.35); }
    .challenge-cat { display: inline-flex; align-items: center; justify-content: center; padding: .15rem .45rem; border-radius: 3px; border: 1px solid currentColor; font-size: .66rem; letter-spacing: .09em; text-transform: uppercase; font-family: var(--font-mono); margin-left: auto; }
    .cat-web { color: var(--cyan); } .cat-crypto { color: var(--purple); } .cat-forensics { color: var(--amber); } .cat-pwn { color: var(--red); } .cat-linux { color: var(--green); } .cat-default { color: var(--text-dim); }
    .challenge-title { font-family: var(--font-display); font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: .65rem 0 .55rem; letter-spacing: .06em; text-transform: uppercase; }
    .challenge-points { font-family: var(--font-mono); font-size: 1.25rem; color: var(--green); text-shadow: 0 0 12px rgba(0,255,136,.4); margin-bottom: .9rem; letter-spacing: .06em; }
    .challenge-status { font-family: var(--font-mono); font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; padding: .22rem .45rem; border-radius: 3px; border: 1px solid currentColor; display: inline-block; }
    .status-solved { color: var(--green); background: rgba(0,255,136,.08); } .status-open { color: var(--cyan); background: rgba(0,212,255,.08); } .status-locked { color: rgba(200,220,232,.45); background: rgba(200,220,232,.06); }

    .challenge-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1rem; }
    .challenge-meta { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: .8rem; font-family: var(--font-mono); font-size: .8rem; }
    .challenge-link a, .term-block a, .challenge-description a { color: var(--cyan); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
    .challenge-link a:hover, .term-block a:hover, .challenge-description a:hover { text-shadow: 0 0 10px rgba(0,212,255,.4); }
    .hint-block { border-left-color: var(--amber); margin-top: .8rem; }
    .hint-block summary { cursor: pointer; color: var(--amber); font-family: var(--font-mono); letter-spacing: .08em; text-transform: uppercase; margin-bottom: .5rem; }
    .submit-panel { border: 1px solid var(--border-bright); border-radius: 4px; background: rgba(0,0,0,.45); padding: 1rem; height: fit-content; }
    .submit-title { font-family: var(--font-display); color: var(--green); font-size: .95rem; letter-spacing: .1em; margin-bottom: .9rem; }
    .submit-meta { margin-top: .85rem; color: var(--text-dim); font-family: var(--font-mono); font-size: .74rem; letter-spacing: .06em; text-transform: uppercase; }

    .leader-header { margin-bottom: 1rem; }
    .leader-title { margin-bottom: .2rem; }
    .leader-subtitle { color: var(--text-dim); font-family: var(--font-mono); letter-spacing: .1em; font-size: .75rem; text-transform: uppercase; }
    .scoreboard-wrap { background: transparent; }
    .score-table { width: 100%; border-collapse: collapse; }
    .score-table thead th { background: rgba(0,255,136,.06); color: var(--green); letter-spacing: .1em; text-transform: uppercase; font-family: var(--font-mono); font-size: .75rem; border-bottom: 1px solid var(--border); padding: .85rem 1rem; }
    .score-table tbody td { border-bottom: 1px solid var(--border); padding: .85rem 1rem; font-family: var(--font-mono); font-size: .86rem; }
    .rank-1 { color: #ffd700; text-shadow: 0 0 14px rgba(255,215,0,.4); font-family: var(--font-display); font-weight: 700; }
    .rank-2 { color: #c0c0c0; } .rank-3 { color: #cd7f32; } .rank-rest { color: var(--text-dim); }
    .score-user { color: var(--cyan); font-family: var(--font-mono); }
    .score-points { color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,.35); font-family: var(--font-display); font-weight: 700; }
    .score-solves { color: var(--text-dim); }
    .current-user-row { border-left: 3px solid var(--green); background: rgba(0,255,136,.04); }

    .admin-banner { border: 1px solid rgba(255,51,85,.35); border-left: 4px solid var(--red); background: rgba(255,51,85,.08); border-radius: 4px; padding: .9rem 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: .55rem; font-family: var(--font-mono); letter-spacing: .08em; text-transform: uppercase; color: var(--red); }
    .dot-danger { width: 8px; height: 8px; border-radius: 50%; background: var(--red); box-shadow: 0 0 8px var(--red); animation: pulse-dot 2s infinite; flex-shrink: 0; }
    .admin-actions { display: flex; flex-wrap: wrap; gap: .55rem; }
    .cmd-action { font-family: var(--font-mono); text-transform: lowercase; letter-spacing: .08em; border-radius: 4px; min-height: 44px; padding: .5rem .75rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; }
    .cmd-action.users { border-color: var(--green); color: var(--green); } .cmd-action.challs { border-color: var(--cyan); color: var(--cyan); } .cmd-action.solves { border-color: var(--purple); color: var(--purple); }
    .cmd-action:hover { box-shadow: 0 0 16px rgba(0,255,136,.18); background: rgba(0,255,136,.08); }
    .section-head { font-size: .96rem; margin-bottom: .7rem; color: var(--green); text-shadow: 0 0 10px rgba(0,255,136,.3); }

    .status-screen { min-height: calc(100vh - 120px); display: flex; align-items: center; justify-content: center; text-align: center; padding: 1rem 0; }
    .status-box { max-width: 740px; width: 100%; border: 1px solid var(--border); border-radius: 6px; background: rgba(0,0,0,.55); padding: 2rem 1.25rem; }
    .status-title { font-size: clamp(1.6rem, 5vw, 2.8rem); margin-bottom: .6rem; font-weight: 900; }
    .status-sub { font-family: var(--font-mono); letter-spacing: .12em; text-transform: uppercase; font-size: .78rem; color: var(--text-dim); }
    .status-pending { border-color: rgba(255,170,0,.45); box-shadow: 0 0 0 1px rgba(255,170,0,.15), 0 0 24px rgba(255,170,0,.12); animation: amberPulse 1.8s infinite; }
    @keyframes amberPulse { 0%,100% { box-shadow: 0 0 0 1px rgba(255,170,0,.15), 0 0 20px rgba(255,170,0,.12); } 50% { box-shadow: 0 0 0 1px rgba(255,170,0,.3), 0 0 30px rgba(255,170,0,.2); } }
    .status-pending .status-title { color: var(--amber); text-shadow: 0 0 14px rgba(255,170,0,.4); }
    .status-banned { border-color: rgba(255,51,85,.5); } .status-banned .status-title { color: var(--red); text-shadow: 0 0 14px rgba(255,51,85,.45); }
    .status-403 { border-color: rgba(255,170,0,.3); } .status-403 .status-title { color: var(--red); text-shadow: 0 0 14px rgba(255,51,85,.45); }
    .status-ascii { font-family: var(--font-mono); color: var(--red); margin: 0 auto 1rem; display: inline-block; text-align: left; letter-spacing: .08em; font-size: .9rem; }

    .ops-footer { border-top: 1px solid var(--border); padding: .7rem 0; margin-top: 1rem; background: rgba(2,4,10,.9); font-family: var(--font-mono); font-size: .8rem; color: var(--text-dim); }
    .ops-footer-shell { color: var(--text-primary); letter-spacing: .05em; }

    @media (max-width: 991.98px) {
      .ops-bar { gap: .6rem; }
      .ops-collapse { width: 100%; flex: 0 0 100%; }
      .ops-nav-shell { flex-direction: column; align-items: stretch; gap: .5rem; padding-bottom: .35rem; }
      .ops-links, .ops-right { justify-content: flex-start; }
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .challenge-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .challenge-layout { grid-template-columns: 1fr; }
      .flash-stack { top: 62px; right: 10px; }
      .landing-hero { min-height: auto; }
    }

    @media (max-width: 767.98px) {
      .challenge-grid, .stats-grid { grid-template-columns: 1fr; }
      .auth-shell-wrap { padding: 28px 18px; }
      .ops-countdown { width: 100%; justify-content: space-between; }
      .ops-user, .ops-exit { min-height: 44px; }
    }
  </style>
</head>

<body>
<header class="ops-topbar">
  <div class="container">
    <div class="ops-bar">
      <a class="ops-brand" href="<?= e(BASE_URL) ?>/index.php">
        <span class="ops-brand-main glow-green"><span class="ops-brand-prompt">&gt;</span><?= e(APP_NAME) ?></span>
        <span class="ops-brand-sub"><span class="dot-online"></span>SYSTEM:ONLINE</span>
      </a>

      <button class="ops-toggle d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#opsNav" aria-controls="opsNav" aria-expanded="false" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <div class="collapse d-lg-flex ops-collapse" id="opsNav">
        <div class="ops-nav-shell">
          <nav class="ops-links">
            <?php if ($u && ($u['status'] ?? '') === 'active'): ?>
              <?php foreach ($navItems as $item): ?>
                <?php $isActive = ($currentPage === basename($item['path'])); ?>
                <a class="ops-link<?= $isActive ? ' active' : '' ?>" href="<?= e(BASE_URL) . e($item['path']) ?>">
                  <span class="cmd-prefix">./</span><?= e($item['label']) ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </nav>

          <div class="ops-right">
            <?php if ($mode === 'before'): ?>
              <div class="ops-countdown ops-before" id="challengeCountdown" data-seconds="<?= e((string)$secondsLeft) ?>">
                <span class="ops-countdown-state glow-amber">OPENS IN</span>
                <span class="ops-countdown-time">[ <span id="cdText">--:--:--</span> ]</span>
              </div>
            <?php elseif ($mode === 'running'): ?>
              <div class="ops-countdown ops-live" id="challengeCountdown" data-seconds="<?= e((string)$secondsLeft) ?>">
                <span class="ops-countdown-state glow-green">LIVE</span>
                <span class="ops-countdown-time">[ <span id="cdText">--:--:--</span> ]</span>
              </div>
            <?php else: ?>
              <div class="ops-countdown ops-closed">
                <span class="ops-countdown-state">CLOSED</span>
                <span class="ops-countdown-time">[ 00:00:00 ]</span>
              </div>
            <?php endif; ?>

            <?php if ($u): ?>
              <span class="ops-user">@<?= e($u['username']) ?></span>
              <a class="ops-exit" href="<?= e(BASE_URL) ?>/logout.php">[ EXIT ]</a>
            <?php else: ?>
              <span class="ops-user">@guest</span>
              <a class="ops-link<?= $currentPage === 'login.php' ? ' active' : '' ?>" href="<?= e(BASE_URL) ?>/login.php"><span class="cmd-prefix">./</span>login</a>
              <a class="ops-link<?= $currentPage === 'register.php' ? ' active' : '' ?>" href="<?= e(BASE_URL) ?>/register.php"><span class="cmd-prefix">./</span>register</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

<?php if ($flashes): ?>
  <div class="flash-stack" id="flashStack">
    <?php foreach ($flashes as $f): ?>
      <?php
        $flashType = strtolower((string)($f['type'] ?? 'info'));
        $flashClass = 'flash-info';
        $flashPrefix = '[INFO]';

        if ($flashType === 'success') {
          $flashClass = 'flash-success';
          $flashPrefix = '[SUCCESS]';
        } elseif ($flashType === 'danger' || $flashType === 'error') {
          $flashClass = 'flash-danger';
          $flashPrefix = '[ERROR]';
        } elseif ($flashType === 'warning') {
          $flashClass = 'flash-warning';
          $flashPrefix = '[WARNING]';
        }
      ?>
      <div class="flash-item term-block <?= e($flashClass) ?>">
        <div class="flash-prefix"><?= e($flashPrefix) ?></div>
        <div class="flash-body"><?= e($f['msg']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  const countdown = document.getElementById('challengeCountdown');
  const cdText = document.getElementById('cdText');

  if (countdown && cdText) {
    let seconds = parseInt(countdown.dataset.seconds || '0', 10);

    function pad(n) {
      return String(n).padStart(2, '0');
    }

    function format(sec) {
      const h = Math.floor(sec / 3600);
      const m = Math.floor((sec % 3600) / 60);
      const s = sec % 60;
      return `${pad(h)}:${pad(m)}:${pad(s)}`;
    }

    function tick() {
      cdText.textContent = format(Math.max(seconds, 0));

      if (seconds <= 0) {
        setTimeout(() => location.reload(), 900);
        return;
      }

      seconds -= 1;
      setTimeout(tick, 1000);
    }

    tick();
  }

  const flashItems = Array.from(document.querySelectorAll('.flash-item'));
  flashItems.forEach((item, index) => {
    setTimeout(() => {
      item.classList.add('show');
    }, 60 + (index * 80));

    setTimeout(() => {
      item.classList.add('hide');
      setTimeout(() => item.remove(), 420);
    }, 4000 + (index * 180));
  });
})();
</script>

<main class="container app-main">
