<?php

require_once __DIR__ . '/../src/route.php';

header('Content-type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

function json_out($data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

try {
    $pdo = connect_db();

    if ($method === 'GET' && $id > 0) {
        $stmt = $pdo->prepare('SELECT id, name, geojson, distance_m, created_at, updated_at FROM route WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            json_out(['error' => 'route not found'], 404);
        }
        json_out([
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'geojson'    => json_decode($row['geojson'], true),
            'distance_m' => (float) $row['distance_m'],
            'created_at' => (int) $row['created_at'],
            'updated_at' => (int) $row['updated_at'],
        ]);
    }

    if ($method === 'GET') {
        $stmt = $pdo->query('SELECT id, name, distance_m, created_at FROM route ORDER BY created_at DESC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = array_map(fn($r) => [
            'id'         => (int) $r['id'],
            'name'       => $r['name'],
            'distance_m' => (float) $r['distance_m'],
            'created_at' => (int) $r['created_at'],
        ], $rows);
        json_out($out);
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        $v = validate_route_input($payload);
        if (!empty($v['errors'])) {
            json_out(['error' => implode('; ', $v['errors'])], 400);
        }
        $geojson = json_encode(['type' => 'LineString', 'coordinates' => $v['coords']]);
        $distance = polyline_distance_m($v['coords']);
        $now = time();
        $stmt = $pdo->prepare('INSERT INTO route (name, geojson, distance_m, created_at, updated_at) VALUES (:name, :geojson, :distance, :created, :updated)');
        $stmt->execute([
            ':name'     => $v['name'],
            ':geojson'  => $geojson,
            ':distance' => $distance,
            ':created'  => $now,
            ':updated'  => $now,
        ]);
        json_out(['id' => (int) $pdo->lastInsertId(), 'distance_m' => $distance], 201);
    }

    if ($method === 'PATCH' && $id > 0) {
        $check = $pdo->prepare('SELECT id FROM route WHERE id = :id');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            json_out(['error' => 'route not found'], 404);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            json_out(['error' => 'request body must be a JSON object'], 400);
        }

        $sets = [];
        $params = [':id' => $id];
        $errors = [];
        $newDistance = null;

        if (array_key_exists('name', $payload)) {
            $r = validate_route_name($payload['name']);
            if ($r['error'] !== null) {
                $errors[] = $r['error'];
            } else {
                $sets[] = 'name = :name';
                $params[':name'] = $r['name'];
            }
        }

        if (array_key_exists('geojson', $payload)) {
            $r = validate_route_coords($payload['geojson']);
            if ($r['error'] !== null) {
                $errors[] = $r['error'];
            } else {
                $newDistance = polyline_distance_m($r['coords']);
                $sets[] = 'geojson = :geojson';
                $params[':geojson'] = json_encode(['type' => 'LineString', 'coordinates' => $r['coords']]);
                $sets[] = 'distance_m = :distance';
                $params[':distance'] = $newDistance;
            }
        }

        if (!empty($errors)) {
            json_out(['error' => implode('; ', $errors)], 400);
        }
        if (empty($sets)) {
            json_out(['error' => 'nothing to update (provide name and/or geojson)'], 400);
        }

        $sets[] = 'updated_at = :updated';
        $params[':updated'] = time();
        $stmt = $pdo->prepare('UPDATE route SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
        json_out(['id' => $id, 'distance_m' => $newDistance]);
    }

    if ($method === 'DELETE' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM route WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            json_out(['error' => 'route not found'], 404);
        }
        json_out(['deleted' => true]);
    }

    json_out(['error' => 'unsupported request'], 405);

} catch (Throwable $e) {
    log_error('routes.php failed', [
        'exception' => $e::class,
        'error'     => $e->getMessage(),
        'method'    => $method,
        'id'        => $id,
    ]);
    json_out(['error' => 'internal error'], 500);
}
