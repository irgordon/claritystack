<?php
namespace Core;

class Security {
    private static $key = null;

    private static function getKey() {
        if (self::$key === null) {
            $config = require __DIR__ . '/../config/env.php';
            self::$key = $config['APP_KEY'];
        }
        return self::$key;
    }

    public static function encrypt($data) {
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function decrypt($data) {
        $key = self::getKey();
        if (!$data) return '';

        $decoded = base64_decode($data);
        if (strpos($decoded, '::') === false) {
            return '';
        }

        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
}
