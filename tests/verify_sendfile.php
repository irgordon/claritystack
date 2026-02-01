<?php
require_once __DIR__ . '/../clarity_app/api/core/Storage/LocalAdapter.php';

use Core\Storage\LocalAdapter;

$tempDir = sys_get_temp_dir() . '/test_storage_' . uniqid();
mkdir($tempDir);
$testFile = $tempDir . '/test.txt';
file_put_contents($testFile, 'Hello World');

// Test 1: No Config (Fallback to readfile)
// Note: We can't easily capture readfile output in CLI without buffering,
// and headers() usually won't work perfectly in CLI, but we can check if the function runs without error.
// We'll skip output verification for readfile here as we trust PHP, just want to ensure it doesn't crash.
$adapter = new LocalAdapter($tempDir);
ob_start();
$adapter->output('test.txt');
$output = ob_get_clean();
if ($output !== 'Hello World') {
    echo "Test 1 Failed: Output mismatch\n";
    exit(1);
}

// Test 2: With Sendfile Config
// In CLI, `header()` calls don't return to `headers_list()` unless run in a specific way or mocked.
// PHP CLI SAPI does not output headers. However, `xdebug_get_headers` might exist, or we rely on the fact that `readfile` is NOT called.
// If `readfile` WAS called, we would see "Hello World" in output.
// If optimized, we should see NO output (body).

$adapterOpt = new LocalAdapter($tempDir, [
    'sendfile_header' => 'X-Accel-Redirect',
    'sendfile_prefix' => '/protected_files'
]);

ob_start();
$adapterOpt->output('test.txt');
$outputOpt = ob_get_clean();

if ($outputOpt !== '') {
    echo "Test 2 Failed: Output should be empty when using X-Sendfile, but got: '$outputOpt'\n";
    exit(1);
}

// Since we can't inspect headers in standard CLI easily without extensions,
// the absence of body output is a strong indicator the optimization path was taken.
// We can also verify via a mock if we really wanted to, but this functional test proves the branch was taken.

echo "Verification Successful!\n";

// Cleanup
unlink($testFile);
rmdir($tempDir);
