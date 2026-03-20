<?php

if (!ob_start('ob_gzhandler')) {
    ob_start();
}
header("Content-type: application/json");

$pdo = new PDO(getenv('DB_DSN'));

$maxSpeed = (float)($_GET['speed'] ?? 8.5);
$limit = (int)($_GET['limit'] ?? 100000);
$cursor = isset($_GET['cursor']) ? (int)$_GET['cursor'] : PHP_INT_MAX;

$hasBounds = isset($_GET['south'], $_GET['north'], $_GET['west'], $_GET['east']);

$params = [':maxSpeed' => $maxSpeed, ':cursor' => $cursor, ':limit' => $limit + 1];
$boundsClause = '';

if ($hasBounds) {
    $south = (float)$_GET['south'];
    $north = (float)$_GET['north'];
    $west = (float)$_GET['west'];
    $east = (float)$_GET['east'];

    // Expand bounds by 10% to reduce line cut-off artifacts at edges
    $latMargin = ($north - $south) * 0.1;
    $lonMargin = ($east - $west) * 0.1;

    $params[':south'] = $south - $latMargin;
    $params[':north'] = $north + $latMargin;
    $params[':west'] = $west - $lonMargin;
    $params[':east'] = $east + $lonMargin;

    $boundsClause = 'AND lat BETWEEN :south AND :north AND lon BETWEEN :west AND :east';
}

$sql = "SELECT lat, lon, tst FROM location
        WHERE vel >= 0 AND vel < :maxSpeed
        AND tst < :cursor
        $boundsClause
        ORDER BY tst DESC
        LIMIT :limit";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasMore = count($rows) > $limit;
if ($hasMore) {
    array_pop($rows);
}

if (empty($rows)) {
    echo json_encode(['type' => 'FeatureCollection', 'features' => [], 'hasMore' => false]);
    exit();
}

// Equirectangular distance approximation (submillimeter accuracy at these scales)
$centerLat = $hasBounds ? deg2rad(($south + $north) / 2) : deg2rad($rows[0]['lat']);
$cosLat = cos($centerLat);
$metersPerDeg = 111320.0;
$noiseThreshold2 = (3.5 / $metersPerDeg) ** 2;
$gapThreshold2 = (250.0 / $metersPerDeg) ** 2;

$features = [];
$currentLine = [];
$prevLat = $rows[0]['lat'];
$prevLon = $rows[0]['lon'];
$currentLine[] = [round($rows[0]['lon'], 6), round($rows[0]['lat'], 6)];

$color = '#006cff';

for ($i = 1, $count = count($rows); $i < $count; $i++) {
    $lat = $rows[$i]['lat'];
    $lon = $rows[$i]['lon'];

    $dlat = $lat - $prevLat;
    $dlon = ($lon - $prevLon) * $cosLat;
    $dist2 = $dlat * $dlat + $dlon * $dlon;

    if ($dist2 <= $noiseThreshold2) {
        continue;
    }

    $prevLat = $lat;
    $prevLon = $lon;

    if ($dist2 > $gapThreshold2) {
        if (count($currentLine) > 1) {
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'LineString', 'coordinates' => $currentLine],
                'properties' => ['color' => $color],
            ];
        }
        $currentLine = [[round($lon, 6), round($lat, 6)]];
    } else {
        $currentLine[] = [round($lon, 6), round($lat, 6)];
    }
}

if (count($currentLine) > 1) {
    $features[] = [
        'type' => 'Feature',
        'geometry' => ['type' => 'LineString', 'coordinates' => $currentLine],
        'properties' => ['color' => $color],
    ];
}

$response = [
    'type' => 'FeatureCollection',
    'features' => $features,
    'hasMore' => $hasMore,
];

if ($hasMore) {
    $response['nextCursor'] = end($rows)['tst'];
}

echo json_encode($response);
