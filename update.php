<?php
/**
 * MXP Password Manager - Version Migration System
 *
 * Handles database schema updates between versions
 *
 * @package MXP_Password_Manager
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Update {

    /**
     * Version list in chronological order
     *
     * @var array
     */
    public static $version_list = ['1.0.0', '2.0.0', '2.1.0'];

    /**
     * Apply updates from a specific version
     *
     * @param string $current_ver Current installed version
     * @return void
     */
    public static function apply_update(string $current_ver): void {
        global $wpdb;

        foreach (self::$version_list as $version) {
            if (version_compare($current_ver, $version, '<')) {
                $method = 'mxp_update_to_v' . str_replace('.', '_', $version);
                if (method_exists(__CLASS__, $method)) {
                    $result = call_user_func([__CLASS__, $method]);
                    if ($result === false) {
                        // Log error but continue
                        error_log("MXP Password Manager: Migration to v{$version} failed");
                    }
                }
            }
        }

        // Update stored version
        update_site_option('mxp_password_manager_version', end(self::$version_list));
    }

    /**
     * Migration to v1.0.0
     *
     * Initial version - creates base tables
     *
     * @return bool
     */
    private static function mxp_update_to_v1_0_0(): bool {
        // Base tables are created in install(), no migration needed
        return true;
    }

    /**
     * Migration to v2.0.0
     *
     * - Adds encryption support
     * - Adds notification preferences
     *
     * @return bool
     */
    private static function mxp_update_to_v2_0_0(): bool {
        global $wpdb;

        // Check if encryption is configured, encrypt existing data
        if (Mxp_Encryption::is_configured()) {
            $table = $wpdb->base_prefix . 'to_service_list';
            $encrypted_fields = ['account', 'password', '2fa_token', 'note'];

            // Get all services
            $services = $wpdb->get_results("SELECT sid, account, password, 2fa_token, note FROM {$table}");

            foreach ($services as $service) {
                $updates = [];

                foreach ($encrypted_fields as $field) {
                    $value = $service->$field;
                    if (!empty($value)) {
                        // Check if already encrypted (starts with valid base64 pattern)
                        $decoded = base64_decode($value, true);
                        if ($decoded === false || strlen($decoded) < 29) {
                            // Not encrypted, encrypt it
                            $updates[$field] = Mxp_Encryption::encrypt($value);
                        }
                    }
                }

                if (!empty($updates)) {
                    $wpdb->update(
                        $table,
                        $updates,
                        ['sid' => $service->sid],
                        array_fill(0, count($updates), '%s'),
                        ['%d']
                    );
                }
            }
        }

        return true;
    }

    /**
     * Migration to v2.1.0
     *
     * - Adds category system
     * - Adds tag system
     * - Adds status, priority, last_used, created_time columns
     *
     * @return bool
     */
    private static function mxp_update_to_v2_1_0(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $service_table = $wpdb->base_prefix . 'to_service_list';

        // Add new columns to to_service_list
        $columns_to_add = [
            'category_id' => "ADD COLUMN category_id INT(10) UNSIGNED DEFAULT NULL AFTER sid",
            'status' => "ADD COLUMN status ENUM('active','archived','suspended') DEFAULT 'active' AFTER note",
            'priority' => "ADD COLUMN priority TINYINT(1) UNSIGNED DEFAULT 3 AFTER status",
            'last_used' => "ADD COLUMN last_used DATETIME DEFAULT NULL AFTER priority",
            'created_time' => "ADD COLUMN created_time DATETIME DEFAULT CURRENT_TIMESTAMP AFTER last_used",
        ];

        foreach ($columns_to_add as $column => $sql) {
            // Check if column exists
            $column_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
                DB_NAME,
                $service_table,
                $column
            ));

            if (!$column_exists) {
                $wpdb->query("ALTER TABLE {$service_table} {$sql}");
            }
        }

        // Add indexes
        $indexes = [
            'idx_service_status' => "CREATE INDEX idx_service_status ON {$service_table}(status)",
            'idx_service_category' => "CREATE INDEX idx_service_category ON {$service_table}(category_id)",
        ];

        foreach ($indexes as $index_name => $sql) {
            $index_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
                DB_NAME,
                $service_table,
                $index_name
            ));

            if (!$index_exists) {
                $wpdb->query($sql);
            }
        }

        // Create categories table
        $categories_table = $wpdb->base_prefix . 'to_service_categories';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$categories_table}'") !== $categories_table) {
            $sql = "CREATE TABLE {$categories_table} (
                cid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                category_name VARCHAR(100) NOT NULL,
                category_icon VARCHAR(50) DEFAULT 'dashicons-category',
                sort_order INT(10) DEFAULT 0,
                created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (cid),
                UNIQUE KEY unique_category_name (category_name)
            ) {$charset_collate};";
            $wpdb->query($sql);

            // Insert default categories
            $default_categories = Mxp_Hooks::apply_filters('mxp_default_categories', []);
            foreach ($default_categories as $cat) {
                $wpdb->insert(
                    $categories_table,
                    [
                        'category_name' => $cat['name'],
                        'category_icon' => $cat['icon'],
                        'sort_order' => $cat['order'],
                    ],
                    ['%s', '%s', '%d']
                );
            }
        }

        // Create tags table
        $tags_table = $wpdb->base_prefix . 'to_service_tags';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tags_table}'") !== $tags_table) {
            $sql = "CREATE TABLE {$tags_table} (
                tid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                tag_name VARCHAR(50) NOT NULL,
                tag_color VARCHAR(7) DEFAULT '#6c757d',
                created_by INT(10) UNSIGNED NOT NULL,
                created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (tid),
                UNIQUE KEY unique_tag_name (tag_name)
            ) {$charset_collate};";
            $wpdb->query($sql);
        }

        // Create tag map table
        $tag_map_table = $wpdb->base_prefix . 'to_service_tag_map';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tag_map_table}'") !== $tag_map_table) {
            $sql = "CREATE TABLE {$tag_map_table} (
                mid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id INT(10) UNSIGNED NOT NULL,
                tag_id INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (mid),
                UNIQUE KEY unique_service_tag (service_id, tag_id),
                KEY idx_tagmap_service (service_id),
                KEY idx_tagmap_tag (tag_id)
            ) {$charset_collate};";
            $wpdb->query($sql);
        }

        // Set created_time for existing records
        $wpdb->query("UPDATE {$service_table} SET created_time = updated_time WHERE created_time IS NULL");

        return true;
    }

    /**
     * Get current database version
     *
     * @return string
     */
    public static function get_db_version(): string {
        return get_site_option('mxp_password_manager_version', '0.0.0');
    }

    /**
     * Check if update is needed
     *
     * @param string $plugin_version Current plugin version
     * @return bool
     */
    public static function needs_update(string $plugin_version): bool {
        $db_version = self::get_db_version();
        return version_compare($db_version, $plugin_version, '<');
    }
}
