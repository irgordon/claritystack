<?php
require_once __DIR__ . '/../clarity_app/api/core/Security.php';

use Core\Security;

// 1. Setup Test Key
$appKey = bin2hex(random_bytes(32));

// 2. Inject Key into Security class using Reflection
$reflection = new ReflectionClass('Core\Security');
$property = $reflection->getProperty('key');
$property->setAccessible(true);
$property->setValue(null, $appKey);

echo "Test Key: " . $appKey . "\n";

// 3. Replicate Encryption Logic from InstallController
$encrypt = function($data) use ($appKey) {
    if (empty($data)) return '';

    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = random_bytes($ivLength);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $appKey, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
};

// 4. Test Cases
$secrets = [
    'sk_test_123456',
    'smtp_password_secret',
    'Hello World',
    ''
];

foreach ($secrets as $secret) {
    echo "Testing secret: '$secret' ... ";

    // Encrypt using Installer logic
    $encrypted = $encrypt($secret);

    // Decrypt using Core\Security
    $decrypted = Security::decrypt($encrypted);

    if ($decrypted === $secret) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        echo "  Expected: '$secret'\n";
        echo "  Got: '$decrypted'\n";
        exit(1);
    }
}

echo "\nVerification Successful: InstallController encryption is compatible with Core\Security.\n";
