<?php
// Benchmark for ConfigHelper::get() performance
// Simulates repeated requests where the static cache is initially empty.

require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';
require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';

use Core\CacheService;

// 1. Setup SQLite In-Memory DB
$dbFile = sys_get_temp_dir() . '/bench_config_helper.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Schema
    $pdo->exec("CREATE TABLE settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        business_name TEXT,
        public_config TEXT,
        private_config TEXT,
        updated_at DATETIME
    )");

    // Seed Data
    $publicConfig = json_encode(['site_name' => 'Clarity Benchmark', 'link_timeout' => 15]);
    $privateConfig = json_encode(['secret_key' => '12345']);

    $stmt = $pdo->prepare("INSERT INTO settings (business_name, public_config, private_config, updated_at) VALUES ('Biz', ?, ?, datetime('now'))");
    $stmt->execute([$publicConfig, $privateConfig]);

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage() . "\n");
}

// Clear existing cache
ConfigHelper::clearCache();

// 2. Define Benchmark Function
function benchmark($iterations, $dbFile) {
    $start = microtime(true);

    // We want to simulate per-request overhead.
    // In original benchmark, we reset ConfigHelper::$cache.
    // Now ConfigHelper uses CacheService, which has its own L1 cache.
    // So we must reset CacheService memory cache to simulate new request.

    $reflectionService = new ReflectionClass('Core\CacheService');
    $memoryCacheProp = $reflectionService->getProperty('memoryCache');
    $memoryCacheProp->setAccessible(true);

    for ($i = 0; $i < $iterations; $i++) {
        // Reset Memory Cache (simulate fresh request)
        $memoryCacheProp->setValue(null, []);

        // Access a config value (triggers load() which hits File Cache via CacheService)
        $val = ConfigHelper::get('site_name');

        if ($val !== 'Clarity Benchmark') {
             die("Verification failed! Got: " . var_export($val, true));
        }
    }

    $end = microtime(true);
    return $end - $start;
}

// Pre-configure Database once
Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => null,
    'DB_PASS' => null
]);

// Test Connection Explicitly
try {
    Database::getInstance()->connect();
    echo "DB Connection verified.\n";
} catch (Exception $e) {
    die("DB Init failed: " . $e->getMessage() . "\n");
}

// 3. Run Benchmark
$iterations = 1000;
echo "Running ConfigHelper (CacheService) Benchmark ($iterations iterations)...\n";

// Warmup
benchmark(10, $dbFile);

$duration = benchmark($iterations, $dbFile);
$avgMs = ($duration / $iterations) * 1000;
$ops = $iterations / $duration;

echo sprintf("Total Time: %.4f s\n", $duration);
echo sprintf("Avg Time per Load: %.4f ms\n", $avgMs);
echo sprintf("Ops/sec: %.2f\n", $ops);

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
ConfigHelper::clearCache();
?>
