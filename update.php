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

class Mxp_Pm_Update {

    /**
     * Version list in chronological order
     *
     * @var array
     */
    public static $version_list = ['1.0.0', '2.0.0', '2.1.0', '3.0.0', '3.1.0', '3.3.0', '3.3.1', '3.3.2'];

    /**
     * Apply updates from a specific version
     *
     * @param string $current_ver Current installed version
     * @return void
     */
    public static function apply_update(string $current_ver): void {
        global $wpdb;

        error_log("MXP Password Manager: Starting update from v{$current_ver}");

        foreach (self::$version_list as $version) {
            if (version_compare($current_ver, $version, '<')) {
                $method = 'mxp_pm_update_to_v' . str_replace('.', '_', $version);
                error_log("MXP Password Manager: Applying migration to v{$version}");

                if (method_exists(__CLASS__, $method)) {
                    $result = call_user_func([__CLASS__, $method]);
                    if ($result === false) {
                        // Log error but continue
                        error_log("MXP Password Manager: Migration to v{$version} failed");
                    } else {
                        error_log("MXP Password Manager: Migration to v{$version} completed successfully");
                    }
                }
            }
        }

        // Update stored version
        mxp_pm_update_option('mxp_pm_password_manager_version', end(self::$version_list));
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
        if (Mxp_Pm_Encryption::is_configured()) {
            $table = mxp_pm_get_table_prefix() . 'to_service_list';
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
                            $updates[$field] = Mxp_Pm_Encryption::encrypt($value);
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
        $prefix = mxp_pm_get_table_prefix();
        $service_table = $prefix . 'to_service_list';

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
        $categories_table = $prefix . 'to_service_categories';
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
        }

        // Create tags table
        $tags_table = $prefix . 'to_service_tags';
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
        $tag_map_table = $prefix . 'to_service_tag_map';
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
     * Migration to v3.0.0
     *
     * - Adds scope and owner_blog_id columns for multisite control
     * - Creates site_access table for managing site-level access
     * - Creates central_admins table for cross-site administrators
     * - Adds granted_from_blog_id to auth_list
     *
     * @return bool
     */
    private static function mxp_update_to_v3_0_0(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = mxp_pm_get_table_prefix();
        $service_table = $prefix . 'to_service_list';
        $auth_table = $prefix . 'to_auth_list';

        // Step 1: Add scope and owner_blog_id columns to to_service_list
        $columns_to_add = [
            'scope' => "ADD COLUMN scope ENUM('global','site') DEFAULT 'global' AFTER sid",
            'owner_blog_id' => "ADD COLUMN owner_blog_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER scope",
        ];

        foreach ($columns_to_add as $column => $sql) {
            if (!self::column_exists($service_table, $column)) {
                $wpdb->query("ALTER TABLE {$service_table} {$sql}");
            }
        }

        // Step 2: Add indexes for new columns
        self::add_index_if_not_exists($service_table, 'idx_service_scope', 'scope');
        self::add_index_if_not_exists($service_table, 'idx_service_blog', 'owner_blog_id');

        // Step 3: Add granted_from_blog_id to to_auth_list
        if (!self::column_exists($auth_table, 'granted_from_blog_id')) {
            $wpdb->query("ALTER TABLE {$auth_table} ADD COLUMN granted_from_blog_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER user_id");
            self::add_index_if_not_exists($auth_table, 'idx_auth_blog', 'granted_from_blog_id');
        }

        // Step 4: Create to_site_access table
        $site_access_table = $prefix . 'to_site_access';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$site_access_table}'") !== $site_access_table) {
            $sql = "CREATE TABLE {$site_access_table} (
                said INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                service_id INT(10) UNSIGNED NOT NULL,
                blog_id BIGINT(20) UNSIGNED NOT NULL,
                access_level ENUM('view','edit','full') DEFAULT 'view',
                created_by INT(10) UNSIGNED NOT NULL,
                created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (said),
                UNIQUE KEY unique_service_blog (service_id, blog_id),
                KEY idx_site_access_service (service_id),
                KEY idx_site_access_blog (blog_id)
            ) {$charset_collate};";
            $wpdb->query($sql);
        }

        // Step 5: Create to_central_admins table
        $central_admins_table = $prefix . 'to_central_admins';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$central_admins_table}'") !== $central_admins_table) {
            $sql = "CREATE TABLE {$central_admins_table} (
                caid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NOT NULL,
                permission_level ENUM('viewer','editor','admin') DEFAULT 'viewer',
                created_by INT(10) UNSIGNED NOT NULL,
                created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (caid),
                UNIQUE KEY unique_central_user (user_id)
            ) {$charset_collate};";
            $wpdb->query($sql);
        }

        // Step 6: Migrate existing data - set all existing services as global
        $wpdb->query("UPDATE {$service_table} SET scope = 'global' WHERE scope IS NULL");

        // Step 7: Migrate existing mxp_view_all_services users to central admins
        self::migrate_existing_admins_to_central($central_admins_table);

        // Step 8: Set default options
        if (!mxp_pm_get_option('mxp_pm_central_control_enabled')) {
            mxp_pm_update_option('mxp_pm_central_control_enabled', is_multisite());
        }
        if (!mxp_pm_get_option('mxp_pm_default_service_scope')) {
            mxp_pm_update_option('mxp_pm_default_service_scope', 'global');
        }

        return true;
    }

    /**
     * Migration to v3.1.0
     *
     * - Adds created_by column to track service creator
     * - Adds allow_authorized_edit column for creator-controlled edit permission
     *
     * @return bool
     */
    private static function mxp_update_to_v3_1_0(): bool {
        global $wpdb;

        $prefix = mxp_pm_get_table_prefix();
        $service_table = $prefix . 'to_service_list';
        $auth_table = $prefix . 'to_auth_list';

        // Step 1: Add created_by column
        if (!self::column_exists($service_table, 'created_by')) {
            $wpdb->query("ALTER TABLE {$service_table} ADD COLUMN created_by INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER priority");
            self::add_index_if_not_exists($service_table, 'idx_service_creator', 'created_by');
        }

        // Step 2: Add allow_authorized_edit column
        if (!self::column_exists($service_table, 'allow_authorized_edit')) {
            $wpdb->query("ALTER TABLE {$service_table} ADD COLUMN allow_authorized_edit TINYINT(1) DEFAULT 1 AFTER created_by");
        }

        // Step 3: Migrate existing data - set created_by from first auth_list entry
        $services_without_creator = $wpdb->get_results(
            "SELECT sid FROM {$service_table} WHERE created_by = 0"
        );

        foreach ($services_without_creator as $service) {
            // Get the first authorized user (earliest added_time) as creator
            $first_auth_user = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$auth_table} WHERE service_id = %d ORDER BY added_time ASC, sid ASC LIMIT 1",
                $service->sid
            ));

            $creator_id = $first_auth_user ? (int) $first_auth_user : 1;

            $wpdb->update(
                $service_table,
                ['created_by' => $creator_id],
                ['sid' => $service->sid],
                ['%d'],
                ['%d']
            );
        }

        // Step 4: Ensure all services have allow_authorized_edit = 1 (default enabled)
        $wpdb->query("UPDATE {$service_table} SET allow_authorized_edit = 1 WHERE allow_authorized_edit IS NULL");

        return true;
    }

    /**
     * Migrate existing admin users to central admins table
     *
     * @param string $table Central admins table name
     * @return void
     */
    private static function migrate_existing_admins_to_central(string $table): void {
        global $wpdb;

        // Get existing users with mxp_view_all_services permission
        $view_all_users = mxp_pm_get_option('mxp_pm_view_all_services_users', []);

        if (!empty($view_all_users)) {
            foreach ((array) $view_all_users as $user_id) {
                // Check if already exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
                    $user_id
                ));

                if (!$exists) {
                    $wpdb->insert(
                        $table,
                        [
                            'user_id' => $user_id,
                            'permission_level' => 'admin',
                            'created_by' => 1,
                        ],
                        ['%d', '%s', '%d']
                    );
                }
            }
        }
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $table  Table name
     * @param string $column Column name
     * @return bool
     */
    private static function column_exists(string $table, string $column): bool {
        global $wpdb;

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table,
            $column
        ));

        return (bool) $result;
    }

    /**
     * Add index if it doesn't exist
     *
     * @param string $table      Table name
     * @param string $index_name Index name
     * @param string $column     Column name
     * @return void
     */
    private static function add_index_if_not_exists(string $table, string $index_name, string $column): void {
        global $wpdb;

        $index_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
            DB_NAME,
            $table,
            $index_name
        ));

        if (!$index_exists) {
            $wpdb->query("CREATE INDEX {$index_name} ON {$table}({$column})");
        }
    }

    /**
     * Migration to v3.3.0
     *
     * - Renames all tables from to_ prefix to mxp_pm_ prefix
     * - Migrates all options from mxp_ to mxp_pm_
     *
     * @return bool
     */
    private static function mxp_pm_update_to_v3_3_0(): bool {
        global $wpdb;

        $prefix = mxp_pm_get_table_prefix();

        $table_mappings = [
            'to_service_list' => 'mxp_pm_service_list',
            'to_auth_list' => 'mxp_pm_auth_list',
            'to_audit_log' => 'mxp_pm_audit_log',
            'to_service_categories' => 'mxp_pm_service_categories',
            'to_service_tags' => 'mxp_pm_service_tags',
            'to_service_tag_map' => 'mxp_pm_service_tag_map',
            'to_site_access' => 'mxp_pm_site_access',
            'to_central_admins' => 'mxp_pm_central_admins'
        ];

        foreach ($table_mappings as $old_table => $new_table) {
            $old_full = $prefix . $old_table;
            $new_full = $prefix . $new_table;

            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$old_full'");

            if ($table_exists) {
                $wpdb->query("RENAME TABLE `{$old_full}` TO `{$new_full}`");
            }
        }

        $option_mappings = [
            'mxp_password_manager_version' => 'mxp_pm_password_manager_version',
            'mxp_github_repo' => 'mxp_pm_github_repo',
            'mxp_central_control_enabled' => 'mxp_pm_central_control_enabled',
            'mxp_default_service_scope' => 'mxp_pm_default_service_scope',
            'mxp_view_all_services_users' => 'mxp_pm_view_all_services_users'
        ];

        foreach ($option_mappings as $old_option => $new_option) {
            $value = mxp_pm_get_option($old_option);
            if ($value !== false) {
                mxp_pm_update_option($new_option, $value);
                mxp_pm_delete_option($old_option);
            }
        }

        // Insert default categories if categories table is empty
        $categories_table = $prefix . 'mxp_pm_service_categories';
        $category_count = $wpdb->get_var("SELECT COUNT(*) FROM {$categories_table}");

        if (empty($category_count) || $category_count == 0) {
            $default_categories = Mxp_Pm_Hooks::apply_filters('mxp_pm_default_categories', []);
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

        mxp_pm_update_option('mxp_pm_password_manager_version', '3.3.0');

        return true;
    }
    /**
     * Get current database version
     *
     * @return string
     */
    public static function get_db_version(): string {
        return mxp_pm_get_option('mxp_pm_password_manager_version', '0.0.0');
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
