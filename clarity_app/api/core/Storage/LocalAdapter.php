<?php
namespace Core\Storage;

require_once __DIR__ . '/StorageInterface.php';

class LocalAdapter implements StorageInterface {
    private $rootPath;

    public function __construct(string $rootPath) {
        $this->rootPath = rtrim($rootPath, '/');
        // Ensure directory exists
        if (!is_dir($this->rootPath)) {
            // Check if we can create it (might fail if parent doesn't exist or permissions)
            @mkdir($this->rootPath, 0755, true);
        }
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
        // Assuming the ID is not available here, but the path is.
        // FileController uses ID to look up path.
        // If we need a URL that works, we might need to rethink this or return null.
        // For now returning a placeholder that indicates it needs proxying.
        return '/api/files/view?path=' . urlencode($path);
    }

    /**
     * Outputs the file to the browser.
     * Used by FileController for secure streaming.
     */
    public function output(string $path) {
        $fullPath = $this->getFullPath($path);
        if (!file_exists($fullPath)) {
            // Let the controller handle the 404 response code if needed,
            // but here we just return or throw.
            // FileController checks database first, so if we are here, file SHOULD exist.
            return;
        }

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
