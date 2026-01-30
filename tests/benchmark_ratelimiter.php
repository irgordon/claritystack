<?php
require_once __DIR__ . '/../clarity_app/api/core/RateLimiter.php';

use Core\RateLimiter;

function benchmark_ratelimiter($iterations, $ip_count) {
    echo "Benchmarking RateLimiter with $iterations iterations across $ip_count IPs...\n";

    $ips = [];
    for ($i = 0; $i < $ip_count; $i++) {
        $ips[] = "10.0." . floor($i / 255) . "." . ($i % 255);
    }

    $start = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $ip = $ips[$i % $ip_count];
        RateLimiter::check($ip, 10, 60);
    }

    $end = microtime(true);
    $duration = $end - $start;
    $ops = $iterations / $duration;

    echo "Time: " . number_format($duration, 4) . "s\n";
    echo "Ops/sec: " . number_format($ops, 2) . "\n";

    // Cleanup
    foreach ($ips as $ip) {
        $file = sys_get_temp_dir() . '/ratelimit_' . md5($ip);
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

// Run benchmark with more IPs
benchmark_ratelimiter(5000, 1000);
