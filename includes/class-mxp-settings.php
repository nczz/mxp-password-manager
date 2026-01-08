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
        // Register settings page for both multisite network admin and single site admin
        if (is_multisite()) {
            add_action('network_admin_menu', [__CLASS__, 'register_settings_page']);
        } else {
            add_action('admin_menu', [__CLASS__, 'register_settings_page']);
        }
    }

    /**
     * Register settings page (works for both network admin and regular admin)
     *
     * @return void
     */
    public static function register_settings_page(): void {
        // Only show settings page to plugin admins
        if (!self::is_plugin_admin()) {
            return;
        }

        $capability = is_multisite() ? 'manage_network_options' : 'manage_options';
        $parent_slug = is_multisite() ? 'settings.php' : 'options-general.php';

        add_submenu_page(
            $parent_slug,
            '帳號管理設定',
            '帳號管理設定',
            $capability,
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
        // Check plugin admin permission (prevent direct URL access)
        if (!self::is_plugin_admin()) {
            wp_die(__('您沒有權限存取此頁面。請聯繫外掛管理員取得存取權限。', 'mxp-password-manager'), __('存取被拒絕', 'mxp-password-manager'), ['response' => 403]);
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
                <a href="?page=mxp-account-settings&tab=updates" class="nav-tab <?php echo $active_tab === 'updates' ? 'nav-tab-active' : ''; ?>">更新設定</a>
                <?php if (is_multisite()): ?>
                <a href="?page=mxp-account-settings&tab=central_control" class="nav-tab <?php echo $active_tab === 'central_control' ? 'nav-tab-active' : ''; ?>">中控設定</a>
                <a href="?page=mxp-account-settings&tab=central_admins" class="nav-tab <?php echo $active_tab === 'central_admins' ? 'nav-tab-active' : ''; ?>">中控管理員</a>
                <?php endif; ?>
                <a href="?page=mxp-account-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">進階設定</a>
            </nav>

            <form method="post" action="<?php echo esc_url(self::get_form_action_url()); ?>">
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
                    case 'central_control':
                        if (is_multisite()) {
                            self::render_central_control_tab();
                        }
                        break;
                    case 'central_admins':
                        if (is_multisite()) {
                            self::render_central_admins_tab();
                        }
                        break;
                    case 'advanced':
                        self::render_advanced_tab();
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

        <?php if ($source === 'none'): ?>
            <h3>金鑰設定</h3>
            <div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                <p style="margin: 0; color: #856404;">
                    <strong>⚠ 尚未設定加密金鑰</strong><br>
                    請選擇以下其中一種方式設定金鑰，設定後此區塊將不再顯示。
                </p>
            </div>

            <h4>方式一：wp-config.php 常數 (推薦)</h4>
            <p>在 wp-config.php 中加入以下程式碼：</p>
            <pre style="background: #23282d; color: #fff; padding: 10px; overflow-x: auto; user-select: all;">define('MXP_ENCRYPTION_KEY', '<?php echo esc_html(Mxp_Encryption::generate_key()); ?>');</pre>
            <p class="description" style="color: #dc3545;">
                <strong>重要：</strong>請立即複製上方金鑰到 wp-config.php，此金鑰僅顯示一次，頁面重新整理後將產生新的金鑰。
            </p>

            <h4 style="margin-top: 20px;">方式二：環境變數</h4>
            <pre style="background: #23282d; color: #fff; padding: 10px; overflow-x: auto;">export MXP_ENCRYPTION_KEY="your-base64-encoded-key"</pre>

            <h4 style="margin-top: 20px;">方式三：自動產生並儲存到資料庫</h4>
            <table class="form-table" style="margin-top: 0;">
                <tr>
                    <td style="padding-left: 0;">
                        <label>
                            <input type="checkbox" name="generate_key" value="1">
                            自動產生並儲存新的加密金鑰到資料庫
                        </label>
                        <p class="description">
                            此方式較不安全，建議優先使用 wp-config.php 常數方式。
                        </p>
                    </td>
                </tr>
            </table>
        <?php elseif ($is_configured): ?>
            <div style="background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin-top: 20px;">
                <p style="margin: 0; color: #155724;">
                    <strong>✓ 加密金鑰已設定完成</strong><br>
                    金鑰來源：<?php echo esc_html($source_labels[$source] ?? '未知'); ?>
                </p>
            </div>
        <?php endif; ?>
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
        $plugin_admins = self::get('plugin_admins', []);
        $view_all_users = self::get('mxp_view_all_services_users', []);
        $manage_encryption_users = self::get('mxp_manage_encryption_users', []);
        $add_service_capability = mxp_pm_get_option('mxp_add_service_capability', 'manage_options');

        // Get all users for selection
        $user_args = is_multisite() ? ['blog_id' => 0, 'number' => 100] : ['number' => 100];
        $all_users = get_users($user_args);

        // Get available roles for capability selection
        $available_capabilities = [
            'manage_options' => '管理員 (Administrator) - manage_options',
            'edit_others_posts' => '編輯者 (Editor) - edit_others_posts',
            'publish_posts' => '作者 (Author) - publish_posts',
            'edit_posts' => '投稿者 (Contributor) - edit_posts',
            'read' => '訂閱者 (Subscriber) - read',
        ];
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    外掛管理員
                    <span style="color: #d63638;">*</span>
                </th>
                <td>
                    <select name="mxp_plugin_admins[]" multiple class="regular-text" style="height: 150px;">
                        <?php foreach ($all_users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>" <?php echo in_array($user->ID, (array) $plugin_admins) ? 'selected' : ''; ?>>
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description" style="color: #d63638;">
                        <strong>重要：</strong>只有被選中的使用者才能存取「帳號密碼管理」頁面。<br>
                        即使是 WordPress 管理員，若未被選中也無法存取此外掛功能。
                    </p>
                    <p class="description">
                        <?php if (is_multisite()): ?>
                            超級管理員 (Super Admin) 不受此限制，預設擁有完整存取權限。
                        <?php else: ?>
                            首次設定時，請務必將自己加入此清單，否則將無法再次存取設定頁面。
                        <?php endif; ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">新增服務權限</th>
                <td>
                    <select name="mxp_add_service_capability" class="regular-text">
                        <?php foreach ($available_capabilities as $cap => $label): ?>
                            <option value="<?php echo esc_attr($cap); ?>" <?php selected($add_service_capability, $cap); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">選擇具有此權限等級以上的使用者才能新增服務。超級管理員預設擁有此權限。</p>
                </td>
            </tr>
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
     * Render central control settings tab (Multisite only)
     *
     * @return void
     */
    private static function render_central_control_tab(): void {
        $central_control_enabled = mxp_pm_get_option('mxp_central_control_enabled', false);
        $default_scope = mxp_pm_get_option('mxp_default_service_scope', 'global');
        $site_can_create_global = mxp_pm_get_option('mxp_site_can_create_global', true);
        ?>
        <h2>中控功能設定</h2>
        <p class="description">配置多站台網路的中央控制功能。</p>

        <table class="form-table">
            <tr>
                <th scope="row">啟用中控模式</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_central_control_enabled" value="1" <?php checked($central_control_enabled); ?>>
                        啟用跨站台中央控制功能
                    </label>
                    <p class="description">啟用後，中控管理員可以管理所有站台的服務帳號。</p>
                </td>
            </tr>
            <tr>
                <th scope="row">預設服務範圍</th>
                <td>
                    <select name="mxp_default_service_scope">
                        <option value="global" <?php selected($default_scope, 'global'); ?>>全域共享 - 所有站台可見</option>
                        <option value="site" <?php selected($default_scope, 'site'); ?>>站台專屬 - 僅限建立站台可見</option>
                    </select>
                    <p class="description">新增服務時的預設範圍設定。</p>
                </td>
            </tr>
            <tr>
                <th scope="row">站台建立全域服務</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_site_can_create_global" value="1" <?php checked($site_can_create_global); ?>>
                        允許各站台建立全域共享服務
                    </label>
                    <p class="description">關閉後，只有中控管理員可以建立全域共享服務。</p>
                </td>
            </tr>
        </table>

        <h3>範圍說明</h3>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
            <p><strong>全域共享 (Global)</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>服務對所有站台可見（預設行為）</li>
                <li>可透過「站台存取控制」限制特定站台的存取</li>
                <li>授權可跨站台授予使用者</li>
            </ul>

            <p style="margin-top: 15px;"><strong>站台專屬 (Site)</strong></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>服務僅在建立它的站台可見</li>
                <li>只能授權給該站台的使用者</li>
                <li>中控管理員仍可檢視和管理</li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render central admins settings tab (Multisite only)
     *
     * @return void
     */
    private static function render_central_admins_tab(): void {
        $central_admins = Mxp_Multisite::get_central_admins();
        $network_users = Mxp_Multisite::get_network_users(200);
        ?>
        <h2>中控管理員設定</h2>
        <p class="description">管理具有跨站台權限的中控管理員。超級管理員自動擁有最高權限。</p>

        <h3>現有中控管理員</h3>
        <?php if (empty($central_admins)): ?>
            <p><em>目前沒有設定中控管理員（超級管理員除外）。</em></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>使用者</th>
                        <th>Email</th>
                        <th>權限層級</th>
                        <th>新增時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($central_admins as $admin): ?>
                        <tr>
                            <td><?php echo esc_html($admin['user_data']['display_name']); ?></td>
                            <td><?php echo esc_html($admin['user_data']['user_email']); ?></td>
                            <td><?php echo esc_html(Mxp_Multisite::get_level_label($admin['permission_level'])); ?></td>
                            <td><?php echo esc_html($admin['created_time']); ?></td>
                            <td>
                                <label>
                                    <input type="checkbox" name="remove_central_admins[]" value="<?php echo esc_attr($admin['user_id']); ?>">
                                    移除
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3 style="margin-top: 30px;">新增中控管理員</h3>
        <table class="form-table">
            <tr>
                <th scope="row">選擇使用者</th>
                <td>
                    <select name="new_central_admin_user" class="regular-text">
                        <option value="">-- 選擇使用者 --</option>
                        <?php
                        $existing_admin_ids = array_column($central_admins, 'user_id');
                        foreach ($network_users as $user):
                            if (in_array($user->ID, $existing_admin_ids) || is_super_admin($user->ID)) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">權限層級</th>
                <td>
                    <select name="new_central_admin_level">
                        <option value="viewer"><?php echo esc_html(Mxp_Multisite::get_level_label('viewer')); ?> - 可查看所有服務</option>
                        <option value="editor"><?php echo esc_html(Mxp_Multisite::get_level_label('editor')); ?> - 可查看和編輯所有服務</option>
                        <option value="admin"><?php echo esc_html(Mxp_Multisite::get_level_label('admin')); ?> - 完全控制（含授權管理）</option>
                    </select>
                </td>
            </tr>
        </table>

        <h3>權限層級說明</h3>
        <div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #ddd;">
                        <th style="text-align: left; padding: 8px;">權限</th>
                        <th style="text-align: center; padding: 8px;">檢視者</th>
                        <th style="text-align: center; padding: 8px;">編輯者</th>
                        <th style="text-align: center; padding: 8px;">管理員</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 8px;">查看所有服務</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                    </tr>
                    <tr style="background: #fff;">
                        <td style="padding: 8px;">編輯所有服務</td>
                        <td style="text-align: center; padding: 8px;">✗</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px;">跨站台授權</td>
                        <td style="text-align: center; padding: 8px;">✗</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                    </tr>
                    <tr style="background: #fff;">
                        <td style="padding: 8px;">管理中控設定</td>
                        <td style="text-align: center; padding: 8px;">✗</td>
                        <td style="text-align: center; padding: 8px;">✗</td>
                        <td style="text-align: center; padding: 8px;">✓</td>
                    </tr>
                </tbody>
            </table>
        </div>
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

        // Check permission based on environment
        $has_permission = is_multisite() ? is_super_admin() : current_user_can('manage_options');
        if (!$has_permission) {
            return;
        }

        $tab = sanitize_key($_POST['tab'] ?? 'encryption');

        switch ($tab) {
            case 'encryption':
                // Generate new key if requested
                if (!empty($_POST['generate_key'])) {
                    $new_key = Mxp_Encryption::generate_key();
                    mxp_pm_update_option('mxp_encryption_key', $new_key);
                }
                break;

            case 'notifications':
                self::update('notifications_enabled', !empty($_POST['notifications_enabled']));
                self::update('notification_default_format', sanitize_key($_POST['notification_default_format'] ?? 'html'));
                self::update('notification_from_name', sanitize_text_field($_POST['notification_from_name'] ?? ''));
                self::update('notification_from_email', sanitize_email($_POST['notification_from_email'] ?? ''));
                break;

            case 'permissions':
                // Save plugin admins
                $plugin_admins = isset($_POST['mxp_plugin_admins']) ? array_map('absint', $_POST['mxp_plugin_admins']) : [];
                self::update('plugin_admins', $plugin_admins);

                $view_all_users = isset($_POST['mxp_view_all_services_users']) ? array_map('absint', $_POST['mxp_view_all_services_users']) : [];
                $manage_encryption_users = isset($_POST['mxp_manage_encryption_users']) ? array_map('absint', $_POST['mxp_manage_encryption_users']) : [];

                self::update('mxp_view_all_services_users', $view_all_users);
                self::update('mxp_manage_encryption_users', $manage_encryption_users);

                // Save add service capability setting
                $add_service_capability = sanitize_key($_POST['mxp_add_service_capability'] ?? 'manage_options');
                $valid_capabilities = ['manage_options', 'edit_others_posts', 'publish_posts', 'edit_posts', 'read'];
                if (in_array($add_service_capability, $valid_capabilities, true)) {
                    mxp_pm_update_option('mxp_add_service_capability', $add_service_capability);
                }
                break;

            case 'central_control':
                if (is_multisite()) {
                    mxp_pm_update_option('mxp_central_control_enabled', !empty($_POST['mxp_central_control_enabled']));
                    mxp_pm_update_option('mxp_default_service_scope', sanitize_key($_POST['mxp_default_service_scope'] ?? 'global'));
                    mxp_pm_update_option('mxp_site_can_create_global', !empty($_POST['mxp_site_can_create_global']));
                }
                break;

            case 'central_admins':
                if (is_multisite()) {
                    // Remove selected central admins
                    if (!empty($_POST['remove_central_admins'])) {
                        foreach ($_POST['remove_central_admins'] as $user_id) {
                            Mxp_Multisite::remove_central_admin(absint($user_id));
                        }
                    }
                    
                    // Add new central admin
                    if (!empty($_POST['new_central_admin_user'])) {
                        $new_user_id = absint($_POST['new_central_admin_user']);
                        $new_level = sanitize_key($_POST['new_central_admin_level'] ?? 'viewer');
                        
                        if (in_array($new_level, ['viewer', 'editor', 'admin'], true)) {
                            Mxp_Multisite::add_central_admin($new_user_id, $new_level);
                        }
                    }
                }
                break;

            case 'updates':
                $github_repo = sanitize_text_field($_POST['mxp_github_repo'] ?? '');
                $github_token = sanitize_text_field($_POST['mxp_github_access_token'] ?? '');
                $auto_update_enabled = !empty($_POST['mxp_auto_update_enabled']);
                $allow_beta_updates = !empty($_POST['mxp_allow_beta_updates']);
                $cache_duration = absint($_POST['mxp_update_check_interval'] ?? 43200);

                // Validate GitHub repository format if provided
                if (!empty($github_repo) && !preg_match('/^[a-z0-9._-]+\/[a-z0-9._-]+$/i', $github_repo)) {
                    wp_die(__('GitHub repository 格式不正確。請使用 owner/repo 格式。', 'mxp-password-manager'));
                }

                // Validate GitHub token format if provided
                if (!empty($github_token)) {
                    if (strpos($github_token, 'ghp_') !== 0 && strpos($github_token, 'github_pat_') !== 0) {
                        wp_die(__('GitHub Token 格式不正確。', 'mxp-password-manager'));
                    }
                }

                // Save settings (only save repo if provided, otherwise use default)
                if (!empty($github_repo)) {
                    mxp_pm_update_option('mxp_github_repo', $github_repo);
                }
                if (!empty($github_token)) {
                    mxp_pm_update_option('mxp_github_access_token', $github_token);
                }
                mxp_pm_update_option('mxp_auto_update_enabled', $auto_update_enabled);
                mxp_pm_update_option('mxp_allow_beta_updates', $allow_beta_updates);
                mxp_pm_update_option('mxp_update_check_interval', $cache_duration);

                break;

            case 'advanced':
                mxp_pm_update_option('mxp_delete_data_on_uninstall', !empty($_POST['mxp_delete_data_on_uninstall']));
                break;
        }

        // Redirect back with success message
        wp_redirect(add_query_arg([
            'page' => 'mxp-account-settings',
            'tab' => $tab,
            'updated' => 'true',
        ], self::get_settings_page_url()));
        exit;
    }

    /**
     * Get form action URL based on environment
     *
     * @return string
     */
    private static function get_form_action_url(): string {
        if (is_multisite()) {
            return network_admin_url('edit.php?action=mxp_save_settings');
        }
        return admin_url('admin-post.php?action=mxp_save_settings');
    }

    /**
     * Get settings page URL based on environment
     *
     * @return string
     */
    private static function get_settings_page_url(): string {
        if (is_multisite()) {
            return network_admin_url('settings.php');
        }
        return admin_url('options-general.php');
    }

    /**
     * Get a setting value
     *
     * @param string $key     Setting key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        return mxp_pm_get_option(self::$prefix . $key, $default);
    }

    /**
     * Update a setting value
     *
     * @param string $key   Setting key
     * @param mixed  $value Setting value
     * @return bool
     */
    public static function update(string $key, $value): bool {
        return mxp_pm_update_option(self::$prefix . $key, $value);
    }

    /**
     * Delete a setting
     *
     * @param string $key Setting key
     * @return bool
     */
    public static function delete(string $key): bool {
        return mxp_pm_delete_option(self::$prefix . $key);
    }

    /**
     * Check if user is a plugin admin (can access the plugin)
     *
     * @param int $user_id User ID (default: current user)
     * @return bool
     */
    public static function is_plugin_admin(int $user_id = 0): bool {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Only in Multisite: Super admins always have access
        // In single site, is_super_admin() returns true for all admins, so we skip this check
        if (is_multisite() && is_super_admin($user_id)) {
            return true;
        }

        // Check if user is in the plugin admins list
        $plugin_admins = self::get('plugin_admins', []);

        // If no plugin admins are set, allow all users with manage_options capability (for initial setup)
        if (empty($plugin_admins)) {
            return user_can($user_id, 'manage_options');
        }

        return in_array($user_id, (array) $plugin_admins, true);
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

    /**
     * Render advanced settings tab
     *
     * @return void
     */
    private static function render_advanced_tab(): void {
        $delete_data_on_uninstall = mxp_pm_get_option('mxp_delete_data_on_uninstall', false);
        ?>
        <h2>進階設定</h2>

        <table class="form-table">
            <tr>
                <th scope="row">刪除外掛時清除資料</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_delete_data_on_uninstall" value="1" <?php checked($delete_data_on_uninstall, true); ?>>
                        刪除外掛時一併刪除所有資料
                    </label>
                    <p class="description" style="color: #d63638;">
                        <strong>警告：</strong>啟用此選項後，當外掛被刪除時，以下資料將被永久清除：
                    </p>
                    <ul style="list-style: disc; margin-left: 20px; color: #666;">
                        <li>所有服務帳號資料（包含加密的帳號、密碼、2FA 金鑰等）</li>
                        <li>所有分類與標籤</li>
                        <li>所有使用者授權設定</li>
                        <li>所有操作稽核日誌</li>
                        <li>加密金鑰與外掛設定</li>
                        <?php if (is_multisite()): ?>
                        <li>站台存取控制設定</li>
                        <li>中控管理員設定</li>
                        <?php endif; ?>
                    </ul>
                    <p class="description">此操作<strong>無法復原</strong>，請確保已備份重要資料。</p>
                </td>
            </tr>
        </table>

        <hr>

        <h3>資料庫資訊</h3>
        <table class="form-table">
            <tr>
                <th scope="row">資料表前綴</th>
                <td><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?></code></td>
            </tr>
            <tr>
                <th scope="row">資料表列表</th>
                <td>
                    <ul style="margin: 0;">
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_service_list</code> - 服務帳號資料</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_service_categories</code> - 服務分類</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_service_tags</code> - 服務標籤</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_service_tag_map</code> - 服務標籤對應</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_auth_list</code> - 使用者授權</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_audit_log</code> - 操作稽核日誌</li>
                        <?php if (is_multisite()): ?>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_site_access</code> - 站台存取控制</li>
                        <li><code><?php echo esc_html(mxp_pm_get_table_prefix()); ?>to_central_admins</code> - 中控管理員</li>
                        <?php endif; ?>
                    </ul>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render updates settings tab
     *
     * @return void
     */
    private static function render_updates_tab(): void {
        $github_repo = mxp_pm_get_option('mxp_github_repo', '');
        $github_token = mxp_pm_get_option('mxp_github_access_token', '');
        $auto_update_enabled = mxp_pm_get_option('mxp_auto_update_enabled', true);
        $allow_beta_updates = mxp_pm_get_option('mxp_allow_beta_updates', false);
        $cache_duration = mxp_pm_get_option('mxp_update_check_interval', 43200);

        // Default repository (from constant)
        $default_repo = defined('MXP_GITHUB_REPO') ? MXP_GITHUB_REPO : 'nczz/mxp-password-manager';

        // Mask token for display
        $masked_token = $github_token ? substr($github_token, 0, 7) . '...' : '';

        // Get update status
        $update_check = mxp_pm_get_option('mxp-password-manager_update_check', []);
        $last_check = $update_check['last_check'] ?? '';
        $latest_version = $update_check['latest_version'] ?? '';
        $update_available = $update_check['update_available'] ?? false;
        ?>
        <h2>更新設定</h2>

        <div id="mxp-update-status" style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 20px;">
            <h3 style="margin-top: 0;">更新狀態</h3>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 5px;"><strong>目前版本：</strong></td>
                    <td style="padding: 5px;"><?php echo esc_html(MXP_PM_VERSION); ?></td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>最新版本：</strong></td>
                    <td style="padding: 5px;">
                        <?php if ($latest_version): ?>
                            <?php echo esc_html($latest_version); ?>
                            <?php if ($update_available): ?>
                                <span style="color: #dc3545; margin-left: 10px;">🚀 有新版本可用！</span>
                            <?php else: ?>
                                <span style="color: #28a745; margin-left: 10px;">✓ 已是最新版本</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em>尚未檢查</em>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 5px;"><strong>最後檢查時間：</strong></td>
                    <td style="padding: 5px;">
                        <?php echo $last_check ? esc_html($last_check) : '<em>從未檢查</em>'; ?>
                    </td>
                </tr>
            </table>

            <p style="margin-top: 15px;">
                <button type="button" class="button button-primary" id="mxp-check-update-btn">
                    立即檢查更新
                </button>
                <span id="mxp-check-update-status" style="margin-left: 10px;"></span>
            </p>
        </div>

        <h3 style="margin-top: 30px;">GitHub 更新設定</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    GitHub Repository
                </th>
                <td>
                    <input type="text"
                           name="mxp_github_repo"
                           value="<?php echo esc_attr($github_repo); ?>"
                           class="regular-text"
                           placeholder="<?php echo esc_attr($default_repo); ?>">
                    <p class="description">
                        您的 GitHub repository 格式：owner/repo（例如：username/mxp-password-manager）<br>
                        <strong>留空使用默認值：</strong> <code><?php echo esc_html($default_repo); ?></code>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">GitHub Token</th>
                <td>
                    <input type="password"
                           name="mxp_github_access_token"
                           value="<?php echo esc_attr($github_token); ?>"
                           class="regular-text"
                           placeholder="github_pat_...">
                    <p class="description">
                        GitHub Personal Access Token（可選）。提供 token 可以提高 API 限制（從 60 次/小時到 5,000 次/小時）。
                        <br>
                        推薦在 wp-config.php 中設定：define('MXP_GITHUB_ACCESS_TOKEN', 'your_token_here');
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">啟用自動更新</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_auto_update_enabled" value="1" <?php checked($auto_update_enabled, true); ?>>
                        啟用從 GitHub 自動更新檢查
                    </label>
                    <p class="description">
                        啟用後，WordPress 將定期檢查是否有新版本可用。
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">包含 Beta 版本</th>
                <td>
                    <label>
                        <input type="checkbox" name="mxp_allow_beta_updates" value="1" <?php checked($allow_beta_updates, true); ?>>
                        接收預發布（Beta）版本更新
                    </label>
                    <p class="description">
                        Beta 版本可能不穩定，僅建議用於測試環境。
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">更新檢查間隔</th>
                <td>
                    <select name="mxp_update_check_interval">
                        <option value="3600" <?php selected($cache_duration, 3600); ?>>1 小時</option>
                        <option value="7200" <?php selected($cache_duration, 7200); ?>>2 小時</option>
                        <option value="21600" <?php selected($cache_duration, 21600); ?>>6 小時</option>
                        <option value="43200" <?php selected($cache_duration, 43200); ?>>12 小時（推薦）</option>
                        <option value="86400" <?php selected($cache_duration, 86400); ?>>24 小時</option>
                    </select>
                    <p class="description">
                        設定多久檢查一次更新。較短的間隔會更頻繁地檢查，但可能增加 GitHub API 負載。
                    </p>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 30px;">使用說明</h3>
        <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0366d6; margin-bottom: 20px;">
            <h4 style="margin-top: 0;">📌 如何使用 GitHub 更新</h4>
            <ol style="line-height: 1.8;">
                <li><strong>無需設定：</strong>安裝後會自動使用默認的 GitHub repository</li>
                <li>（可選）在上方自定義 GitHub repository</li>
                <li>（可選）添加 GitHub Personal Access Token 以提高 API 限制</li>
                <li>在您的 GitHub repository 中建立 Releases</li>
                <li>使用語義化版本標籤：v1.0.0, v1.1.0 等</li>
                <li>在 Release 中附加外掛 ZIP 文件</li>
                <li>撰寫更新日誌（Markdown 格式）</li>
            </ol>
            <h4>🎯 自動更新流程</h4>
            <ul style="line-height: 1.8;">
                <li>WordPress 定期檢查 GitHub Releases</li>
                <li>發現新版本時，在外掛頁面顯示「更新」按鈕</li>
                <li>點擊「更新」按鈕下載並安裝新版本</li>
                <li>安裝完成後，自動執行資料庫遷移（如有）</li>
                <li>更新成功後清除緩存</li>
            </ul>
            <h4>💡 API 限制說明</h4>
            <ul style="line-height: 1.8;">
                <li><strong>無 Token：</strong>60 次/小時（對於自動更新檢查已足夠）</li>
                <li><strong>有 Token：</strong>5,000 次/小時（適用於需要頻繁檢查的場景）</li>
                <li>Token 是可選的，不影響基本的更新功能</li>
            </ul>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mxp-check-update-btn').on('click', function() {
                var btn = $(this);
                var status = $('#mxp-check-update-status');
                
                btn.prop('disabled', true).text('檢查中...');
                status.text('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mxp_check_updates',
                        nonce: '<?php echo wp_create_nonce('mxp_github_updater_nonce', 'mxp_check_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var data = response.data;
                            
                            if (data.update_available) {
                                status.html('<span style="color: #dc3545;">✓ 發現新版本：' + data.latest_version + '</span>');
                                
                                // Refresh update status
                                $('#mxp-update-status td:nth-child(2) .latest-version').text(data.latest_version);
                                $('#mxp-update-status .new-version-badge').show();
                            } else {
                                status.html('<span style="color: #28a745;">✓ 已是最新版本</span>');
                            }
                        } else {
                            status.html('<span style="color: #dc3545;">✗ ' + (response.data.message || '檢查失敗') + '</span>');
                        }
                        
                        btn.prop('disabled', false).text('立即檢查更新');
                    },
                    error: function() {
                        status.html('<span style="color: #dc3545;">✗ 網路錯誤</span>');
                        btn.prop('disabled', false).text('立即檢查更新');
                    }
                });
            });
        });
        </script>
        <?php
    }
}
