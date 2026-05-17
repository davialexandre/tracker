<?php

require_once __DIR__ . '/../src/gpx.php';

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
