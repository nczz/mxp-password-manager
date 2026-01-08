<?php
/**
 * MXP Password Manager - GitHub Updater Configuration
 *
 * Configuration class for GitHub-based plugin updates
 *
 * @package MXP_Password_Manager
 * @since 3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration class for GitHub updater
 *
 * Stores all configuration needed for GitHub-based plugin updates
 */
class MXP_GitHub_Updater_Config {

    /**
     * Plugin file path
     * @var string
     */
    public $plugin_file;

    /**
     * GitHub repository (format: owner/repo)
     * @var string
     */
    public $github_repo;

    /**
     * Plugin name
     * @var string
     */
    public $plugin_name;

    /**
     * Plugin homepage/URI
     * @var string
     */
    public $plugin_homepage;

    /**
     * Plugin author
     * @var string
     */
    public $plugin_author;

    /**
     * Plugin description
     * @var string
     */
    public $plugin_description;

    /**
     * Minimum WordPress version required
     * @var string
     */
    public $requires_wordpress = '5.0';

    /**
     * Minimum PHP version required
     * @var string
     */
    public $requires_php = '7.4';

    /**
     * Cache duration in seconds (default: 12 hours)
     * @var int
     */
    public $cache_duration = 43200;

    /**
     * Text domain
     * @var string
     */
    public $text_domain = 'mxp-password-manager';

    /**
     * AJAX nonce action
     * @var string
     */
    public $ajax_nonce = 'mxp_pm_github_updater_nonce';

    /**
     * Option name for GitHub access token
     * @var string
     */
    public $github_token_option = 'mxp_pm_github_access_token';

    /**
     * Option name for allow beta updates
     * @var string
     */
    public $allow_beta_option = 'mxp_pm_allow_beta_updates';

    /**
     * Custom temporary directory (optional)
     * @var string|null
     */
    public $custom_temp_dir = null;

    /**
     * Asset filename pattern (placeholder: {slug}-{version}.zip)
     * @var string
     */
    public $asset_pattern = '{slug}-{version}.zip';

    /**
     * Constructor
     *
     * @param string $plugin_file Plugin main file path
     * @param string $github_repo GitHub repository (owner/repo)
     * @param array  $options Additional configuration options
     */
    public function __construct(string $plugin_file, string $github_repo, array $options = []) {
        $this->plugin_file = $plugin_file;
        $this->github_repo = $github_repo;

        // Get plugin data for default values
        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data($plugin_file);
            $this->plugin_name = $options['plugin_name'] ?? $plugin_data['Name'];
            $this->plugin_homepage = $options['plugin_homepage'] ?? $plugin_data['PluginURI'];
            $this->plugin_author = $options['plugin_author'] ?? $plugin_data['Author'];
            $this->plugin_description = $options['plugin_description'] ?? $plugin_data['Description'];
        }

        // Apply additional options
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get GitHub access token
     *
     * Priority: wp-config constant > option > environment variable
     *
     * @return string|null
     */
    public function get_github_token(): ?string {
        // Method 1: wp-config constant (most secure)
        if (defined('MXP_GITHUB_ACCESS_TOKEN') && MXP_GITHUB_ACCESS_TOKEN) {
            return MXP_GITHUB_ACCESS_TOKEN;
        }

        // Method 2: Option (for settings UI)
        $token = get_option($this->github_token_option, '');

        // Validate token format
        if ($token && (strpos($token, 'ghp_') === 0 || strpos($token, 'github_pat_') === 0)) {
            return $token;
        }

        // Method 3: Environment variable
        $env_token = getenv('MXP_GITHUB_ACCESS_TOKEN');
        if ($env_token) {
            return $env_token;
        }

        return null;
    }

    /**
     * Check if beta updates are allowed
     *
     * @return bool
     */
    public function allow_beta_updates(): bool {
        return (bool) get_option($this->allow_beta_option, false);
    }

    /**
     * Get plugin basename
     *
     * @return string
     */
    public function get_plugin_basename(): string {
        return plugin_basename($this->plugin_file);
    }

    /**
     * Get plugin slug (directory name)
     *
     * @return string
     */
    public function get_plugin_slug(): string {
        return dirname($this->get_plugin_basename());
    }

    /**
     * Get plugin file basename
     *
     * @return string
     */
    public function get_plugin_file_basename(): string {
        return basename($this->plugin_file);
    }
}
