<?php
namespace Core;

require_once __DIR__ . '/Storage/StorageFactory.php';
use Core\Storage\StorageFactory;

class FileSecurity {
    /**
     * Allowed MIME types and their corresponding file extensions.
     * We strictly check Magic Bytes against this list.
     */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/heic' => 'heic' // Requires Imagick extension usually
    ];

    private $storage;

    /**
     * Constructor loads the configuration and initializes the correct Storage Adapter.
     */
    public function __construct() {
        $configPath = __DIR__ . '/../config/env.php';
        
        if (!file_exists($configPath)) {
            throw new \Exception("Configuration file missing.");
        }

        $config = require $configPath;
        
        // Use the Factory to get the configured storage driver (Local, S3, Cloudinary, etc.)
        $this->storage = StorageFactory::create($config);
    }

    /**
     * Main entry point for handling a file upload.
     * * @param array $file The $_FILES['input_name'] array
     * @param string $projectUuid The UUID of the project this file belongs to
     * @return array Metadata about the saved file (path, hash, size, exif)
     * @throws \Exception If validation or upload fails
     */
    public function processUpload(array $file, string $projectUuid) {
        // 1. Check PHP Upload Errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception("Upload error code: " . $file['error']);
        }

        // 2. Security: Verify MIME Type via Magic Bytes
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new \Exception("Invalid file type ($mime). Allowed: JPG, PNG, GIF, WEBP, HEIC.");
        }

        // 3. Generate Secure Paths
        // We discard the original filename to prevent directory traversal attacks
        $extension = self::ALLOWED_MIMES[$mime];
        $randomName = bin2hex(random_bytes(16)); // 32-character random string
        
        // The relative path stored in the database
        $originalPath = "$projectUuid/$randomName.$extension";
        $thumbPath = "$projectUuid/{$randomName}_thumb.jpg";

        // 4. Extract Metadata (EXIF) before processing
        // We do this on the temp file before moving it
        $metadata = $this->extractExif($file['tmp_name']);

        // 5. Save Original File to Storage
        // The adapter handles whether this goes to Local disk, S3, or Cloudinary
        $success = $this->storage->put($file['tmp_name'], $originalPath);
        
        if (!$success) {
            throw new \Exception("Failed to write file to storage provider.");
        }

        // 6. Generate & Save Thumbnail
        // We create a temporary thumbnail locally, upload it, then delete the temp
        $tempThumb = $file['tmp_name'] . '_thumb.jpg';
        $this->generateThumbnail($file['tmp_name'], $tempThumb, 400);
        
        $this->storage->put($tempThumb, $thumbPath);
        
        // Cleanup temp thumbnail
        if (file_exists($tempThumb)) {
            unlink($tempThumb);
        }

        // 7. Return Data for Database Insertion
        return [
            'system_path' => $originalPath,
            'thumb_path' => $thumbPath,
            'mime' => $mime,
            'hash' => hash_file('sha256', $file['tmp_name']),
            'size' => $file['size'],
            'metadata' => json_encode($metadata)
        ];
    }

    /**
     * Generates a resized JPEG thumbnail from the source image.
     * * @param string $sourcePath Path to the uploaded temp file
     * @param string $destPath Path to write the temp thumbnail
     * @param int $maxWidth Maximum width in pixels
     */
    private function generateThumbnail($sourcePath, $destPath, $maxWidth) {
        list($width, $height, $type) = getimagesize($sourcePath);
        
        // Calculate new dimensions preserving aspect ratio
        $ratio = $width / $height;
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $ratio;

        // Create resource from source
        switch ($type) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG:  $src = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_GIF:  $src = imagecreatefromgif($sourcePath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($sourcePath); break;
            default: return; // Skip thumbnail if format not supported by GD
        }

        if (!$src) return;

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/WEBP
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        // Resample
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save as JPEG (Quality 60) for thumbnails to keep lists fast
        // We convert everything to JPG for thumbs to ensure consistency
        // Note: Transparent backgrounds will turn black in JPG. 
        // If transparency is critical for thumbs, change this to imagepng.
        imagejpeg($dst, $destPath, 60);

        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Extracts EXIF data safely using PHP's built-in function.
     * Suppresses warnings for files without EXIF headers.
     * * @param string $filePath
     * @return array
     */
    private function extractExif($filePath) {
        try {
            // Silence warning if file is not JPEG or has no EXIF
            $exif = @exif_read_data($filePath);
            
            if (!$exif) return [];

            return [
                'camera_make'  => $exif['Make'] ?? 'Unknown',
                'camera_model' => $exif['Model'] ?? 'Unknown',
                'exposure'     => $exif['ExposureTime'] ?? '',
                'aperture'     => $exif['COMPUTED']['ApertureFNumber'] ?? '',
                'iso'          => $exif['ISOSpeedRatings'] ?? '',
                'focal_length' => $exif['FocalLength'] ?? '',
                'date_taken'   => $exif['DateTimeOriginal'] ?? ''
            ];
        } catch (\Exception $e) {
            // EXIF extraction is non-critical; return empty array on failure
            return [];
        }
    }
}
?>
