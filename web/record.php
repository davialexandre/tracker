<?php

require_once __DIR__ . '/../src/bootstrap.php';

header("Content-type: application/json");

$payload = json_decode(file_get_contents('php://input'), true);

if (empty($payload['_type']) || $payload['_type'] !== 'location') {
    echo '[]';
    exit();
}

try {
    insert_location(connect_db(), $payload);
} catch (Throwable $e) {
    log_error('record.php insert failed', [
        'exception' => $e::class,
        'error'     => $e->getMessage(),
        'payload'   => $payload,
    ]);
    http_response_code(500);
}

echo '[]';
