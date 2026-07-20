<?php

require_once __DIR__ . '/bootstrap.php';

function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $r = 6371000.0; // Earth radius in meters
    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);
    $dPhi = deg2rad($lat2 - $lat1);
    $dLam = deg2rad($lon2 - $lon1);
    $a = sin($dPhi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dLam / 2) ** 2;
    return 2 * $r * asin(min(1.0, sqrt($a)));
}

function parse_gpx_file(string $path): array {
    libxml_use_internal_errors(true);
    $reader = new XMLReader();
    if (!$reader->open($path)) {
        throw new RuntimeException("XMLReader failed to open $path");
    }

    $points  = [];
    $skipped = 0;

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'trkpt') {
            continue;
        }

        $lat = (float) $reader->getAttribute('lat');
        $lon = (float) $reader->getAttribute('lon');
        $alt = 0;
        $tst = null;
        $rawSpeed = null;

        // Self-closing <trkpt/> emits one ELEMENT event with isEmptyElement
        // === true and NO END_ELEMENT. Skip the inner walk so we don't consume
        // the next sibling trkpt's children. The point will be skipped below
        // via the tst === null check (self-closing trkpts have no <time>).
        if (!$reader->isEmptyElement) {
            // Walk children of this trkpt until its end tag.
            $depth = $reader->depth;
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::END_ELEMENT
                    && $reader->depth === $depth
                    && $reader->localName === 'trkpt') {
                    break;
                }
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }
                switch ($reader->localName) {
                    case 'time':
                        $text = $reader->readString();
                        $parsed = strtotime($text);
                        if ($parsed !== false) {
                            $tst = $parsed;
                        }
                        break;
                    case 'ele':
                        $alt = (int) round((float) $reader->readString());
                        break;
                    case 'speed':
                        $rawSpeed = (float) $reader->readString();
                        break;
                    // <extensions> is not skipped: we keep descending so nested
                    // <*:speed> elements are matched by the case above on a later
                    // iteration of this inner loop.
                }
            }
        }

        if ($tst === null) {
            $skipped++;
            continue;
        }

        $points[] = [
            'lat'      => $lat,
            'lon'      => $lon,
            'alt'      => $alt,
            'tst'      => $tst,
            'rawSpeed' => $rawSpeed,
        ];
    }

    $reader->close();

    // Sort defensively by timestamp.
    usort($points, fn($a, $b) => $a['tst'] <=> $b['tst']);

    if (empty($points) && !empty(libxml_get_errors())) {
        libxml_clear_errors();
        throw new RuntimeException("GPX parse failed with no usable points: $path");
    }
    libxml_clear_errors();

    return ['points' => $points, 'skipped' => $skipped];
}

function assign_speeds(array $points): array {
    $n = count($points);
    for ($i = 0; $i < $n; $i++) {
        $raw = $points[$i]['rawSpeed'];

        // Priority 1: use GPX-supplied speed if sane (m/s, finite, plausible).
        if ($raw !== null && $raw >= 0 && $raw < 200) {
            $points[$i]['vel'] = (int) round($raw);
            continue;
        }

        // Priority 2: derive from previous neighbor.
        if ($i === 0) {
            $points[$i]['vel'] = 0;
            continue;
        }

        $dt = $points[$i]['tst'] - $points[$i - 1]['tst'];
        if ($dt <= 0) {
            $points[$i]['vel'] = 0;
            continue;
        }

        $dist = haversine(
            $points[$i - 1]['lat'], $points[$i - 1]['lon'],
            $points[$i]['lat'],     $points[$i]['lon']
        );
        $points[$i]['vel'] = (int) round($dist / $dt);
    }
    return $points;
}

function import_one(PDO $pdo, string $path): array {
    $parsed = parse_gpx_file($path);
    $points = assign_speeds($parsed['points']);
    $inserted = insert_locations($pdo, $points);
    return [
        'imported' => $inserted,
        'skipped'  => $parsed['skipped'],
    ];
}

/**
 * Total length in metres over a list of [lon, lat] positions.
 * Returns 0.0 for fewer than 2 points.
 */
function polyline_distance_m(array $coords): float {
    $total = 0.0;
    $n = count($coords);
    for ($i = 1; $i < $n; $i++) {
        $total += haversine(
            (float) $coords[$i - 1][1], (float) $coords[$i - 1][0],
            (float) $coords[$i][1],     (float) $coords[$i][0]
        );
    }
    return $total;
}

/**
 * Build a GPX 1.1 track from a list of [lon, lat] positions.
 * No timestamps, no elevation — this is a plan, not a recording.
 */
function build_route_gpx(string $name, array $coords): string {
    $safeName = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $lines = [];
    $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $lines[] = '<gpx version="1.1" creator="tracker" xmlns="http://www.topografix.com/GPX/1/1">';
    $lines[] = '  <trk>';
    $lines[] = '    <name>' . $safeName . '</name>';
    $lines[] = '    <trkseg>';
    foreach ($coords as $c) {
        $lines[] = sprintf('      <trkpt lat="%.6f" lon="%.6f"></trkpt>', (float) $c[1], (float) $c[0]);
    }
    $lines[] = '    </trkseg>';
    $lines[] = '  </trk>';
    $lines[] = '</gpx>';
    return implode("\n", $lines) . "\n";
}
