<?php

$payload = file_get_contents('php://input');

header("Content-type: application/json");

$payload = json_decode($payload, true);

if (empty($payload['_type']) || $payload['_type'] !== 'location') {
    echo json_encode([]);
    exit();
}

$dbDsn = getenv('DB_DSN');

$pdo  = new PDO($dbDsn);
$stmt = $pdo->prepare(<<<SQL
    INSERT INTO 
        location(acc, alt, lat, lon, vac, vel, tst, received_at) 
        VALUES(:acc, :alt, :lat, :lon, :vac, :vel, :tst, :received_at)
SQL
);
$stmt->execute([
  'acc' => $payload['acc'],
  'alt' => $payload['alt'],
  'lat' => $payload['lat'],
  'lon' => $payload['lon'],
  'vac' => $payload['vac'],
  'vel' => $payload['vel'],
  'tst' => $payload['tst'],
  'received_at' => time()
]);
$pdo = null;

echo json_encode([]);
