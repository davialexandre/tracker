<?php

require_once __DIR__ . '/../src/route.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function gpx_error(int $status, string $message): void {
    header('Content-type: application/json');
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit();
}

if ($id <= 0) {
    gpx_error(400, 'id is required');
}

try {
    $pdo = connect_db();
    $stmt = $pdo->prepare('SELECT name, geojson FROM route WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    log_error('route_gpx.php query failed', [
        'exception' => $e::class,
        'error'     => $e->getMessage(),
        'id'        => $id,
    ]);
    gpx_error(500, 'internal error');
}

if (!$row) {
    gpx_error(404, 'route not found');
}

$geo = json_decode($row['geojson'], true);
$coords = $geo['coordinates'] ?? [];
$gpx = build_route_gpx($row['name'], $coords);
$slug = route_filename_slug($row['name']);

header('Content-Type: application/gpx+xml');
header('Content-Disposition: attachment; filename="' . $slug . '.gpx"');
echo $gpx;
