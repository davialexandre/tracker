<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/log.php';

const LOCATION_INSERT_SQL = <<<SQL
    INSERT INTO location (acc, alt, lat, lon, vac, vel, tst, received_at)
    VALUES (:acc, :alt, :lat, :lon, :vac, :vel, :tst, :received_at)
    SQL;

function connect_db(): PDO {
    $pdo = new PDO(getenv('DB_DSN'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function location_bind_values(array $row, ?int $now = null): array {
    return [
        ':acc'         => $row['acc'] ?? 0,
        ':alt'         => $row['alt'] ?? 0,
        ':lat'         => $row['lat'],
        ':lon'         => $row['lon'],
        ':vac'         => $row['vac'] ?? 0,
        ':vel'         => $row['vel'] ?? 0,
        ':tst'         => $row['tst'],
        ':received_at' => $now ?? time(),
    ];
}
