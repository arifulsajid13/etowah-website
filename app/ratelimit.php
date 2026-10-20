<?php
declare(strict_types=1);

/* Per-visitor rate limits, stored by IP hash (never the raw IP). */

function rate_limit_allows(string $bucket, int $max, int $windowSeconds): bool
{
    if (random_int(1, 50) === 1) {
        db_run('DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL 1 DAY');
    }
    $count = (int) db_value(
        'SELECT COUNT(*) FROM rate_limits WHERE ip_hash = ? AND bucket = ? AND created_at > NOW() - INTERVAL ? SECOND',
        [ip_hash(), $bucket, $windowSeconds]
    );
    return $count < $max;
}

function rate_limit_record(string $bucket): void
{
    db_run('INSERT INTO rate_limits (ip_hash, bucket, created_at) VALUES (?, ?, NOW())', [ip_hash(), $bucket]);
}
