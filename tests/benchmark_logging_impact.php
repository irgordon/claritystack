<?php

$iterations = 10000;
$logFile = sys_get_temp_dir() . '/benchmark_clarity.log';

if (file_exists($logFile)) unlink($logFile);

function simulate_logger($message, $context, $file) {
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => 'INFO',
        'message' => $message,
        'context' => $context
    ];
    file_put_contents($file, json_encode($entry) . PHP_EOL, FILE_APPEND);
}

// Baseline: Log every time
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    simulate_logger("Storage Access: {$i}", [
        'category' => 'storage',
        'file_id' => $i,
        'user_id' => 123,
        'is_thumb' => false
    ], $logFile);
}
$baselineTime = microtime(true) - $start;
echo "Baseline (Log every request): " . number_format($baselineTime, 4) . "s\n";

// Cleanup
if (file_exists($logFile)) unlink($logFile);

// Optimization: Log 1% of the time
$start = microtime(true);
$loggedCount = 0;
for ($i = 0; $i < $iterations; $i++) {
    if (mt_rand(1, 100) === 1) {
        simulate_logger("Storage Access: {$i}", [
            'category' => 'storage',
            'file_id' => $i,
            'user_id' => 123,
            'is_thumb' => false
        ], $logFile);
        $loggedCount++;
    }
}
$optimizedTime = microtime(true) - $start;
echo "Optimization (Log 1% sample): " . number_format($optimizedTime, 4) . "s\n";
echo "Actual logs written in optimization: $loggedCount\n";

$improvement = $baselineTime / $optimizedTime;
echo "Improvement: " . number_format($improvement, 2) . "x\n";

// Cleanup
if (file_exists($logFile)) unlink($logFile);
