<?php
namespace Core;

class Security {
    private static $key = null;
    private static $sodiumKey = null;

    private static function getKey() {
        if (self::$key === null) {
            $config = require __DIR__ . '/../config/env.php';
            self::$key = $config['APP_KEY'];
        }
        return self::$key;
    }

    public static function encrypt($data) {
        // Performance Optimization: Use Sodium (XSalsa20-Poly1305) if available
        // It is significantly faster than OpenSSL AES-256-CBC
        if (extension_loaded('sodium')) {
            if (self::$sodiumKey === null) {
                self::$sodiumKey = hash('sha256', self::getKey(), true);
            }

            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($data, $nonce, self::$sodiumKey);
            return 'v2:' . base64_encode($nonce . $ciphertext);
        }

        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = random_bytes($ivLength);
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    public static function encryptBatch($items) {
        $results = [];
        $count = count($items);
        if ($count === 0) return $results;

        // Performance Optimization: Batch random_bytes generation to reduce syscalls
        if (extension_loaded('sodium')) {
            if (self::$sodiumKey === null) {
                self::$sodiumKey = hash('sha256', self::getKey(), true);
            }

            $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $allNonces = random_bytes($nonceSize * $count);

            foreach ($items as $i => $data) {
                $nonce = substr($allNonces, $i * $nonceSize, $nonceSize);
                $ciphertext = sodium_crypto_secretbox($data, $nonce, self::$sodiumKey);
                $results[] = 'v2:' . base64_encode($nonce . $ciphertext);
            }
        } else {
            $key = self::getKey();
            $ivLength = openssl_cipher_iv_length('aes-256-cbc');
            $allIVs = random_bytes($ivLength * $count);

            foreach ($items as $i => $data) {
                $iv = substr($allIVs, $i * $ivLength, $ivLength);
                $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
                $results[] = base64_encode($encrypted . '::' . $iv);
            }
        }

        return $results;
    }

    public static function decrypt($data) {
        $key = self::getKey();
        if (!$data) return '';

        // Check for v2 (Sodium) tokens
        if (strpos($data, 'v2:') === 0) {
            if (!extension_loaded('sodium')) {
                // If sodium was disabled after creating tokens, we can't decrypt
                error_log("Security::decrypt - Sodium extension missing for v2 token");
                return '';
            }

            $encoded = substr($data, 3);
            $decoded = base64_decode($encoded);

            if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return '';
            }

            $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

            if (self::$sodiumKey === null) {
                self::$sodiumKey = hash('sha256', $key, true);
            }

            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, self::$sodiumKey);
            return $plaintext !== false ? $plaintext : '';
        }

        // Legacy Fallback (OpenSSL)
        $decoded = base64_decode($data);
        if (strpos($decoded, '::') === false) {
            return '';
        }

        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
}
