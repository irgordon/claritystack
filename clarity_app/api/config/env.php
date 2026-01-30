<?php
/**
 * ClarityStack Environment Configuration
 * * SECURITY WARNING: 
 * This file contains the Master Database Credentials and the Application Encryption Key.
 * It does NOT contain storage or API keys (S3, Stripe, Mail), which are stored encrypted in the DB.
 * * Permissions: 600 (Read/Write by Owner only).
 */

return [
    // -------------------------------------------------------------------------
    // DATABASE CONNECTION (PostgreSQL)
    // -------------------------------------------------------------------------
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'claritystack',
    'DB_USER' => 'clarity_user',
    'DB_PASS' => 'your_secure_password_here',

    // -------------------------------------------------------------------------
    // MASTER ENCRYPTION KEY
    // -------------------------------------------------------------------------
    // Used by Core\Security to encrypt/decrypt the secrets stored in the 'settings' table.
    // WARNING: If you lose this key, you lose access to all encrypted API keys in the DB.
    'APP_KEY' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90',

    // -------------------------------------------------------------------------
    // DEBUG MODE
    // -------------------------------------------------------------------------
    'DEBUG'   => false
];
?>
