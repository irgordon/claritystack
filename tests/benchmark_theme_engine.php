<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Configure Database to use SQLite Memory to avoid postgres dependency issues during benchmark
$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);

// Initialize the engine
try {
    $engine = new ThemeEngine('clarity_default');
} catch (Exception $e) {
    die("Failed to initialize ThemeEngine: " . $e->getMessage() . "\n");
}

// Reflection to access private renderBlock
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('renderBlock');
$method->setAccessible(true);

$iterations = 10000;
$blockType = 'hero';
$props = ['title' => 'Benchmark'];
$children = [];

echo "Benchmarking renderBlock for block '$blockType' with $iterations iterations...\n";

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $method->invoke($engine, $blockType, $props, $children);
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time: " . number_format($duration, 4) . " seconds\n";
echo "Average time per call: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";
