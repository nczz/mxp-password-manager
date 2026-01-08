<?php
/**
 * Unit Tests for Mxp_Pm_Encryption
 *
 * Tests critical encryption functionality to ensure security before refactoring.
 */

namespace MXP_PM\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock;

class Mxp_Pm_Encryption_Test extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    /**
     * Test encryption/decryption round-trip
     */
    public function test_encrypt_decrypt_success() {
        $plaintext = 'my_secret_password';

        // Set encryption key for testing
        putenv('MXP_ENCRYPTION_KEY=' . base64_encode('32-byte-encryption-key-test!!!'));

        // Encrypt
        $encrypted = \Mxp_Encryption::encrypt($plaintext);

        $this->assertIsString($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertNotEmpty($encrypted);

        // Decrypt
        $decrypted = \Mxp_Encryption::decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    /**
     * Test encryption returns false on failure
     */
    public function test_encrypt_failure_without_key() {
        $plaintext = 'test_value';

        // Clear environment key
        putenv('MXP_ENCRYPTION_KEY=');

        WP_Mock::userFunction('get_option', [
            'return' => false
        ]);

        $result = \Mxp_Encryption::encrypt($plaintext);

        $this->assertFalse($result);
    }

    /**
     * Test decryption returns false on invalid data
     */
    public function test_decrypt_invalid_data() {
        putenv('MXP_ENCRYPTION_KEY=' . base64_encode('32-byte-encryption-key-test!!!'));

        $result = \Mxp_Encryption::decrypt('invalid_encrypted_data');

        $this->assertFalse($result);
    }

    /**
     * Test is_configured returns true when key is set
     */
    public function test_is_configured_with_key() {
        putenv('MXP_ENCRYPTION_KEY=' . base64_encode('32-byte-encryption-key-test!!!'));

        WP_Mock::userFunction('get_option', [
            'return' => false
        ]);

        $this->assertTrue(\Mxp_Encryption::is_configured());
    }

    /**
     * Test is_configured returns false when no key
     */
    public function test_is_configured_without_key() {
        putenv('MXP_ENCRYPTION_KEY=');

        WP_Mock::userFunction('get_option', [
            'return' => false
        ]);

        $this->assertFalse(\Mxp_Encryption::is_configured());
    }
}
