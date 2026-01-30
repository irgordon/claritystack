<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';

class DownloadController {
    private $db;
    public function __construct() { $this->db = \Database::getInstance()->connect(); }

    public function generateLink($projectId) {
        // [Add Permission Check Here: Ensure user paid]
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
