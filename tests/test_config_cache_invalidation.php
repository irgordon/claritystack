<?php
require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';
require_once __DIR__ . '/../clarity_app/api/core/Database.php';

$dbFile = sys_get_temp_dir() . '/test_config_inval.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    // Setup DB
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at DATETIME)");
    $pdo->exec("INSERT INTO settings (business_name, public_config, private_config) VALUES ('Test Biz', '{\"site_name\":\"Test Site\"}', '{\"secret\":\"123\"}')");

    Database::getInstance()->setConfig([
        'DB_DRIVER' => 'sqlite',
        'DB_NAME' => $dbFile,
        'DB_USER' => null,
        'DB_PASS' => null
    ]);

    // 1. Initial Load
    ConfigHelper::clearCache(); // Ensure clean start
    $val = ConfigHelper::get('site_name');
    if ($val !== 'Test Site') die("FAIL: Initial load failed. Got: " . var_export($val, true) . "\n");

    // 2. Check Cache File Existence
    // Replicate ConfigHelper path logic: sys_get_temp_dir() . '/clarity_config_' . md5(__DIR__) . '.php';
    // __DIR__ inside ConfigHelper is the core directory.
    $coreDir = realpath(__DIR__ . '/../clarity_app/api/core');
    $cacheFile = sys_get_temp_dir() . '/clarity_config_' . md5($coreDir) . '.php';

    if (!file_exists($cacheFile)) die("FAIL: Cache file not created at $cacheFile.\n");

    // 3. Modify Cache File manually to prove we are reading from it
    $cachedData = include $cacheFile;
    $cachedData['public']['site_name'] = 'Hacked Site';
    file_put_contents($cacheFile, "<?php return " . var_export($cachedData, true) . ";");

    // Reset Memory Cache to force file read
    $reflection = new ReflectionClass('ConfigHelper');
    $cacheProperty = $reflection->getProperty('cache');
    $cacheProperty->setAccessible(true);
    $cacheProperty->setValue(null, null);

    $val = ConfigHelper::get('site_name');
    if ($val !== 'Hacked Site') die("FAIL: Did not read from cache file. Got: " . var_export($val, true) . "\n");

    // 4. Test Invalidation
    ConfigHelper::clearCache();
    if (file_exists($cacheFile)) die("FAIL: Cache file not deleted after clearCache().\n");
    if ($cacheProperty->getValue() !== null) die("FAIL: Memory cache not cleared.\n");

    // 5. Reload (should fetch from DB 'Test Site' again, ignoring our manual hack which is deleted)
    $val = ConfigHelper::get('site_name');
    if ($val !== 'Test Site') die("FAIL: Reload after invalidation failed. Got: " . var_export($val, true) . "\n");

    echo "PASS: Cache Invalidation and Persistence verified.\n";

} catch (Exception $e) {
    die("ERROR: " . $e->getMessage() . "\n");
} finally {
    // Cleanup
    if (file_exists($dbFile)) unlink($dbFile);
    ConfigHelper::clearCache(); // Cleanup cache file
}
?>
