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

// Initialize DB schema
$conn = $db->connect();
$conn->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at DATETIME)");
$conn->exec("INSERT INTO settings (business_name, public_config, updated_at) VALUES ('Benchmark Biz', '{\"seo\":{\"site_name\":\"Bench\"}}', datetime('now'))");

try {
    $engine = new ThemeEngine('clarity_default');
} catch (Exception $e) {
    die("Failed to initialize ThemeEngine: " . $e->getMessage() . "\n");
}

// Dummy blocks tree - HEAVY
$blocksTree = [];
for($k=0; $k<200; $k++) {
    $blocksTree[] = [
        'type' => 'hero',
        'props' => [
            'title' => 'Welcome ' . $k,
            'content' => '<div style="color:red">Rich Content ' . $k . '</div><script>alert(1)</script>'
        ],
        'children' => []
    ];
    $blocksTree[] = [
        'type' => 'text_simple',
        'props' => ['body' => 'Lorem <b>ipsum</b> dolor sit amet. ' . str_repeat('Long text ', 10)],
        'children' => []
    ];
}

$layoutSlug = 'master';
$iterations = 20;

echo "Benchmarking renderPage ($iterations iterations with " . count($blocksTree) . " blocks)...\n";

$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    $result = $engine->renderPage($layoutSlug, $blocksTree);
    if (empty($result)) {
        die("Render failed on iteration $i\n");
    }
}

$end = microtime(true);
$duration = $end - $start;

echo "Total time: " . number_format($duration, 4) . " seconds\n";
echo "Average time per page: " . number_format(($duration / $iterations) * 1000, 4) . " ms\n";
