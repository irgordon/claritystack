<?php
// tests/benchmark_zip_streamer.php
require_once __DIR__ . '/../clarity_app/api/core/ZipStreamer.php';

use Core\ZipStreamer;

// Define StorageInterface locally if not autoloaded
if (!interface_exists('StorageInterface')) {
    interface StorageInterface {
        public function readStream(string $path);
    }
}

class MockRemoteStorage implements StorageInterface {
    public function readStream(string $path) {
        // Simulate network latency (e.g. 50ms per file connect)
        usleep(50000);

        // Return a stream resource
        // We simulate download time by sleeping before returning
        // In reality, download time happens during reading.
        usleep(50000);

        $stream = fopen("php://temp", "w+");
        fwrite($stream, str_repeat("A", 1024 * 100)); // 100KB
        rewind($stream);
        return $stream;
    }
}

$storage = new MockRemoteStorage();
$photos = array_fill(0, 20, ['system_filename' => 'photo.jpg', 'original_filename' => 'photo.jpg']);

echo "Benchmarking Streamed Zip Generation (20 files, 100KB each, 100ms latency each)...\n";
$start = microtime(true);

// Capture output
ob_start();

$zip = new ZipStreamer();

foreach ($photos as $i => $photo) {
    $stream = $storage->readStream($photo['system_filename']);
    if ($stream) {
        $zip->addFileFromStream("photo_{$i}.jpg", $stream);
        fclose($stream);
    }
}

$zip->finish();

$content = ob_get_clean();

$end = microtime(true);
$duration = $end - $start;
$memory = memory_get_peak_usage(true);

echo "Streamed Time: " . number_format($duration, 4) . "s\n";
echo "Zip Size: " . strlen($content) . " bytes\n";
echo "Peak Memory: " . number_format($memory / 1024 / 1024, 2) . " MB\n";
?>
