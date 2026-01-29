<?php
/**
 * ClarityStack Environment Configuration
 * * SECURITY WARNING: 
 * This file contains sensitive credentials. 
 * Ensure file permissions are set to 600 or 640 (Read/Write by Owner only).
 * It should NEVER be accessible via the web browser.
 */

return [
    // Database Connection
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'claritystack',
    'DB_USER' => 'clarity_user',
    'DB_PASS' => 'your_secure_password_here',

    // Application Encryption Key
    // Used by Core\Security to encrypt Stripe Keys and SMTP passwords in the database.
    // Generate a new one with: bin2hex(random_bytes(32))
    'APP_KEY' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90',

    // Debug Mode (Set to false in Production)
    'DEBUG'   => false
];
