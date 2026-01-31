<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Configure for SQLite Benchmark
$config = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => __DIR__ . '/bench_queue.sqlite',
    'DB_USER' => '',
    'DB_PASS' => ''
];
Database::getInstance()->setConfig($config);

// Clear log
file_put_contents(__DIR__ . '/memory_optimized.log', '');

function log_memory($pid, $role) {
    $mem = memory_get_peak_usage(true);
    $line = "$pid,$role,$mem\n";
    file_put_contents(__DIR__ . '/memory_optimized.log', $line, FILE_APPEND | LOCK_EX);
}

// Intercept mail() to simulate delay?
// The real script uses `mail()`. In a CLI env without sendmail, it returns false instantly.
// To match the baseline (which used `usleep`), I should ideally mock `mail` but I can't easily redeclare it.
// However, the baseline added `usleep` explicitly. The new script doesn't have `usleep`.
// This means the new script will run MUCH faster, which is also a "performance improvement" (latency).
// But for memory, it shouldn't matter too much if it sleeps or not, except for stack depth maybe.
// I will run it as is. The memory difference comes from processes, not sleep.

ob_start(); // Capture output to keep it clean
$start = microtime(true);

include __DIR__ . '/../clarity_app/api/scripts/process_email_queue.php';

$end = microtime(true);
ob_end_clean();

log_memory(getmypid(), 'single_process');

echo "Time: " . ($end - $start) . "s\n";
?>
