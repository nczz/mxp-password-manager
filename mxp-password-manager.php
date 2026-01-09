<?php
/**
 * Plugin Name: MXP Password Manager
 * Plugin URI: https://github.com/nczz/mxp-password-manager
 * Description: WordPress 企業帳號密碼集中管理外掛（支援單站與 Multisite）
 * Version: 3.3.5
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Chun
 * License: GPL v2 or later
 * Network: true
 * Update URI: https://github.com/nczz/mxp-password-manager
 *
 * @package MXP_Password_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('MXP_PM_VERSION', '3.3.5');
define('MXP_PM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MXP_PM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MXP_PM_PLUGIN_FILE', __FILE__);

// GitHub Auto-Update (default repository - can be overridden in wp-config.php)
if (!defined('MXP_PM_GITHUB_REPO')) {
    define('MXP_PM_GITHUB_REPO', 'nczz/mxp-password-manager');
}

/**
 * Get the correct table prefix for both Multisite and single site installations
 *
 * @return string Table prefix
 */
function mxp_pm_get_table_prefix(): string {
    global $wpdb;
    // Use base_prefix for multisite (shared tables), prefix for single site
    return is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
}

/**
 * Get option value (compatible with both Multisite and single site)
 *
 * @param string $option  Option name
 * @param mixed  $default Default value
 * @return mixed Option value
 */
function mxp_pm_get_option(string $option, $default = false) {
    return is_multisite() ? get_site_option($option, $default) : get_option($option, $default);
}

/**
 * Update option value (compatible with both Multisite and single site)
 *
 * @param string $option Option name
 * @param mixed  $value  Option value
 * @return bool Success
 */
function mxp_pm_update_option(string $option, $value): bool {
    return is_multisite() ? update_site_option($option, $value) : update_option($option, $value);
}

/**
 * Delete option value (compatible with both Multisite and single site)
 *
 * @param string $option Option name
 * @return bool Success
 */
function mxp_pm_delete_option(string $option): bool {
    return is_multisite() ? delete_site_option($option) : delete_option($option);
}

// Load includes
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-hooks.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-encryption.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-notification.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-settings.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-multisite.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-github-updater-config.php';
require_once MXP_PM_PLUGIN_DIR . 'includes/class-mxp-pm-updater.php';
require_once MXP_PM_PLUGIN_DIR . 'update.php';

/**
 * Main Plugin Class
 */
class Mxp_Pm_AccountManager {

    /**
     * Plugin version
     * @var string
     */
    public $VERSION = '3.1.0';

    /**
     * Singleton instance
     * @var Mxp_Pm_AccountManager
     */
    private static $instance = null;

    /**
     * Plugin slug
     * @var string
     */
    public $plugin_slug = 'mxp-password-manager';

    /**
     * Get singleton instance
     * @return Mxp_Pm_AccountManager
     */
    public static function get_instance(): Mxp_Pm_AccountManager {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        // Initialize modules
        Mxp_Pm_Hooks::init();
        Mxp_Pm_Settings::init();

        $stored_version = mxp_pm_get_option('mxp_pm_password_manager_version', '');

        // Check if we need to run install (fresh install)
        $prefix = mxp_pm_get_table_prefix();
        $main_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$prefix}mxp_pm_service_list'");

        if (empty($stored_version) || !$main_table_exists) {
            $this->install();
        } elseif (version_compare($stored_version, $this->VERSION, '<')) {
            Mxp_Pm_Update::apply_update($stored_version);
        }

        // Initialize GitHub auto-updater in admin
        if (is_admin()) {
            // Get GitHub repo from settings or use default constant
            $github_repo = mxp_pm_get_option('mxp_pm_github_repo', MXP_PM_GITHUB_REPO);

            new Mxp_Pm_Updater(
                MXP_PM_PLUGIN_FILE,
                $github_repo,
                MXP_PM_VERSION
            );
        }

