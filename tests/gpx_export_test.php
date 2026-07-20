<?php

require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../src/gpx.php';

// polyline_distance_m
check_eq(0.0, polyline_distance_m([]), 'distance of empty is 0');
check_eq(0.0, polyline_distance_m([[0, 0]]), 'distance of single point is 0');
// 1 degree of latitude at the equator ~= 111194.9 m
check_near(111194.9, polyline_distance_m([[0, 0], [0, 1]]), 1.0, 'one degree latitude distance');

// build_route_gpx: structure
$gpx = build_route_gpx('My Route', [[-46.6, -23.5], [-46.61, -23.51]]);
$xml = simplexml_load_string($gpx);
check($xml !== false, 'GPX parses as XML');
$xml->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
$pts = $xml->xpath('//g:trkpt');
check_eq(2, count($pts), 'two trkpt elements');
check_eq('-23.500000', (string)$pts[0]['lat'], 'first trkpt lat (from [lon,lat])');
check_eq('-46.600000', (string)$pts[0]['lon'], 'first trkpt lon (from [lon,lat])');
$names = $xml->xpath('//g:trk/g:name');
check_eq('My Route', (string)$names[0], 'track name preserved');

// build_route_gpx: XML escaping
$gpx2 = build_route_gpx('A & B <x>', [[0, 0], [1, 1]]);
check(strpos($gpx2, '<name>A &amp; B &lt;x&gt;</name>') !== false, 'name is XML-escaped');
check(simplexml_load_string($gpx2) !== false, 'escaped GPX still parses');

test_summary();
