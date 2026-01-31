<?php
require_once __DIR__ . '/../clarity_app/api/core/ThemeEngine.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';

// Clear Config Cache to ensure test isolation
ConfigHelper::clearCache();

// Configure Database
$db = Database::getInstance();
$db->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => ':memory:',
    'DB_USER' => '',
    'DB_PASS' => ''
]);

$conn = $db->connect();
$conn->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at DATETIME)");
$conn->exec("INSERT INTO settings (business_name, public_config, updated_at) VALUES ('Test Biz', '{\"seo\":{\"site_name\":\"Test\"}}', '2023-01-01 12:00:00')");

$engine = new ThemeEngine('clarity_default');
$layoutSlug = 'master';
$blocksTree = [['type' => 'text_simple', 'props' => ['body' => 'Original Content'], 'children' => []]];

// Helper to get cache key
function getCacheKey($layoutSlug, $blocksTree, $updatedAt) {
    $layoutPath = realpath(__DIR__ . '/../clarity_app/themes/clarity_default/layouts/master/view.php');
    $mtime = filemtime($layoutPath);
    return md5($layoutSlug . serialize($blocksTree) . $updatedAt . $mtime);
}

$settingsUpdatedAt = '2023-01-01 12:00:00';

// 1. First Render (Cache Miss -> Write)
echo "1. Performing first render...\n";
$output1 = $engine->renderPage($layoutSlug, $blocksTree);

$cacheKey1 = getCacheKey($layoutSlug, $blocksTree, $settingsUpdatedAt);
$cacheDir = sys_get_temp_dir() . '/clarity_page_cache';
$cacheFile1 = $cacheDir . '/' . $cacheKey1 . '.html';

if (!file_exists($cacheFile1)) {
    die("FAILED: Cache file was not created: $cacheFile1\n");
}
echo "PASSED: Cache file created.\n";

// 2. Second Render (Cache Hit)
// Modify the cache file content manually to prove it's being read
file_put_contents($cacheFile1, "CACHED_CONTENT_MARKER");
$output2 = $engine->renderPage($layoutSlug, $blocksTree);

if ($output2 === "CACHED_CONTENT_MARKER") {
    echo "PASSED: Second render read from cache.\n";
} else {
    die("FAILED: Second render did not read from cache. Got: " . substr($output2, 0, 50) . "...\n");
}

// 3. Update Settings (Invalidation)
echo "3. Updating settings to invalidate cache...\n";
$newTime = '2023-01-02 12:00:00';
$conn->exec("UPDATE settings SET updated_at = '$newTime'");

// Simulate what SettingsController would do: Clear ConfigHelper cache so it picks up the change
ConfigHelper::clearCache();

$output3 = $engine->renderPage($layoutSlug, $blocksTree);
// Should not be the cached marker
if ($output3 !== "CACHED_CONTENT_MARKER") {
    echo "PASSED: Render after settings update bypassed old cache.\n";
} else {
    die("FAILED: Render after settings update used stale cache.\n");
}

// Check new cache file exists
$cacheKey2 = getCacheKey($layoutSlug, $blocksTree, $newTime);
if (file_exists($cacheDir . '/' . $cacheKey2 . '.html')) {
     echo "PASSED: New cache file created for updated settings.\n";
} else {
     die("FAILED: New cache file not created.\n");
}

// 4. Change Content (Invalidation)
echo "4. Changing content tree...\n";
$blocksTreeChanged = [['type' => 'text_simple', 'props' => ['body' => 'New Content'], 'children' => []]];
$output4 = $engine->renderPage($layoutSlug, $blocksTreeChanged);
$cacheKey3 = getCacheKey($layoutSlug, $blocksTreeChanged, $newTime);

if (file_exists($cacheDir . '/' . $cacheKey3 . '.html')) {
    echo "PASSED: New cache file created for changed content.\n";
} else {
    die("FAILED: New cache file not created for changed content.\n");
}

// 5. Layout Modification (Invalidation)
echo "5. Touching layout file to invalidate cache...\n";
$layoutPath = realpath(__DIR__ . '/../clarity_app/themes/clarity_default/layouts/master/view.php');
clearstatcache();
$oldMtime = filemtime($layoutPath);
sleep(1); // Ensure timestamp differs
touch($layoutPath);
clearstatcache();

// Re-render
$output5 = $engine->renderPage($layoutSlug, $blocksTreeChanged);
$cacheKey4 = getCacheKey($layoutSlug, $blocksTreeChanged, $newTime); // This function reads current mtime

if ($cacheKey4 === $cacheKey3) {
    echo "WARNING: Cache key did not change (mtime issue?).\n";
} else {
    if (file_exists($cacheDir . '/' . $cacheKey4 . '.html')) {
        echo "PASSED: New cache file created after layout touch.\n";
    } else {
        die("FAILED: New cache file not created after layout touch.\n");
    }
}

// Cleanup
@unlink($cacheFile1);
@unlink($cacheDir . '/' . $cacheKey2 . '.html');
@unlink($cacheDir . '/' . $cacheKey3 . '.html');
@unlink($cacheDir . '/' . $cacheKey4 . '.html');

echo "ALL TESTS PASSED.\n";
?>
