<?php
/**
 * ClarityStack Environment Configuration
 * * SECURITY WARNING: 
 * This file contains sensitive credentials. 
 * Ensure file permissions are set to 600 or 640 (Read/Write by Owner only).
 * It should NEVER be accessible via the web browser.
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
    // APPLICATION SECURITY
    // -------------------------------------------------------------------------
    // Used by Core\Security to encrypt Stripe Keys and SMTP passwords in the database.
    // Generate a new one with: bin2hex(random_bytes(32))
    'APP_KEY' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90',
    
    // Set to true only during development to see verbose errors
    'DEBUG'   => false,

    // -------------------------------------------------------------------------
    // STORAGE CONFIGURATION
    // -------------------------------------------------------------------------
    // Options: 'local', 's3', 'cloudinary', 'imagekit', 'drive'
    'STORAGE_DRIVER' => 'local',

    // Option 1: LOCAL STORAGE
    // The absolute path on the server where files are stored (outside webroot)
    'STORAGE_PATH' => __DIR__ . '/../../../../storage_secure',

    // Option 2: AWS S3 / DIGITAL OCEAN SPACES / WASABI
    'S3_KEY'      => '',
    'S3_SECRET'   => '',
    'S3_REGION'   => 'us-east-1',
    'S3_BUCKET'   => 'clarity-bucket',
    'S3_ENDPOINT' => '', // Leave empty for AWS, fill for MinIO/DigitalOcean

    // Option 3: CLOUDINARY
    'CLOUDINARY_NAME'   => '',
    'CLOUDINARY_KEY'    => '',
    'CLOUDINARY_SECRET' => '',

    // Option 4: IMAGEKIT
    'IMAGEKIT_PUBLIC'   => '',
    'IMAGEKIT_PRIVATE'  => '',
    'IMAGEKIT_URL'      => 'https://ik.imagekit.io/your_id',

    // Option 5: GOOGLE DRIVE
    // Path to the .json credentials file downloaded from Google Cloud Console
    'DRIVE_CREDENTIALS' => __DIR__ . '/service-account.json',
    'DRIVE_ROOT_FOLDER' => '1A2B3C4D5E6F7G8H9I0J', // ID of the folder to store files in
];
?>
