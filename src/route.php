<?php

require_once __DIR__ . '/gpx.php';

const ROUTE_MAX_POINTS = 10000;
const ROUTE_NAME_MAX = 200;

function validate_route_name(mixed $name): array {
    if (!is_string($name)) {
        return ['error' => 'name must be a string', 'name' => ''];
    }
    $trimmed = trim($name);
    if ($trimmed === '') {
        return ['error' => 'name is required', 'name' => ''];
    }
    if (mb_strlen($trimmed) > ROUTE_NAME_MAX) {
        return ['error' => 'name too long (max ' . ROUTE_NAME_MAX . ')', 'name' => ''];
    }
    return ['error' => null, 'name' => $trimmed];
}

function validate_route_coords(mixed $geojson): array {
    if (!is_array($geojson)) {
        return ['error' => 'geojson must be an object', 'coords' => []];
    }
    if (($geojson['type'] ?? null) !== 'LineString') {
        return ['error' => 'geojson.type must be "LineString"', 'coords' => []];
    }
    $raw = $geojson['coordinates'] ?? null;
    if (!is_array($raw)) {
        return ['error' => 'geojson.coordinates must be an array', 'coords' => []];
    }
    $n = count($raw);
    if ($n < 2) {
        return ['error' => 'a route needs at least 2 points', 'coords' => []];
    }
    if ($n > ROUTE_MAX_POINTS) {
        return ['error' => 'too many points (max ' . ROUTE_MAX_POINTS . ')', 'coords' => []];
    }
    $coords = [];
    foreach ($raw as $pos) {
        if (!is_array($pos) || count($pos) < 2 || !is_numeric($pos[0]) || !is_numeric($pos[1])) {
            return ['error' => 'each coordinate must be [lon, lat]', 'coords' => []];
        }
        $lon = (float) $pos[0];
        $lat = (float) $pos[1];
        if ($lon < -180 || $lon > 180 || $lat < -90 || $lat > 90) {
            return ['error' => 'coordinate out of range', 'coords' => []];
        }
        $coords[] = [$lon, $lat];
    }
    return ['error' => null, 'coords' => $coords];
}

function validate_route_input(mixed $payload): array {
    if (!is_array($payload)) {
        return ['errors' => ['request body must be a JSON object'], 'name' => '', 'coords' => []];
    }
    $errors = [];
    $name = '';
    $coords = [];

    $nameResult = validate_route_name($payload['name'] ?? null);
    if ($nameResult['error'] !== null) {
        $errors[] = $nameResult['error'];
    } else {
        $name = $nameResult['name'];
    }

    $coordsResult = validate_route_coords($payload['geojson'] ?? null);
    if ($coordsResult['error'] !== null) {
        $errors[] = $coordsResult['error'];
    } else {
        $coords = $coordsResult['coords'];
    }

    return ['errors' => $errors, 'name' => $name, 'coords' => $coords];
}

function route_filename_slug(string $name): string {
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug === '' ? 'route' : $slug;
}
