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

try {
    $engine = new ThemeEngine('clarity_default');
} catch (Exception $e) {
    die("Failed to initialize ThemeEngine: " . $e->getMessage() . "\n");
}

// Reflection to access private renderBlock
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('renderBlock');
$method->setAccessible(true);

$iterations = 5000;
$blockType = 'hero';
// We pass 'content' which triggers purifyHtml, even if hero view doesn't use it.
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
$props = [
    'title' => 'Benchmark',
    'content' => $complexHtml
];
$children = [];

echo "Benchmarking purifyHtml via renderBlock ($iterations iterations)...\n";

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $method->invoke($engine, $blockType, $props, $children);
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time: " . number_format($duration, 4) . " seconds\n";
echo "Average time per call: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";
