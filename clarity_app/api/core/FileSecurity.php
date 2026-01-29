<?php
namespace Core;
use Core\Storage\StorageInterface;

class FileSecurity {
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/heic' => 'heic'
    ];
    private $storage;

    public function __construct(StorageInterface $storage) {
        $this->storage = $storage;
    }

    public function processUpload(array $file, string $projectUuid) {
        if ($file['error'] !== UPLOAD_ERR_OK) throw new \Exception("Upload error: " . $file['error']);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new \Exception("Invalid file type. Allowed: JPG, PNG, GIF, HEIC.");
        }

        // Generate Paths
        $extension = self::ALLOWED_MIMES[$mime];
        $randomName = bin2hex(random_bytes(16));
        $originalPath = "$projectUuid/$randomName.$extension";
        $thumbPath = "$projectUuid/{$randomName}_thumb.jpg";

        // Extract Metadata
        $metadata = $this->extractExif($file['tmp_name']);

        // Save Original
        $this->storage->put($file['tmp_name'], $originalPath);

        // Generate & Save Thumbnail
        $this->generateThumbnail($file['tmp_name'], 400);
        $this->storage->put($file['tmp_name'] . '_thumb', $thumbPath);

        return [
            'system_path' => $originalPath,
            'thumb_path' => $thumbPath,
            'mime' => $mime,
            'hash' => hash_file('sha256', $file['tmp_name']),
            'size' => $file['size'],
            'metadata' => json_encode($metadata)
        ];
    }

    private function generateThumbnail($filePath, $maxWidth) {
        list($width, $height) = getimagesize($filePath);
        $ratio = $width / $height;
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $ratio;

        $src = imagecreatefromstring(file_get_contents($filePath));
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($dst, $filePath . '_thumb', 60);
        imagedestroy($src);
        imagedestroy($dst);
    }

    private function extractExif($filePath) {
        try {
            $exif = @exif_read_data($filePath);
            if (!$exif) return [];
            return [
                'camera_make' => $exif['Make'] ?? 'Unknown',
                'camera_model' => $exif['Model'] ?? 'Unknown',
                'exposure' => $exif['ExposureTime'] ?? '',
                'aperture' => $exif['COMPUTED']['ApertureFNumber'] ?? '',
                'iso' => $exif['ISOSpeedRatings'] ?? '',
                'focal_length' => $exif['FocalLength'] ?? ''
            ];
        } catch (\Exception $e) { return []; }
    }
}
?>
