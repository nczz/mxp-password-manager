<?php
/**
 * MXP Password Manager - Notification Module
 *
 * Email notification system with HTML and plain text support
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Pm_Notification {

    /**
     * Notification type constants
     */
    const NOTIFY_AUTH_GRANTED = 'auth_granted';
    const NOTIFY_AUTH_REVOKED = 'auth_revoked';
    const NOTIFY_SERVICE_UPDATED = 'service_updated';
    const NOTIFY_PASSWORD_CHANGED = 'password_changed';
    const NOTIFY_SERVICE_CREATED = 'service_created';

    /**
     * Send notification to a single user
     *
     * @param int    $user_id User ID
     * @param string $type    Notification type
     * @param array  $data    Notification data
     * @return bool Success status
     */
    public static function send_to_user(int $user_id, string $type, array $data): bool {
        // Check if should notify
        if (!self::should_notify_user($user_id, $type)) {
            return false;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        // Get user preferred format
        $format = self::get_preferred_format($user_id);

        // Prepare template data
        $data = array_merge($data, [
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'timestamp' => current_time('Y-m-d H:i:s'),
        ]);

        // Get subject
        $subject = self::get_subject($type, $data);
        $subject = Mxp_Pm_Hooks::apply_filters('mxp_pm_notification_subject', $subject, $type);

        // Get content
        $content = self::get_template($type, $data, $format);
        $content = Mxp_Pm_Hooks::apply_filters('mxp_pm_notification_message', $content, $type, $data);

        // Set headers based on format
        $headers = [];
        if ($format === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }

        // Add from header if configured
        $from_name = Mxp_Pm_Settings::get('notification_from_name', get_bloginfo('name'));
        $from_email = Mxp_Pm_Settings::get('notification_from_email', get_option('admin_email'));
        $headers[] = "From: {$from_name} <{$from_email}>";

        // Send email
        $result = wp_mail($user->user_email, $subject, $content, $headers);

        // Trigger hook
        Mxp_Pm_Hooks::do_action('mxp_pm_notification_sent', $user_id, $type, $result);

        return $result;
    }

    /**
     * Send notification to all authorized users of a service
     *
     * @param int    $service_id Service ID
     * @param string $type       Notification type
     * @param array  $data       Notification data
     * @param int    $exclude_user_id User ID to exclude (optional)
     * @return array Results keyed by user ID
     */
    public static function send_to_service_users(int $service_id, string $type, array $data, int $exclude_user_id = 0): array {
        global $wpdb;

        // Get authorized users
        $prefix = mxp_pm_get_table_prefix();
        $users = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$prefix}mxp_pm_auth_list WHERE service_id = %d",
            $service_id
        ));

        // Apply filter to recipients
        $users = Mxp_Pm_Hooks::apply_filters('mxp_pm_notification_recipients', $users, $service_id, $type);

        // Remove excluded user
        if ($exclude_user_id > 0) {
            $users = array_filter($users, function ($id) use ($exclude_user_id) {
                return (int) $id !== $exclude_user_id;
            });
        }

        $results = [];
        foreach ($users as $user_id) {
            $results[(int) $user_id] = self::send_to_user((int) $user_id, $type, $data);
        }

        return $results;
    }

    /**
     * Get email template content
     *
     * @param string $template_name Template name
     * @param array  $data          Template data
     * @param string $format        Format ('html' or 'text')
     * @return string Rendered content
     */
    public static function get_template(string $template_name, array $data, string $format = 'html'): string {
        $template_path = plugin_dir_path(dirname(__FILE__)) .
            "assets/templates/emails/{$format}/{$template_name}.php";

        if (!file_exists($template_path)) {
            // Fallback to text format if HTML not found
            if ($format === 'html') {
                $template_path = plugin_dir_path(dirname(__FILE__)) .
                    "assets/templates/emails/text/{$template_name}.php";
            }

            if (!file_exists($template_path)) {
                return self::get_fallback_template($template_name, $data, $format);
            }
        }

        // Extract data variables for template
        extract($data, EXTR_SKIP);

        ob_start();
        include $template_path;
        return ob_get_clean();
    }

    /**
     * Get fallback template when file not found
     *
     * @param string $template_name Template name
     * @param array  $data          Template data
     * @param string $format        Format
     * @return string
     */
    private static function get_fallback_template(string $template_name, array $data, string $format): string {
        $service_name = $data['service_name'] ?? '';
        $user_name = $data['user_name'] ?? '';
        $site_name = $data['site_name'] ?? '';
        $action_by = $data['action_by'] ?? '';
        $timestamp = $data['timestamp'] ?? '';

        $messages = [
            self::NOTIFY_AUTH_GRANTED => "您好 {$user_name}，\n\n您已獲得「{$service_name}」的存取權限。\n授權者：{$action_by}\n授權時間：{$timestamp}",
            self::NOTIFY_AUTH_REVOKED => "您好 {$user_name}，\n\n您的「{$service_name}」存取權限已被移除。\n操作者：{$action_by}\n操作時間：{$timestamp}",
            self::NOTIFY_SERVICE_UPDATED => "您好 {$user_name}，\n\n「{$service_name}」的資訊已更新。\n更新者：{$action_by}\n更新時間：{$timestamp}",
            self::NOTIFY_PASSWORD_CHANGED => "您好 {$user_name}，\n\n「{$service_name}」的密碼已變更。\n變更者：{$action_by}\n變更時間：{$timestamp}",
            self::NOTIFY_SERVICE_CREATED => "您好 {$user_name}，\n\n新服務「{$service_name}」已建立，您已被授權存取。\n建立者：{$action_by}\n建立時間：{$timestamp}",
        ];

        $message = $messages[$template_name] ?? "來自 {$site_name} 的帳號管理通知";

        if ($format === 'html') {
            return "<html><body><p>" . nl2br(esc_html($message)) . "</p></body></html>";
        }

        return $message;
    }

    /**
     * Get email subject
     *
     * @param string $type Notification type
     * @param array  $data Template data
     * @return string
     */
    private static function get_subject(string $type, array $data): string {
        $site_name = $data['site_name'] ?? get_bloginfo('name');
        $service_name = $data['service_name'] ?? '';

        $subjects = [
            self::NOTIFY_AUTH_GRANTED => "[{$site_name}] 您已獲得「{$service_name}」的存取權限",
            self::NOTIFY_AUTH_REVOKED => "[{$site_name}] 您的「{$service_name}」存取權限已被移除",
            self::NOTIFY_SERVICE_UPDATED => "[{$site_name}]「{$service_name}」的資訊已更新",
            self::NOTIFY_PASSWORD_CHANGED => "[{$site_name}]「{$service_name}」的密碼已變更",
            self::NOTIFY_SERVICE_CREATED => "[{$site_name}] 新服務「{$service_name}」已建立",
        ];

        return $subjects[$type] ?? "[{$site_name}] 帳號管理通知";
    }

    /**
     * Get user notification preferences
     *
     * @param int $user_id User ID
     * @return array Preferences
     */
    public static function get_user_preferences(int $user_id): array {
        return [
            'format' => get_user_meta($user_id, 'mxp_pm_notification_format', true) ?: 'html',
            'auth_change' => get_user_meta($user_id, 'mxp_pm_notify_auth_change', true) !== '0',
            'password_change' => get_user_meta($user_id, 'mxp_pm_notify_password_change', true) !== '0',
            'service_update' => get_user_meta($user_id, 'mxp_pm_notify_service_update', true) === '1',
        ];
    }

    /**
     * Check if should notify a user
     *
     * @param int    $user_id User ID
     * @param string $type    Notification type
     * @return bool
     */
    public static function should_notify_user(int $user_id, string $type): bool {
        // Check global notification setting
        if (!Mxp_Pm_Settings::get('notifications_enabled', true)) {
            return false;
        }

        $prefs = self::get_user_preferences($user_id);

        // Check if user disabled all notifications
        if ($prefs['format'] === 'none') {
            return false;
        }

        // Check specific notification type
        switch ($type) {
            case self::NOTIFY_AUTH_GRANTED:
            case self::NOTIFY_AUTH_REVOKED:
                return $prefs['auth_change'];

            case self::NOTIFY_PASSWORD_CHANGED:
                return $prefs['password_change'];

            case self::NOTIFY_SERVICE_UPDATED:
            case self::NOTIFY_SERVICE_CREATED:
                return $prefs['service_update'];

            default:
                return true;
        }
    }

    /**
     * Get user's preferred email format
     *
     * @param int $user_id User ID
     * @return string 'html' or 'text'
     */
    public static function get_preferred_format(int $user_id): string {
        $format = get_user_meta($user_id, 'mxp_pm_notification_format', true);

        if (in_array($format, ['html', 'text'], true)) {
            return $format;
        }

        // Return default format from settings
        return Mxp_Pm_Settings::get('notification_default_format', 'html');
    }

    /**
     * Save user notification preferences
     *
     * @param int   $user_id User ID
     * @param array $prefs   Preferences to save
     * @return bool
     */
    public static function save_user_preferences(int $user_id, array $prefs): bool {
        $valid_formats = ['html', 'text', 'none'];

        if (isset($prefs['format']) && in_array($prefs['format'], $valid_formats, true)) {
            update_user_meta($user_id, 'mxp_pm_notification_format', $prefs['format']);
        }

        if (isset($prefs['auth_change'])) {
            update_user_meta($user_id, 'mxp_pm_notify_auth_change', $prefs['auth_change'] ? '1' : '0');
        }

        if (isset($prefs['password_change'])) {
            update_user_meta($user_id, 'mxp_pm_notify_password_change', $prefs['password_change'] ? '1' : '0');
        }

        if (isset($prefs['service_update'])) {
            update_user_meta($user_id, 'mxp_pm_notify_service_update', $prefs['service_update'] ? '1' : '0');
        }

        return true;
    }

    /**
     * Get all notification types
     *
     * @return array
     */
    public static function get_notification_types(): array {
        return [
            self::NOTIFY_AUTH_GRANTED => [
                'label' => '授權新增通知',
                'description' => '當您獲得新服務的存取權限時通知',
            ],
            self::NOTIFY_AUTH_REVOKED => [
                'label' => '授權移除通知',
                'description' => '當您的服務存取權限被移除時通知',
            ],
            self::NOTIFY_SERVICE_UPDATED => [
                'label' => '服務更新通知',
                'description' => '當您有權存取的服務資訊更新時通知',
            ],
            self::NOTIFY_PASSWORD_CHANGED => [
                'label' => '密碼變更通知',
                'description' => '當您有權存取的服務密碼變更時通知',
            ],
            self::NOTIFY_SERVICE_CREATED => [
                'label' => '新服務通知',
                'description' => '當新服務建立且您被授權存取時通知',
            ],
        ];
    }
}
