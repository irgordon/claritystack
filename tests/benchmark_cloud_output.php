<?php
// tests/benchmark_cloud_output.php

// 1. Setup
$tempDir = sys_get_temp_dir() . '/bench_cloud_' . uniqid();
mkdir($tempDir);
$fileName = 'cloud_file.dat';
$filePath = $tempDir . '/' . $fileName;

// Create 5MB file to simulate a medium sized image/asset
$fp = fopen($filePath, 'w');
$chunk = str_repeat('0', 1024 * 1024);
for ($i = 0; $i < 5; $i++) {
    fwrite($fp, $chunk);
}
fclose($fp);

echo "Benchmark: Cloud Storage Output Strategy (5MB File)\n";
echo "--------------------------------------------------\n";

// 2. Scenario A: Proxy/Streaming (Simulating current behavior for some adapters)
// The application downloads the file (or reads stream) and outputs it to client.
$start = microtime(true);
$startMem = memory_get_usage();

ob_start();
$handle = fopen($filePath, 'rb');
while (!feof($handle)) {
    echo fread($handle, 8192);
}
fclose($handle);
ob_end_clean();

$timeStreaming = microtime(true) - $start;
$memStreaming = memory_get_peak_usage() - $startMem;

// 3. Scenario B: Redirect (Optimized)
// The application simply sends a Location header.
$start = microtime(true);
$startMem = memory_get_usage();

ob_start();
header("Location: https://storage.cloud.com/" . $fileName);
ob_end_clean();

$timeRedirect = microtime(true) - $start;
$memRedirect = memory_get_peak_usage() - $startMem; // Should be negligible

// 4. Report
echo "Scenario A (PHP Streaming): " . number_format($timeStreaming, 6) . " s\n";
echo "Scenario B (Redirect):      " . number_format($timeRedirect, 6) . " s\n";

$improvement = $timeStreaming / ($timeRedirect ?: 0.000001);
echo "Speedup: " . number_format($improvement, 2) . "x\n";

// Cleanup
unlink($filePath);
rmdir($tempDir);
