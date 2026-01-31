<?php
// tests/benchmark_session_lock.php

$mode = $argv[1] ?? 'baseline'; // 'baseline' or 'optimized'
$shouldClose = ($mode === 'optimized') ? 'true' : 'false';
$sessionId = 'bench-' . uniqid(); // Use hyphen instead of underscore

echo "Benchmarking Session Locking [$mode]...\n";
echo "Worker sleep time: 1s\n";
echo "Starting 2 concurrent workers with same session ID: $sessionId\n";

$start = microtime(true);

// Start two workers in parallel
$cmd = sprintf(
    'php %s/session_lock_worker.php %s %s',
    __DIR__,
    escapeshellarg($sessionId),
    escapeshellarg($shouldClose)
);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$p1 = proc_open($cmd, $descriptors, $pipes1);
$p2 = proc_open($cmd, $descriptors, $pipes2);

// Read output
$output1 = stream_get_contents($pipes1[1]);
$output2 = stream_get_contents($pipes2[1]);

// Wait for both to finish
proc_close($p1);
proc_close($p2);

$end = microtime(true);
$duration = $end - $start;

echo "Worker 1 Output:\n$output1\n";
echo "Worker 2 Output:\n$output2\n";

echo "Total duration: " . number_format($duration, 4) . "s\n";

if ($mode === 'baseline') {
    if ($duration >= 2.0) {
        echo "Result: Locking confirmed (Sequential execution)\n";
    } else {
        echo "Result: Unexpected speed (Parallel execution?)\n";
    }
} else {
    if ($duration < 1.5) {
        echo "Result: Optimization confirmed (Parallel execution)\n";
    } else {
        echo "Result: Locking still present (Sequential execution)\n";
    }
}

// Cleanup
$sessionFile = sys_get_temp_dir() . '/clarity_sessions/sess_' . $sessionId;
if (file_exists($sessionFile)) unlink($sessionFile);
