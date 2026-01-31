<?php
require_once __DIR__ . '/../clarity_app/api/core/RateLimiter.php';

use Core\RateLimiter;

function benchmark_connection_overhead($iterations) {
    echo "Benchmarking RateLimiter Connection Overhead with $iterations iterations...\n";

    $start = microtime(true);

    $reflection = new ReflectionClass('Core\RateLimiter');
    $property = $reflection->getProperty('pdo');
    $property->setAccessible(true);

    for ($i = 0; $i < $iterations; $i++) {
        // Force reset of the connection to simulate a new request
        $property->setValue(null, null);

        // This will trigger getPdo() and new PDO connection
        RateLimiter::check("127.0.0.1", 1000, 60);
    }

    $end = microtime(true);
    $duration = $end - $start;
    $ops = $iterations / $duration;

    echo "Time: " . number_format($duration, 4) . "s\n";
    echo "Ops/sec: " . number_format($ops, 2) . "\n";
    echo "Avg Latency: " . number_format(($duration / $iterations) * 1000, 4) . "ms\n";
}

// Ensure the DB exists and is initialized so we are measuring connection checks mostly,
// not table creation every time (though the code checks file existence).
RateLimiter::check("127.0.0.1", 1, 60);

benchmark_connection_overhead(1000);
