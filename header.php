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

$navItems = [
  ['label' => './index', 'path' => '/index.php'],
];

if ($u && ($u['status'] ?? '') === 'active') {
  $navItems[] = ['label' => './dashboard', 'path' => '/dashboard.php'];
  if (challenges_window_open()) {
    $navItems[] = ['label' => './challenges', 'path' => '/challenges.php'];
  }
  $navItems[] = ['label' => './leaderboard', 'path' => '/leaderboard.php'];
}

if ($u && ($u['role'] ?? '') === 'admin') {
  $navItems[] = ['label' => './admin', 'path' => '/admin.php'];
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
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700;800&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <style>
    :root {
      --bg: #050810;
      --surface: #0b1220;
      --surface-soft: #0f1a2c;
      --text: #c8d8e8;
      --muted: rgba(200, 216, 232, 0.68);
      --green: #00ff88;
      --cyan: #00d4ff;
      --amber: #ffaa00;
      --red: #ff3366;
      --line: rgba(0, 255, 136, 0.35);
      --line-soft: rgba(0, 255, 136, 0.2);
    }

    * {
      box-sizing: border-box;
    }

    html,
    body {
      min-height: 100%;
    }

    body {
      margin: 0;
      font-family: 'JetBrains Mono', 'Fira Code', monospace;
      color: var(--text);
      background-color: var(--bg);
      background-image:
        linear-gradient(rgba(0, 255, 136, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0, 255, 136, 0.03) 1px, transparent 1px),
        radial-gradient(circle at 10% 5%, rgba(0, 212, 255, 0.12), transparent 45%),
        radial-gradient(circle at 90% 95%, rgba(0, 255, 136, 0.12), transparent 40%);
      background-size: 40px 40px, 40px 40px, auto, auto;
    }

    body::after {
      content: '';
      position: fixed;
      inset: 0;
      z-index: 999;
      pointer-events: none;
      opacity: 0.12;
      background-image: repeating-linear-gradient(
        to bottom,
        rgba(255, 255, 255, 0.04) 0,
        rgba(255, 255, 255, 0.04) 1px,
        transparent 2px,
        transparent 4px
      );
    }

    a {
      color: var(--cyan);
      text-decoration: none;
    }

    a:hover {
      color: var(--green);
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6,
    .terminal-heading {
      color: var(--text);
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-weight: 700;
    }

    .text-muted,
    .text-light-emphasis,
    .form-text,
    .muted-cyber,
    .footer-muted {
      color: var(--muted) !important;
    }

    code {
      color: var(--green);
      background: rgba(0, 255, 136, 0.08);
      border: 1px solid rgba(0, 255, 136, 0.2);
      border-radius: 4px;
      padding: 0.1rem 0.35rem;
    }

    hr {
      border-color: rgba(0, 255, 136, 0.2) !important;
      opacity: 1;
    }

    .terminal-topbar {
      position: sticky;
      top: 0;
      z-index: 1040;
      border-bottom: 1px solid var(--line);
      background: rgba(5, 8, 16, 0.94);
      backdrop-filter: blur(8px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    }

    .terminal-topbar-inner {
      min-height: 72px;
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
      padding: 0.7rem 0;
    }

    .system-status {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--green);
      white-space: nowrap;
      text-shadow: 0 0 12px rgba(0, 255, 136, 0.4);
    }

    .live-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 12px rgba(0, 255, 136, 0.9);
      animation: livePulse 1.4s infinite;
    }

    @keyframes livePulse {
      0% {
        transform: scale(1);
        opacity: 1;
      }
      60% {
        transform: scale(1.2);
        opacity: 0.45;
      }
      100% {
        transform: scale(1);
        opacity: 1;
      }
    }

    .terminal-nav {
      display: flex;
      align-items: center;
      gap: 0.55rem;
      flex: 1 1 auto;
      flex-wrap: wrap;
    }

    .nav-cmd {
      display: inline-flex;
      align-items: center;
      padding: 0.35rem 0.65rem;
      border: 1px solid transparent;
      color: var(--text);
      text-transform: lowercase;
      letter-spacing: 0.08em;
      border-radius: 4px;
      transition: all 0.2s ease;
    }

    .nav-cmd:hover {
      border-color: var(--line-soft);
      color: var(--green);
    }

    .nav-cmd.active {
      color: var(--green);
      border-color: var(--green);
      text-shadow: 0 0 12px rgba(0, 255, 136, 0.55);
      box-shadow: 0 0 12px rgba(0, 255, 136, 0.25);
    }

    .session-panel {
      margin-left: auto;
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 0.5rem;
      flex-wrap: wrap;
      font-size: 0.84rem;
    }

    .session-chip {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.6rem;
      border-radius: 4px;
      border: 1px solid rgba(0, 212, 255, 0.45);
      background: rgba(0, 212, 255, 0.08);
      color: var(--text);
      white-space: nowrap;
    }

    .countdown-chip {
      border-color: rgba(0, 255, 136, 0.45);
      background: rgba(0, 255, 136, 0.08);
      color: var(--green);
      text-shadow: 0 0 8px rgba(0, 255, 136, 0.35);
    }

    .countdown-ended {
      border-color: rgba(255, 170, 0, 0.45);
      background: rgba(255, 170, 0, 0.08);
      color: var(--amber);
      text-shadow: 0 0 8px rgba(255, 170, 0, 0.35);
    }

    #cdText {
      min-width: 8ch;
      text-align: right;
      display: inline-block;
      font-weight: 700;
    }

    .app-main {
      position: relative;
      z-index: 2;
      padding-top: 1.6rem;
    }

    .card {
      background: rgba(10, 17, 29, 0.92) !important;
      border: 1px solid var(--line-soft) !important;
      color: var(--text) !important;
      border-radius: 8px !important;
      box-shadow: inset 0 0 0 1px rgba(0, 212, 255, 0.06), 0 12px 28px rgba(0, 0, 0, 0.25);
    }

    .table {
      --bs-table-bg: transparent;
      --bs-table-color: var(--text);
      --bs-table-border-color: rgba(0, 255, 136, 0.18);
      margin-bottom: 0;
      color: var(--text);
    }

    .table thead th {
      border-bottom: 1px solid rgba(0, 212, 255, 0.5);
      color: var(--cyan);
      text-transform: uppercase;
      letter-spacing: 0.12em;
      font-size: 0.78rem;
      font-weight: 700;
    }

    .table tbody tr {
      border-color: rgba(0, 255, 136, 0.15);
      transition: background 0.2s ease;
    }

    .table tbody tr:hover {
      background: rgba(0, 255, 136, 0.06);
    }

    .form-control,
    .form-select,
    textarea,
    .input-group-text {
      background: rgba(4, 9, 17, 0.9);
      border: 1px solid rgba(0, 255, 136, 0.4);
      color: var(--text);
      border-radius: 4px;
    }

    .form-control::placeholder,
    textarea::placeholder {
      color: rgba(200, 216, 232, 0.45);
    }

    .form-select option {
      background: #07101c;
      color: var(--text);
    }

    .form-control:focus,
    .form-select:focus,
    textarea:focus {
      border-color: var(--green);
      background: rgba(0, 255, 136, 0.06);
      color: var(--text);
      box-shadow: 0 0 0 0.2rem rgba(0, 255, 136, 0.14);
    }

    .btn {
      font-family: 'JetBrains Mono', 'Fira Code', monospace;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-size: 0.82rem;
    }

    .btn-primary,
    .btn-success,
    .btn-terminal {
      border: 1px solid var(--green);
      background: rgba(0, 255, 136, 0.13);
      color: var(--green);
      box-shadow: 0 0 14px rgba(0, 255, 136, 0.2);
    }

    .btn-primary:hover,
    .btn-success:hover,
    .btn-terminal:hover {
      background: rgba(0, 255, 136, 0.22);
      color: var(--green);
      border-color: var(--green);
      box-shadow: 0 0 20px rgba(0, 255, 136, 0.35);
    }

    .btn-info {
      border: 1px solid var(--cyan);
      background: rgba(0, 212, 255, 0.12);
      color: var(--cyan);
      box-shadow: 0 0 14px rgba(0, 212, 255, 0.2);
    }

    .btn-info:hover {
      border-color: var(--cyan);
      background: rgba(0, 212, 255, 0.2);
      color: var(--cyan);
    }

    .btn-warning {
      border: 1px solid var(--amber);
      background: rgba(255, 170, 0, 0.12);
      color: var(--amber);
      box-shadow: 0 0 14px rgba(255, 170, 0, 0.2);
    }

    .btn-warning:hover {
      border-color: var(--amber);
      background: rgba(255, 170, 0, 0.2);
      color: var(--amber);
    }

    .btn-danger {
      border: 1px solid var(--red);
      background: rgba(255, 51, 102, 0.12);
      color: var(--red);
      box-shadow: 0 0 14px rgba(255, 51, 102, 0.2);
    }

    .btn-danger:hover {
      border-color: var(--red);
      background: rgba(255, 51, 102, 0.2);
      color: var(--red);
    }

    .btn-outline-light,
    .btn-outline-secondary,
    .btn-secondary,
    .btn-dark,
    .btn-outline-primary,
    .btn-outline-warning,
    .btn-outline-danger,
    .btn-outline-success,
    .btn-outline-info {
      border-color: rgba(0, 255, 136, 0.45);
      background: transparent;
      color: var(--text);
    }

    .btn-outline-light:hover,
    .btn-outline-secondary:hover,
    .btn-secondary:hover,
    .btn-dark:hover,
    .btn-outline-primary:hover,
    .btn-outline-warning:hover,
    .btn-outline-danger:hover,
    .btn-outline-success:hover,
    .btn-outline-info:hover {
      border-color: var(--green);
      color: var(--green);
      background: rgba(0, 255, 136, 0.12);
      box-shadow: 0 0 16px rgba(0, 255, 136, 0.25);
    }

    .badge {
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 700;
      border: 1px solid currentColor;
      padding: 0.35rem 0.5rem;
    }

    .badge.text-bg-success {
      color: var(--green) !important;
      background: rgba(0, 255, 136, 0.12) !important;
    }

    .badge.text-bg-info {
      color: var(--cyan) !important;
      background: rgba(0, 212, 255, 0.12) !important;
    }

    .badge.text-bg-warning {
      color: var(--amber) !important;
      background: rgba(255, 170, 0, 0.12) !important;
    }

    .badge.text-bg-danger {
      color: var(--red) !important;
      background: rgba(255, 51, 102, 0.12) !important;
    }

    .badge.text-bg-secondary {
      color: var(--muted) !important;
      background: rgba(200, 216, 232, 0.08) !important;
    }

    .alert {
      border-radius: 6px;
      border: 1px solid rgba(0, 212, 255, 0.3);
      background: rgba(0, 212, 255, 0.08);
      color: var(--text);
    }

    .alert-success {
      border-color: rgba(0, 255, 136, 0.45);
      background: rgba(0, 255, 136, 0.08);
      color: var(--text);
    }

    .alert-warning {
      border-color: rgba(255, 170, 0, 0.45);
      background: rgba(255, 170, 0, 0.08);
      color: var(--text);
    }

    .alert-danger {
      border-color: rgba(255, 51, 102, 0.45);
      background: rgba(255, 51, 102, 0.1);
      color: var(--text);
    }

    .alert-info {
      border-color: rgba(0, 212, 255, 0.45);
      background: rgba(0, 212, 255, 0.1);
      color: var(--text);
    }

    .modal-content {
      background: var(--surface);
      border: 1px solid var(--line-soft);
      color: var(--text);
    }

    .modal-header,
    .modal-footer {
      border-color: rgba(0, 255, 136, 0.2);
    }

    .btn-close {
      filter: invert(1) grayscale(1) brightness(1.5);
    }

    .terminal-window-head {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      margin-bottom: 0.75rem;
    }

    .terminal-window-head .dot-red,
    .terminal-window-head .dot-amber,
    .terminal-window-head .dot-green {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      display: inline-block;
    }

    .terminal-window-head .dot-red {
      background: var(--red);
    }

    .terminal-window-head .dot-amber {
      background: var(--amber);
    }

    .terminal-window-head .dot-green {
      background: var(--green);
    }

    .terminal-block {
      position: relative;
      border: 1px solid var(--line-soft);
      background: rgba(0, 0, 0, 0.28);
      border-radius: 6px;
      padding: 0.95rem;
    }

    .terminal-cursor {
      display: inline-block;
      width: 0.7ch;
      color: var(--green);
      animation: cursorBlink 1s steps(1, end) infinite;
    }

    @keyframes cursorBlink {
      0%,
      49% {
        opacity: 1;
      }
      50%,
      100% {
        opacity: 0;
      }
    }

    .hero-terminal {
      min-height: calc(100vh - 190px);
      display: flex;
      align-items: center;
    }

    .ascii-banner {
      margin: 0;
      color: var(--green);
      white-space: pre;
      line-height: 1.2;
      font-size: clamp(8px, 1.05vw, 15px);
      text-shadow: 0 0 18px rgba(0, 255, 136, 0.35);
    }

    .typewriter {
      display: inline-block;
      min-height: 1.4em;
      color: var(--cyan);
      border-right: 2px solid var(--green);
      padding-right: 6px;
    }

    .btn-command {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      border: 1px solid var(--green);
      background: rgba(0, 255, 136, 0.1);
      color: var(--green);
      padding: 0.7rem 1rem;
      text-transform: none;
      letter-spacing: 0.06em;
      box-shadow: 0 0 14px rgba(0, 255, 136, 0.2);
    }

    .btn-command .prompt {
      color: var(--cyan);
    }

    .btn-command:hover {
      background: rgba(0, 255, 136, 0.2);
      box-shadow: 0 0 20px rgba(0, 255, 136, 0.35);
      color: var(--green);
    }

    .auth-shell {
      min-height: calc(100vh - 240px);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .auth-terminal-card {
      width: min(560px, 100%);
      border: 1px solid var(--green);
      background: rgba(5, 8, 16, 0.95);
      box-shadow: 0 0 24px rgba(0, 255, 136, 0.18);
      border-radius: 8px;
      padding: 2rem;
      position: relative;
    }

    .auth-terminal-card::before {
      content: '[ AUTH TERMINAL ]';
      position: absolute;
      top: -0.75rem;
      left: 1rem;
      color: var(--cyan);
      font-size: 0.74rem;
      padding: 0 0.35rem;
      background: var(--bg);
      letter-spacing: 0.1em;
    }

    .shell-label {
      color: var(--green);
      letter-spacing: 0.08em;
      text-transform: lowercase;
      margin-bottom: 0.35rem;
      display: block;
    }

    .shell-label::before {
      content: '> ';
      color: var(--cyan);
    }

    .terminal-input {
      border: 0 !important;
      border-bottom: 1px solid rgba(0, 255, 136, 0.45) !important;
      border-radius: 0 !important;
      background: transparent !important;
      box-shadow: none !important;
      padding-left: 0;
      padding-right: 0;
      color: var(--text);
    }

    .terminal-input:focus {
      border-bottom-color: var(--green) !important;
      box-shadow: 0 3px 0 -1px rgba(0, 255, 136, 0.4) !important;
      background: transparent !important;
    }

    .field-line {
      position: relative;
    }

    .field-line:focus-within::after {
      content: '|';
      position: absolute;
      right: 0.15rem;
      bottom: 0.5rem;
      color: var(--green);
      animation: cursorBlink 1s steps(1, end) infinite;
    }

    .challenge-controls {
      display: flex;
      flex-wrap: wrap;
      gap: 0.7rem;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }

    .challenge-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 1rem;
    }

    .challenge-card {
      border: 1px solid var(--line-soft);
      background: rgba(10, 16, 28, 0.95);
      border-radius: 8px;
      padding: 0.95rem;
      transition: border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
    }

    .challenge-card:hover {
      transform: translateY(-2px);
      border-color: var(--green);
      box-shadow: 0 0 18px rgba(0, 255, 136, 0.2);
    }

    .challenge-title {
      margin: 0.5rem 0;
      font-size: 1rem;
      letter-spacing: 0.08em;
    }

    .challenge-points {
      color: var(--green);
      font-weight: 700;
      margin-bottom: 0.7rem;
    }

    .cat-tag {
      display: inline-block;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 0.2rem 0.45rem;
      border: 1px solid currentColor;
      border-radius: 4px;
    }

    .cat-web { color: var(--cyan); }
    .cat-forensics { color: var(--green); }
    .cat-crypto { color: var(--amber); }
    .cat-pwn { color: var(--red); }
    .cat-default { color: var(--text); }

    .status-badge {
      display: inline-block;
      font-size: 0.72rem;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      border-radius: 4px;
      border: 1px solid currentColor;
      padding: 0.2rem 0.45rem;
    }

    .status-open {
      color: var(--cyan);
      background: rgba(0, 212, 255, 0.1);
    }

    .status-solved {
      color: var(--green);
      background: rgba(0, 255, 136, 0.1);
    }

    .status-locked {
      color: var(--red);
      background: rgba(255, 51, 102, 0.1);
    }

    .leaderboard-terminal tbody tr {
      border-left: 3px solid transparent;
      transition: border-color 0.2s ease, background 0.2s ease;
    }

    .leaderboard-terminal tbody tr:hover {
      border-left-color: var(--green);
      background: rgba(0, 255, 136, 0.08);
    }

    .rank-chip {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 34px;
      height: 34px;
      border-radius: 4px;
      border: 1px solid rgba(200, 216, 232, 0.35);
      background: rgba(200, 216, 232, 0.06);
      font-weight: 700;
    }

    .rank-1 {
      color: #ffd966;
      border-color: #ffd966;
      box-shadow: 0 0 14px rgba(255, 217, 102, 0.4);
    }

    .rank-2 {
      color: #d7e3f2;
      border-color: #d7e3f2;
      box-shadow: 0 0 12px rgba(215, 227, 242, 0.32);
    }

    .rank-3 {
      color: #d59d66;
      border-color: #d59d66;
      box-shadow: 0 0 12px rgba(213, 157, 102, 0.32);
    }

    .stat-box {
      border: 1px solid var(--line-soft);
      background: rgba(8, 14, 25, 0.95);
      border-radius: 8px;
      padding: 0.95rem;
      height: 100%;
    }

    .stat-label {
      color: var(--cyan);
      font-size: 0.75rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      margin-bottom: 0.45rem;
    }

    .stat-value {
      color: var(--green);
      font-size: 1.7rem;
      font-weight: 700;
      text-shadow: 0 0 12px rgba(0, 255, 136, 0.35);
    }

    .ascii-progress {
      font-size: 1rem;
      color: var(--green);
      letter-spacing: 0.03em;
      margin: 0;
    }

    .admin-stat-number {
      font-size: 2rem;
      font-weight: 800;
      letter-spacing: 0.04em;
    }

    .neon-green {
      color: var(--green);
      text-shadow: 0 0 12px rgba(0, 255, 136, 0.4);
    }

    .neon-cyan {
      color: var(--cyan);
      text-shadow: 0 0 12px rgba(0, 212, 255, 0.4);
    }

    .neon-amber {
      color: var(--amber);
      text-shadow: 0 0 12px rgba(255, 170, 0, 0.35);
    }

    .neon-red {
      color: var(--red);
      text-shadow: 0 0 12px rgba(255, 51, 102, 0.35);
    }

    .terminal-footer {
      margin-top: 3rem;
      border-top: 1px solid var(--line-soft);
      background: rgba(5, 8, 16, 0.95);
      position: relative;
      z-index: 2;
    }

    .footer-title {
      color: var(--cyan);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      font-size: 0.82rem;
      margin-bottom: 0.45rem;
    }

    .footer-link {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      color: var(--text);
      margin-right: 0.85rem;
      margin-bottom: 0.45rem;
    }

    .footer-link:hover {
      color: var(--green);
    }

    .terminal-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      border: 1px solid rgba(0, 212, 255, 0.35);
      background: rgba(0, 212, 255, 0.08);
      padding: 0.28rem 0.55rem;
      border-radius: 4px;
      color: var(--text);
      font-size: 0.78rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    @media (max-width: 991.98px) {
      .terminal-topbar-inner {
        justify-content: center;
      }

      .system-status,
      .session-panel,
      .terminal-nav {
        width: 100%;
        justify-content: center;
      }

      .session-panel {
        margin-left: 0;
      }

      .hero-terminal {
        min-height: auto;
        padding-top: 1.5rem;
        padding-bottom: 1rem;
      }

      .ascii-banner {
        font-size: clamp(7px, 1.75vw, 11px);
      }
    }
  </style>
