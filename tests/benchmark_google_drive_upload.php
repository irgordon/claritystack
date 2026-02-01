<?php
namespace Google {
    class Client {
        public function setAuthConfig($config) {}
        public function addScope($scope) {}
    }
}

namespace Google\Service {
    class Drive {
        const DRIVE = 'drive';
        public $files;
        public function __construct($client) {
            $this->files = new \Google\Service\Drive\Resource\Files();
        }
    }
}

namespace Google\Service\Drive {
    class DriveFile {
        public function __construct($metadata = []) {}
    }
}

namespace Google\Service\Drive\Resource {
    class Files {
        public function create($metadata, $params = []) {
            $data = $params['data'];
            if (is_string($data)) {
                echo "Received data as STRING. Length: " . strlen($data) . "\n";
            } elseif (is_resource($data)) {
                echo "Received data as RESOURCE.\n";
            } else {
                echo "Received data as UNKNOWN type: " . gettype($data) . "\n";
            }

            // Simulate upload delay/processing if needed, but for memory we just want to see the spike
            return new \Google\Service\Drive\DriveFile();
        }

        public function listFiles($params) {
            return new FileList();
        }

        public function get($fileId, $params) {
             // Mock response
             return new class {
                 public function getBody() {
                     return new class {
                         public function getContents() { return "content"; }
                     };
                 }
             };
        }
        public function delete($fileId) {}
    }

    class FileList {
        public function getFiles() { return []; }
    }
}

namespace {
    // Load the adapter
    require_once __DIR__ . '/../clarity_app/api/core/Storage/StorageInterface.php';
    require_once __DIR__ . '/../clarity_app/api/core/Storage/GoogleDriveAdapter.php';

    use Core\Storage\GoogleDriveAdapter;

    // Create a large file (20MB)
    $tempFile = sys_get_temp_dir() . '/test_large_file.bin';
    $size = 20 * 1024 * 1024;
    $fp = fopen($tempFile, 'w');
    // Write in chunks to avoid memory spike during creation
    for ($i = 0; $i < $size; $i += 1024 * 1024) {
        fwrite($fp, str_repeat('A', 1024 * 1024));
    }
    fclose($fp);

    echo "Created 20MB test file at $tempFile\n";

    $config = [
        'service_account_json_path' => 'mock_path.json',
        'root_folder_id' => 'mock_folder_id'
    ];

    $adapter = new GoogleDriveAdapter($config);

    echo "Starting memory: " . memory_get_usage() . " bytes\n";
    $startPeak = memory_get_peak_usage();

    $adapter->put($tempFile, 'destination.bin');

    $endPeak = memory_get_peak_usage();
    echo "Peak memory usage: " . $endPeak . " bytes\n";
    echo "Memory increase (Peak - Start Peak): " . ($endPeak - $startPeak) . " bytes\n";

    unlink($tempFile);
}
