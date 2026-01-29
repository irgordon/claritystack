<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/EmailService.php';

class AuthController {
    private $db;
    public function __construct() { $this->db = (new \Database())->connect(); }

    public function requestLink() {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        
        $user = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $user->execute([$email]);
        $uid = $user->fetchColumn();

        if ($uid) {
            $selector = bin2hex(random_bytes(12));
            $token = bin2hex(random_bytes(32));
            $timeout = \ConfigHelper::getTimeout();
            $expires = date('Y-m-d H:i:s', strtotime("+$timeout minutes"));

            $this->db->prepare("INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)")
                     ->execute([$uid, $selector, hash('sha256', $token), $expires]);

            $link = "https://" . $_SERVER['HTTP_HOST'] . "/verify?selector=$selector&token=$token";
            EmailService::send($email, 'auth_magic_link', ['link' => $link]);
        }
        echo json_encode(['status' => 'sent']);
    }

    public function verifyLink() {
        $input = json_decode(file_get_contents('php://input'), true);
        $stmt = $this->db->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$input['selector']]);
        $row = $stmt->fetch();

        if ($row && hash_equals($row['token_hash'], hash('sha256', $input['token']))) {
            $this->db->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);
            session_start();
            $_SESSION['user_id'] = $row['user_id'];
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(401); echo json_encode(['error' => 'Invalid link']);
        }
    }
}
