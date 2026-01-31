<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';

// Setup Test DB
$dbPath = sys_get_temp_dir() . '/seo_benchmark.sqlite';
@unlink($dbPath);

$config = [
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbPath,
    'DB_USER' => '',
    'DB_PASS' => ''
];

Database::getInstance()->setConfig($config);
$db = Database::getInstance()->connect();

// Create Tables
$db->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, public_config TEXT, private_config TEXT, business_name TEXT, updated_at TEXT)");
$db->exec("CREATE TABLE pages (id INTEGER PRIMARY KEY, slug TEXT, title TEXT, meta_description TEXT, og_image_url TEXT)");

// Insert Data
$db->exec("INSERT INTO settings (public_config, private_config, business_name, updated_at) VALUES ('{\"seo\": {\"site_name\": \"Test Site\"}}', '{}', 'My Business', '1234567890')");
$db->exec("INSERT INTO pages (slug, title, meta_description, og_image_url) VALUES ('home', 'Home Page', 'Best page ever', 'http://example.com/image.jpg')");

// Clear ConfigHelper cache
ConfigHelper::clearCache();

// Benchmark Logic
$slug = 'home';
$iterations = 1000;

echo "Benchmarking Baseline (Raw Queries)...\n";
$start = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    // 2. Fetch SEO Data
    $stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();

    // 3. Fetch Global Config
    $settings = $db->query("SELECT public_config FROM settings LIMIT 1")->fetch();
}

$end = microtime(true);
$baseline = $end - $start;
echo "Baseline: " . number_format($baseline, 4) . "s (" . number_format(($baseline / $iterations) * 1000, 4) . "ms/req)\n";

// Optimized Benchmark Preview (Simulated)
echo "Benchmarking Optimized (ConfigHelper + Cache)...\n";

// Clear ConfigHelper cache again to start fresh
ConfigHelper::clearCache();

// Pre-warm the cache once for fairness if we assume steady state,
// OR we can include the warm-up in the loop.
// Realistically, the cache is hit most of the time.
// Let's run the loop.

$startOpt = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    // Simulate Optimized Logic

    // ConfigHelper (Memoized internally)
    $publicConfig = ConfigHelper::getPublicConfig();

    // Page Cache (Simulation)
    $cacheFile = sys_get_temp_dir() . '/clarity_seo_' . md5($slug) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 3600)) {
        $json = file_get_contents($cacheFile);
        if ($json) $page = json_decode($json, true);
    } else {
        $stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        // Write Cache
        file_put_contents($cacheFile, json_encode($page));
    }
}

$endOpt = microtime(true);
$optimized = $endOpt - $startOpt;
echo "Optimized: " . number_format($optimized, 4) . "s (" . number_format(($optimized / $iterations) * 1000, 4) . "ms/req)\n";

echo "Improvement: " . number_format((($baseline - $optimized) / $baseline) * 100, 2) . "%\n";

@unlink($dbPath);
@unlink(sys_get_temp_dir() . '/clarity_seo_' . md5($slug) . '.json');
?>
