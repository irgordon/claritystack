<?php
// tests/benchmark_integrated_sendfile.php

require_once __DIR__ . '/../clarity_app/api/core/Storage/LocalAdapter.php';
use Core\Storage\LocalAdapter;

// 1. Setup
$tempDir = sys_get_temp_dir() . '/bench_storage_' . uniqid();
mkdir($tempDir);
$fileName = 'large_file.dat';
$filePath = $tempDir . '/' . $fileName;

// Create 50MB file
$fp = fopen($filePath, 'w');
$chunk = str_repeat('0', 1024 * 1024);
for ($i = 0; $i < 50; $i++) {
    fwrite($fp, $chunk);
}
fclose($fp);

// 2. Benchmark Standard (Baseline)
$adapter = new LocalAdapter($tempDir);

$start = microtime(true);
ob_start();
$adapter->output($fileName);
ob_end_clean();
$timeBaseline = microtime(true) - $start;

// 3. Benchmark Optimized
$adapterOpt = new LocalAdapter($tempDir, [
    'sendfile_header' => 'X-Accel-Redirect',
    'sendfile_prefix' => '/protected'
]);

$start = microtime(true);
ob_start();
// This should just set a header and return
$adapterOpt->output($fileName);
ob_end_clean();
$timeOpt = microtime(true) - $start;

// 4. Report
echo "Integrated Benchmark (LocalAdapter output 50MB):\n";
echo "Baseline (PHP readfile): " . number_format($timeBaseline, 6) . " s\n";
echo "Optimized (Header only): " . number_format($timeOpt, 6) . " s\n";
echo "Speedup: " . number_format($timeBaseline / ($timeOpt ?: 0.000001), 2) . "x\n";

// Cleanup
unlink($filePath);
rmdir($tempDir);
