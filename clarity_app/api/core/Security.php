<?php
namespace Core;

class Security {
    // In production, load this from .env
    // key example 'b1a2c3d4e5f60718293a4b5c6d7e8f90123456789abcdef0fedcba9876543210' do not use this one obviously
    //
    private static $key = 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_IN_PROD'; 

    public static function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', self::$key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function decrypt($data) {
        if (!$data) return '';
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', self::$key, 0, $iv);
    }
}
?>
