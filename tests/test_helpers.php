<?php

$GLOBALS['__test_count'] = 0;
$GLOBALS['__test_failures'] = 0;

function check(bool $cond, string $msg): void {
    $GLOBALS['__test_count']++;
    if ($cond) {
        fwrite(STDOUT, "  ok   - $msg\n");
    } else {
        $GLOBALS['__test_failures']++;
        fwrite(STDOUT, "  FAIL - $msg\n");
    }
}

function check_eq($expected, $actual, string $msg): void {
    check(
        $expected === $actual,
        $msg . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')'
    );
}

function check_near(float $expected, float $actual, float $tol, string $msg): void {
    check(abs($expected - $actual) <= $tol, $msg . " (expected ~$expected, got $actual)");
}

function test_summary(): void {
    fwrite(STDOUT, "\n{$GLOBALS['__test_count']} checks, {$GLOBALS['__test_failures']} failures\n");
    exit($GLOBALS['__test_failures'] > 0 ? 1 : 0);
}
