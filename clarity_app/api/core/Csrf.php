<?php
namespace Core;

class Csrf {
    /**
     * Generates or retrieves the current CSRF token.
     * Must be called after session_start().
     */
    public static function getToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            // Generate a cryptographically secure 32-byte token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verifies the incoming request header against the session token.
     * Should be called at the start of any POST/PUT/DELETE controller method.
     */
    public static function verify() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            // If no token exists in session, the session might have expired.
            http_response_code(419); // Page Expired
            die(json_encode(['error' => 'Session expired. Please refresh.']));
        }

        // Check standard headers for the token
        $headers = getallheaders();
        $token = $headers['X-CSRF-TOKEN'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403); // Forbidden
            die(json_encode(['error' => 'CSRF token mismatch. Security check failed.']));
        }
    }
}
?>
