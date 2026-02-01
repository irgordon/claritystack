<?php
require_once __DIR__ . '/../clarity_app/api/core/SmtpClient.php';

$port = 2525;
$logFile = sys_get_temp_dir() . '/smtp_server.log';

// Start Mock Server
$pid = exec("php " . __DIR__ . "/mock_smtp_server.php > $logFile 2>&1 & echo $!");
sleep(1); // Wait for server to start

echo "Mock Server PID: $pid\n";

$iterations = 50;
$from = "test@example.com";
$to = "recipient@example.com";
$subject = "Test";
$body = "Body";

// --- Benchmark 1: One Connection Per Email (Baseline) ---
echo "\nRunning Baseline (New Connection per Email)...\n";
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    try {
        $client = new SmtpClient('127.0.0.1', $port);
        $client->connect();
        $client->send($from, $to, "$subject $i", $body);
        $client->quit();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

$end = microtime(true);
$baselineTime = $end - $start;
echo "Baseline Time: " . number_format($baselineTime, 4) . "s\n";
echo "Avg per email: " . number_format(($baselineTime / $iterations) * 1000, 2) . "ms\n";


// --- Benchmark 2: Persistent Connection (Optimization) ---
echo "\nRunning Optimization (Persistent Connection)...\n";
$start = microtime(true);

try {
    $client = new SmtpClient('127.0.0.1', $port);
    $client->connect();

    for ($i = 0; $i < $iterations; $i++) {
        $client->send($from, $to, "$subject $i", $body);
    }

    $client->quit();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$end = microtime(true);
$optTime = $end - $start;
echo "Optimization Time: " . number_format($optTime, 4) . "s\n";
echo "Avg per email: " . number_format(($optTime / $iterations) * 1000, 2) . "ms\n";

// Cleanup
exec("kill $pid");

// Results
if ($baselineTime > 0) {
    $speedup = $baselineTime / $optTime;
    echo "\nSpeedup: " . number_format($speedup, 2) . "x\n";
}
