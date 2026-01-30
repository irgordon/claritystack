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

$engine = new ThemeEngine('clarity_default');
$reflection = new ReflectionClass($engine);
$method = $reflection->getMethod('purifyHtml');
$method->setAccessible(true);

function test($engine, $method, $input, $expected, $desc) {
    $output = $method->invoke($engine, $input);
    if ($output === $expected) {
        echo "PASS: $desc\n";
    } else {
        echo "FAIL: $desc\n";
        echo "  Input:    $input\n";
        echo "  Expected: $expected\n";
        echo "  Got:      $output\n";
        exit(1);
    }
}

// 1. Basic allowed tags
test($engine, $method, '<p>Test</p>', '<p>Test</p>', 'Allow simple p tag');

// 2. Removing disallowed tags
test($engine, $method, '<script>alert(1)</script><p>Ok</p>', '<p>Ok</p>', 'Remove script tag');

// 3. Removing dangerous attributes
test($engine, $method, '<div onclick="alert(1)">Click</div>', '<div>Click</div>', 'Remove onclick');

// 4. Removing javascript: href
test($engine, $method, '<a href="javascript:alert(1)">Link</a>', '<a>Link</a>', 'Remove javascript: href');

// 5. Nested removal
test($engine, $method, '<div><script>bad</script><span>Good</span></div>', '<div><span>Good</span></div>', 'Nested script removal');

// 6. Handling bad parent removal
// If <bad> is removed, its children (even if valid) are effectively removed from the document tree.
test($engine, $method, '<bad><span>Gone</span></bad>', '', 'Remove unknown tag and its children');

// 7. Large input
$large = str_repeat('<p>Test</p>', 100);
test($engine, $method, $large, $large, 'Large input');

echo "All correctness tests passed.\n";
