<?php
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ConfigHelper.php';
require_once __DIR__ . '/../core/EmailService.php';
require_once __DIR__ . '/../core/Csrf.php';
require_once __DIR__ . '/../core/RateLimiter.php';

use Core\Csrf;
use Core\RateLimiter;

class AuthController {
    private $db;

    public function __construct() {
        $this->db = \Database::getInstance()->connect();
    }

    /**
     * Request a Magic Link (Login Step 1)
     * POST /api/auth/magic-link
     */
    public function requestLink() {
        // 1. SECURITY: Rate Limiting
        // Allow 5 attempts per 60 seconds per IP to prevent Email Flooding
        if (!RateLimiter::check($_SERVER['REMOTE_ADDR'], 5, 60)) {
            http_response_code(429);
            die(json_encode(['error' => 'Too many requests. Please try again later.']));
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address']);
            exit;
        }
        
        // 2. Lookup User
        $userStmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->execute([$email]);
        $uid = $userStmt->fetchColumn();

        if ($uid) {
            // 3. Generate Secure Tokens
            $selector = bin2hex(random_bytes(12));  // Public lookup key
            $token    = bin2hex(random_bytes(32));  // Private validation key
            
            // Expiration (Default 10 mins)
            $timeout = \ConfigHelper::getTimeout();
            $expires = date('Y-m-d H:i:s', strtotime("+$timeout minutes"));

            // Store Hash
            $this->db->prepare("INSERT INTO auth_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)")
                     ->execute([$uid, $selector, hash('sha256', $token), $expires]);

            // Send Email
            $link = "https://" . $_SERVER['HTTP_HOST'] . "/verify?selector=$selector&token=$token";
            EmailService::send($email, 'auth_magic_link', ['link' => $link]);
        }
        
        // Always return success to prevent Email Enumeration
        echo json_encode(['status' => 'sent', 'message' => 'If this account exists, a link has been sent.']);
    }

    /**
     * Verify Magic Link (Login Step 2)
     * POST /api/auth/verify
     */
    public function verifyLink() {
        // 1. SECURITY: Rate Limiting (Stricter)
        // Prevent brute forcing the token (though cryptographically unlikely)
        if (!RateLimiter::check($_SERVER['REMOTE_ADDR'], 10, 60)) {
            http_response_code(429);
            die(json_encode(['error' => 'Too many requests. Please try again later.']));
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $selector = $input['selector'] ?? '';
        $token    = $input['token'] ?? '';

        // 2. Validate Token
        $stmt = $this->db->prepare("SELECT id, user_id, token_hash FROM auth_tokens WHERE selector = ? AND expires_at > NOW()");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if ($row && hash_equals($row['token_hash'], hash('sha256', $token))) {
            // 3. Login Successful: Start Session
            session_start();
            session_regenerate_id(true); // Prevent Session Fixation
            
            $_SESSION['user_id'] = $row['user_id'];
            
            // Invalidate used token
            $this->db->prepare("DELETE FROM auth_tokens WHERE id = ?")->execute([$row['id']]);

            // 4. SECURITY: Generate CSRF Token for this session
            $csrfToken = Csrf::getToken();

            echo json_encode([
                'status' => 'success',
                'csrf_token' => $csrfToken // React must store this for future POSTs
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired link']);
        }
    }

}
?>
