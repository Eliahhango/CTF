<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_session();

/**
 * Format event date/time using app timezone.
 */
function format_ctf_datetime(string $raw): string
{
    try {
        $tz = new DateTimeZone(APP_TIMEZONE);
        $dt = new DateTimeImmutable($raw, $tz);
        return $dt->format('F j, Y g:i A') . ' (' . APP_TIMEZONE . ')';
    } catch (Throwable $e) {
        return $raw;
    }
}

/**
 * Split multiline text into non-empty trimmed lines.
 *
 * @return list<string>
 */
function info_lines(string $text): array
{
    $parts = preg_split('/\r\n|\r|\n/', $text);
    if (!is_array($parts)) {
        return [];
    }

    $lines = [];
    foreach ($parts as $line) {
        $clean = trim($line);
        if ($clean !== '') {
            $lines[] = $clean;
        }
    }

    return $lines;
}

$openFormatted = format_ctf_datetime(CHALLENGES_OPEN_AT);
$closeFormatted = format_ctf_datetime(CHALLENGES_CLOSE_AT);
$rulesLines = info_lines((string)CTF_RULES);
$prizeLines = info_lines((string)CTF_PRIZES);
$discord = trim((string)CTF_DISCORD);
$hasDiscord = $discord !== '' && is_safe_http_url($discord);

include __DIR__ . '/header.php';
?>

<div class="card mb-3">
  <div class="card-body p-4">
    <h1 class="page-title mb-1"><?= e(APP_NAME) ?></h1>
    <p class="page-subtitle mb-2">
      <?= e($openFormatted) ?> - <?= e($closeFormatted) ?>
    </p>
    <span class="badge text-bg-primary">Organized by <?= e((string)CTF_ORGANIZER) ?></span>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">About</h2>
        <p class="mb-0"><?= nl2br(linkify((string)CTF_DESCRIPTION), false) ?></p>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Schedule</h2>
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small mb-1">Challenges Open</div>
              <div class="fw-semibold"><?= e($openFormatted) ?></div>
            </div>
          </div>
          <div class="col-sm-6">
            <div class="border rounded p-3 h-100">
              <div class="text-muted small mb-1">Challenges Close</div>
              <div class="fw-semibold"><?= e($closeFormatted) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Flag Format</h2>
    <pre class="mb-0"><code>ccd{example_flag_here}</code></pre>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body p-4">
    <h2 class="h5 mb-3">Rules</h2>
    <?php if (!$rulesLines): ?>
      <div class="alert alert-info mb-0">Rules will be announced soon.</div>
    <?php else: ?>
      <?php foreach ($rulesLines as $line): ?>
        <p class="mb-2"><?= nl2br(linkify($line), false) ?></p>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($prizeLines): ?>
  <div class="card mb-3">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Prizes</h2>
      <?php foreach ($prizeLines as $line): ?>
        <p class="mb-2"><?= nl2br(linkify($line), false) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($hasDiscord): ?>
  <div class="card mb-3">
    <div class="card-body p-4">
      <h2 class="h5 mb-3">Contact</h2>
      <a class="btn btn-primary" href="<?= e($discord) ?>" target="_blank" rel="noopener noreferrer">Join Discord</a>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
