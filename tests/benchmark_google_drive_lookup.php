<?php
// Mock Google Client Classes for Benchmarking

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
        public $id;
        public function __construct($metadata = []) {
            $this->id = $metadata['id'] ?? uniqid();
        }
        public function getId() {
            return $this->id;
        }
    }
    class FileList {
        private $files;
        public function __construct($files) {
            $this->files = $files;
        }
        public function getFiles() { return $this->files; }
    }
}

namespace Google\Service\Drive\Resource {
    class Files {
        public function create($metadata, $params = []) {
            return new \Google\Service\Drive\DriveFile();
        }

        public function listFiles($params) {
            // Simulate network latency (e.g., 200ms)
            usleep(200000);

            // Return a mock file found
            $mockFile = new \Google\Service\Drive\DriveFile(['id' => 'mock_file_id_123']);
            return new \Google\Service\Drive\FileList([$mockFile]);
        }

        public function get($fileId, $params) {
             // Mock response
             return new class {
                 public function getBody() {
                     return new class {
                         public function getContents() { return "content"; }
                         public function detach() { return fopen('php://memory', 'r+'); }
                     };
                 }
             };
        }
        public function delete($fileId) {}
    }
}

namespace {
    // Load the adapter and cache service
    require_once __DIR__ . '/../clarity_app/api/core/Storage/StorageInterface.php';
    require_once __DIR__ . '/../clarity_app/api/core/Storage/GoogleDriveAdapter.php';
    require_once __DIR__ . '/../clarity_app/api/core/CacheService.php';

    use Core\Storage\GoogleDriveAdapter;
    use Core\CacheService;

    // Clear cache before starting
    CacheService::flush();

    $config = [
        'service_account_json_path' => 'mock_path.json',
        'root_folder_id' => 'mock_folder_id'
    ];

    $adapter = new GoogleDriveAdapter($config);

    $iterations = 5;
    $filename = "test_image.jpg";

    echo "Running Google Drive Lookup Benchmark ($iterations iterations)...\n";

    $startTime = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        // We call get(), which internally calls findFileIdByName()
        $content = $adapter->get($filename);
    }

    $endTime = microtime(true);
    $totalTime = $endTime - $startTime;
    $avgTime = $totalTime / $iterations;

    echo "Total Time: " . number_format($totalTime, 4) . " s\n";
    echo "Average Time per Lookup: " . number_format($avgTime, 4) . " s\n";
}
