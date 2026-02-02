<?php
// tests/benchmark_zip_generation.php

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
        // To simulate a remote stream that is not seekable and not a local file
        // we can use a php://temp stream.
        // In reality, copying from a remote stream to a local file involves network I/O time proportional to size.
        // We simulate this by sleeping a bit more during the "copy".
        // But stream_copy_to_stream is blocking.
        // Let's just sleep here to simulate the total download time.
        usleep(50000); // Another 50ms for transfer

        $stream = fopen("php://temp", "w+");
        fwrite($stream, str_repeat("A", 1024 * 100)); // 100KB to be fast
        rewind($stream);
        return $stream;
    }
}

$storage = new MockRemoteStorage();
$photos = array_fill(0, 20, ['system_filename' => 'photo.jpg', 'original_filename' => 'photo.jpg']);

echo "Benchmarking Legacy Zip Generation (20 files, 100KB each, 100ms latency each)...\n";
$start = microtime(true);

$zipFile = tempnam(sys_get_temp_dir(), 'zip_bench_');
$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Could not create zip archive");
}

$tempFiles = [];

foreach ($photos as $i => $photo) {
    $stream = $storage->readStream($photo['system_filename']);
    if ($stream) {
        $meta = stream_get_meta_data($stream);
        // Simulate the logic in DownloadController
        if ($meta['wrapper_type'] === 'plainfile' && isset($meta['uri']) && file_exists($meta['uri'])) {
             $zip->addFile($meta['uri'], $photo['original_filename']);
        } else {
             // Remote stream: Copy to temp file
             $tmp = tempnam(sys_get_temp_dir(), 'p_');
             $dest = fopen($tmp, 'wb');
             if (stream_copy_to_stream($stream, $dest) > 0) {
                fclose($dest);
                $zip->addFile($tmp, "photo_{$i}.jpg");
                $tempFiles[] = $tmp;
             } else {
                fclose($dest);
                @unlink($tmp);
             }
        }
        fclose($stream);
    }
}

$zip->close();

foreach ($tempFiles as $f) {
    @unlink($f);
}

$end = microtime(true);
$duration = $end - $start;
echo "Legacy Time: " . number_format($duration, 4) . "s\n";
echo "Zip Size: " . filesize($zipFile) . " bytes\n";
@unlink($zipFile);
?>
