<?php
/**
 * MXP Password Manager - Multisite Support Module
 *
 * Handles cross-site functionality and central control
 *
 * @package MXP_Password_Manager
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Pm_Multisite {

    /**
     * Permission level constants
     */
    const LEVEL_VIEWER = 'viewer';
    const LEVEL_EDITOR = 'editor';
    const LEVEL_ADMIN = 'admin';

    /**
     * Scope constants
     */
    const SCOPE_GLOBAL = 'global';
    const SCOPE_SITE = 'site';

    /**
     * Check if multisite central control features are enabled
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return is_multisite() && (bool) mxp_pm_get_option('mxp_pm_central_control_enabled', false);
    }

    /**
     * Get user's central admin level
     *
     * @param int $user_id User ID (0 for current user)
     * @return string|null Permission level or null if not a central admin
     */
    public static function get_central_admin_level(int $user_id = 0): ?string {
        if ($user_id === 0) {
            $user_id = get_current_user_id();
        }

        // Super admin always has full admin level
        if (is_super_admin($user_id)) {
            return self::LEVEL_ADMIN;
        }

        // Check central_admins table
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $level = $wpdb->get_var($wpdb->prepare(
            "SELECT permission_level FROM {$prefix}mxp_pm_central_admins WHERE user_id = %d",
            $user_id
        ));

        return $level ?: null;
    }

    /**
     * Check if user is a central admin (any level)
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function is_central_admin(int $user_id = 0): bool {
        return self::get_central_admin_level($user_id) !== null;
    }

    /**
     * Check if user has at least the specified permission level
     *
     * @param int    $user_id   User ID (0 for current user)
     * @param string $min_level Minimum required level
     * @return bool
     */
    private static function has_min_level(int $user_id, string $min_level): bool {
        if (!self::is_enabled()) {
            return false;
        }

        $level = self::get_central_admin_level($user_id);
        if ($level === null) {
            return false;
        }

        $level_hierarchy = [
            self::LEVEL_VIEWER => 1,
            self::LEVEL_EDITOR => 2,
            self::LEVEL_ADMIN => 3,
        ];

        return ($level_hierarchy[$level] ?? 0) >= ($level_hierarchy[$min_level] ?? 0);
    }

    /**
     * Check if user can view all services across sites
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function can_view_all(int $user_id = 0): bool {
        return self::has_min_level($user_id, self::LEVEL_VIEWER);
    }

    /**
     * Check if user can edit all services across sites
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function can_edit_all(int $user_id = 0): bool {
        return self::has_min_level($user_id, self::LEVEL_EDITOR);
    }

    /**
     * Check if user can manage authorizations across sites
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function can_manage_auth(int $user_id = 0): bool {
        return self::has_min_level($user_id, self::LEVEL_ADMIN);
    }

    /**
     * Check if user can perform cross-site authorization
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function can_cross_site_auth(int $user_id = 0): bool {
        return self::has_min_level($user_id, self::LEVEL_EDITOR);
    }

    /**
     * Check if user can create global services
     *
     * @param int $user_id User ID (0 for current user)
     * @return bool
     */
    public static function can_create_global(int $user_id = 0): bool {
        if (!is_multisite()) {
            return false;
        }

        // Check if site is allowed to create global services
        $site_can_create = mxp_pm_get_option('mxp_site_can_create_global', true);

        if (!$site_can_create && !self::is_central_admin($user_id)) {
            return false;
        }

        return Mxp_Pm_Hooks::apply_filters('mxp_pm_can_create_global_service', true, $user_id, get_current_blog_id());
    }

    /**
     * Get all network sites for dropdown
     *
     * @param int $limit Maximum number of sites
     * @return array Array of site info ['blog_id', 'name', 'url']
     */
    public static function get_network_sites(int $limit = 100): array {
        if (!is_multisite()) {
            return [];
        }

        $sites = get_sites([
            'number' => $limit,
            'public' => 1,
            'deleted' => 0,
            'archived' => 0,
        ]);

        $result = [];
        foreach ($sites as $site) {
            $result[] = [
                'blog_id' => (int) $site->blog_id,
                'name' => get_blog_option($site->blog_id, 'blogname'),
                'url' => $site->siteurl,
            ];
        }

        return $result;
    }

    /**
     * Get all network users for authorization
     *
     * @param int $limit Maximum number of users
     * @return array Array of WP_User objects
     */
    public static function get_network_users(int $limit = 500): array {
        if (!is_multisite()) {
            return get_users(['number' => $limit, 'orderby' => 'display_name']);
        }

        return get_users([
            'blog_id' => 0,
            'number' => $limit,
            'orderby' => 'display_name',
        ]);
    }

    /**
     * Get users available for service authorization
     *
     * @param int      $service_id Service ID
     * @param int|null $blog_id    Blog ID context (null for network-wide)
     * @return array Array of WP_User objects
     */
    public static function get_available_users_for_auth(int $service_id, ?int $blog_id = null): array {
        $requesting_user_id = get_current_user_id();

        // If can do cross-site auth, return all network users
        if (self::can_cross_site_auth($requesting_user_id)) {
            $users = self::get_network_users();
            return Mxp_Pm_Hooks::apply_filters('mxp_pm_available_auth_users', $users, $service_id, $requesting_user_id);
        }

        // Otherwise return users from current blog only
        $current_blog = $blog_id ?: get_current_blog_id();
        $args = is_multisite() ? ['blog_id' => $current_blog, 'number' => 500] : ['number' => 500];
        $users = get_users($args);

        return Mxp_Pm_Hooks::apply_filters('mxp_pm_available_auth_users', $users, $service_id, $requesting_user_id);
    }

    /**
     * Check if a site can access a specific service
     *
     * @param int $service_id Service ID
     * @param int $blog_id    Blog ID
     * @return bool
     */
    public static function site_can_access_service(int $service_id, int $blog_id): bool {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        // Get service scope and owner
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT scope, owner_blog_id FROM {$prefix}mxp_pm_service_list WHERE sid = %d",
            $service_id
        ));

        if (!$service) {
            return false;
        }

        // Site-specific: only owning site can access
        if ($service->scope === self::SCOPE_SITE) {
            return (int) $service->owner_blog_id === $blog_id;
        }

        // Global scope: check various conditions

        // Legacy data (owner_blog_id = NULL) - accessible everywhere
        if ($service->owner_blog_id === null) {
            return true;
        }

        // Owner site always has access
        if ((int) $service->owner_blog_id === $blog_id) {
            return true;
        }

        // Global services are accessible by all sites by default
        // Only check to_site_access if there are explicit restrictions
        $has_restrictions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}mxp_pm_site_access WHERE service_id = %d",
            $service_id
        ));

        // No restrictions = all sites can access
        if (!$has_restrictions) {
            return true;
        }

        // Check explicit access grant
        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}mxp_pm_site_access
             WHERE service_id = %d AND blog_id = %d",
            $service_id,
            $blog_id
        ));

        return $has_access > 0;
    }

    /**
     * Get site's access level for a service
     *
     * @param int $service_id Service ID
     * @param int $blog_id    Blog ID
     * @return string|null 'view', 'edit', 'full', or null
     */
    public static function get_site_access_level(int $service_id, int $blog_id): ?string {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        return $wpdb->get_var($wpdb->prepare(
            "SELECT access_level FROM {$prefix}mxp_pm_site_access
             WHERE service_id = %d AND blog_id = %d",
            $service_id,
            $blog_id
        ));
    }

    /**
     * Grant site access to a service
     *
     * @param int    $service_id Service ID
     * @param int    $blog_id    Blog ID
     * @param string $level      Access level ('view', 'edit', 'full')
     * @return bool Success
     */
    public static function grant_site_access(int $service_id, int $blog_id, string $level = 'view'): bool {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $result = $wpdb->replace(
            $prefix . 'mxp_pm_site_access',
            [
                'service_id' => $service_id,
                'blog_id' => $blog_id,
                'access_level' => $level,
                'created_by' => get_current_user_id(),
            ],
            ['%d', '%d', '%s', '%d']
        );

        if ($result !== false) {
            Mxp_Pm_Hooks::do_action('mxp_pm_site_access_granted', $service_id, $blog_id, $level);
            return true;
        }

        return false;
    }

    /**
     * Revoke site access to a service
     *
     * @param int $service_id Service ID
     * @param int $blog_id    Blog ID
     * @return bool Success
     */
    public static function revoke_site_access(int $service_id, int $blog_id): bool {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $result = $wpdb->delete(
            $prefix . 'mxp_pm_site_access',
            ['service_id' => $service_id, 'blog_id' => $blog_id],
            ['%d', '%d']
        );

        if ($result !== false) {
            Mxp_Pm_Hooks::do_action('mxp_pm_site_access_revoked', $service_id, $blog_id);
            return true;
        }

        return false;
    }

    /**
     * Get all sites with access to a service
     *
     * @param int $service_id Service ID
     * @return array Array of blog_id => access_level
     */
    public static function get_service_site_access(int $service_id): array {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT blog_id, access_level FROM {$prefix}mxp_pm_site_access WHERE service_id = %d",
            $service_id
        ), ARRAY_A);

        $access = [];
        foreach ($results as $row) {
            $access[(int) $row['blog_id']] = $row['access_level'];
        }

        return $access;
    }

    /**
     * Add a central admin
     *
     * @param int    $user_id User ID
     * @param string $level   Permission level
     * @return bool Success
     */
    public static function add_central_admin(int $user_id, string $level = self::LEVEL_VIEWER): bool {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $result = $wpdb->replace(
            $prefix . 'mxp_pm_central_admins',
            [
                'user_id' => $user_id,
                'permission_level' => $level,
                'created_by' => get_current_user_id(),
            ],
            ['%d', '%s', '%d']
        );

        if ($result !== false) {
            Mxp_Pm_Hooks::do_action('mxp_pm_central_admin_added', $user_id, $level);
            wp_cache_delete('mxp_pm_central_admins');
            return true;
        }

        return false;
    }

    /**
     * Remove a central admin
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function remove_central_admin(int $user_id): bool {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $result = $wpdb->delete(
            $prefix . 'mxp_pm_central_admins',
            ['user_id' => $user_id],
            ['%d']
        );

        if ($result !== false) {
            Mxp_Pm_Hooks::do_action('mxp_pm_central_admin_removed', $user_id);
            wp_cache_delete('mxp_pm_central_admins');
            return true;
        }

        return false;
    }

    /**
     * Get all central admins
     *
     * @return array Array of ['user_id', 'permission_level', 'user_data']
     */
    public static function get_central_admins(): array {
        $cached = wp_cache_get('mxp_pm_central_admins');
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        $results = $wpdb->get_results(
            "SELECT caid, user_id, permission_level, created_time FROM {$prefix}mxp_pm_central_admins ORDER BY permission_level, created_time",
            ARRAY_A
        );

        $admins = [];
        foreach ($results as $row) {
            $user = get_user_by('id', $row['user_id']);
            if ($user) {
                $row['user_data'] = [
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'user_login' => $user->user_login,
                ];
                $admins[] = $row;
            }
        }

        wp_cache_set('mxp_pm_central_admins', $admins, '', 3600);

        return $admins;
    }

    /**
     * Get default scope for new services
     *
     * @return string 'global' or 'site'
     */
    public static function get_default_scope(): string {
        if (!is_multisite()) {
            return self::SCOPE_SITE;
        }

        return mxp_pm_get_option('mxp_pm_default_service_scope', self::SCOPE_GLOBAL);
    }

    /**
     * Get service scope label
     *
     * @param string $scope Scope value
     * @return string Translated label
     */
    public static function get_scope_label(string $scope): string {
        $labels = [
            self::SCOPE_GLOBAL => __('全域共享', 'mxp-password-manager'),
            self::SCOPE_SITE => __('站台專屬', 'mxp-password-manager'),
        ];

        return $labels[$scope] ?? $scope;
    }

    /**
     * Get permission level label
     *
     * @param string $level Permission level
     * @return string Translated label
     */
    public static function get_level_label(string $level): string {
        $labels = [
            self::LEVEL_VIEWER => __('檢視者', 'mxp-password-manager'),
            self::LEVEL_EDITOR => __('編輯者', 'mxp-password-manager'),
            self::LEVEL_ADMIN => __('管理員', 'mxp-password-manager'),
        ];

        return $labels[$level] ?? $level;
    }

    /**
     * Build service access SQL conditions
     *
     * @param int  $user_id      User ID
     * @param int  $blog_id      Current blog ID
     * @param bool $can_view_all Whether user can view all services
     * @return string SQL WHERE conditions
     */
    public static function build_access_conditions(int $user_id, int $blog_id, bool $can_view_all = false): string {
        global $wpdb;
        $prefix = mxp_pm_get_table_prefix();

        // Central admin or has view all permission - see everything
        if ($can_view_all || self::can_view_all($user_id)) {
            return '1=1';
        }

        // For single site installations, simply check authorization list
        if (!is_multisite()) {
            return $wpdb->prepare(
                "s.sid IN (SELECT service_id FROM {$prefix}mxp_pm_auth_list WHERE user_id = %d)",
                $user_id
            );
        }

        $conditions = [];

        // Condition 1: Global services user is authorized for
        // (Global services are visible to all sites by default)
        $conditions[] = $wpdb->prepare(
            "(s.scope = 'global' AND s.sid IN (
                SELECT service_id FROM {$prefix}mxp_pm_auth_list WHERE user_id = %d
            ))",
            $user_id
        );

        // Condition 2: Site-specific services on current site that user is authorized for
        $conditions[] = $wpdb->prepare(
            "(s.scope = 'site' AND s.owner_blog_id = %d AND s.sid IN (
                SELECT service_id FROM {$prefix}mxp_pm_auth_list WHERE user_id = %d
            ))",
            $blog_id,
            $user_id
        );

        // Condition 3: Legacy services (scope IS NULL or owner_blog_id IS NULL) user is authorized for
        $conditions[] = $wpdb->prepare(
            "((s.scope IS NULL OR s.owner_blog_id IS NULL) AND s.sid IN (
                SELECT service_id FROM {$prefix}mxp_pm_auth_list WHERE user_id = %d
            ))",
            $user_id
        );

        $sql = '(' . implode(' OR ', $conditions) . ')';

        return Mxp_Pm_Hooks::apply_filters('mxp_pm_service_access_conditions', $sql, $user_id, $blog_id);
    }
}
