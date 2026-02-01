<?php
// Benchmark for index.php SEO Logic
// We can't include index.php directly because it outputs HTML and exits.
// We will mock the parts we need and test CacheService interaction.

require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';

use Core\CacheService;

// Setup Mock DB
$dbFile = sys_get_temp_dir() . '/bench_seo.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

$pdo = new PDO("sqlite:$dbFile");
$pdo->exec("CREATE TABLE pages (slug TEXT, title TEXT, meta_description TEXT, og_image_url TEXT)");
$pdo->exec("INSERT INTO pages VALUES ('home', 'Home Page', 'Welcome', 'img.jpg')");

// Override Database Singleton to use our mock
Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => null,
    'DB_PASS' => null
]);

$slug = 'home';
$key = md5($slug);

CacheService::flush('seo');

// 1. First Load (Cache Miss)
$start = microtime(true);
$page = CacheService::remember('seo', $key, 3600, function() use ($slug) {
    $db = Database::getInstance()->connect();
    $stmt = $db->prepare("SELECT title, meta_description, og_image_url FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
});
$firstLoad = microtime(true) - $start;

if ($page['title'] !== 'Home Page') die("First load failed");

// 2. Second Load (Cache Hit)
$start = microtime(true);
$page = CacheService::remember('seo', $key, 3600, function() use ($slug) {
    die("Should not be called!");
});
$secondLoad = microtime(true) - $start;

echo "First Load (DB): " . number_format($firstLoad * 1000, 4) . " ms\n";
echo "Second Load (Cache): " . number_format($secondLoad * 1000, 4) . " ms\n";

if ($secondLoad > $firstLoad) {
    echo "WARNING: Cache slower than DB (might be SQLite speed vs File IO overhead)\n";
} else {
    echo "Improvement: " . number_format(($firstLoad - $secondLoad) * 1000, 4) . " ms\n";
}
