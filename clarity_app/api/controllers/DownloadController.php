<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';

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
        $token = $_GET['token'] ?? '';
        $stmt = $this->db->prepare("SELECT t.id, t.expires_at, p.storage_path, p.title FROM download_tokens t JOIN projects p ON t.project_id = p.id WHERE t.token_hash = ?");
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row || strtotime($row['expires_at']) < time()) die("Link expired");

        $this->db->prepare("DELETE FROM download_tokens WHERE id = ?")->execute([$row['id']]);

        // Streaming Logic
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.$row['title'].'.zip"');
        
        // In real app: Open ZipArchive, add files from storage_path, stream to output
        // For security review demo, we stop here.
        echo "PK..."; 
    }
}
?>
