<?php

ini_set('display_errors', '0');
error_reporting(E_ALL);

require __DIR__ . '/log.php';

header("Content-type: application/json");

$payload = json_decode(file_get_contents('php://input'), true);

if (empty($payload['_type']) || $payload['_type'] !== 'location') {
    echo '[]';
    exit();
}

try {
    $pdo  = new PDO(getenv('DB_DSN'));
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO
            location(acc, alt, lat, lon, vac, vel, tst, received_at)
            VALUES(:acc, :alt, :lat, :lon, :vac, :vel, :tst, :received_at)
    SQL);
    $stmt->execute([
      'acc' => $payload['acc'] ?? 0,
      'alt' => $payload['alt'] ?? 0,
      'lat' => $payload['lat'],
      'lon' => $payload['lon'],
      'vac' => $payload['vac'] ?? 0,
      'vel' => $payload['vel'] ?? 0,
      'tst' => $payload['tst'],
      'received_at' => time()
    ]);
} catch (Throwable $e) {
    log_error('record.php insert failed', [
        'exception' => $e::class,
        'error'     => $e->getMessage(),
        'payload'   => $payload,
    ]);
    http_response_code(500);
}

echo '[]';
