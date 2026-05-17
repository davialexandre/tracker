<?php

enum LogLevel: string
{
    case Error   = 'error';
    case Warning = 'warning';
    case Info    = 'info';
    case Debug   = 'debug';
}

function log_error(string $message, array $context = []): void
{
    _log_write(LogLevel::Error, $message, $context);
}

function log_warning(string $message, array $context = []): void
{
    _log_write(LogLevel::Warning, $message, $context);
}

function log_info(string $message, array $context = []): void
{
    _log_write(LogLevel::Info, $message, $context);
}

function log_debug(string $message, array $context = []): void
{
    _log_write(LogLevel::Debug, $message, $context);
}

function _log_write(LogLevel $level, string $message, array $context): void
{
    static $handle = null;
    $handle ??= fopen('php://stderr', 'w');

    $entry = [
        'time'    => date('c'),
        'level'   => $level->value,
        'message' => $message,
    ];

    if (!empty($context)) {
        $entry['context'] = $context;
    }

    if (isset($_SERVER['REQUEST_METHOD'])) {
        $entry['request'] = [
            'method'     => $_SERVER['REQUEST_METHOD'],
            'uri'        => $_SERVER['REQUEST_URI']     ?? null,
            'remote'     => $_SERVER['REMOTE_ADDR']     ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }

    fwrite($handle, json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}
