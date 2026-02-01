<?php
require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';

use Core\CacheService;

echo "Testing CacheService...\n";

// Ensure clean state
CacheService::flush();

// Test Set/Get
CacheService::set('test', 'foo', 'bar', 60);
$val = CacheService::get('test', 'foo');
if ($val !== 'bar') {
    die("FAIL: Expected 'bar', got " . var_export($val, true) . "\n");
}
echo "PASS: Set/Get\n";

// Test Remember
$called = false;
$val = CacheService::remember('test', 'baz', 60, function() use (&$called) {
    $called = true;
    return 'qux';
});
if ($val !== 'qux' || !$called) {
    die("FAIL: Remember miss. Val: " . var_export($val, true) . ", Called: " . ($called ? 'yes' : 'no') . "\n");
}
echo "PASS: Remember miss\n";

$called = false;
$val = CacheService::remember('test', 'baz', 60, function() use (&$called) {
    $called = true;
    return 'fail';
});
if ($val !== 'qux' || $called) {
    die("FAIL: Remember hit\n");
}
echo "PASS: Remember hit\n";

// Test Expiry
CacheService::set('test', 'short', 'life', 1);
sleep(2);
$val = CacheService::get('test', 'short');
if ($val !== null) {
    die("FAIL: Expiry\n");
}
echo "PASS: Expiry\n";

// Test Flush
CacheService::set('test', 'persist', 'data', 60);
CacheService::flush('test');
if (CacheService::get('test', 'persist') !== null) {
    die("FAIL: Flush\n");
}
echo "PASS: Flush\n";

echo "All tests passed.\n";
