<?php

require_once __DIR__ . '/../clarity_app/api/core/Storage/LocalAdapter.php';

$tempDir = sys_get_temp_dir() . '/clarity_storage_verify_' . uniqid();
if (is_dir($tempDir)) {
    // Should not exist for this test
    rmdir($tempDir);
}

echo "Testing with rootPath: $tempDir\n";

// Test 1: Instantiation should not create directory (after change)
// Before change: it creates directory.
// After change: it does NOT create directory.
$adapter = new \Core\Storage\LocalAdapter($tempDir);

if (is_dir($tempDir)) {
    echo "Directory created immediately (Current behavior).\n";
} else {
    echo "Directory NOT created immediately (New behavior).\n";
}

// Test 2: put() should create directory
$fileContent = "Hello World";
$filePath = "test.txt";
$sourceFile = sys_get_temp_dir() . '/source_' . uniqid();
file_put_contents($sourceFile, $fileContent);

echo "Putting file...\n";
$adapter->put($sourceFile, $filePath);

if (is_dir($tempDir)) {
    echo "Directory created after put(). OK.\n";
} else {
    echo "Directory NOT created after put(). FAIL.\n";
}

if ($adapter->get($filePath) === $fileContent) {
    echo "File content verified. OK.\n";
} else {
    echo "File content mismatch. FAIL.\n";
}

// Cleanup
$adapter->delete($filePath);
if (is_dir($tempDir)) {
    // cleanup empty dir
    rmdir($tempDir);
}
unlink($sourceFile);
