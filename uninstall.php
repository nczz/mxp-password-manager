<?php
/**
 * MXP Password Manager - Uninstall Script
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data if the user has enabled the option.
 *
 * @package MXP_Password_Manager
 * @since 3.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Get option value (compatible with both Multisite and single site)
 *
 * @param string $option  Option name
 * @param mixed  $default Default value
 * @return mixed Option value
 */
function mxp_pm_uninstall_get_option(string $option, $default = false) {
    return is_multisite() ? get_site_option($option, $default) : get_option($option, $default);
}

/**
 * Delete option value (compatible with both Multisite and single site)
 *
 * @param string $option Option name
 * @return bool Success
 */
function mxp_pm_uninstall_delete_option(string $option): bool {
    return is_multisite() ? delete_site_option($option) : delete_option($option);
}

/**
 * Get the correct table prefix for both Multisite and single site installations
 *
 * @return string Table prefix
 */
function mxp_pm_uninstall_get_table_prefix(): string {
    global $wpdb;
    return is_multisite() ? $wpdb->base_prefix : $wpdb->prefix;
}

// Check if user opted to delete all data
$delete_data = mxp_pm_uninstall_get_option('mxp_pm_delete_data_on_uninstall', false);

if (!$delete_data) {
    // User did not opt to delete data, exit
    return;
}

global $wpdb;
$prefix = mxp_pm_uninstall_get_table_prefix();

// Drop all plugin tables
$tables = [
    'mxp_pm_service_list',
    'mxp_pm_service_categories',
    'mxp_pm_service_tags',
    'mxp_pm_service_tag_map',
    'mxp_pm_auth_list',
    'mxp_pm_audit_log',
    'mxp_pm_site_access',
    'mxp_pm_central_admins',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$table}");
}

// Delete all plugin options
$options = [
    // Encryption
    'mxp_pm_encryption_key',
    // Notifications
    'mxp_pm_notifications_enabled',
    'mxp_pm_notification_default_format',
    'mxp_pm_notification_from_name',
    'mxp_pm_notification_from_email',
    // Permissions
    'mxp_pm_view_all_services_users',
    'mxp_pm_manage_encryption_users',
    'mxp_pm_add_service_capability',
    // Multisite central control
    'mxp_pm_central_control_enabled',
    'mxp_pm_default_service_scope',
    'mxp_pm_site_can_create_global',
    // Version (both possible names)
    'mxp_pm_password_manager_version',
    'mxp_pm_db_version',
    // Advanced
    'mxp_pm_delete_data_on_uninstall',
];

foreach ($options as $option) {
    mxp_pm_uninstall_delete_option($option);
}

// Delete all options with mxp_ prefix (catch any we might have missed)
if (is_multisite()) {
    $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'mxp_pm_%'");
} else {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'mxp_pm_%'");
}

// Delete user meta for notification preferences
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'mxp_pm_%'");

// Clear any cached data
wp_cache_flush();
