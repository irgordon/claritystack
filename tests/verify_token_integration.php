<?php
// tests/verify_token_integration.php

// 1. Setup DB
$dbFile = sys_get_temp_dir() . '/test_token_auth.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, role TEXT, password TEXT)");
    $pdo->exec("CREATE TABLE projects (id INTEGER PRIMARY KEY, client_email TEXT)");
    $pdo->exec("CREATE TABLE photos (id INTEGER PRIMARY KEY, project_id INTEGER, system_filename TEXT, thumb_path TEXT, mime_type TEXT, original_filename TEXT, created_at DATETIME)");
    $pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, business_name TEXT, public_config TEXT, private_config TEXT, updated_at TEXT)");

    $pdo->exec("INSERT INTO users (id, email, role) VALUES (1, 'user@example.com', 'client')");
    $pdo->exec("INSERT INTO projects (id, client_email) VALUES (1, 'user@example.com')");
    $pdo->exec("INSERT INTO photos (id, project_id, system_filename, thumb_path, mime_type, original_filename, created_at) VALUES (1, 1, 'file_1.jpg', 'thumb_1.jpg', 'image/jpeg', 'orig_1.jpg', '2023-01-01 00:00:00')");

    // Create dummy files
    $storageDir = sys_get_temp_dir() . '/clarity_storage_test';
    if (!is_dir($storageDir)) mkdir($storageDir);
    file_put_contents($storageDir . '/thumb_1.jpg', 'fake_image_content');

    // Configure ConfigHelper to use this storage path
    $publicConfig = json_encode(['storage_path' => $storageDir]);
    $stmt = $pdo->prepare("INSERT INTO settings (id, business_name, public_config, private_config, updated_at) VALUES (1, 'Test Biz', ?, '{}', '0')");
    $stmt->execute([$publicConfig]);

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
require_once __DIR__ . '/../clarity_app/api/controllers/FileController.php';

// Mock Session
session_start();
$_SESSION['user_id'] = 1;

// 2. Get Token via ProjectController
echo "Getting Token...\n";
ob_start();
$pc = new ProjectController();
$pc->listPhotos(1);
$json = ob_get_clean();
$data = json_decode($json, true);

if (!isset($data['data'][0]['token'])) {
    die("FAILED: No token in response. Output: $json\n");
}
$token = $data['data'][0]['token'];
echo "Token received.\n";

// 3. Test FileController with Token (Opt-In)
echo "Test 1: Token Auth (Bypass DB)...\n";

// Mock $_GET
$_GET['token'] = $token;
$_GET['type'] = 'thumb';

// DESTRUCTIVE TEST: Delete the photo from DB.
$pdo->exec("DELETE FROM photos WHERE id = 1");

ob_start();
try {
    $fc = new FileController();
    $fc->view(1);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

if ($output === 'fake_image_content') {
    echo "SUCCESS: Image served via token (DB record was deleted)!\n";
} else {
    echo "FAILED: Output did not match.\n";
}

// 4. Test FileController Fallback
echo "Test 2: Fallback (DB Lookup)...\n";

// Re-insert photo
$pdo->exec("INSERT INTO photos (id, project_id, system_filename, thumb_path, mime_type, original_filename, created_at) VALUES (1, 1, 'file_1.jpg', 'thumb_1.jpg', 'image/jpeg', 'orig_1.jpg', '2023-01-01 00:00:00')");

// Clear token
unset($_GET['token']);

ob_start();
try {
    $fc = new FileController();
    $fc->view(1);
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

if ($output === 'fake_image_content') {
    echo "SUCCESS: Image served via fallback DB lookup!\n";
} else {
    echo "FAILED: Output did not match in fallback.\n";
}

// Cleanup
if (file_exists($dbFile)) unlink($dbFile);
if (is_dir($storageDir)) {
    @unlink($storageDir . '/thumb_1.jpg');
    @rmdir($storageDir);
}
?>
