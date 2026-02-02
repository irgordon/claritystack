<?php
require_once __DIR__ . '/../clarity_app/api/core/Database.php';
require_once __DIR__ . '/../clarity_app/api/core/FileSecurity.php';
require_once __DIR__ . '/../clarity_app/api/core/Security.php';

use Core\FileSecurity;

// 1. Setup Mock DB
$dbFile = sys_get_temp_dir() . '/bench_image_upload.sqlite';
if (file_exists($dbFile)) unlink($dbFile);

$pdo = new PDO("sqlite:$dbFile");
$pdo->exec("CREATE TABLE settings (id INTEGER PRIMARY KEY, public_config TEXT, private_config TEXT)");
$pdo->exec("CREATE TABLE image_queue (
    id INTEGER PRIMARY KEY,
    original_path TEXT NOT NULL,
    thumb_path TEXT NOT NULL,
    width INTEGER,
    status TEXT,
    created_at TEXT,
    updated_at TEXT
)");

// Mock Settings
// We don't need real keys for Local storage
$publicConfig = json_encode([
    'storage_driver' => 'local',
]);

$pdo->prepare("INSERT INTO settings (public_config, private_config) VALUES (?, ?)")
    ->execute([$publicConfig, '{}']);

// Configure Database Singleton to use our SQLite DB
// Provide DB_USER/DB_PASS to avoid warnings
\Database::getInstance()->setConfig([
    'DB_DRIVER' => 'sqlite',
    'DB_NAME' => $dbFile,
    'DB_USER' => '',
    'DB_PASS' => ''
]);

// 2. Generate Dummy Image (Large enough to cause delay)
$imagePath = sys_get_temp_dir() . '/test_image.jpg';
$width = 3000;
$height = 3000;
$im = imagecreatetruecolor($width, $height);
// Fill with noise to make compression work a bit
for($i=0; $i<1000; $i++) {
    imagesetpixel($im, rand(0, $width), rand(0, $height), imagecolorallocate($im, rand(0,255), rand(0,255), rand(0,255)));
}
imagejpeg($im, $imagePath, 90);
imagedestroy($im);

// 3. Mock Upload
$file = [
    'name' => 'test_image.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $imagePath,
    'error' => 0,
    'size' => filesize($imagePath)
];

// Ensure storage directory exists
$repoRoot = realpath(__DIR__ . '/../');
$storageSecure = $repoRoot . '/storage_secure';
if (!is_dir($storageSecure)) mkdir($storageSecure);

echo "Benchmarking FileSecurity::processUpload with {$width}x{$height} image...\n";

// 4. Run Benchmark
try {
    $fs = new FileSecurity();
    $projectUuid = 'bench-' . uniqid();

    $start = microtime(true);
    $result = $fs->processUpload($file, $projectUuid);
    $end = microtime(true);

    $duration = $end - $start;
    echo "Time taken: " . number_format($duration, 4) . " seconds\n";

    if (isset($result['thumb_path'])) {
        echo "Thumbnail path: " . $result['thumb_path'] . "\n";
    } else {
        echo "Thumbnail path: NOT FOUND\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // echo $e->getTraceAsString();
}

// Cleanup
if (file_exists($imagePath)) unlink($imagePath);
if (file_exists($dbFile)) unlink($dbFile);
?>