</head>

<body>
<header class="terminal-topbar">
  <div class="container terminal-topbar-inner">
    <div class="system-status">
      <span class="live-dot"></span>
      <span>[SYSTEM: ONLINE]</span>
    </div>

    <nav class="terminal-nav" aria-label="Primary navigation">
      <?php foreach ($navItems as $item): ?>
        <?php $isActive = ($currentPage === basename($item['path'])); ?>
        <a class="nav-cmd<?= $isActive ? ' active' : '' ?>" href="<?= e(BASE_URL) . e($item['path']) ?>">
          <?= e($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="session-panel">
      <?php if ($u): ?>
        <span class="session-chip">SESSION: <?= e($u['username']) ?>@<?= e($u['role'] ?? 'user') ?></span>
      <?php else: ?>
        <span class="session-chip">SESSION: GUEST</span>
      <?php endif; ?>

      <?php if ($mode === 'before'): ?>
        <span class="session-chip countdown-chip" id="challengeCountdown" data-seconds="<?= e((string)$secondsLeft) ?>">OPENS IN <span id="cdText">--:--:--</span></span>
      <?php elseif ($mode === 'running'): ?>
        <span class="session-chip countdown-chip" id="challengeCountdown" data-seconds="<?= e((string)$secondsLeft) ?>">ENDS IN <span id="cdText">--:--:--</span></span>
      <?php else: ?>
        <span class="session-chip countdown-ended">EVENT CLOSED 00:00:00</span>
      <?php endif; ?>

      <?php if ($u): ?>
        <a class="nav-cmd" href="<?= e(BASE_URL) ?>/logout.php">./logout</a>
      <?php else: ?>
        <a class="nav-cmd<?= $currentPage === 'login.php' ? ' active' : '' ?>" href="<?= e(BASE_URL) ?>/login.php">./login</a>
        <a class="nav-cmd<?= $currentPage === 'register.php' ? ' active' : '' ?>" href="<?= e(BASE_URL) ?>/register.php">./register</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container app-main">
  <?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
  <?php endforeach; ?>
