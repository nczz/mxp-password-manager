<?php
/**
 * MXP Password Manager - Encryption Module
 *
 * AES-256-GCM encryption for sensitive data
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Encryption {

    /**
     * Encryption algorithm
     *
     * @var string
     */
    private static $cipher = 'aes-256-gcm';

    /**
     * IV length (96 bits = 12 bytes)
     *
     * @var int
     */
    private static $iv_length = 12;

    /**
     * Tag length (128 bits = 16 bytes)
     *
     * @var int
     */
    private static $tag_length = 16;

    /**
     * Get encryption key
     *
     * Priority: wp-config constant > environment variable > database
     *
     * @return string
     */
    public static function get_key(): string {
        // 1. Check wp-config.php constant (highest priority)
        if (defined('MXP_ENCRYPTION_KEY') && MXP_ENCRYPTION_KEY) {
            return base64_decode(MXP_ENCRYPTION_KEY);
        }

        // 2. Check environment variable
        $env_key = getenv('MXP_ENCRYPTION_KEY');
        if (!empty($env_key)) {
            return base64_decode($env_key);
        }

        // Also check $_ENV
        if (!empty($_ENV['MXP_ENCRYPTION_KEY'])) {
            return base64_decode($_ENV['MXP_ENCRYPTION_KEY']);
        }

        // 3. Fallback to database (site option for single site, network option for multisite)
        $db_key = mxp_pm_get_option('mxp_encryption_key', '');
        if (!empty($db_key)) {
            return base64_decode($db_key);
        }

        return '';
    }

    /**
     * Get current key source
     *
     * @return string 'constant' | 'env' | 'database' | 'none'
     */
    public static function get_key_source(): string {
        if (defined('MXP_ENCRYPTION_KEY') && MXP_ENCRYPTION_KEY) {
            return 'constant';
        }

        $env_key = getenv('MXP_ENCRYPTION_KEY');
        if (!empty($env_key) || !empty($_ENV['MXP_ENCRYPTION_KEY'])) {
            return 'env';
        }

        if (mxp_pm_get_option('mxp_encryption_key', '')) {
            return 'database';
        }

        return 'none';
    }

    /**
     * Check if encryption is configured
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $key = self::get_key();
        return !empty($key) && strlen($key) === 32;
    }

    /**
     * Encrypt data
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64 encoded ciphertext (IV + Tag + Ciphertext)
     */
    public static function encrypt(string $plaintext): string {
        if (empty($plaintext)) {
            return '';
        }

        $key = self::get_key();
        if (empty($key)) {
            // No key configured, return plaintext (not recommended)
            return $plaintext;
        }

        // Generate random IV
        $iv = random_bytes(self::$iv_length);
        $tag = '';

        // Encrypt with AES-256-GCM
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::$tag_length
        );

        if ($ciphertext === false) {
            return '';
        }

        // Trigger hook before returning
        Mxp_Hooks::do_action('mxp_before_encrypt', 'field', $plaintext);

        // Format: Base64(IV + Tag + Ciphertext)
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt data
     *
     * @param string $encrypted Base64 encoded ciphertext
     * @return string Decrypted plaintext
     */
    public static function decrypt(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }

        $key = self::get_key();
        if (empty($key)) {
            // No key configured, assume data is not encrypted
            return $encrypted;
        }

        // Decode from Base64
        $data = base64_decode($encrypted, true);
        if ($data === false) {
            // Not valid Base64, might be unencrypted data
            return $encrypted;
        }

        // Minimum length: IV (12) + Tag (16) + at least 1 byte data
        $min_length = self::$iv_length + self::$tag_length + 1;
        if (strlen($data) < $min_length) {
            return $encrypted;
        }

        // Extract IV, Tag, and Ciphertext
        $iv = substr($data, 0, self::$iv_length);
        $tag = substr($data, self::$iv_length, self::$tag_length);
        $ciphertext = substr($data, self::$iv_length + self::$tag_length);

        // Decrypt
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            // Decryption failed, might be unencrypted or corrupted
            return $encrypted;
        }

        // Trigger hook after decryption
        Mxp_Hooks::do_action('mxp_after_decrypt', 'field', $plaintext);

        return $plaintext;
    }

    /**
     * Generate a new encryption key
     *
     * @return string Base64 encoded 32-byte key
     */
    public static function generate_key(): string {
        $key = random_bytes(32); // 256 bits
        return base64_encode($key);
    }

    /**
     * Execute key rotation
     *
     * Re-encrypts all sensitive data with a new key
     *
     * @param string $old_key_b64 Old key (Base64)
     * @param string $new_key_b64 New key (Base64)
     * @return array Result with success count and errors
     */
    public static function rotate_key(string $old_key_b64, string $new_key_b64): array {
        global $wpdb;

        $old_key = base64_decode($old_key_b64);
        $new_key = base64_decode($new_key_b64);

        if (strlen($old_key) !== 32 || strlen($new_key) !== 32) {
            return [
                'success' => false,
                'message' => '金鑰長度必須為 32 位元組',
                'count' => 0,
                'errors' => [],
            ];
        }

        $encrypted_fields = Mxp_Hooks::apply_filters('mxp_encrypt_fields', []);
        $table = mxp_pm_get_table_prefix() . 'to_service_list';
        $errors = [];
        $count = 0;

        // Get all services
        $services = $wpdb->get_results("SELECT * FROM {$table}");

        foreach ($services as $service) {
            $updates = [];

            foreach ($encrypted_fields as $field) {
                if (empty($service->$field)) {
                    continue;
                }

                // Decrypt with old key
                $decrypted = self::decrypt_with_key($service->$field, $old_key);
                if ($decrypted === false) {
                    $errors[] = "Service {$service->sid}: Failed to decrypt {$field}";
                    continue;
                }

                // Re-encrypt with new key
                $reencrypted = self::encrypt_with_key($decrypted, $new_key);
                if ($reencrypted === false) {
                    $errors[] = "Service {$service->sid}: Failed to re-encrypt {$field}";
                    continue;
                }

                $updates[$field] = $reencrypted;
            }

            if (!empty($updates)) {
                $result = $wpdb->update(
                    $table,
                    $updates,
                    ['sid' => $service->sid],
                    array_fill(0, count($updates), '%s'),
                    ['%d']
                );

                if ($result !== false) {
                    $count++;
                } else {
                    $errors[] = "Service {$service->sid}: Database update failed";
                }
            }
        }

        // Update stored key if using database mode
        if (self::get_key_source() === 'database') {
            mxp_pm_update_option('mxp_encryption_key', $new_key_b64);
        }

        // Trigger hook
        Mxp_Hooks::do_action('mxp_key_rotated', current_time('mysql'));

        return [
            'success' => empty($errors),
            'message' => empty($errors) ? '金鑰輪替完成' : '金鑰輪替部分失敗',
            'count' => $count,
            'errors' => $errors,
        ];
    }

    /**
     * Encrypt with specific key
     *
     * @param string $plaintext Data to encrypt
     * @param string $key       Raw 32-byte key
     * @return string|false Base64 encoded ciphertext or false on failure
     */
    private static function encrypt_with_key(string $plaintext, string $key) {
        if (empty($plaintext) || strlen($key) !== 32) {
            return false;
        }

        $iv = random_bytes(self::$iv_length);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::$tag_length
        );

        if ($ciphertext === false) {
            return false;
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt with specific key
     *
     * @param string $encrypted Base64 encoded ciphertext
     * @param string $key       Raw 32-byte key
     * @return string|false Decrypted plaintext or false on failure
     */
    private static function decrypt_with_key(string $encrypted, string $key) {
        if (empty($encrypted) || strlen($key) !== 32) {
            return false;
        }

        $data = base64_decode($encrypted, true);
        if ($data === false) {
            return false;
        }

        $min_length = self::$iv_length + self::$tag_length + 1;
        if (strlen($data) < $min_length) {
            return false;
        }

        $iv = substr($data, 0, self::$iv_length);
        $tag = substr($data, self::$iv_length, self::$tag_length);
        $ciphertext = substr($data, self::$iv_length + self::$tag_length);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext;
    }

    /**
     * Get encryption algorithm info
     *
     * @return array
     */
    public static function get_algorithm_info(): array {
        return [
            'cipher' => self::$cipher,
            'key_length' => 256,
            'iv_length' => self::$iv_length * 8,
            'tag_length' => self::$tag_length * 8,
        ];
    }

    /**
     * Validate key format
     *
     * @param string $key_b64 Base64 encoded key
     * @return bool
     */
    public static function validate_key(string $key_b64): bool {
        $key = base64_decode($key_b64, true);
        return $key !== false && strlen($key) === 32;
    }
}