        $this->init();
    }

    /**
     * Install plugin - create database tables
     */
    public function install(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = mxp_pm_get_table_prefix();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table 1: mxp_pm_service_list
        $sql1 = "CREATE TABLE {$prefix}mxp_pm_service_list (
            sid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            scope ENUM('global','site') DEFAULT 'global',
            owner_blog_id BIGINT(20) UNSIGNED DEFAULT NULL,
            category_id INT(10) UNSIGNED DEFAULT NULL,
            service_name VARCHAR(500) NOT NULL DEFAULT '',
            login_url TEXT,
            account VARCHAR(500) DEFAULT '',
            password TEXT,
            reg_email VARCHAR(500) DEFAULT '',
            reg_phone VARCHAR(500) DEFAULT '',
            reg_phone2 VARCHAR(500) DEFAULT '',
            2fa_token TEXT,
            recover_code TEXT,
            note TEXT,
            status ENUM('active','archived','suspended') DEFAULT 'active',
            priority TINYINT(1) UNSIGNED DEFAULT 3,
            created_by INT(10) UNSIGNED NOT NULL DEFAULT 0,
            allow_authorized_edit TINYINT(1) DEFAULT 1,
            last_used DATETIME DEFAULT NULL,
            created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_time DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (sid),
            KEY idx_service_status (status),
            KEY idx_service_category (category_id),
            KEY idx_service_priority (priority),
            KEY idx_service_scope (scope),
            KEY idx_service_blog (owner_blog_id),
            KEY idx_service_creator (created_by)
        ) $charset_collate;";

        // Table 2: mxp_pm_service_categories
        $sql2 = "CREATE TABLE {$prefix}mxp_pm_service_categories (
            cid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            category_name VARCHAR(100) NOT NULL,
            category_icon VARCHAR(50) DEFAULT 'dashicons-category',
            sort_order INT(10) DEFAULT 0,
            created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (cid),
            UNIQUE KEY unique_category_name (category_name)
        ) $charset_collate;";

        // Table 3: mxp_pm_service_tags
        $sql3 = "CREATE TABLE {$prefix}mxp_pm_service_tags (
            tid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            tag_name VARCHAR(50) NOT NULL,
            tag_color VARCHAR(7) DEFAULT '#6c757d',
            created_by INT(10) UNSIGNED NOT NULL,
            created_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (tid),
            UNIQUE KEY unique_tag_name (tag_name)
        ) $charset_collate;";

        // Table 4: mxp_pm_service_tag_map
        $sql4 = "CREATE TABLE {$prefix}mxp_pm_service_tag_map (
            mid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id INT(10) UNSIGNED NOT NULL,
            tag_id INT(10) UNSIGNED NOT NULL,
            PRIMARY KEY (mid),
            UNIQUE KEY unique_service_tag (service_id, tag_id),
            KEY idx_tagmap_service (service_id),
            KEY idx_tagmap_tag (tag_id)
        ) $charset_collate;";

        // Table 5: mxp_pm_auth_list
        $sql5 = "CREATE TABLE {$prefix}mxp_pm_auth_list (
            sid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            granted_from_blog_id BIGINT(20) UNSIGNED DEFAULT NULL,
            added_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (sid),
            KEY idx_auth_service (service_id),
            KEY idx_auth_user (user_id),
            KEY idx_auth_blog (granted_from_blog_id)
        ) $charset_collate;";

        // Table 6: mxp_pm_audit_log
        $sql6 = "CREATE TABLE {$prefix}mxp_pm_audit_log (
            sid INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id INT(10) UNSIGNED NOT NULL,
            user_id INT(10) UNSIGNED NOT NULL,
            user_name VARCHAR(100) DEFAULT '',
            action VARCHAR(100) DEFAULT '',
            field_name VARCHAR(100) DEFAULT '',
            old_value TEXT,
            new_value TEXT,
            added_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (sid),
            KEY idx_audit_service (service_id),
            KEY idx_audit_user (user_id)
        ) $charset_collate;";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
        dbDelta($sql6);

        // Insert default categories
        $default_categories = Mxp_Pm_Hooks::apply_filters('mxp_pm_default_categories', []);
        foreach ($default_categories as $cat) {
            $wpdb->insert(
                "{$prefix}mxp_pm_service_categories",
                [
                    'category_name' => $cat['name'],
                    'category_icon' => $cat['icon'],
                    'sort_order' => $cat['order'],
                ],
                ['%s', '%s', '%d']
            );
        }

        // Update version
        mxp_pm_update_option('mxp_pm_password_manager_version', $this->VERSION);
    }

    /**
     * Initialize hooks and actions
     */
    public function init(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'create_plugin_menu']);

        // Assets
        add_action('admin_enqueue_scripts', [$this, 'load_assets']);

        // AJAX handlers
        add_action('wp_ajax_mxp_pm_get_service', [$this, 'ajax_to_get_service']);
        add_action('wp_ajax_mxp_pm_update_service_info', [$this, 'ajax_to_update_service_info']);
        add_action('wp_ajax_mxp_pm_add_new_account_service', [$this, 'ajax_to_add_new_account_service']);
        add_action('wp_ajax_mxp_pm_search_services', [$this, 'ajax_to_search_services']);
        add_action('wp_ajax_mxp_pm_archive_service', [$this, 'ajax_to_archive_service']);
        add_action('wp_ajax_mxp_pm_restore_service', [$this, 'ajax_to_restore_service']);
        add_action('wp_ajax_mxp_pm_batch_action', [$this, 'ajax_to_batch_action']);
        add_action('wp_ajax_mxp_pm_manage_categories', [$this, 'ajax_to_manage_categories']);
        add_action('wp_ajax_mxp_pm_manage_tags', [$this, 'ajax_to_manage_tags']);
        add_action('wp_ajax_mxp_pm_delete_service', [$this, 'ajax_to_delete_service']);
        add_action('wp_ajax_mxp_pm_manage_site_access', [$this, 'ajax_to_manage_site_access']);
        add_action('wp_ajax_mxp_pm_get_network_users', [$this, 'ajax_to_get_network_users']);

        // User profile hooks
        add_action('show_user_profile', [$this, 'render_user_notification_settings']);
        add_action('edit_user_profile', [$this, 'render_user_notification_settings']);
        add_action('personal_options_update', [$this, 'save_user_notification_settings']);
        add_action('edit_user_profile_update', [$this, 'save_user_notification_settings']);

        // Settings save handler (works for both network admin and regular admin)
        if (is_multisite()) {
            add_action('network_admin_edit_mxp_save_settings', [Mxp_Pm_Settings::class, 'handle_settings_save']);
        } else {
            add_action('admin_post_mxp_save_settings', [Mxp_Pm_Settings::class, 'handle_settings_save']);
        }
    }

    /**
     * Create admin menu
     */
    public function create_plugin_menu(): void {
        $capability = Mxp_Pm_Hooks::apply_filters('mxp_pm_admin_menu_capability', 'read');

        add_menu_page(
            '帳號密碼管理',
            '帳號管理',
            $capability,
            $this->plugin_slug,
            [$this, 'mxp_pm_account_manager_dashboard_cb'],
            'dashicons-lock',
            30
        );
    }

    /**
     * Load frontend assets
     */
    public function load_assets(string $hook): void {
        // Load on dashboard page
        $is_dashboard = strpos($hook, $this->plugin_slug) !== false;

        // Load on settings page (both network and single site)
        $is_settings = $hook === 'settings_page_mxp-account-settings';

        if (!$is_dashboard && !$is_settings) {
            return;
        }

        // WordPress Dashicons
        wp_enqueue_style('dashicons');

        // CSS - Third-party (local vendor)
        wp_enqueue_style('select2', MXP_PM_PLUGIN_URL . 'assets/vendor/select2/select2.min.css', [], '4.1.0');
        wp_enqueue_style('mxp-main', MXP_PM_PLUGIN_URL . 'assets/css/main.css', ['select2'], MXP_PM_VERSION);

        // JS - Third-party (local vendor)
        wp_enqueue_script('crypto-js', MXP_PM_PLUGIN_URL . 'assets/vendor/cryptojs/crypto-js.min.js', [], '4.2.0', true);
        wp_enqueue_script('select2', MXP_PM_PLUGIN_URL . 'assets/vendor/select2/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('wp-util'); // Required for wp.template()

        // JS - Main application
        wp_enqueue_script('mxp-main', MXP_PM_PLUGIN_URL . 'assets/js/main.js', ['jquery', 'crypto-js', 'select2', 'wp-util'], MXP_PM_VERSION, true);

        // Localize script
        wp_localize_script('mxp-main', 'mxp_pm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mxp_pm_nonce'),
        ]);

        // Only localize the main object on dashboard page (settings page doesn't need it)
        if ($is_dashboard) {
            wp_localize_script('mxp-main', 'mxp_pm_password_manager_obj', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mxp_pm_nonce'),
                'user_maps' => $this->username_maps(),
                'categories' => $this->get_categories(),
                'tags' => $this->get_tags(),
                'current_user_id' => get_current_user_id(),
            ]);
        }
    }

    /**
     * Dashboard callback
     */
    public function mxp_pm_account_manager_dashboard_cb(): void {
        // Check if encryption key is configured
        if (!Mxp_Pm_Encryption::is_configured()) {
            $this->render_encryption_required_notice();
            return;
        }

        $user_id = get_current_user_id();
        $can_view_all = Mxp_Pm_Settings::user_can('view_all_services');

        // Get categories for sidebar
        $categories = $this->get_categories();

        // Get service counts
        $counts = $this->get_service_counts($user_id, $can_view_all);

        include MXP_PM_PLUGIN_DIR . 'templates/dashboard.php';
    }

    /**
     * Render notice when encryption key is not configured
     */
    private function render_encryption_required_notice(): void {
        $settings_url = is_multisite()
            ? network_admin_url('settings.php?page=mxp-account-settings')
            : admin_url('options-general.php?page=mxp-account-settings');

        $can_configure = current_user_can('manage_options') || (is_multisite() && current_user_can('manage_network_options'));
        ?>
        <div class="wrap">
            <h1>帳號密碼管理</h1>
            <div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #d63638; padding: 20px; margin-top: 20px; max-width: 800px;">
                <h2 style="margin-top: 0; color: #d63638;">
                    <span class="dashicons dashicons-warning" style="font-size: 24px; vertical-align: middle;"></span>
                    加密金鑰尚未設定
                </h2>
                <p style="font-size: 14px; line-height: 1.6;">
                    為了確保您的帳號密碼資料安全，必須先設定加密金鑰才能使用帳號管理服務。
                </p>
                <p style="font-size: 14px; line-height: 1.6;">
                    所有儲存的帳號、密碼、2FA 金鑰等敏感資料都會使用 AES-256-GCM 演算法加密保護。
                </p>
                <?php if ($can_configure): ?>
                    <p style="margin-top: 20px;">
                        <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-hero">
                            <span class="dashicons dashicons-admin-settings" style="vertical-align: middle; margin-right: 5px;"></span>
                            前往設定加密金鑰
                        </a>
                    </p>
                <?php else: ?>
                    <div style="background: #f0f0f1; padding: 15px; margin-top: 15px; border-radius: 4px;">
                        <p style="margin: 0; color: #50575e;">
                            <strong>請聯繫網站管理員</strong><br>
                            您沒有設定加密金鑰的權限，請聯繫網站管理員進行設定。
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Check if encryption is configured for AJAX requests
     * Returns false and sends error response if not configured
     *
     * @return bool True if configured, false otherwise (also sends JSON error)
     */
    private function require_encryption_configured(): bool {
        if (!Mxp_Pm_Encryption::is_configured()) {
            wp_send_json_error([
                'code' => 403,
                'message' => '加密金鑰尚未設定，請先完成設定後再使用此功能。',
                'require_setup' => true,
            ]);
            return false;
        }
        return true;
    }

    /**
     * Get username maps
     */
    public function username_maps(): array {
        $args = is_multisite() ? ['blog_id' => get_current_blog_id()] : [];
        $users = get_users($args);
        $maps = [];
        foreach ($users as $user) {
            $maps[$user->ID] = $user->display_name;
        }
        return $maps;
    }

    /**
     * Get all team users
     */
    public function get_all_team_users(): array {
        $args = is_multisite() ? ['blog_id' => get_current_blog_id()] : [];
        return get_users($args);
    }

    /**
     * Get categories
     */
    public function get_categories(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_categories ORDER BY sort_order ASC",
            ARRAY_A
        );
    }

    /**
     * Get tags
     */
    public function get_tags(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_tags ORDER BY tag_name ASC",
            ARRAY_A
        );
    }

    /**
     * Get service counts
     */
    public function get_service_counts(int $user_id, bool $can_view_all): array {
        global $wpdb;

        $base_query = "SELECT status, COUNT(*) as count FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_list";

        if ($can_view_all) {
            $results = $wpdb->get_results("{$base_query} GROUP BY status", ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare(
                "{$base_query} s
                 INNER JOIN " . mxp_pm_get_table_prefix() . "mxp_pm_auth_list a ON s.sid = a.service_id
                 WHERE a.user_id = %d GROUP BY s.status",
                $user_id
            ), ARRAY_A);
        }

        $counts = ['active' => 0, 'archived' => 0, 'suspended' => 0, 'total' => 0];
        foreach ($results as $row) {
            $counts[$row['status']] = (int) $row['count'];
            $counts['total'] += (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Add audit log entry
     */
    public function add_audit_log(array $params): void {
        global $wpdb;

        $user = wp_get_current_user();

        $wpdb->insert(
            mxp_pm_get_table_prefix() . "mxp_pm_audit_log",
            [
                'service_id' => absint($params['service_id']),
                'user_id' => get_current_user_id(),
                'user_name' => $user->display_name,
                'action' => sanitize_text_field($params['action'] ?? ''),
                'field_name' => sanitize_text_field($params['field_name'] ?? ''),
                'old_value' => $params['old_value'] ?? '',
                'new_value' => $params['new_value'] ?? '',
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Check if user can access service
     */
    public function user_can_access_service(int $service_id, int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Site administrators can view all services
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check view all permission (legacy)
        if (Mxp_Pm_Settings::user_can('view_all_services', $user_id)) {
            return true;
        }

        // Check central admin permission (new in v3.0.0)
        if (Mxp_Pm_Multisite::can_view_all($user_id)) {
            return true;
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();
        $blog_id = is_multisite() ? get_current_blog_id() : 0;

        // Get service scope info
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT scope, owner_blog_id FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        if (!$service) {
            return false;
        }

        // Check if user is authorized
        $authorized = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}mxp_pm_auth_list WHERE service_id = %d AND user_id = %d",
            $service_id,
            $user_id
        ));

        if (!$authorized) {
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_view_service', false, $service_id, $user_id);
        }

        // For non-multisite or global scope with null owner, allow access
        if (!is_multisite() || $service->owner_blog_id === null) {
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_view_service', true, $service_id, $user_id);
        }

        // Site-specific: only allow if on the owning site
        if ($service->scope === 'site') {
            $can_access = (int) $service->owner_blog_id === $blog_id;
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_view_service', $can_access, $service_id, $user_id);
        }

        // Global scope: check if current site can access
        $site_can_access = Mxp_Pm_Multisite::site_can_access_service($service_id, $blog_id);
        return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_view_service', $site_can_access, $service_id, $user_id);
    }

    /**
     * Check if user can edit service
     */
    public function user_can_edit_service(int $service_id, int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Central admin with editor+ level can edit all
        if (Mxp_Pm_Multisite::can_edit_all($user_id)) {
            return true;
        }

        // Super admin can edit all
        if (is_super_admin($user_id)) {
            return true;
        }

        // Regular users need to be authorized AND be on the owning site (for site-specific)
        // OR be a site admin for global services
        if (!$this->user_can_access_service($service_id, $user_id)) {
            return false;
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();
        $blog_id = is_multisite() ? get_current_blog_id() : 0;

        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT scope, owner_blog_id, created_by, allow_authorized_edit FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        if (!$service) {
            return false;
        }

        // Creator always has edit permission
        if ((int) $service->created_by === $user_id) {
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_edit_service', true, $service_id, $user_id);
        }

        // If creator disabled authorized user editing, non-creator cannot edit
        if (!$service->allow_authorized_edit) {
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_edit_service', false, $service_id, $user_id);
        }

        // If allow_authorized_edit is enabled, authorized users can edit
        // (user_can_access_service already verified the user is authorized)
        return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_edit_service', true, $service_id, $user_id);
    }

    /**
     * Check if user can archive/restore service
     * Similar to edit permission but respects allow_authorized_edit setting
     */
    public function user_can_archive_service(int $service_id, int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Central admin with editor+ level can archive all
        if (Mxp_Pm_Multisite::can_edit_all($user_id)) {
            return true;
        }

        // Super admin can archive all
        if (is_super_admin($user_id)) {
            return true;
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT created_by, allow_authorized_edit FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        if (!$service) {
            return false;
        }

        // Creator always has archive permission
        if ((int) $service->created_by === $user_id) {
            return true;
        }

        // If creator disabled authorized user editing, non-creator cannot archive
        if (!$service->allow_authorized_edit) {
            return false;
        }

        // Otherwise, defer to edit permission
        return $this->user_can_edit_service($service_id, $user_id);
    }

    // ==========================================
    // AJAX Handlers
    // ==========================================

    /**
     * AJAX: Get service details
     */
    public function ajax_to_get_service(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $sid = absint($_POST['sid'] ?? 0);
        $current_user_id = get_current_user_id();

        // Debug: log access check
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MXP Get Service Debug - sid: $sid, current_user_id: $current_user_id");
            error_log("MXP Get Service Debug - can_access: " . ($this->user_can_access_service($sid) ? 'true' : 'false'));
        }

        if (!$sid || !$this->user_can_access_service($sid)) {
            wp_send_json_error(['code' => 403, 'message' => '無權限存取此服務', 'debug_user_id' => $current_user_id]);
        }

        global $wpdb;

        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, c.category_name, c.category_icon
             FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_list s
             LEFT JOIN " . mxp_pm_get_table_prefix() . "mxp_pm_service_categories c ON s.category_id = c.cid
             WHERE s.sid = %d",
            $sid
        ), ARRAY_A);

        if (!$service) {
            wp_send_json_error(['code' => 404, 'message' => '服務不存在']);
        }

        // Decrypt sensitive fields
        $encrypted_fields = Mxp_Pm_Hooks::apply_filters('mxp_pm_encrypt_fields', []);
        foreach ($encrypted_fields as $field) {
            if (isset($service[$field]) && !empty($service[$field])) {
                $service[$field] = Mxp_Pm_Encryption::decrypt($service[$field]);
            }
        }

        // Get tags
        $service['tags'] = $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_tags t
             INNER JOIN " . mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map m ON t.tid = m.tag_id
             WHERE m.service_id = %d",
            $sid
        ), ARRAY_A);

        // Get authorization list
        $service['auth_list'] = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM " . mxp_pm_get_table_prefix() . "mxp_pm_auth_list WHERE service_id = %d",
            $sid
        ));

        // Get audit log with user info
        $audit_logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . mxp_pm_get_table_prefix() . "mxp_pm_audit_log WHERE service_id = %d ORDER BY added_time DESC LIMIT 50",
            $sid
        ), ARRAY_A);

        // Encrypted fields that need decryption in audit log
        $encrypted_fields = ['account', 'password', '2fa_token', 'note'];

        // Add user display name and decrypt sensitive values
        foreach ($audit_logs as &$log) {
            $user = get_user_by('id', $log['user_id']);
            $log['user_display'] = $user ? "{$user->display_name} ({$user->user_login})" : "使用者 #{$log['user_id']}";

            // Decrypt old/new values if field is encrypted
            if (in_array($log['field_name'], $encrypted_fields)) {
                if (!empty($log['old_value'])) {
                    $decrypted = Mxp_Pm_Encryption::decrypt($log['old_value']);
                    $log['old_value'] = $decrypted !== false ? $decrypted : $log['old_value'];
                }
                if (!empty($log['new_value'])) {
                    $decrypted = Mxp_Pm_Encryption::decrypt($log['new_value']);
                    $log['new_value'] = $decrypted !== false ? $decrypted : $log['new_value'];
                }
            }
        }
        $service['audit_log'] = $audit_logs;

        // Add scope info (v3.0.0)
        $service['scope_label'] = Mxp_Pm_Multisite::get_scope_label($service['scope'] ?? 'global');
        $service['is_global'] = ($service['scope'] ?? 'global') === 'global';

        // Get owner blog name for site-specific services
        if (!empty($service['owner_blog_id']) && is_multisite()) {
            $service['owner_blog_name'] = get_blog_option($service['owner_blog_id'], 'blogname');
        }

        // Get site access list for global services (if user can manage)
        if ($service['is_global'] && is_multisite() && Mxp_Pm_Multisite::can_manage_auth()) {
            $service['site_access'] = Mxp_Pm_Multisite::get_service_site_access($sid);
        }

        // Add edit permission flag
        $service['can_edit'] = $this->user_can_edit_service($sid);

        // Add archive permission flag
        $service['can_archive'] = $this->user_can_archive_service($sid);

        // Add creator info (v3.1.0)
        $current_user_id = get_current_user_id();
        $service['is_creator'] = (int) ($service['created_by'] ?? 0) === $current_user_id;

        // Get creator display name
        if (!empty($service['created_by'])) {
            $creator = get_user_by('id', $service['created_by']);
            $service['created_by_name'] = $creator ? $creator->display_name : '未知使用者';
        } else {
            $service['created_by_name'] = '未知使用者';
        }

        // Add field aliases for template compatibility
        $service['service_url'] = $service['login_url'] ?? '';

        // Add computed fields for template
        $status_options = Mxp_Pm_Hooks::apply_filters('mxp_pm_status_options', []);
        $priority_options = Mxp_Pm_Hooks::apply_filters('mxp_pm_priority_options', []);

        $service['status_label'] = $status_options[$service['status']] ?? $service['status'];
        $service['priority_label'] = $priority_options[$service['priority']] ?? $service['priority'];
        $service['has_2fa'] = !empty($service['2fa_token']);

        // Log view action
        $this->add_audit_log(['service_id' => $sid, 'action' => '查看']);

        // Update last_used
        $wpdb->update(
            mxp_pm_get_table_prefix() . "mxp_pm_service_list",
            ['last_used' => current_time('mysql')],
            ['sid' => $sid],
            ['%s'],
            ['%d']
        );

        // Trigger hook
        Mxp_Pm_Hooks::do_action('mxp_pm_service_viewed', $sid, get_current_user_id());

        wp_send_json_success(['code' => 200, 'data' => $service]);
    }

    /**
     * AJAX: Update service info
     */
    public function ajax_to_update_service_info(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $sid = absint($_POST['sid'] ?? 0);

        if (!$sid || !$this->user_can_access_service($sid)) {
            wp_send_json_error(['code' => 403, 'message' => '無權限修改此服務']);
        }

        // Check edit permission
        if (!Mxp_Pm_Hooks::apply_filters('mxp_pm_can_edit_service', true, $sid, get_current_user_id())) {
            wp_send_json_error(['code' => 403, 'message' => '無編輯權限']);
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();
        $current_user_id = get_current_user_id();

        // Get old values for audit
        $old_service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $sid
        ), ARRAY_A);

        // Check if current user is the creator
        $is_creator = (int) ($old_service['created_by'] ?? 0) === $current_user_id;

        // Get old auth list for comparison
        $old_auth_list = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$prefix}mxp_pm_auth_list WHERE service_id = %d",
            $sid
        ));

        $encrypted_fields = Mxp_Pm_Hooks::apply_filters('mxp_pm_encrypt_fields', []);
        $updates = [];
        $changed = [];

        // Define updatable fields and their sanitization
        $field_map = [
            'service_name' => 'sanitize_text_field',
            'service_url' => 'esc_url_raw',  // Maps to login_url in DB
            'login_url' => 'esc_url_raw',
            'category_id' => 'absint',
            'priority' => 'absint',
            'account' => 'sanitize_text_field',
            'password' => 'sanitize_text_field',
            '2fa_token' => 'sanitize_text_field',
            'recover_code' => 'sanitize_textarea_field',
            'reg_email' => 'sanitize_email',
            'reg_phone' => 'sanitize_text_field',
            'reg_phone2' => 'sanitize_text_field',
            'note' => 'sanitize_textarea_field',
            'scope' => 'sanitize_key',
            'status' => 'sanitize_key',
        ];

        foreach ($field_map as $field => $sanitizer) {
            // Handle service_url -> login_url mapping
            $post_field = $field;
            $db_field = $field;
            if ($field === 'service_url') {
                $db_field = 'login_url';
                if (!isset($_POST['service_url']) && isset($_POST['login_url'])) {
                    $post_field = 'login_url';
                }
            }

            if (!isset($_POST[$post_field])) {
                continue;
            }

            $value = $_POST[$post_field];

            // Apply sanitization
            if ($sanitizer === 'absint') {
                $value = absint($value);
            } elseif ($sanitizer === 'esc_url_raw') {
                $value = esc_url_raw($value);
            } elseif ($sanitizer === 'sanitize_textarea_field') {
                $value = sanitize_textarea_field($value);
            } elseif ($sanitizer === 'sanitize_key') {
                $value = sanitize_key($value);
            } elseif ($sanitizer === 'sanitize_email') {
                $value = sanitize_email($value);
            } else {
                $value = sanitize_text_field($value);
            }

            // Validate specific fields
            if ($db_field === 'status' && !in_array($value, ['active', 'archived', 'suspended'])) {
                $value = 'active';
            }
            if ($db_field === 'scope' && !in_array($value, ['global', 'site'])) {
                $value = 'global';
            }

            // Handle scope change permission (v3.0.0)
            if ($db_field === 'scope' && $value === 'global' && !Mxp_Pm_Multisite::can_create_global()) {
                continue;
            }

            // Skip if value hasn't changed (for non-encrypted fields)
            $old_value = $old_service[$db_field] ?? '';
            if (!in_array($db_field, $encrypted_fields)) {
                if ((string) $value === (string) $old_value) {
                    continue;
                }
            }

            // For encrypted fields, we need to compare decrypted values
            if (in_array($db_field, $encrypted_fields)) {
                $decrypted_old = !empty($old_value) ? Mxp_Pm_Encryption::decrypt($old_value) : '';
                if ($value === $decrypted_old) {
                    continue;
                }
            }

            // Encrypt if needed
            $value_for_db = $value;
            if (in_array($db_field, $encrypted_fields) && !empty($value)) {
                $value_for_db = Mxp_Pm_Encryption::encrypt($value);
            }

            $updates[$db_field] = $value_for_db;
            $changed[$db_field] = $value;

            // Log change - encrypt sensitive values for audit log storage
            if (in_array($db_field, $encrypted_fields)) {
                // Store encrypted values in audit log (will be decrypted when viewing)
                $log_old = !empty($old_value) ? $old_value : ''; // old_value is already encrypted in DB
                $log_new = !empty($value) ? Mxp_Pm_Encryption::encrypt($value) : '';
            } else {
                $log_old = $old_value;
                $log_new = $value;
            }

            $this->add_audit_log([
                'service_id' => $sid,
                'action' => '更新',
                'field_name' => $db_field,
                'old_value' => $log_old,
                'new_value' => $log_new,
            ]);

            // Trigger scope change hook
            if ($db_field === 'scope' && $old_service['scope'] !== $value) {
                Mxp_Pm_Hooks::do_action('mxp_pm_service_scope_changed', $sid, $value, $old_service['scope']);
            }
        }

        // Handle allow_authorized_edit update (only creator can change this)
        if (isset($_POST['allow_authorized_edit']) && $is_creator) {
            $new_allow_edit = absint($_POST['allow_authorized_edit']) ? 1 : 0;
            $old_allow_edit = (int) ($old_service['allow_authorized_edit'] ?? 1);

            if ($new_allow_edit !== $old_allow_edit) {
                $updates['allow_authorized_edit'] = $new_allow_edit;
                $changed['allow_authorized_edit'] = $new_allow_edit;

                $this->add_audit_log([
                    'service_id' => $sid,
                    'action' => '更新',
                    'field_name' => 'allow_authorized_edit',
                    'old_value' => $old_allow_edit ? '允許' : '禁止',
                    'new_value' => $new_allow_edit ? '允許' : '禁止',
                ]);
            }
        }

        // Handle tags update
        if (isset($_POST['tags'])) {
            $new_tags = is_array($_POST['tags']) ? array_map('absint', $_POST['tags']) : [];
            $this->update_service_tags($sid, $new_tags);
        }

        // Handle auth_users update
        if (isset($_POST['auth_users'])) {
            $new_auth_users = is_array($_POST['auth_users']) ? array_map('absint', $_POST['auth_users']) : [];

            // Ensure creator is always in the auth list (v3.1.0)
            $creator_id = (int) ($old_service['created_by'] ?? 0);
            if ($creator_id > 0 && !in_array($creator_id, $new_auth_users, true)) {
                $new_auth_users[] = $creator_id;
            }

            $this->update_auth_list_with_log($sid, $new_auth_users, $old_auth_list);
        }

        // Update database
        if (!empty($updates)) {
            $wpdb->update(
                "{$prefix}mxp_pm_service_list",
                $updates,
                ['sid' => $sid]
            );

            // Trigger hooks
            Mxp_Pm_Hooks::do_action('mxp_pm_service_updated', $sid, $changed, $old_service);

            // Check if password changed
            if (isset($changed['password'])) {
                $service_name = $old_service['service_name'];
                Mxp_Pm_Notification::send_to_service_users($sid, Mxp_Pm_Notification::NOTIFY_PASSWORD_CHANGED, [
                    'service_name' => $service_name,
                    'action_by' => wp_get_current_user()->display_name,
                ], get_current_user_id());
            }
        }

        wp_send_json_success(['code' => 200, 'message' => '更新成功', 'sid' => $sid]);
    }

    /**
     * Update auth list with audit logging
     */
    private function update_auth_list_with_log(int $service_id, array $new_user_ids, array $old_user_ids): void {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        // Find added and removed users
        $added = array_diff($new_user_ids, $old_user_ids);
        $removed = array_diff($old_user_ids, $new_user_ids);

        // Remove old authorizations
        foreach ($removed as $user_id) {
            $wpdb->delete(
                "{$prefix}mxp_pm_auth_list",
                ['service_id' => $service_id, 'user_id' => $user_id],
                ['%d', '%d']
            );

            $user = get_user_by('id', $user_id);
            $user_display = $user ? "{$user->display_name} ({$user->user_login})" : "使用者 #{$user_id}";

            $this->add_audit_log([
                'service_id' => $service_id,
                'action' => '移除授權',
                'field_name' => 'auth_list',
                'old_value' => $user_display,
                'new_value' => '',
            ]);

            Mxp_Pm_Hooks::do_action('mxp_pm_auth_revoked', $service_id, $user_id);
        }

        // Add new authorizations
        foreach ($added as $user_id) {
            $wpdb->insert(
                "{$prefix}mxp_pm_auth_list",
                [
                    'service_id' => $service_id,
                    'user_id' => $user_id,
                    'granted_from_blog_id' => is_multisite() ? get_current_blog_id() : null,
                ],
                ['%d', '%d', '%d']
            );

            $user = get_user_by('id', $user_id);
            $user_display = $user ? "{$user->display_name} ({$user->user_login})" : "使用者 #{$user_id}";

            $this->add_audit_log([
                'service_id' => $service_id,
                'action' => '新增授權',
                'field_name' => 'auth_list',
                'old_value' => '',
                'new_value' => $user_display,
            ]);

            Mxp_Pm_Hooks::do_action('mxp_pm_auth_granted', $service_id, $user_id);
        }
    }

    /**
     * Update authorization list
     */
    private function update_auth_list(int $service_id, $user_ids): void {
        global $wpdb;

        if (is_string($user_ids)) {
            $user_ids = array_filter(array_map('absint', explode(',', $user_ids)));
        } else {
            $user_ids = array_map('absint', (array) $user_ids);
        }

        // Get current list
        $current = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM " . mxp_pm_get_table_prefix() . "mxp_pm_auth_list WHERE service_id = %d",
            $service_id
        ));

        $to_add = array_diff($user_ids, $current);
        $to_remove = array_diff($current, $user_ids);

        // Get service name for notifications
        $service_name = $wpdb->get_var($wpdb->prepare(
            "SELECT service_name FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        // Add new users
        foreach ($to_add as $uid) {
            $wpdb->insert(
                mxp_pm_get_table_prefix() . "mxp_pm_auth_list",
                ['service_id' => $service_id, 'user_id' => $uid],
                ['%d', '%d']
            );

            $this->add_audit_log([
                'service_id' => $service_id,
                'action' => '新增',
                'field_name' => '授權人員',
                'new_value' => $this->username_maps()[$uid] ?? $uid,
            ]);

            Mxp_Pm_Hooks::do_action('mxp_pm_auth_granted', $service_id, $uid);

            // Send notification
            Mxp_Pm_Notification::send_to_user($uid, Mxp_Pm_Notification::NOTIFY_AUTH_GRANTED, [
                'service_name' => $service_name,
                'action_by' => wp_get_current_user()->display_name,
            ]);
        }

        // Remove users
        foreach ($to_remove as $uid) {
            $wpdb->delete(
                mxp_pm_get_table_prefix() . "mxp_pm_auth_list",
                ['service_id' => $service_id, 'user_id' => $uid],
                ['%d', '%d']
            );

            $this->add_audit_log([
                'service_id' => $service_id,
                'action' => '移除',
                'field_name' => '授權人員',
                'old_value' => $this->username_maps()[$uid] ?? $uid,
            ]);

            Mxp_Pm_Hooks::do_action('mxp_pm_auth_revoked', $service_id, $uid);

            // Send notification
            Mxp_Pm_Notification::send_to_user($uid, Mxp_Pm_Notification::NOTIFY_AUTH_REVOKED, [
                'service_name' => $service_name,
                'action_by' => wp_get_current_user()->display_name,
            ]);
        }
    }

    /**
     * Update service tags
     */
    private function update_service_tags(int $service_id, $tag_ids): void {
        global $wpdb;

        if (is_string($tag_ids)) {
            $tag_ids = array_filter(array_map('absint', explode(',', $tag_ids)));
        } else {
            $tag_ids = array_map('absint', (array) $tag_ids);
        }

        // Remove all existing
        $wpdb->delete(
            mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map",
            ['service_id' => $service_id],
            ['%d']
        );

        // Add new tags
        foreach ($tag_ids as $tid) {
            $wpdb->insert(
                mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map",
                ['service_id' => $service_id, 'tag_id' => $tid],
                ['%d', '%d']
            );
        }
    }

    /**
     * AJAX: Add new service
     */
    public function ajax_to_add_new_account_service(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        global $wpdb;

        $service_name = sanitize_text_field($_POST['service_name'] ?? '');
        if (empty($service_name)) {
            wp_send_json_error(['code' => 400, 'message' => '服務名稱為必填']);
        }

        // Support both auth_list and auth_users (form field name)
        $auth_list = isset($_POST['auth_list']) ? array_map('absint', (array) $_POST['auth_list']) : [];
        if (empty($auth_list) && isset($_POST['auth_users'])) {
            $auth_list = array_map('absint', (array) $_POST['auth_users']);
        }
        if (empty($auth_list)) {
            wp_send_json_error(['code' => 400, 'message' => '至少需要一位授權人員']);
        }

        // Determine scope (v3.0.0)
        $scope = sanitize_key($_POST['scope'] ?? '');
        if (!in_array($scope, ['global', 'site'], true)) {
            $scope = Mxp_Pm_Multisite::get_default_scope();
        }

        // Check if user can create global services
        if ($scope === 'global' && !Mxp_Pm_Multisite::can_create_global()) {
            wp_send_json_error(['code' => 403, 'message' => '無權限建立全域共享服務']);
        }

        $encrypted_fields = Mxp_Pm_Hooks::apply_filters('mxp_pm_encrypt_fields', []);

        // Prepare data - support both login_url and service_url
        $login_url = $_POST['login_url'] ?? $_POST['service_url'] ?? '';
        $data = [
            'service_name' => $service_name,
            'scope' => $scope,
            'owner_blog_id' => is_multisite() ? get_current_blog_id() : null,
            'category_id' => absint($_POST['category_id'] ?? 0) ?: null,
            'login_url' => esc_url_raw($login_url),
            'account' => '',
            'password' => '',
            'reg_email' => sanitize_email($_POST['reg_email'] ?? ''),
            'reg_phone' => sanitize_text_field($_POST['reg_phone'] ?? ''),
            'reg_phone2' => sanitize_text_field($_POST['reg_phone2'] ?? ''),
            '2fa_token' => '',
            'recover_code' => sanitize_textarea_field($_POST['recover_code'] ?? ''),
            'note' => '',
            'status' => 'active',
            'priority' => absint($_POST['priority'] ?? 3),
            'created_by' => get_current_user_id(),
            'allow_authorized_edit' => isset($_POST['allow_authorized_edit']) ? absint($_POST['allow_authorized_edit']) : 1,
        ];

        // Handle encrypted fields
        foreach ($encrypted_fields as $field) {
            $value = $_POST[$field] ?? '';
            if (!empty($value)) {
                if ($field === 'note') {
                    $value = sanitize_textarea_field($value);
                } else {
                    $value = sanitize_text_field($value);
                }
                $data[$field] = Mxp_Pm_Encryption::encrypt($value);
            }
        }

        $wpdb->insert(mxp_pm_get_table_prefix() . "mxp_pm_service_list", $data);
        $service_id = $wpdb->insert_id;

        if (!$service_id) {
            wp_send_json_error(['code' => 500, 'message' => '新增服務失敗']);
        }

        // Add authorization
        foreach ($auth_list as $uid) {
            $wpdb->insert(
                mxp_pm_get_table_prefix() . "mxp_pm_auth_list",
                ['service_id' => $service_id, 'user_id' => $uid],
                ['%d', '%d']
            );

            Mxp_Pm_Hooks::do_action('mxp_pm_auth_granted', $service_id, $uid);

            // Send notification
            Mxp_Pm_Notification::send_to_user($uid, Mxp_Pm_Notification::NOTIFY_SERVICE_CREATED, [
                'service_name' => $service_name,
                'action_by' => wp_get_current_user()->display_name,
            ]);
        }

        // Add tags
        if (!empty($_POST['tags'])) {
            $this->update_service_tags($service_id, $_POST['tags']);
        }

        // Audit log
        $this->add_audit_log([
            'service_id' => $service_id,
            'action' => '新增',
            'field_name' => '服務',
            'new_value' => $service_name,
        ]);

        Mxp_Pm_Hooks::do_action('mxp_pm_service_created', $service_id, $data);

        wp_send_json_success(['code' => 200, 'message' => '新增成功', 'sid' => $service_id]);
    }

    /**
     * AJAX: Search services
     */
    public function ajax_to_search_services(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $user_id = get_current_user_id();
        $blog_id = is_multisite() ? get_current_blog_id() : 0;
        $can_view_all = current_user_can('manage_options') || Mxp_Pm_Settings::user_can('view_all_services') || Mxp_Pm_Multisite::can_view_all();

        // Search parameters (support both parameter names for compatibility)
        $keyword = sanitize_text_field($_POST['keyword'] ?? $_POST['search'] ?? '');

        // Status filter - handle empty string, array, or single value
        $status_input = $_POST['status'] ?? 'active';
        if (is_array($status_input)) {
            $status = array_filter(array_map('sanitize_key', $status_input));
        } else {
            $status_input = sanitize_key($status_input);
            $status = !empty($status_input) ? [$status_input] : [];
        }

        $category_ids = isset($_POST['category_id']) ? array_filter(array_map('absint', (array) $_POST['category_id'])) : [];
        $tag_ids = isset($_POST['tag_id']) || isset($_POST['tags']) ? array_filter(array_map('absint', (array) ($_POST['tag_id'] ?? $_POST['tags']))) : [];
        $priority_min = absint($_POST['priority_min'] ?? 1);
        $priority_max = absint($_POST['priority_max'] ?? 5);
        $sort_by = sanitize_key($_POST['sort_by'] ?? 'updated_time');
        $sort_order = strtoupper($_POST['sort_order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, absint($_POST['page'] ?? 1));
        $per_page = min(100, max(1, absint($_POST['per_page'] ?? 10)));
        $offset = ($page - 1) * $per_page;

        // Scope filter (v3.0.0)
        $scope_filter = sanitize_key($_POST['scope'] ?? '');

        // Valid sort columns
        $valid_sorts = ['updated_time', 'service_name', 'priority', 'last_used', 'created_time'];
        if (!in_array($sort_by, $valid_sorts)) {
            $sort_by = 'updated_time';
        }

        // Build query
        $select = "SELECT DISTINCT s.*, c.category_name, c.category_icon";
        $from = " FROM {$prefix}mxp_pm_service_list s";
        $from .= " LEFT JOIN {$prefix}mxp_pm_service_categories c ON s.category_id = c.cid";

        $where = " WHERE 1=1";
        $params = [];

        // Status filter
        if (!empty($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '%s'));
            $where .= " AND s.status IN ({$placeholders})";
            $params = array_merge($params, $status);
        }

        // Scope filter (v3.0.0)
        if (!empty($scope_filter) && in_array($scope_filter, ['global', 'site'])) {
            $where .= " AND s.scope = %s";
            $params[] = $scope_filter;
        }

        // Authorization filter with scope awareness (v3.0.0)
        if (!$can_view_all) {
            // Use the new scope-aware access conditions
            $access_conditions = Mxp_Pm_Multisite::build_access_conditions($user_id, $blog_id, false);
            $where .= " AND {$access_conditions}";
        }

        // Keyword search
        if (!empty($keyword)) {
            $search_fields = isset($_POST['search_fields']) ? array_map('sanitize_key', (array) $_POST['search_fields']) : ['service_name', 'account', 'note'];
            $search_where = [];

            foreach ($search_fields as $field) {
                if (in_array($field, ['service_name', 'login_url', 'reg_email'])) {
                    $search_where[] = "s.{$field} LIKE %s";
                    $params[] = '%' . $wpdb->esc_like($keyword) . '%';
                }
            }

            if (!empty($search_where)) {
                $where .= " AND (" . implode(' OR ', $search_where) . ")";
            }
        }

        // Category filter
        if (!empty($category_ids)) {
            $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
            $where .= " AND s.category_id IN ({$placeholders})";
            $params = array_merge($params, $category_ids);
        }

        // Tag filter
        if (!empty($tag_ids)) {
            $from .= " INNER JOIN " . mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map tm ON s.sid = tm.service_id";
            $placeholders = implode(',', array_fill(0, count($tag_ids), '%d'));
            $where .= " AND tm.tag_id IN ({$placeholders})";
            $params = array_merge($params, $tag_ids);
        }

        // Priority filter
        $where .= " AND s.priority BETWEEN %d AND %d";
        $params[] = $priority_min;
        $params[] = $priority_max;

        // Apply search query filter
        $query_parts = Mxp_Pm_Hooks::apply_filters('mxp_pm_search_query', compact('select', 'from', 'where', 'params'), $_POST);
        extract($query_parts);

        // Count total
        // Debug: log query parts
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MXP Search Debug - user_id: $user_id, can_view_all: " . ($can_view_all ? 'true' : 'false'));
            error_log("MXP Search Debug - is_multisite: " . (is_multisite() ? 'true' : 'false'));
            error_log("MXP Search Debug - status: " . print_r($status, true));
            error_log("MXP Search Debug - where: $where");
            error_log("MXP Search Debug - params: " . print_r($params, true));
        }

        $count_sql = !empty($params)
            ? $wpdb->prepare("SELECT COUNT(DISTINCT s.sid) {$from} {$where}", ...$params)
            : "SELECT COUNT(DISTINCT s.sid) {$from} {$where}";
        $total_items = (int) $wpdb->get_var($count_sql);

        // Get results
        $sql = $wpdb->prepare(
            "{$select} {$from} {$where} ORDER BY s.{$sort_by} {$sort_order} LIMIT %d OFFSET %d",
            ...array_merge($params, [$per_page, $offset])
        );

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("MXP Search Debug - final SQL: $sql");
        }

        $services = $wpdb->get_results($sql, ARRAY_A);

        // Get options for computed fields
        $priority_options = Mxp_Pm_Hooks::apply_filters('mxp_pm_priority_options', []);

        // Get tags for each service and add scope info
        foreach ($services as &$service) {
            $service['tags'] = $wpdb->get_results($wpdb->prepare(
                "SELECT t.* FROM {$prefix}mxp_pm_service_tags t
                 INNER JOIN {$prefix}mxp_pm_service_tag_map m ON t.tid = m.tag_id
                 WHERE m.service_id = %d",
                $service['sid']
            ), ARRAY_A);

            // Decrypt account for display
            if (!empty($service['account'])) {
                $service['account'] = Mxp_Pm_Encryption::decrypt($service['account']);
            }

            // Add scope info (v3.0.0)
            $service['scope_label'] = Mxp_Pm_Multisite::get_scope_label($service['scope'] ?? 'global');
            $service['is_global'] = ($service['scope'] ?? 'global') === 'global';

            // Get owner blog name
            if (!empty($service['owner_blog_id']) && is_multisite()) {
                $service['owner_blog_name'] = get_blog_option($service['owner_blog_id'], 'blogname');
            }

            // Add edit permission
            $service['can_edit'] = $this->user_can_edit_service((int) $service['sid']);

            // Add field aliases for template compatibility
            $service['service_url'] = $service['login_url'] ?? '';

            // Add computed fields for list item template
            $service['has_2fa'] = !empty($service['2fa_token']);
            $service['priority_label'] = $priority_options[$service['priority']] ?? $service['priority'];
        }

        // Apply results filter
        $services = Mxp_Pm_Hooks::apply_filters('mxp_pm_search_results', $services, $_POST);

        // Get stats for dashboard
        $stats = [
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}mxp_pm_service_list"),
            'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}mxp_pm_service_list WHERE status = 'active'"),
            'archived' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}mxp_pm_service_list WHERE status = 'archived'"),
        ];

        wp_send_json_success([
            'code' => 200,
            'data' => [
                'services' => $services,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total_items' => $total_items,
                    'total_pages' => ceil($total_items / $per_page),
                ],
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * AJAX: Archive service
     */
    public function ajax_to_archive_service(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $sid = absint($_POST['sid'] ?? 0);
        $current_user_id = get_current_user_id();

        if (!$sid || !$this->user_can_access_service($sid)) {
            wp_send_json_error(['code' => 403, 'message' => '無權限操作此服務']);
        }

        // Check allow_authorized_edit permission
        if (!$this->user_can_archive_service($sid, $current_user_id)) {
            wp_send_json_error(['code' => 403, 'message' => '無歸檔權限，只有建立者可以歸檔此服務']);
        }

        if (!Mxp_Pm_Hooks::apply_filters('mxp_pm_can_archive_service', true, $sid, $current_user_id)) {
            wp_send_json_error(['code' => 403, 'message' => '無歸檔權限']);
        }

        global $wpdb;

        $wpdb->update(
            mxp_pm_get_table_prefix() . "mxp_pm_service_list",
            ['status' => 'archived'],
            ['sid' => $sid],
            ['%s'],
            ['%d']
        );

        $this->add_audit_log([
            'service_id' => $sid,
            'action' => '歸檔',
        ]);

        Mxp_Pm_Hooks::do_action('mxp_pm_service_archived', $sid, $current_user_id);

        wp_send_json_success(['code' => 200, 'message' => '服務已歸檔']);
    }

    /**
     * AJAX: Restore service
     */
    public function ajax_to_restore_service(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $sid = absint($_POST['sid'] ?? 0);
        $restore_to = sanitize_key($_POST['restore_to'] ?? 'active');
        $current_user_id = get_current_user_id();

        if (!in_array($restore_to, ['active', 'suspended'])) {
            $restore_to = 'active';
        }

        if (!$sid || !$this->user_can_access_service($sid)) {
            wp_send_json_error(['code' => 403, 'message' => '無權限操作此服務']);
        }

        // Check allow_authorized_edit permission
        if (!$this->user_can_archive_service($sid, $current_user_id)) {
            wp_send_json_error(['code' => 403, 'message' => '無還原權限，只有建立者可以還原此服務']);
        }

        global $wpdb;

        $wpdb->update(
            mxp_pm_get_table_prefix() . "mxp_pm_service_list",
            ['status' => $restore_to],
            ['sid' => $sid],
            ['%s'],
            ['%d']
        );

        $this->add_audit_log([
            'service_id' => $sid,
            'action' => '取消歸檔',
            'new_value' => $restore_to,
        ]);

        Mxp_Pm_Hooks::do_action('mxp_pm_service_restored', $sid, $current_user_id, $restore_to);

        wp_send_json_success(['code' => 200, 'message' => '服務已恢復']);
    }

    /**
     * AJAX: Batch action
     */
    public function ajax_to_batch_action(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $action_type = sanitize_key($_POST['action_type'] ?? '');
        $service_ids = isset($_POST['service_ids']) ? array_map('absint', (array) $_POST['service_ids']) : [];

        if (empty($service_ids)) {
            wp_send_json_error(['code' => 400, 'message' => '請選擇服務']);
        }

        $valid_actions = ['archive', 'restore', 'change_category', 'add_tags', 'change_status', 'delete'];
        if (!in_array($action_type, $valid_actions)) {
            wp_send_json_error(['code' => 400, 'message' => '無效的操作類型']);
        }

        global $wpdb;

        $affected = 0;
        $failed = [];

        foreach ($service_ids as $sid) {
            if (!$this->user_can_access_service($sid)) {
                $failed[] = $sid;
                continue;
            }

            switch ($action_type) {
                case 'archive':
                    $wpdb->update(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['status' => 'archived'], ['sid' => $sid]);
                    $this->add_audit_log(['service_id' => $sid, 'action' => '歸檔']);
                    $affected++;
                    break;

                case 'restore':
                    $wpdb->update(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['status' => 'active'], ['sid' => $sid]);
                    $this->add_audit_log(['service_id' => $sid, 'action' => '取消歸檔']);
                    $affected++;
                    break;

                case 'change_category':
                    $category_id = absint($_POST['category_id'] ?? 0);
                    $wpdb->update(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['category_id' => $category_id ?: null], ['sid' => $sid]);
                    $this->add_audit_log(['service_id' => $sid, 'action' => '更新', 'field_name' => 'category_id']);
                    $affected++;
                    break;

                case 'add_tags':
                    $tag_ids = isset($_POST['tag_ids']) ? array_map('absint', (array) $_POST['tag_ids']) : [];
                    foreach ($tag_ids as $tid) {
                        $wpdb->replace(mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map", ['service_id' => $sid, 'tag_id' => $tid]);
                    }
                    $affected++;
                    break;

                case 'change_status':
                    $new_status = sanitize_key($_POST['new_status'] ?? 'active');
                    if (in_array($new_status, ['active', 'archived', 'suspended'])) {
                        $wpdb->update(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['status' => $new_status], ['sid' => $sid]);
                        $this->add_audit_log(['service_id' => $sid, 'action' => '更新', 'field_name' => 'status', 'new_value' => $new_status]);
                        $affected++;
                    }
                    break;

                case 'delete':
                    // Only allow delete for archived services
                    $status = $wpdb->get_var($wpdb->prepare("SELECT status FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_list WHERE sid = %d", $sid));
                    if ($status === 'archived') {
                        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['sid' => $sid]);
                        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_auth_list", ['service_id' => $sid]);
                        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map", ['service_id' => $sid]);
                        Mxp_Pm_Hooks::do_action('mxp_pm_service_deleted', $sid);
                        $affected++;
                    } else {
                        $failed[] = $sid;
                    }
                    break;
            }
        }

        Mxp_Pm_Hooks::do_action('mxp_pm_batch_action_completed', $action_type, $service_ids, get_current_user_id());

        wp_send_json_success([
            'code' => 200,
            'message' => '批次操作完成',
            'affected_count' => $affected,
            'failed_ids' => $failed,
        ]);
    }

    /**
     * AJAX: Manage categories
     */
    public function ajax_to_manage_categories(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        // Support both parameter names
        $action_type = sanitize_key($_POST['action_type'] ?? $_POST['operation'] ?? 'list');

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();
        $table = "{$prefix}mxp_pm_service_categories";

        switch ($action_type) {
            case 'list':
                $categories = $wpdb->get_results(
                    "SELECT c.*, COUNT(s.sid) as service_count
                     FROM {$table} c
                     LEFT JOIN {$prefix}mxp_pm_service_list s ON c.cid = s.category_id
                     GROUP BY c.cid
                     ORDER BY c.sort_order ASC",
                    ARRAY_A
                );
                wp_send_json_success(['code' => 200, 'categories' => $categories]);
                break;

            case 'add':
            case 'create':
                $name = sanitize_text_field($_POST['category_name'] ?? '');
                $icon = sanitize_text_field($_POST['category_icon'] ?? 'dashicons-category');

                if (empty($name)) {
                    wp_send_json_error(['code' => 400, 'message' => '分類名稱為必填']);
                }

                $max_order = (int) $wpdb->get_var("SELECT MAX(sort_order) FROM {$table}");

                $wpdb->insert($table, [
                    'category_name' => $name,
                    'category_icon' => $icon,
                    'sort_order' => $max_order + 1,
                ], ['%s', '%s', '%d']);

                $cid = $wpdb->insert_id;
                Mxp_Pm_Hooks::do_action('mxp_pm_category_created', $cid, ['name' => $name, 'icon' => $icon]);

                wp_send_json_success(['code' => 200, 'message' => '分類已建立', 'cid' => $cid]);
                break;

            case 'update':
                $cid = absint($_POST['cid'] ?? 0);
                $name = sanitize_text_field($_POST['category_name'] ?? '');
                $icon = sanitize_text_field($_POST['category_icon'] ?? '');

                if (!$cid) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的分類 ID']);
                }

                $updates = [];
                if (!empty($name)) $updates['category_name'] = $name;
                if (!empty($icon)) $updates['category_icon'] = $icon;

                if (!empty($updates)) {
                    $wpdb->update($table, $updates, ['cid' => $cid]);
                    Mxp_Pm_Hooks::do_action('mxp_pm_category_updated', $cid, $updates);
                }

                wp_send_json_success(['code' => 200, 'message' => '分類已更新']);
                break;

            case 'delete':
                $cid = absint($_POST['cid'] ?? 0);

                if (!$cid) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的分類 ID']);
                }

                // Check if category is in use
                $service_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$prefix}mxp_pm_service_list WHERE category_id = %d",
                    $cid
                ));

                if ($service_count > 0) {
                    wp_send_json_error(['code' => 400, 'message' => "此分類有 {$service_count} 個服務正在使用，無法刪除"]);
                }

                $wpdb->delete($table, ['cid' => $cid]);
                Mxp_Pm_Hooks::do_action('mxp_pm_category_deleted', $cid);

                wp_send_json_success(['code' => 200, 'message' => '分類已刪除']);
                break;

            case 'reorder':
                $order = isset($_POST['order']) ? array_map('absint', (array) $_POST['order']) : [];

                foreach ($order as $index => $cid) {
                    $wpdb->update($table, ['sort_order' => $index], ['cid' => $cid]);
                }

                wp_send_json_success(['code' => 200, 'message' => '排序已更新']);
                break;

            default:
                wp_send_json_error(['code' => 400, 'message' => '無效的操作']);
        }
    }

    /**
     * AJAX: Manage tags
     */
    public function ajax_to_manage_tags(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        // Support both parameter names
        $action_type = sanitize_key($_POST['action_type'] ?? $_POST['operation'] ?? 'list');

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();
        $table = "{$prefix}mxp_pm_service_tags";

        switch ($action_type) {
            case 'list':
                $tags = $wpdb->get_results(
                    "SELECT t.*, u.display_name as created_by_name, COUNT(m.mid) as usage_count
                     FROM {$table} t
                     LEFT JOIN {$wpdb->users} u ON t.created_by = u.ID
                     LEFT JOIN {$prefix}mxp_pm_service_tag_map m ON t.tid = m.tag_id
                     GROUP BY t.tid
                     ORDER BY t.tag_name ASC",
                    ARRAY_A
                );
                wp_send_json_success(['code' => 200, 'tags' => $tags]);
                break;

            case 'add':
            case 'create':
                $name = sanitize_text_field($_POST['tag_name'] ?? '');
                $color = sanitize_hex_color($_POST['tag_color'] ?? '') ?: '#6c757d';

                if (empty($name)) {
                    wp_send_json_error(['code' => 400, 'message' => '標籤名稱為必填']);
                }

                $wpdb->insert($table, [
                    'tag_name' => $name,
                    'tag_color' => $color,
                    'created_by' => get_current_user_id(),
                ], ['%s', '%s', '%d']);

                $tid = $wpdb->insert_id;
                Mxp_Pm_Hooks::do_action('mxp_pm_tag_created', $tid, ['name' => $name, 'color' => $color]);

                wp_send_json_success(['code' => 200, 'message' => '標籤已建立', 'tid' => $tid]);
                break;

            case 'update':
                $tid = absint($_POST['tid'] ?? 0);
                $name = sanitize_text_field($_POST['tag_name'] ?? '');
                $color = sanitize_hex_color($_POST['tag_color'] ?? '');

                if (!$tid) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的標籤 ID']);
                }

                $updates = [];
                if (!empty($name)) $updates['tag_name'] = $name;
                if (!empty($color)) $updates['tag_color'] = $color;

                if (!empty($updates)) {
                    $wpdb->update($table, $updates, ['tid' => $tid]);
                }

                wp_send_json_success(['code' => 200, 'message' => '標籤已更新']);
                break;

            case 'delete':
                $tid = absint($_POST['tid'] ?? 0);

                if (!$tid) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的標籤 ID']);
                }

                // Check if tag is in use
                $usage_count = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$prefix}mxp_pm_service_tag_map WHERE tag_id = %d",
                    $tid
                ));

                if ($usage_count > 0) {
                    wp_send_json_error(['code' => 400, 'message' => "此標籤有 {$usage_count} 個服務正在使用，無法刪除"]);
                }

                $wpdb->delete($table, ['tid' => $tid]);
                Mxp_Pm_Hooks::do_action('mxp_pm_tag_deleted', $tid);

                wp_send_json_success(['code' => 200, 'message' => '標籤已刪除']);
                break;

            default:
                wp_send_json_error(['code' => 400, 'message' => '無效的操作']);
        }
    }

    /**
     * AJAX: Delete service
     */
    public function ajax_to_delete_service(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');
        $this->require_encryption_configured();

        $sid = absint($_POST['sid'] ?? 0);

        if (!$sid || !$this->user_can_access_service($sid)) {
            wp_send_json_error(['code' => 403, 'message' => '無權限操作此服務']);
        }

        global $wpdb;

        // Only allow delete for archived services
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM " . mxp_pm_get_table_prefix() . "mxp_pm_service_list WHERE sid = %d",
            $sid
        ));

        if ($status !== 'archived') {
            wp_send_json_error(['code' => 400, 'message' => '只能刪除已歸檔的服務']);
        }

        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_service_list", ['sid' => $sid]);
        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_auth_list", ['service_id' => $sid]);
        $wpdb->delete(mxp_pm_get_table_prefix() . "mxp_pm_service_tag_map", ['service_id' => $sid]);

        Mxp_Pm_Hooks::do_action('mxp_pm_service_deleted', $sid);

        wp_send_json_success(['code' => 200, 'message' => '服務已永久刪除']);
    }

    /**
     * AJAX: Manage site access for global services (v3.0.0)
     */
    public function ajax_to_manage_site_access(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');

        if (!is_multisite()) {
            wp_send_json_error(['code' => 400, 'message' => '此功能僅適用於多站台環境']);
        }

        if (!Mxp_Pm_Multisite::can_manage_auth()) {
            wp_send_json_error(['code' => 403, 'message' => '無權限管理站台存取']);
        }

        $action_type = sanitize_key($_POST['action_type'] ?? 'list');
        $service_id = absint($_POST['service_id'] ?? 0);

        if (!$service_id) {
            wp_send_json_error(['code' => 400, 'message' => '無效的服務 ID']);
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        // Check if service is global
        $scope = $wpdb->get_var($wpdb->prepare(
            "SELECT scope FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        if ($scope !== 'global') {
            wp_send_json_error(['code' => 400, 'message' => '站台存取管理僅適用於全域共享服務']);
        }

        switch ($action_type) {
            case 'list':
                $site_access = Mxp_Pm_Multisite::get_service_site_access($service_id);
                $all_sites = Mxp_Pm_Multisite::get_network_sites();

                // Add access info to sites
                foreach ($all_sites as &$site) {
                    $site['access_level'] = $site_access[$site['blog_id']] ?? null;
                    $site['has_explicit_access'] = isset($site_access[$site['blog_id']]);
                }

                wp_send_json_success(['code' => 200, 'data' => [
                    'sites' => $all_sites,
                    'current_access' => $site_access,
                ]]);
                break;

            case 'grant':
                $blog_id = absint($_POST['blog_id'] ?? 0);
                $access_level = sanitize_key($_POST['access_level'] ?? 'view');

                if (!$blog_id) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的站台 ID']);
                }

                if (!in_array($access_level, ['view', 'edit', 'full'], true)) {
                    $access_level = 'view';
                }

                $result = Mxp_Pm_Multisite::grant_site_access($service_id, $blog_id, $access_level);

                if ($result) {
                    $this->add_audit_log([
                        'service_id' => $service_id,
                        'action' => '授予站台存取',
                        'field_name' => 'site_access',
                        'new_value' => "blog_id:{$blog_id}, level:{$access_level}",
                    ]);
                    wp_send_json_success(['code' => 200, 'message' => '站台存取已授予']);
                } else {
                    wp_send_json_error(['code' => 500, 'message' => '授予站台存取失敗']);
                }
                break;

            case 'revoke':
                $blog_id = absint($_POST['blog_id'] ?? 0);

                if (!$blog_id) {
                    wp_send_json_error(['code' => 400, 'message' => '無效的站台 ID']);
                }

                $result = Mxp_Pm_Multisite::revoke_site_access($service_id, $blog_id);

                if ($result) {
                    $this->add_audit_log([
                        'service_id' => $service_id,
                        'action' => '撤銷站台存取',
                        'field_name' => 'site_access',
                        'old_value' => "blog_id:{$blog_id}",
                    ]);
                    wp_send_json_success(['code' => 200, 'message' => '站台存取已撤銷']);
                } else {
                    wp_send_json_error(['code' => 500, 'message' => '撤銷站台存取失敗']);
                }
                break;

            case 'batch_update':
                $access_list = isset($_POST['access_list']) ? (array) $_POST['access_list'] : [];

                // Clear existing explicit access
                $wpdb->delete($prefix . 'mxp_pm_site_access', ['service_id' => $service_id], ['%d']);

                // Add new access entries
                foreach ($access_list as $item) {
                    $blog_id = absint($item['blog_id'] ?? 0);
                    $level = sanitize_key($item['level'] ?? 'view');

                    if ($blog_id && in_array($level, ['view', 'edit', 'full'], true)) {
                        Mxp_Pm_Multisite::grant_site_access($service_id, $blog_id, $level);
                    }
                }

                $this->add_audit_log([
                    'service_id' => $service_id,
                    'action' => '批次更新站台存取',
                    'field_name' => 'site_access',
                ]);

                wp_send_json_success(['code' => 200, 'message' => '站台存取已更新']);
                break;

            default:
                wp_send_json_error(['code' => 400, 'message' => '無效的操作類型']);
        }
    }

    /**
     * AJAX: Get network users for cross-site authorization (v3.0.0)
     */
    public function ajax_to_get_network_users(): void {
        check_ajax_referer('mxp_pm_nonce', 'mxp_pm_nonce');

        $service_id = absint($_POST['service_id'] ?? 0);

        // Get available users based on user's permissions
        $users = Mxp_Pm_Multisite::get_available_users_for_auth($service_id);

        $user_data = [];
        foreach ($users as $user) {
            $user_data[] = [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
            ];
        }

        wp_send_json_success(['code' => 200, 'data' => $user_data]);
    }

    /**
     * Render user notification settings on profile page
     */
    public function render_user_notification_settings($user): void {
        $prefs = Mxp_Pm_Notification::get_user_preferences($user->ID);
        ?>
        <h3>帳號管理通知設定</h3>
        <table class="form-table">
            <tr>
                <th><label for="mxp_pm_notification_format">通知方式</label></th>
                <td>
                    <select name="mxp_pm_notification_format" id="mxp_pm_notification_format">
                        <option value="html" <?php selected($prefs['format'], 'html'); ?>>Email (HTML 格式)</option>
                        <option value="text" <?php selected($prefs['format'], 'text'); ?>>Email (純文字格式)</option>
                        <option value="none" <?php selected($prefs['format'], 'none'); ?>>不接收通知</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>通知類型</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_pm_notify_auth_change" value="1" <?php checked($prefs['auth_change']); ?>>
                        授權變更通知 (新增/移除授權)
                    </label><br>
                    <label>
                        <input type="checkbox" name="mxp_pm_notify_password_change" value="1" <?php checked($prefs['password_change']); ?>>
                        密碼變更通知
                    </label><br>
                    <label>
                        <input type="checkbox" name="mxp_pm_notify_service_update" value="1" <?php checked($prefs['service_update']); ?>>
                        一般服務更新通知
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user notification settings
     */
    public function save_user_notification_settings($user_id): void {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        Mxp_Pm_Notification::save_user_preferences($user_id, [
            'format' => sanitize_key($_POST['mxp_pm_notification_format'] ?? 'html'),
            'auth_change' => !empty($_POST['mxp_pm_notify_auth_change']),
            'password_change' => !empty($_POST['mxp_pm_notify_password_change']),
            'service_update' => !empty($_POST['mxp_pm_notify_service_update']),
        ]);
    }
}

// Initialize plugin
add_action('plugins_loaded', function () {
    Mxp_Pm_AccountManager::get_instance();
});
