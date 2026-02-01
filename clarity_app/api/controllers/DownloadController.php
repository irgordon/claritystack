<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/Storage/StorageFactory.php';

use Core\Storage\StorageFactory;

class DownloadController {
    private $db;
    public function __construct() { $this->db = \Database::getInstance()->connect(); }

    public function generateLink($projectId) {
        // 1. Authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 2. Fetch Project & User Context
        $stmt = $this->db->prepare("
            SELECT p.status, p.client_email, p.package_snapshot, u.role, u.email as user_email
            FROM projects p
            LEFT JOIN users u ON u.id = ?
            WHERE p.id = ?
        ");
        $stmt->execute([$userId, $projectId]);
        $project = $stmt->fetch();

        if (!$project) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }

        // 3. Authorization (Ownership)
        $isAdmin = ($project['role'] === 'admin');
        $isOwner = ($project['user_email'] === $project['client_email']);

        if (!$isAdmin && !$isOwner) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }

        // 4. Payment Verification
        // Admins can always download. Clients must pay unless price is 0.
        if (!$isAdmin) {
            $package = json_decode($project['package_snapshot'] ?? '{}', true);
            $price = (int)($package['price_cents'] ?? 0);
            $isPaid = ($project['status'] === 'paid');

            if ($price > 0 && !$isPaid) {
                http_response_code(403);
                echo json_encode(['error' => 'Payment required to download files.']);
                exit;
            }
        }

        $token = bin2hex(random_bytes(32));
        $timeout = \ConfigHelper::getTimeout();
        $expires = date('Y-m-d H:i:s', strtotime("+$timeout minutes"));

        $this->db->prepare("INSERT INTO download_tokens (project_id, token_hash, expires_at) VALUES (?, ?, ?)")
                 ->execute([$projectId, hash('sha256', $token), $expires]);

        echo json_encode(['url' => "/api/download/stream?token=$token"]);
    }

    public function streamZip() {
        // Increase limits for large zip generation
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $token = $_GET['token'] ?? '';
        $stmt = $this->db->prepare("SELECT t.id, t.expires_at, p.id as project_id, p.title FROM download_tokens t JOIN projects p ON t.project_id = p.id WHERE t.token_hash = ?");
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row || strtotime($row['expires_at']) < time()) {
            http_response_code(410);
            die("Link expired");
        }

        // Invalidate token (One-time use)
        $this->db->prepare("DELETE FROM download_tokens WHERE id = ?")->execute([$row['id']]);

        // Get Photos
        $stmtPhotos = $this->db->prepare("SELECT system_filename, original_filename FROM photos WHERE project_id = ?");
        $stmtPhotos->execute([$row['project_id']]);
        $photos = $stmtPhotos->fetchAll(PDO::FETCH_ASSOC);

        if (empty($photos)) {
            die("No photos to download");
        }

        // Initialize Storage
        $config = \ConfigHelper::getStorageConfig();
        $storage = StorageFactory::create($config);

        // Create Zip
        $zipFile = tempnam(sys_get_temp_dir(), 'zip_');
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            http_response_code(500);
            die("Could not create zip archive");
        }

        $tempFiles = [];

        foreach ($photos as $photo) {
            $stream = $storage->readStream($photo['system_filename']);
            if ($stream) {
                // Optimization: If local file stream, add directly from path to avoid copying
                $meta = stream_get_meta_data($stream);
                if ($meta['wrapper_type'] === 'plainfile' && isset($meta['uri']) && file_exists($meta['uri'])) {
                     $zip->addFile($meta['uri'], $photo['original_filename']);
                } else {
                     // Remote stream: Copy to temp file
                     $tmp = tempnam(sys_get_temp_dir(), 'p_');
                     $dest = fopen($tmp, 'wb');
                     if (stream_copy_to_stream($stream, $dest) > 0) {
                        fclose($dest);
                        $zip->addFile($tmp, $photo['original_filename']);
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
        
        // Cleanup temp files (ZipArchive has read them by now)
        foreach ($tempFiles as $f) {
            @unlink($f);
        }

        if (file_exists($zipFile)) {
            // Clear output buffers
            if (ob_get_level()) ob_end_clean();

            // Sanitize filename to prevent header injection
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['title'] ?: 'download');

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $safeTitle . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zipFile);
            unlink($zipFile);
        } else {
            http_response_code(500);
            die("Error generating zip");
        }
    }
}
?>
