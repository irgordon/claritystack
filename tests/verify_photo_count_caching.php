<?php
// tests/verify_project_count.php

// 1. Setup DB
$dbFile = sys_get_temp_dir() . '/test_count.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

// Cleanup potential stale cache from other tests
$staleCache = sys_get_temp_dir() . '/clarity_count_' . md5(1);
if (file_exists($staleCache)) unlink($staleCache);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, role TEXT, password TEXT)");
    $pdo->exec("CREATE TABLE projects (id INTEGER PRIMARY KEY, client_email TEXT)");
    $pdo->exec("CREATE TABLE photos (id INTEGER PRIMARY KEY, project_id INTEGER, system_filename TEXT, thumb_path TEXT, mime_type TEXT, original_filename TEXT, created_at DATETIME)");
    $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at TEXT)");

    $pdo->exec("INSERT INTO users (id, email, role) VALUES (1, 'user@example.com', 'client')");
    $pdo->exec("INSERT INTO projects (id, client_email) VALUES (1, 'user@example.com')");

    // Insert 55 photos to verify pagination (Limit is 50, so should be 2 pages)
    for ($i = 0; $i < 55; $i++) {
        $pdo->exec("INSERT INTO photos (project_id, system_filename, thumb_path, mime_type, original_filename, created_at) VALUES (1, 'file_$i.jpg', 'thumb_$i.jpg', 'image/jpeg', 'orig_$i.jpg', '2023-01-01 00:00:00')");
    }

} catch (Exception $e) {
    die("Setup failed: " . $e->getMessage());
}

// Configure environment
putenv("CLARITY_TEST_DB=$dbFile");

require_once __DIR__ . '/../clarity_app/api/core/Database.php';

// Inject Test Config
Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => null,
    'DB_PASS' => null
]);

require_once __DIR__ . '/../clarity_app/api/core/ConfigHelper.php';
\ConfigHelper::clearCache();

require_once __DIR__ . '/../clarity_app/api/controllers/ProjectController.php';

// Mock Session
session_start();
$_SESSION['user_id'] = 1;

// 2. Call listPhotos
echo "Calling listPhotos...\n";
ob_start();
$pc = new ProjectController();
// Page 1
$_GET['page'] = 1;
$pc->listPhotos(1);
$json = ob_get_clean();
$data = json_decode($json, true);

if (!isset($data['meta']['total_pages'])) {
    die("FAILED: No total_pages in response. Output: $json\n");
}

$totalPages = $data['meta']['total_pages'];
echo "Total Pages: $totalPages\n";

if ($totalPages != 2) {
    die("FAILED: Expected 2 pages, got $totalPages\n");
}

// Check if cache file exists
$cacheFile = sys_get_temp_dir() . '/clarity_count_' . md5(1);
if (file_exists($cacheFile)) {
    echo "SUCCESS: Cache file created.\n";
    $cachedCount = (int)file_get_contents($cacheFile);
    if ($cachedCount === 55) {
        echo "SUCCESS: Cached count is correct (55).\n";
    } else {
        echo "FAILED: Cached count is incorrect ($cachedCount).\n";
    }
} else {
    echo "FAILED: Cache file not created.\n";
}

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
if (file_exists($cacheFile)) unlink($cacheFile);
?>
