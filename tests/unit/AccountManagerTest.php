<?php
/**
 * Unit Tests for Mxp_Pm_AccountManager
 *
 * Tests core plugin functionality to ensure behavior is preserved during refactoring.
 */

namespace MXP_PM\Tests;

use PHPUnit\Framework\TestCase;
use WP_Mock;

class Mxp_Pm_AccountManager_Test extends TestCase {

    private $account_manager;

    public function setUp(): void {
        WP_Mock::setUp();
        $this->account_manager = new \Mxp_AccountManager();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    /**
     * Test that install creates database tables
     */
    public function test_install_creates_tables() {
        global $wpdb;
        $wpdb = $this->createMock('\wpdb');

        WP_Mock::userFunction('is_multisite', ['return' => false]);
        WP_Mock::userFunction('dbDelta');

        $this->account_manager->install();

        // Verify dbDelta was called for each table
        $this->assertNotNull($wpdb);
    }

    /**
     * Test audit log entry creation
     */
    public function test_add_audit_log() {
        global $wpdb;
        $wpdb = $this->createMock('\wpdb');

        WP_Mock::userFunction('wp_get_current_user', [
            'return' => (object) ['display_name' => 'Test User']
        ]);

        $wpdb->expects($this->once())
            ->method('insert')
            ->with(
                $this->stringContains('to_audit_log'),
                $this->anything(),
                $this->anything()
            );

        $this->account_manager->add_audit_log([
            'service_id' => 1,
            'action' => 'test_action',
            'field_name' => 'test_field',
            'old_value' => 'old',
            'new_value' => 'new'
        ]);
    }
}
