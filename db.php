<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Return a shared PDO instance with one retry on initial connect failure.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (DB_CONFIG_ERROR !== '') {
        throw new RuntimeException(DB_CONFIG_ERROR);
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $attempts = 0;
    while ($attempts < 2) {
        try {
            $attempts++;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            if ($attempts >= 2) {
                throw $e;
            }

            usleep(200000);
        }
    }

    throw new RuntimeException('Could not establish database connection.');
}