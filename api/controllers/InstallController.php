<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Security.php';

class InstallController {
    private $db;
    public function __construct() { $this->db = (new \Database())->connect(); }

    public function install() {
        $check = $this->db->query("SELECT count(*) FROM information_schema.tables WHERE table_name = 'users'")->fetchColumn();
        if ($check > 0) {
            if ($this->db->query("SELECT count(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0) {
                die(json_encode(['error' => 'Already installed']));
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        // Configuration
        $pub = json_encode([
            'logo_url' => $input['logo_url'] ?? '',
            'primary_color' => $input['primary_color'] ?? '#3b82f6',
            'no_reply_email' => $input['no_reply_email'] ?? 'no-reply@localhost',
            'link_timeout' => (int)($input['link_timeout'] ?? 10)
        ]);
        $priv = json_encode(['stripe_secret' => \Core\Security::encrypt($input['stripe_secret_key'] ?? '')]);

        try {
            $this->db->beginTransaction();
            // In a real app, read schema.sql file content here. Assuming tables exist for brevity.
            
            $sql = "INSERT INTO settings (business_name, public_config, private_config, is_installed) VALUES (?, ?, ?, TRUE)";
            $this->db->prepare($sql)->execute([$input['business_name'], $pub, $priv]);

            $sqlAdmin = "INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'admin')";
            $this->db->prepare($sqlAdmin)->execute([
                $input['admin_email'], 
                password_hash($input['admin_password'], PASSWORD_ARGON2ID)
            ]);

            $this->db->commit();
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
