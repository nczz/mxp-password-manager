<?php
/**
 * MXP Password Manager - Settings Module
 *
 * Network-level settings management
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Settings {

    /**
     * Settings option prefix
     *
     * @var string
     */
    private static $prefix = 'mxp_';

    /**
     * Initialize settings module
     *
     * @return void
     */
    public static function init(): void {
        add_action('network_admin_menu', [__CLASS__, 'register_network_settings_page']);
        add_action('admin_init', [__CLASS__, 'handle_settings_save']);
    }

    /**
     * Register network settings page
     *
     * @return void
     */
    public static function register_network_settings_page(): void {
        add_submenu_page(
            'settings.php',
            '帳號管理設定',
            '帳號管理設定',
            'manage_network_options',
            'mxp-account-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public static function render_settings_page(): void {
        if (!is_super_admin()) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'encryption';

        // Get current settings
        $encryption_source = Mxp_Encryption::get_key_source();
        $is_configured = Mxp_Encryption::is_configured();

        ?>
        <div class="wrap">
            <h1>帳號密碼管理設定</h1>

            <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定已儲存。</p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper">
                <a href="?page=mxp-account-settings&tab=encryption" class="nav-tab <?php echo $active_tab === 'encryption' ? 'nav-tab-active' : ''; ?>">加密設定</a>
                <a href="?page=mxp-account-settings&tab=notifications" class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">通知設定</a>
                <a href="?page=mxp-account-settings&tab=permissions" class="nav-tab <?php echo $active_tab === 'permissions' ? 'nav-tab-active' : ''; ?>">權限設定</a>
            </nav>

            <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=mxp_save_settings')); ?>">
                <?php wp_nonce_field('mxp_settings_nonce', 'mxp_settings_nonce'); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr($active_tab); ?>">

                <?php
                switch ($active_tab) {
                    case 'encryption':
                        self::render_encryption_tab($encryption_source, $is_configured);
                        break;
                    case 'notifications':
                        self::render_notifications_tab();
                        break;
                    case 'permissions':
                        self::render_permissions_tab();
                        break;
                }
                ?>

                <?php submit_button('儲存設定'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render encryption settings tab
     *
     * @param string $source       Key source
     * @param bool   $is_configured Is configured
     * @return void
     */
    private static function render_encryption_tab(string $source, bool $is_configured): void {
        $algo_info = Mxp_Encryption::get_algorithm_info();
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">加密狀態</th>
                <td>
                    <?php if ($is_configured): ?>
                        <span style="color: #28a745;">✓ 已設定</span>
                    <?php else: ?>
                        <span style="color: #dc3545;">✗ 未設定</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">金鑰來源</th>
                <td>
                    <?php
                    $source_labels = [
                        'constant' => 'wp-config.php 常數 (推薦)',
                        'env' => '環境變數',
                        'database' => '資料庫',
                        'none' => '未設定',
                    ];
                    echo esc_html($source_labels[$source] ?? '未知');
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">加密演算法</th>
                <td><?php echo esc_html($algo_info['cipher']); ?> (<?php echo esc_html($algo_info['key_length']); ?>-bit)</td>
            </tr>
        </table>

        <?php if ($source === 'none' || $source === 'database'): ?>
            <h3>金鑰管理</h3>
            <table class="form-table">
                <?php if ($source === 'none'): ?>
                    <tr>
                        <th scope="row">產生新金鑰</th>
                        <td>
                            <label>
                                <input type="checkbox" name="generate_key" value="1">
                                自動產生並儲存新的加密金鑰到資料庫
                            </label>
                            <p class="description">
                                建議使用 wp-config.php 常數方式儲存金鑰以提高安全性。
                            </p>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <h3>設定說明</h3>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
            <p><strong>方式一：wp-config.php 常數 (推薦)</strong></p>
            <pre style="background: #23282d; color: #fff; padding: 10px; overflow-x: auto;">
// 在 wp-config.php 中加入以下程式碼
define('MXP_ENCRYPTION_KEY', '<?php echo esc_html(Mxp_Encryption::generate_key()); ?>');
            </pre>
            <p class="description">請將上方產生的金鑰複製到 wp-config.php，此金鑰僅顯示一次。</p>

            <p style="margin-top: 15px;"><strong>方式二：環境變數</strong></p>
            <pre style="background: #23282d; color: #fff; padding: 10px; overflow-x: auto;">
export MXP_ENCRYPTION_KEY="your-base64-encoded-key"
            </pre>
        </div>
        <?php
    }

    /**
     * Render notifications settings tab
     *
     * @return void
     */
    private static function render_notifications_tab(): void {
        $notifications_enabled = self::get('notifications_enabled', true);
        $default_format = self::get('notification_default_format', 'html');
        $from_name = self::get('notification_from_name', get_bloginfo('name'));
        $from_email = self::get('notification_from_email', get_option('admin_email'));
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">啟用 Email 通知</th>
                <td>
                    <label>
                        <input type="checkbox" name="notifications_enabled" value="1" <?php checked($notifications_enabled); ?>>
                        啟用 Email 通知功能
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">預設通知格式</th>
                <td>
                    <select name="notification_default_format">
                        <option value="html" <?php selected($default_format, 'html'); ?>>HTML 格式</option>
                        <option value="text" <?php selected($default_format, 'text'); ?>>純文字格式</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">寄件者名稱</th>
                <td>
                    <input type="text" name="notification_from_name" value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row">寄件者 Email</th>
                <td>
                    <input type="email" name="notification_from_email" value="<?php echo esc_attr($from_email); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render permissions settings tab
     *
     * @return void
     */
    private static function render_permissions_tab(): void {
        $view_all_users = self::get('mxp_view_all_services_users', []);
        $manage_encryption_users = self::get('mxp_manage_encryption_users', []);

        // Get all users for selection
        $all_users = get_users(['blog_id' => 0, 'number' => 100]);
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">查看所有服務權限</th>
                <td>
                    <select name="mxp_view_all_services_users[]" multiple class="regular-text" style="height: 150px;">
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php echo in_array($user->ID, (array) $view_all_users) ? 'selected' : ''; ?>>
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">選擇可以查看所有服務（不受授權清單限制）的使用者。超級管理員預設擁有此權限。</p>
                </td>
            </tr>
            <tr>
                <th scope="row">加密管理權限</th>
                <td>
                    <select name="mxp_manage_encryption_users[]" multiple class="regular-text" style="height: 150px;">
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php echo in_array($user->ID, (array) $manage_encryption_users) ? 'selected' : ''; ?>>
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">選擇可以管理加密設定和執行金鑰輪替的使用者。</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle settings save
     *
     * @return void
     */
    public static function handle_settings_save(): void {
        if (!isset($_POST['mxp_settings_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['mxp_settings_nonce'], 'mxp_settings_nonce')) {
            return;
        }

        if (!is_super_admin()) {
            return;
        }

        $tab = sanitize_key($_POST['tab'] ?? 'encryption');

        switch ($tab) {
            case 'encryption':
                // Generate new key if requested
                if (!empty($_POST['generate_key'])) {
                    $new_key = Mxp_Encryption::generate_key();
                    update_site_option('mxp_encryption_key', $new_key);
                }
                break;

            case 'notifications':
                self::update('notifications_enabled', !empty($_POST['notifications_enabled']));
                self::update('notification_default_format', sanitize_key($_POST['notification_default_format'] ?? 'html'));
                self::update('notification_from_name', sanitize_text_field($_POST['notification_from_name'] ?? ''));
                self::update('notification_from_email', sanitize_email($_POST['notification_from_email'] ?? ''));
                break;

            case 'permissions':
                $view_all_users = isset($_POST['mxp_view_all_services_users']) ? array_map('absint', $_POST['mxp_view_all_services_users']) : [];
                $manage_encryption_users = isset($_POST['mxp_manage_encryption_users']) ? array_map('absint', $_POST['mxp_manage_encryption_users']) : [];

                self::update('mxp_view_all_services_users', $view_all_users);
                self::update('mxp_manage_encryption_users', $manage_encryption_users);
                break;
        }

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'page' => 'mxp-account-settings',
            'tab' => $tab,
            'updated' => 'true',
        ], network_admin_url('settings.php')));
        exit;
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        return get_site_option(self::$prefix . $key, $default);
    }

    /**
     * Update a setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return bool
     */
    public static function update(string $key, $value): bool {
        return update_site_option(self::$prefix . $key, $value);
    }

    /**
     * Delete a setting
     *
     * @param string $key Setting key
     * @return bool
     */
    public static function delete(string $key): bool {
        return delete_site_option(self::$prefix . $key);
    }

    /**
     * Check if user has a specific capability
     *
     * @param string $capability Capability name
     * @param int    $user_id    User ID (default: current user)
     * @return bool
     */
    public static function user_can(string $capability, int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Super admins have all capabilities
        if (is_super_admin($user_id)) {
            return true;
        }

        // Check custom capability lists
        $custom_caps = [
            'mxp_view_all_services',
            'mxp_manage_encryption',
            'mxp_rotate_encryption_key',
            'mxp_manage_notifications',
        ];

        if (in_array($capability, $custom_caps, true)) {
            $users_with_cap = self::get($capability . '_users', []);
            return in_array($user_id, (array) $users_with_cap, true);
        }

        // Fall back to WordPress capability check
        return user_can($user_id, $capability);
    }

    /**
     * Get all settings with their current values
     *
     * @return array
     */
    public static function get_all(): array {
        return [
            'notifications_enabled' => self::get('notifications_enabled', true),
            'notification_default_format' => self::get('notification_default_format', 'html'),
            'notification_from_name' => self::get('notification_from_name', get_bloginfo('name')),
            'notification_from_email' => self::get('notification_from_email', get_option('admin_email')),
            'mxp_view_all_services_users' => self::get('mxp_view_all_services_users', []),
            'mxp_manage_encryption_users' => self::get('mxp_manage_encryption_users', []),
        ];
    }
}
