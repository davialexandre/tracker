<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/log.php';

function connect_db(): PDO {
    $pdo = new PDO(getenv('DB_DSN'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function insert_location(PDO $pdo, array $row, ?int $now = null): void {
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO location (acc, alt, lat, lon, vac, vel, tst, received_at)
        VALUES (:acc, :alt, :lat, :lon, :vac, :vel, :tst, :received_at)
    SQL);
    $stmt->execute([
        ':acc'         => $row['acc'] ?? 0,
        ':alt'         => $row['alt'] ?? 0,
        ':lat'         => $row['lat'],
        ':lon'         => $row['lon'],
        ':vac'         => $row['vac'] ?? 0,
        ':vel'         => $row['vel'] ?? 0,
        ':tst'         => $row['tst'],
        ':received_at' => $now ?? time(),
    ]);
}

function insert_locations(PDO $pdo, array $rows): int {
    if (empty($rows)) {
        return 0;
    }

    $now = time();
    $pdo->beginTransaction();
    try {
        foreach ($rows as $row) {
            insert_location($pdo, $row, $now);
        }
        $pdo->commit();
        return count($rows);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
