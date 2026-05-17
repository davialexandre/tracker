<?php

require_once __DIR__ . '/bootstrap.php';

// =====================================================================
// Functions (top of file, no side effects on require)
// =====================================================================

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

function insert_points(PDO $pdo, array $points): int {
    if (empty($points)) {
        return 0;
    }

    $now = time();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(LOCATION_INSERT_SQL);
        foreach ($points as $p) {
            $stmt->execute(location_bind_values($p, $now));
        }
        $pdo->commit();
        return count($points);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function import_one(PDO $pdo, string $path): array {
    $parsed = parse_gpx_file($path);
    $points = assign_speeds($parsed['points']);
    $inserted = insert_points($pdo, $points);
    return [
        'imported' => $inserted,
        'skipped'  => $parsed['skipped'],
    ];
}

// =====================================================================
// Handler — runs only on a web request. Requiring this file from the CLI
// (for smoke-checks in later tasks) is safe: PHP_SAPI === 'cli' makes us
// return before touching $_SERVER / $_FILES / headers.
// =====================================================================

if (PHP_SAPI === 'cli') {
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_FILES['gpx'])) {
    header('Location: /import.html', true, 303);
    exit;
}

$pdo = connect_db();

$filesSeen = 0;
$files     = 0;
$imported  = 0;
$skipped   = 0;
$errors    = 0;

// PHP transposes name="gpx[]" to parallel arrays under each key.
$tmpNames = (array) $_FILES['gpx']['tmp_name'];
$uploadErr = (array) $_FILES['gpx']['error'];
$origNames = (array) ($_FILES['gpx']['name'] ?? []);

foreach ($tmpNames as $i => $tmp) {
    $filesSeen++;

    if (($uploadErr[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors++;
        log_warning('gpx import upload error', [
            'index'    => $i,
            'name'     => $origNames[$i] ?? null,
            'err_code' => $uploadErr[$i] ?? null,
        ]);
        continue;
    }

    try {
        $r = import_one($pdo, $tmp);
        $imported += $r['imported'];
        $skipped  += $r['skipped'];
        $files++;
    } catch (Throwable $e) {
        $errors++;
        log_error('gpx import failed for file', [
            'index'     => $i,
            'name'      => $origNames[$i] ?? null,
            'exception' => $e::class,
            'error'     => $e->getMessage(),
        ]);
    }
}

log_info('gpx import completed', [
    'files_seen'      => $filesSeen,
    'files_succeeded' => $files,
    'files_failed'    => $errors,
    'points_imported' => $imported,
    'points_skipped'  => $skipped,
]);

$qs = http_build_query([
    'imported' => $imported,
    'files'    => $files,
    'skipped'  => $skipped,
    'errors'   => $errors,
]);
header("Location: /import.html?$qs", true, 303);
exit;
