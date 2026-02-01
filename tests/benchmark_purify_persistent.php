<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Configure Database to use SQLite Memory
$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);

// Clean up any existing cache for baseline measurement
$cacheDir = sys_get_temp_dir() . '/clarity_purify_cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
}

$complexHtml = '
    <div class="wrapper">
        <h2 onclick="alert(1)">Title</h2>
        <p>This is a <strong>rich text</strong> content with <a href="javascript:void(0)">bad link</a>.</p>
        <ul>
            <li>Item 1</li>
            <li>Item 2 <span style="color:red">removed style?</span></li>
        </ul>
        <script>console.log("bad");</script>
    </div>
';
// Make it heavier
for ($k=0; $k<50; $k++) {
    $complexHtml .= '<p>More content ' . $k . ' <a href="#">Link</a></p>';
}

$iterations = 500;
$blockType = 'hero';
$props = [
    'title' => 'Benchmark',
    'content' => $complexHtml
];
$children = [];

echo "Benchmarking purifyHtml across $iterations FRESH instances (simulating new requests)...\n";

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    try {
        $engine = new ThemeEngine('clarity_default');

        // Reflection to access private renderBlock
        $reflection = new ReflectionClass($engine);
        $method = $reflection->getMethod('renderBlock');
        $method->setAccessible(true);

        $method->invoke($engine, $blockType, $props, $children);
    } catch (Exception $e) {
        // Ignore initialization errors
    }
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time: " . number_format($duration, 4) . " seconds\n";
echo "Average time per call: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";
