<?php
/**
 * ClarityStack Environment Configuration
 * Only contains Database Credentials and the Master Encryption Key.
 * All other settings (Storage, Mail, Stripe) are stored encrypted in the DB.
 */

return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'claritystack',
    'DB_USER' => 'clarity_user',
    'DB_PASS' => 'your_secure_password_here',

    // Master Key: Used to encrypt/decrypt the secrets stored in the DB.
    // If you lose this, you lose access to Stripe/S3 keys stored in the DB.
    'APP_KEY' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90',
    
    'DEBUG'   => false
];
