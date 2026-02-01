<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);

try {
    $engine = new ThemeEngine('clarity_default');
} catch (Exception $e) {}

$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('purifyHtml');
$method->setAccessible(true);
$cacheProp = $reflection->getProperty('purificationCache');
$cacheProp->setAccessible(true);

$complexHtml = '
    <div class="wrapper">
        <h2 onclick="alert(1)">Title with Unicode: 🚀 测试</h2>
        <p>This is a <strong>rich text</strong> content.</p>
    </div>
';
$simpleText = "Just some text without any tags but definitely needs to be checked.";

$iterations = 1000;

echo "Benchmarking purifyHtml (Complex) with $iterations iterations...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $cacheProp->setValue($engine, []);
    $input = $complexHtml . " <!-- $i -->";
    $method->invoke($engine, $input);
}
$end = microtime(true);
echo "Complex Time: " . number_format(($end - $start) * 1000, 2) . " ms\n";

echo "Benchmarking purifyHtml (Simple Text) with $iterations iterations...\n";
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $cacheProp->setValue($engine, []);
    $input = $simpleText . " " . $i;
    $method->invoke($engine, $input);
}
$end = microtime(true);
echo "Simple Text Time: " . number_format(($end - $start) * 1000, 2) . " ms\n";
