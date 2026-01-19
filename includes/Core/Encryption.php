<?php
/**
 * Encryption utilities for secure storage.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Core;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption class for securing sensitive data.
 */
class Encryption
{
    /**
     * Encryption method.
     *
     * @var string
     */
    const METHOD = 'aes-256-cbc';

    /**
     * Get encryption key.
     *
     * @return string
     */
    private static function get_key()
    {
        // Use WordPress salts for the encryption key.
        $key = wp_salt('auth') . wp_salt('secure_auth');
        return substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Encrypt a string.
     *
     * @param string $data Data to encrypt.
     * @return string Encrypted data.
     */
    public static function encrypt($data)
    {
        if (empty($data)) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        $encrypted = openssl_encrypt($data, self::METHOD, $key, 0, $iv);

        // Combine IV and encrypted data, then base64 encode.
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string.
     *
     * @param string $data Encrypted data.
     * @return string Decrypted data.
     */
    public static function decrypt($data)
    {
        if (empty($data)) {
            return '';
        }

        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);

        // Decode the base64 encoded data.
        $decoded = base64_decode($data);

        // Extract IV and encrypted data.
        $iv = substr($decoded, 0, $iv_length);
        $encrypted = substr($decoded, $iv_length);

        // Decrypt.
        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, 0, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Check if encryption is available.
     *
     * @return bool
     */
    public static function is_available()
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }
}
