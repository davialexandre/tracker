<?php

require_once '../vendor/autoload.php';

use GeoJson\Feature\Feature;
use GeoJson\Feature\FeatureCollection;
use GeoJson\Geometry\LineString;
use GeoJson\Geometry\Point;
use Location\Coordinate;
use Location\Distance\Haversine;

ini_set('memory_limit', '-1');
header("Content-type: application/json");

$pdo = new PDO(getenv('DB_DSN'));

$limit = (int)($_GET['limit'] ?? 50000);
$offset = (int)($_GET['offset'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM location ORDER BY tst DESC LIMIT ? OFFSET ?');
$stmt->execute([$limit, $offset]);

$hasMoreData = false;
$checkStmt = $pdo->prepare('SELECT COUNT(*) FROM location WHERE id < (SELECT id FROM location ORDER BY tst DESC LIMIT 1 OFFSET ?)');
$checkStmt->execute([$offset + $limit - 1]);
$hasMoreData = $checkStmt->fetchColumn() > 0;

$positions = [];
while ($location = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $positions[] = [
      'tst' => $location['tst'],
      'coordinate' => new Coordinate($location['lat'], $location['lon']),
      'vel' => $location['vel']
    ];
}

if (empty($positions)) {
    echo json_encode([]);
    exit();
}

$features = [];
$featurePositions = [];
$color = '#006cff';
$previousPosition = $positions[0];
$haversine = new Haversine();
$maxSpeed = $_GET['speed'] ?? 8.5;
foreach ($positions as $position) {
    $distance = $haversine->getDistance($previousPosition['coordinate'], $position['coordinate']);

    if ($distance <= 3.5 || $position['vel'] >= $maxSpeed) {
        continue;
    }

    $previousPosition = $position;

    if ($distance > 250) {
        if (count($featurePositions) > 1) {
            $features[] = new Feature(new LineString($featurePositions), ['color' => $color]);
        }
        $featurePositions = [
          new Point([
            $position['coordinate']->getLng(),
            $position['coordinate']->getLat()
          ])
        ];
    } else {
        $featurePositions[] = new Point([$position['coordinate']->getLng(), $position['coordinate']->getLat()]);
    }
}

if (count($featurePositions) > 1) {
    $features[] = new Feature(new LineString($featurePositions), ['color' => $color]);
}

$featureCollection = new FeatureCollection($features);
$response = [
    'type' => 'FeatureCollection',
    'features' => $featureCollection->getFeatures(),
    'hasMore' => $hasMoreData
];
echo json_encode($response);
