<?php
namespace Core\Storage;

use ImageKit\ImageKit;

class ImagekitAdapter implements StorageInterface {
    private $imageKit;

    public function __construct(array $config) {
        $this->imageKit = new ImageKit(
            $config['public_key'],
            $config['private_key'],
            $config['url_endpoint']
        );
    }

    public function put(string $sourceFile, string $destinationPath): bool {
        $fileName = basename($destinationPath);
        $folder = dirname($destinationPath); // ImageKit expects /folder/name

        $upload = $this->imageKit->upload([
            'file' => fopen($sourceFile, 'r'),
            'fileName' => $fileName,
            'folder' => $folder,
            'useUniqueFileName' => false 
        ]);

        return empty($upload->error);
    }

    public function get(string $path): ?string {
        return file_get_contents($this->getUrl($path));
    }

    public function readStream(string $path) {
        $url = $this->getUrl($path);
        return @fopen($url, 'rb');
    }

    public function delete(string $path): bool {
        // ImageKit requires File ID for deletion, not path.
        // This is a limitation. We would need to search for the file ID first.
        // For simplicity in this example, we return false or implement a search.
        return false; 
    }

    public function getUrl(string $path): string {
        return $this->imageKit->url([
            'path' => $path,
        ]);
    }

    public function output(string $path) {
        $url = $this->getUrl($path);
        header("Location: " . $url);
        exit;
    }
}
