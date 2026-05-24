<?php

require_once __DIR__ . '/../src/bootstrap.php';

if (!ob_start('ob_gzhandler')) {
    ob_start();
}
header("Content-type: application/json");

try {
    $pdo = connect_db();
    $stmt = $pdo->query('SELECT MIN(tst) AS min_tst, MAX(tst) AS max_tst FROM location');
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    log_error('years.php query failed', [
        'exception' => $e::class,
        'error'     => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['min' => null, 'max' => null]);
    exit();
}

if (empty($row) || $row['min_tst'] === null) {
    echo json_encode(['min' => null, 'max' => null]);
    exit();
}

echo json_encode([
    'min' => (int)date('Y', (int)$row['min_tst']),
    'max' => (int)date('Y', (int)$row['max_tst']),
]);
