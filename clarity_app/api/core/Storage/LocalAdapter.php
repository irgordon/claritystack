<?php
namespace Core\Storage;

require_once __DIR__ . '/StorageInterface.php';

class LocalAdapter implements StorageInterface {
    private $rootPath;
    private $config;

    public function __construct(string $rootPath, array $config = []) {
        $this->rootPath = rtrim($rootPath, '/');
        $this->config = $config;
    }

    public function put(string $sourceFile, string $destinationPath): bool {
        $fullPath = $this->getFullPath($destinationPath);
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return copy($sourceFile, $fullPath);
    }

    public function get(string $path): ?string {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return file_get_contents($fullPath);
        }
        return null;
    }

    public function readStream(string $path) {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return fopen($fullPath, 'rb');
        }
        return null;
    }

    public function delete(string $path): bool {
        $fullPath = $this->getFullPath($path);
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }

    public function getUrl(string $path): string {
        // Local files are secure and don't have a direct public URL.
        // We return the proxy URL pattern used by the application.
        return '/api/files/view?path=' . urlencode($path);
    }

    /**
     * Outputs the file to the browser.
     * Implements StorageInterface::output using X-Sendfile/X-Accel-Redirect if configured.
     */
    public function output(string $path) {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            return;
        }

        // Optimization: Offload file serving to the web server if configured
        // (e.g., X-Accel-Redirect for Nginx, X-Sendfile for Apache)
        if (!empty($this->config['sendfile_header'])) {
            $headerName = $this->config['sendfile_header'];
            $prefix = $this->config['sendfile_prefix'] ?? '';

            // Construct the path expected by the web server alias
            $redirectPath = rtrim($prefix, '/') . '/' . ltrim($path, '/');

            header("$headerName: $redirectPath");
            return;
        }

        // Fallback: Serve via PHP (blocking I/O)
        // We don't set Content-Type here because FileController sets it based on DB metadata.
        // But we should set Content-Length.
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
    }

    private function getFullPath($path) {
        // Prevent directory traversal attacks
        // Strict check: Reject any path containing ".."
        if (strpos($path, '..') !== false) {
            throw new \Exception("Invalid path: Directory traversal detected.");
        }
        return $this->rootPath . '/' . ltrim($path, '/');
    }
}
