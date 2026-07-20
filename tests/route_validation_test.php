<?php

require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../src/route.php';

// validate_route_name
check_eq(null, validate_route_name('  Trail  ')['error'], 'valid name has no error');
check_eq('Trail', validate_route_name('  Trail  ')['name'], 'name is trimmed');
check(validate_route_name('')['error'] !== null, 'empty name errors');
check(validate_route_name('   ')['error'] !== null, 'whitespace-only name errors');
check(validate_route_name(123)['error'] !== null, 'non-string name errors');
check(validate_route_name(str_repeat('x', ROUTE_NAME_MAX + 1))['error'] !== null, 'over-long name errors');

// validate_route_coords
$ok = validate_route_coords(['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]);
check_eq(null, $ok['error'], 'valid LineString has no error');
check_eq(2, count($ok['coords']), 'valid LineString keeps both points');
check(is_float($ok['coords'][0][0]), 'coords normalised to float');
check(validate_route_coords(['type' => 'Point', 'coordinates' => [0, 0]])['error'] !== null, 'non-LineString errors');
check(validate_route_coords(['type' => 'LineString', 'coordinates' => [[0, 0]]])['error'] !== null, 'single point errors');
check(validate_route_coords(['type' => 'LineString', 'coordinates' => [[200, 0], [0, 0]]])['error'] !== null, 'lon out of range errors');
check(validate_route_coords(['type' => 'LineString', 'coordinates' => [[0, 100], [0, 0]]])['error'] !== null, 'lat out of range errors');
check(validate_route_coords('nope')['error'] !== null, 'non-array geojson errors');
$big = ['type' => 'LineString', 'coordinates' => array_fill(0, ROUTE_MAX_POINTS + 1, [0, 0])];
check(validate_route_coords($big)['error'] !== null, 'over-cap point count errors');

// validate_route_input (composed)
$good = validate_route_input(['name' => 'Trail', 'geojson' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]]);
check_eq(0, count($good['errors']), 'valid payload has no errors');
check(count(validate_route_input(['geojson' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]])['errors']) > 0, 'missing name errors');
check(count(validate_route_input(['name' => 'Trail'])['errors']) > 0, 'missing geojson errors');
check(count(validate_route_input('nope')['errors']) > 0, 'non-object payload errors');

// route_filename_slug
check_eq('morning-run', route_filename_slug('Morning Run!'), 'slug lowercases and dashes');
check_eq('route', route_filename_slug(''), 'empty name falls back to route');
check_eq('route', route_filename_slug('   '), 'whitespace name falls back to route');

test_summary();
