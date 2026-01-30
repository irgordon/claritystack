<?php
require_once __DIR__ . '/../clarity_app/api/core/RateLimiter.php';

use Core\RateLimiter;

function assert_true($condition, $message) {
    if ($condition !== true) {
        echo "FAIL: $message\n";
        exit(1);
    } else {
        echo "PASS: $message\n";
    }
}

function assert_false($condition, $message) {
    if ($condition !== false) {
        echo "FAIL: $message\n";
        exit(1);
    } else {
        echo "PASS: $message\n";
    }
}

echo "Testing RateLimiter Correctness...\n";

// Test 1: Basic Limit
$ip = "test.1.1.1";
// Clear any previous state for this IP (hard since it uses a shared DB file, but we can assume unique IP for test run or delete file)
// Since we can't easily access the internal DB to delete, we'll use a unique IP per test run.
$ip = "test_limit_" . uniqid();

echo "Test 1: Basic Limit (5 requests)\n";
for ($i = 1; $i <= 5; $i++) {
    assert_true(RateLimiter::check($ip, 5, 60), "Request $i should be allowed");
}
assert_false(RateLimiter::check($ip, 5, 60), "Request 6 should be blocked");


// Test 2: Expiration
echo "Test 2: Expiration\n";
$ip = "test_expire_" . uniqid();
// Limit 1, 1 second window
assert_true(RateLimiter::check($ip, 1, 1), "Request 1 should be allowed");
assert_false(RateLimiter::check($ip, 1, 1), "Request 2 (immediate) should be blocked");

echo "Sleeping 2 seconds...\n";
sleep(2);

assert_true(RateLimiter::check($ip, 1, 1), "Request 3 (after sleep) should be allowed");

echo "All correctness tests passed.\n";
