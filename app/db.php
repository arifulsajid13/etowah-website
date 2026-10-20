<?php
declare(strict_types=1);

function db_connect(array $cfg): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['name']);
    if (!empty($cfg['port'])) {
        $dsn .= ';port=' . (int) $cfg['port'];
    }
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
    // Keep NOW() in MySQL in step with PHP's local time (America/New_York).
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = db_connect((array) config('db', []));
        } catch (PDOException $e) {
            log_error('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
                . '<title>Temporarily unavailable</title></head><body style="font-family:system-ui,sans-serif;padding:40px">'
                . '<h1>We’ll be right back</h1><p>The website is temporarily unavailable. Please try again in a few minutes.</p></body></html>';
            exit;
        }
    }
    return $pdo;
}

/** Run a prepared statement and return it. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [])
{
    $value = db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}
