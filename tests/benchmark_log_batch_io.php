<?php
// tests/benchmark_log_batch_io.php

// 1. Reset Rate Limiter
$rlFile = sys_get_temp_dir() . '/global_ratelimit.sqlite';
if (file_exists($rlFile)) unlink($rlFile);

// 2. Setup Test DB for SettingsController (minimal mock)
$dbFile = sys_get_temp_dir() . '/bench_log_batch.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, public_config TEXT, private_config TEXT, updated_at TEXT)");
    $pdo->exec("INSERT INTO settings (id, public_config) VALUES (1, '{}')");
} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

putenv("CLARITY_TEST_DB=$dbFile");
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => null,
    'DB_PASS' => null
]);

require_once __DIR__ . '/../clarity_app/api/controllers/SettingsController.php';
require_once __DIR__ . '/../clarity_app/api/core/Logger.php';

// Redirect log file to temp
$logFile = sys_get_temp_dir() . '/bench_clarity.log';
if (file_exists($logFile)) unlink($logFile);

$reflection = new ReflectionClass('Core\Logger');
$property = $reflection->getProperty('logFile');
$property->setAccessible(true);
$property->setValue(null, $logFile);

$sc = new SettingsController();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Prepare a batch of 50 items
$batch = [];
for ($i = 0; $i < 50; $i++) {
    $batch[] = [
        'level' => 'INFO',
        'message' => "Benchmark Event $i",
        'context' => ['idx' => $i],
        'category' => 'benchmark'
    ];
}

// Warmup
ob_start();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$sc->logClientEvent($batch);
ob_end_clean();

if (file_exists($logFile)) unlink($logFile); // clear for actual run

$iterations = 50;
$start = microtime(true);

ob_start();
for ($i = 0; $i < $iterations; $i++) {
    $_SERVER['REMOTE_ADDR'] = '127.0.1.' . ($i % 255); // Rotate IP to bypass rate limit
    $sc->logClientEvent($batch);
}
ob_end_clean();

$end = microtime(true);
$duration = $end - $start;
$avgPerBatch = ($duration / $iterations) * 1000; // ms
$itemsPerSecond = ($iterations * 50) / $duration;

echo "Benchmark Results:\n";
echo "Total Time: " . number_format($duration, 4) . "s\n";
echo "Iterations: $iterations\n";
echo "Items per Batch: 50\n";
echo "Avg Time per Batch: " . number_format($avgPerBatch, 2) . "ms\n";
echo "Throughput: " . number_format($itemsPerSecond, 0) . " items/sec\n";

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
if (file_exists($logFile)) unlink($logFile);
if (file_exists($rlFile)) unlink($rlFile);
?>
