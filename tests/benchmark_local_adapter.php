<?php

require_once __DIR__ . '/../clarity_app/api/core/Storage/LocalAdapter.php';

// Create a temporary directory for testing
$tempDir = sys_get_temp_dir() . '/clarity_storage_benchmark_' . uniqid();
if (!is_dir($tempDir)) {
    mkdir($tempDir);
}

// Benchmark
$iterations = 10000;
$startTime = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $adapter = new \Core\Storage\LocalAdapter($tempDir);
}

$endTime = microtime(true);
$duration = $endTime - $startTime;

echo "Time taken for $iterations instantiations: " . number_format($duration, 4) . " seconds\n";
echo "Average time per instantiation: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";

// Cleanup
rmdir($tempDir);
