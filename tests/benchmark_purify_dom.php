<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Mock Database to avoid connection errors during ThemeEngine init
class MockDatabase {
    public function connect() { return $this; }
    public function prepare($sql) { return new MockStmt(); }
}
class MockStmt {
    public function execute($params) {}
}

// Override Database::getInstance if possible, but ThemeEngine calls it statically.
// Since we can't easily mock the static method without runkit or similar,
// we will rely on the fact that ThemeEngine uses dependency injection for DB in constructor?
// No, it calls `\Database::getInstance()->connect()` in constructor.

// Check if we can use the SQLite config trick from other benchmarks.
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
    // If it fails (e.g. missing theme), we might need to mock or ensure theme exists.
    // Assuming 'clarity_default' exists or we can suppress.
    // Actually, ThemeEngine checks theme path.
}

// Reflection to access private purifyHtml
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('purifyHtml');
$method->setAccessible(true);

// Clear caches to force parsing
$cacheProp = $reflection->getProperty('purificationCache');
$cacheProp->setAccessible(true);
$cacheProp->setValue($engine, []);

// Disable file cache for this benchmark or clean it up
$cacheDir = sys_get_temp_dir() . '/clarity_purify_cache';
if (is_dir($cacheDir)) {
    array_map('unlink', glob("$cacheDir/*"));
}

$simpleHtml = '<p>Simple paragraph</p>';
$complexHtml = '
    <div class="wrapper">
        <h2 onclick="alert(1)">Title with Unicode: 🚀 测试</h2>
        <p>This is a <strong>rich text</strong> content with <a href="javascript:void(0)">bad link</a>.</p>
        <ul>
            <li>Item 1</li>
            <li>Item 2 <span style="color:red">removed style?</span></li>
        </ul>
        <script>console.log("bad");</script>
    </div>
';
// Make it heavier and more unicode heavy to test mb_convert_encoding impact
for ($k=0; $k<20; $k++) {
    $complexHtml .= '<p>More content ' . $k . ' <a href="#">Link</a> with emoji 😃 and chinese characters: 你好世界</p>';
}

$iterations = 1000;

echo "Benchmarking purifyHtml with $iterations iterations...\n";

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    // Clear memory cache every time to measure parsing speed
    $cacheProp->setValue($engine, []);

    // We also need to ensure file cache doesn't hit.
    // The easiest way is to append a random string to the HTML so the hash changes.
    // But that changes the input.
    // Instead, let's just clear the file cache inside the loop? No, that's too slow (IO).
    // Let's rely on the fact that we passed a unique string?
    // If we use the SAME string, it will hit the cache.
    // So we MUST disable the cache in ThemeEngine or modify the method.

    // Since we can't modify the method yet, let's modify the input slightly each time.
    $input = $complexHtml . " <!-- $i -->";

    $method->invoke($engine, $input);
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time: " . number_format($duration, 4) . " seconds\n";
echo "Average time per call: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";
