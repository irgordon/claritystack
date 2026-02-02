<?php
namespace Core;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Storage/StorageFactory.php';

use Core\Storage\StorageFactory;
use PDO;

class FileSecurity {
    /**
     * Allowed MIME types map.
     * We strictly check Magic Bytes against this list.
     */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/heic' => 'heic'
    ];

    /**
     * The Active Storage Adapter (Local, S3, Cloudinary, etc.)
     */
    public $storage; // Made public for worker access if needed, or use accessor

    /**
     * Database Connection
     */
    private $db;

    /**
     * Constructor
     * Loads settings from DB -> Decrypts Keys -> Initializes Adapter
     */
    public function __construct() {
        // 1. Connect to Database
        $this->db = \Database::getInstance()->connect();

        // 2. Fetch Encrypted Settings
        $stmt = $this->db->query("SELECT public_config, private_config FROM settings LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Prepare Config Array
        $config = [];

        if (!$row) {
            // Fallback: If DB is empty (fresh install), default to Local Storage
            $config = [
                'STORAGE_DRIVER' => 'local',
                'STORAGE_PATH'   => __DIR__ . '/../../../storage_secure'
            ];
        } else {
            // Decode JSON Columns
            $public  = json_decode($row['public_config'] ?? '{}', true);
            $private = json_decode($row['private_config'] ?? '{}', true);

            // 4. Decrypt & Map Settings
            $config = [
                'STORAGE_DRIVER' => $public['storage_driver'] ?? 'local',
                'STORAGE_PATH'   => __DIR__ . '/../../../storage_secure',
                
                // Cloudinary Config
                'CLOUDINARY_NAME'   => $public['cloudinary_name'] ?? '',
                'CLOUDINARY_KEY'    => $public['cloudinary_key'] ?? '',
                'CLOUDINARY_SECRET' => Security::decrypt($private['cloudinary_secret'] ?? ''),

                // AWS S3 / Spaces Config
                'S3_KEY'      => $public['s3_key'] ?? '',
                'S3_SECRET'   => Security::decrypt($private['s3_secret'] ?? ''),
                'S3_BUCKET'   => $public['s3_bucket'] ?? '',
                'S3_REGION'   => $public['s3_region'] ?? 'us-east-1',
                'S3_ENDPOINT' => $public['s3_endpoint'] ?? '',

                // ImageKit Config
                'IMAGEKIT_PUBLIC'   => $public['imagekit_public'] ?? '',
                'IMAGEKIT_PRIVATE'  => Security::decrypt($private['imagekit_private'] ?? ''),
                'IMAGEKIT_URL'      => $public['imagekit_url'] ?? '',
                
                // Google Drive Config
                // Note: Drive often requires a JSON file path, not just a string key.
                // We assume the service-account.json is uploaded separately or handled via a path in DB.
                'DRIVE_ROOT_FOLDER' => $public['drive_root_folder'] ?? ''
            ];
        }

        // 5. Initialize the Adapter Factory
        $this->storage = StorageFactory::create($config);
    }

    /**
     * Main Upload Processor
     */
    public function processUpload(array $file, string $projectUuid) {
        // 1. Check Upload Errors
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
        $extension = self::ALLOWED_MIMES[$mime];
        $randomName = bin2hex(random_bytes(16)); // 32 chars
        
        $originalPath = "$projectUuid/$randomName.$extension";
        $thumbPath = "$projectUuid/{$randomName}_thumb.jpg";

        // 4. Extract EXIF (Metadata)
        $metadata = $this->extractExif($file['tmp_name']);

        // 5. Save Original to Cloud/Disk
        $success = $this->storage->put($file['tmp_name'], $originalPath);
        
        if (!$success) {
            throw new \Exception("Failed to write file to storage provider.");
        }

        // 6. Queue Thumbnail Generation (Async)
        $this->queueThumbnail($originalPath, $thumbPath, 400, $file['tmp_name']);

        // 7. Return Data
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
     * Queue Thumbnail Job
     */
    private function queueThumbnail($originalPath, $thumbPath, $width, $localSourcePath = null) {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("INSERT INTO image_queue (original_path, thumb_path, width, status, created_at, updated_at) VALUES (?, ?, ?, 'pending', ?, ?)");
            $stmt->execute([$originalPath, $thumbPath, $width, $now, $now]);
        } catch (\Exception $e) {
            // Fallback: If queue fails, generate synchronously to ensure system reliability
            error_log("Queue failed: " . $e->getMessage() . ". Falling back to sync generation.");

            if ($localSourcePath && file_exists($localSourcePath)) {
                $tempThumb = $localSourcePath . '_thumb_fallback.jpg';
                try {
                    $this->generateThumbnail($localSourcePath, $tempThumb, $width);
                    $this->storage->put($tempThumb, $thumbPath);
                } catch (\Exception $ex) {
                    error_log("Fallback generation failed: " . $ex->getMessage());
                }
                if (file_exists($tempThumb)) unlink($tempThumb);
            }
        }
    }

    /**
     * Process a Queue Job (Called by Worker)
     */
    public function processQueueJob($originalPath, $thumbPath, $width) {
        // 1. Fetch content from storage
        // Note: For large files, stream copy is better, but get() returns string in LocalAdapter.
        $content = $this->storage->get($originalPath);
        if ($content === null) {
            return false; // Source not found
        }

        // 2. Save to temp file
        $tempSource = sys_get_temp_dir() . '/thumb_src_' . bin2hex(random_bytes(8));
        file_put_contents($tempSource, $content);

        // 3. Generate Thumbnail
        $tempDest = $tempSource . '_thumb.jpg';
        try {
            $this->generateThumbnail($tempSource, $tempDest, $width);

            // 4. Upload Thumbnail
            if (file_exists($tempDest)) {
                $this->storage->put($tempDest, $thumbPath);
                unlink($tempDest);
                unlink($tempSource);
                return true;
            }
        } catch (\Exception $e) {
            // Cleanup on error
            if (file_exists($tempDest)) unlink($tempDest);
            if (file_exists($tempSource)) unlink($tempSource);
            throw $e;
        }

        if (file_exists($tempSource)) unlink($tempSource);
        return false;
    }

    /**
     * Thumbnail Generator (GD Library)
     */
    public function generateThumbnail($sourcePath, $destPath, $maxWidth) {
        list($width, $height, $type) = getimagesize($sourcePath);
        
        $ratio = $width / $height;
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $ratio;

        switch ($type) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($sourcePath); break;
            case IMAGETYPE_PNG:  $src = imagecreatefrompng($sourcePath); break;
            case IMAGETYPE_GIF:  $src = imagecreatefromgif($sourcePath); break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($sourcePath); break;
            default: return;
        }

        if (!$src) return;

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
            imagecolortransparent($dst, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($dst, $destPath, 60);

        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * EXIF Extractor
     */
    private function extractExif($filePath) {
        try {
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
            return [];
        }
    }
}
?>
