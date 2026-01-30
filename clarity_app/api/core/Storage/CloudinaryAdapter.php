<?php
namespace Core\Storage;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class CloudinaryAdapter implements StorageInterface {
    private $cloudinary;

    public function __construct(array $config) {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $config['cloud_name'],
                'api_key'    => $config['api_key'],
                'api_secret' => $config['api_secret'],
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    public function put(string $sourceFile, string $destinationPath): bool {
        try {
            // public_id needs to be the path without extension for Cloudinary to manage formats
            $publicId = pathinfo($destinationPath, PATHINFO_FILENAME);
            $folder = dirname($destinationPath);

            $this->cloudinary->uploadApi()->upload($sourceFile, [
                'public_id' => $publicId,
                'folder'    => $folder,
                'resource_type' => 'auto',
                // 'type' => 'authenticated' // UNCOMMENT for strict private storage
            ]);
            return true;
        } catch (\Exception $e) {
            error_log("Cloudinary Upload Error: " . $e->getMessage());
            return false;
        }
    }

    public function get(string $path): ?string {
        // For Cloudinary, we usually download from the URL
        $url = $this->getUrl($path);
        return file_get_contents($url);
    }

    public function delete(string $path): bool {
        try {
            // Remove extension for public_id
            $publicId = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME);
            $this->cloudinary->uploadApi()->destroy($publicId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getUrl(string $path): string {
        // Strip extension, Cloudinary adds it automatically based on format
        $publicId = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME);
        return $this->cloudinary->image($publicId)->toUrl();
    }
}
