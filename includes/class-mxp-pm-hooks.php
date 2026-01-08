<?php
/**
 * MXP Password Manager - Hooks Management Class
 *
 * Centralized management of WordPress Actions and Filters
 *
 * @package MXP_Password_Manager
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Pm_Hooks {

    /**
     * Registered actions list
     *
     * @var array
     */
    private static $actions = [
        // Service events
        'mxp_pm_service_created',
        'mxp_pm_service_updated',
        'mxp_pm_service_deleted',
        'mxp_pm_service_viewed',
        'mxp_pm_service_archived',
        'mxp_pm_service_restored',
        'mxp_pm_service_status_changed',
        // Authorization events
        'mxp_pm_auth_granted',
        'mxp_pm_auth_revoked',
        // Audit events
        'mxp_pm_audit_logged',
        // Encryption events
        'mxp_pm_before_encrypt',
        'mxp_pm_after_decrypt',
        'mxp_pm_key_rotated',
        // Notification events
        'mxp_pm_notification_sent',
        // Category events
        'mxp_pm_category_created',
        'mxp_pm_category_updated',
        'mxp_pm_category_deleted',
        // Tag events
        'mxp_pm_tag_created',
        'mxp_pm_tag_deleted',
        // Batch events
        'mxp_pm_batch_action_completed',
        // Multisite Central Control events (v3.0.0)
        'mxp_pm_service_scope_changed',
        'mxp_pm_site_access_granted',
        'mxp_pm_site_access_revoked',
        'mxp_pm_central_admin_added',
        'mxp_pm_central_admin_removed',
    ];

    /**
     * Registered filters list
     *
     * @var array
     */
    private static $filters = [
        // Encryption filters
        'mxp_pm_encrypt_fields',
        'mxp_pm_encryption_method',
        // Service data filters
        'mxp_pm_service_data',
        // Permission filters
        'mxp_pm_can_view_service',
        'mxp_pm_can_edit_service',
        'mxp_pm_can_archive_service',
        'mxp_pm_user_capabilities',
        'mxp_pm_admin_menu_capability',
        // Audit filters
        'mxp_pm_audit_log_data',
        // Notification filters
        'mxp_pm_notification_message',
        'mxp_pm_notification_subject',
        'mxp_pm_notification_recipients',
        // Settings filters
        'mxp_pm_settings_sections',
        // Search filters
        'mxp_pm_search_query',
        'mxp_pm_search_results',
        // Category/Tag filters
        'mxp_pm_default_categories',
        'mxp_pm_available_status',
        'mxp_pm_archive_retention_days',
        // Multisite Central Control filters (v3.0.0)
        'mxp_pm_can_create_global_service',
        'mxp_pm_can_manage_site_access',
        'mxp_pm_service_access_conditions',
        'mxp_pm_available_auth_users',
        // Status/Priority options
        'mxp_pm_status_options',
        'mxp_pm_priority_options',
    ];

    /**
     * Initialize hooks module
     *
     * @return void
     */
    public static function init(): void {
        // Register default filter values
        add_filter('mxp_pm_encrypt_fields', [__CLASS__, 'default_encrypt_fields']);
        add_filter('mxp_pm_default_categories', [__CLASS__, 'default_categories']);
        add_filter('mxp_pm_available_status', [__CLASS__, 'default_status']);
        add_filter('mxp_pm_archive_retention_days', [__CLASS__, 'default_archive_retention']);
        add_filter('mxp_pm_status_options', [__CLASS__, 'default_status_options']);
        add_filter('mxp_pm_priority_options', [__CLASS__, 'default_priority_options']);
    }

    /**
     * Trigger an action hook
     *
     * @param string $hook_name Hook name
     * @param mixed  ...$args   Arguments to pass
     * @return void
     */
    public static function do_action(string $hook_name, ...$args): void {
        do_action($hook_name, ...$args);
    }

    /**
     * Apply a filter hook
     *
     * @param string $hook_name Hook name
     * @param mixed  $value     Value to filter
     * @param mixed  ...$args   Additional arguments
     * @return mixed Filtered value
     */
    public static function apply_filters(string $hook_name, $value, ...$args) {
        return apply_filters($hook_name, $value, ...$args);
    }

    /**
     * Get all registered actions
     *
     * @return array
     */
    public static function get_actions(): array {
        return self::$actions;
    }

    /**
     * Get all registered filters
     *
     * @return array
     */
    public static function get_filters(): array {
        return self::$filters;
    }

    /**
     * Default encrypted fields
     *
     * @param array $fields Existing fields
     * @return array
     */
    public static function default_encrypt_fields(array $fields = []): array {
        $default = ['account', 'password', '2fa_token', 'note'];
        return array_unique(array_merge($default, $fields));
    }

    /**
     * Default categories
     *
     * @param array $categories Existing categories
     * @return array
     */
    public static function default_categories(array $categories = []): array {
        $default = [
            ['name' => '開發工具', 'icon' => 'dashicons-editor-code', 'order' => 1],
            ['name' => '雲端服務', 'icon' => 'dashicons-cloud', 'order' => 2],
            ['name' => '社交媒體', 'icon' => 'dashicons-share', 'order' => 3],
            ['name' => '金融服務', 'icon' => 'dashicons-bank', 'order' => 4],
            ['name' => '企業內部', 'icon' => 'dashicons-building', 'order' => 5],
            ['name' => '其他', 'icon' => 'dashicons-category', 'order' => 6],
        ];
        return array_merge($default, $categories);
    }

    /**
     * Default status options
     *
     * @param array $statuses Existing statuses
     * @return array
     */
    public static function default_status(array $statuses = []): array {
        $default = [
            'active' => [
                'label' => '啟用中',
                'color' => '#28a745',
                'icon' => 'dashicons-yes-alt',
            ],
            'archived' => [
                'label' => '已歸檔',
                'color' => '#6c757d',
                'icon' => 'dashicons-archive',
            ],
            'suspended' => [
                'label' => '已停用',
                'color' => '#dc3545',
                'icon' => 'dashicons-warning',
            ],
        ];
        return array_merge($default, $statuses);
    }

    /**
     * Default archive retention days
     *
     * @param int $days Current days
     * @return int
     */
    public static function default_archive_retention(int $days = 0): int {
        return $days > 0 ? $days : 180;
    }

    /**
     * Default status options for dropdowns
     *
     * @param array $options Existing options
     * @return array
     */
    public static function default_status_options(array $options = []): array {
        $default = [
            'active' => __('使用中', 'mxp-password-manager'),
            'archived' => __('已歸檔', 'mxp-password-manager'),
            'suspended' => __('已停用', 'mxp-password-manager'),
        ];
        return empty($options) ? $default : array_merge($default, $options);
    }

    /**
     * Default priority options for dropdowns
     *
     * @param array $options Existing options
     * @return array
     */
    public static function default_priority_options(array $options = []): array {
        $default = [
            1 => __('最高優先', 'mxp-password-manager'),
            2 => __('高優先', 'mxp-password-manager'),
            3 => __('一般', 'mxp-password-manager'),
            4 => __('低優先', 'mxp-password-manager'),
            5 => __('最低優先', 'mxp-password-manager'),
        ];
        return empty($options) ? $default : array_merge($default, $options);
    }
}
