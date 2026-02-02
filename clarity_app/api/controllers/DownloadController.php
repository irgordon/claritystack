<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/Storage/StorageFactory.php';
require_once __DIR__ . '/../core/ZipStreamer.php';

use Core\Storage\StorageFactory;
use Core\ZipStreamer;

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

        // Sanitize filename to prevent header injection
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $row['title'] ?: 'download');

        // Clear output buffers
        if (ob_get_level()) ob_end_clean();

        // Send Headers
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $safeTitle . '.zip"');
        header('Pragma: no-cache');
        header('Expires: 0');
        // We do not send Content-Length for streaming response

        // Start Streaming
        $zip = new ZipStreamer();

        foreach ($photos as $photo) {
            $stream = $storage->readStream($photo['system_filename']);
            if ($stream) {
                // ZipStreamer handles streaming directly from source to output
                $zip->addFileFromStream($photo['original_filename'], $stream);
                fclose($stream);
            }
        }

        $zip->finish();
    }
}
?>
