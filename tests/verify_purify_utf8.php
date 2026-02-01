<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Mock Database
class MockDatabase {
    public function connect() { return $this; }
    public function prepare($sql) { return new MockStmt(); }
}
class MockStmt {
    public function execute($params) {}
}

$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);

$engine = new ThemeEngine('clarity_default');
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('purifyHtml');
$method->setAccessible(true);
$cacheProp = $reflection->getProperty('purificationCache');
$cacheProp->setAccessible(true);

function test($name, $input, $expectedSubstrings, $engine, $method, $cacheProp) {
    // Clear cache
    $cacheProp->setValue($engine, []);
    $cacheDir = sys_get_temp_dir() . '/clarity_purify_cache';
    if (is_dir($cacheDir)) array_map('unlink', glob("$cacheDir/*"));

    $output = $method->invoke($engine, $input);

    echo "Test '$name': ";
    $passed = true;
    foreach ($expectedSubstrings as $sub) {
        if (strpos($output, $sub) === false) {
            echo "FAILED. Missing '$sub'. Output: $output\n";
            $passed = false;
        }
    }
    // Check for unintended meta tag
    if (strpos($output, '<meta') !== false) {
        echo "FAILED. Output contains <meta> tag.\n";
        $passed = false;
    }

    if ($passed) echo "PASSED\n";
}

test('Simple Text (No Tags)', 'Hello World', ['Hello World'], $engine, $method, $cacheProp);
test('Unicode Emoji', '<p>Hello 😃</p>', ['Hello 😃'], $engine, $method, $cacheProp);
test('Chinese Characters', '<p>你好世界</p>', ['你好世界'], $engine, $method, $cacheProp);
test('XSS Script', '<div><script>alert(1)</script>Content</div>', ['Content'], $engine, $method, $cacheProp);
test('XSS Event', '<div onclick="alert(1)">Click me</div>', ['Click me', 'div'], $engine, $method, $cacheProp);
test('Complex UTF-8', '<div>Rocket 🚀 and Kanji 漢字</div>', ['Rocket 🚀 and Kanji 漢字'], $engine, $method, $cacheProp);
