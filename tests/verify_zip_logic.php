<?php
// Verify Zip Logic and readStream
require_once __DIR__ . '/../clarity_app/api/core/Storage/LocalAdapter.php';

$tmpDir = sys_get_temp_dir();
$testFile = $tmpDir . '/test_photo.txt';
file_put_contents($testFile, "Hello World Content");

$adapter = new \Core\Storage\LocalAdapter($tmpDir);

// Test readStream
$stream = $adapter->readStream('test_photo.txt');
if (!$stream) die("FAIL: readStream returned null\n");

$meta = stream_get_meta_data($stream);
if ($meta['wrapper_type'] !== 'plainfile') die("FAIL: Not plainfile\n");
if ($meta['uri'] !== $testFile) die("FAIL: URI mismatch: " . $meta['uri'] . "\n");

fclose($stream);
echo "PASS: readStream local\n";

// Test Zip
$zipFile = $tmpDir . '/test.zip';
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("FAIL: Zip open\n");
}

$zip->addFile($testFile, 'hello.txt');
$zip->close();

if (!file_exists($zipFile)) die("FAIL: Zip not created\n");
if (filesize($zipFile) < 10) die("FAIL: Zip empty\n");

echo "PASS: Zip created\n";

@unlink($testFile);
@unlink($zipFile);
?>
