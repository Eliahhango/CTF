<?php
// config.php

// DATABASE
define('DB_HOST', 'localhost');
define('DB_NAME', 'if0_41083503_ctf_ccd');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// APP
define('APP_NAME', 'Cyber Club DIT CTF');
define('BASE_URL', '/ccd'); // '' for root, or '/ctf' if hosted in a subfolder
define('SESSION_NAME', 'ctf_session');

// SECURITY
define('CSRF_TTL_SECONDS', 7200);
define('FLAG_SUBMIT_RATE_LIMIT_PER_MIN', 12);
define('PASSWORD_MIN_LEN', 8);

// CHALLENGE OPENING TIME (Dar es Salaam)
define('APP_TIMEZONE', 'Africa/Dar_es_Salaam');

// Set the exact opening date/time (24h format)
define('CHALLENGES_OPEN_AT', '2026-02-27 21:10:00');

define('CHALLENGES_CLOSE_AT', '2026-03-01 21:00:00'); // end time