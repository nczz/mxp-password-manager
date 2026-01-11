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
    private $_plugin_name;

    /**
     * Plugin homepage/URI
     * @var string
     */
    private $_plugin_homepage;

    /**
     * Plugin author
     * @var string
     */
    private $_plugin_author;

    /**
     * Plugin description
     * @var string
     */
    private $_plugin_description;

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

        // Store explicit options, don't load plugin data yet to avoid early translation loading
        if (isset($options['plugin_name'])) {
            $this->_plugin_name = $options['plugin_name'];
        }
        if (isset($options['plugin_homepage'])) {
            $this->_plugin_homepage = $options['plugin_homepage'];
        }
        if (isset($options['plugin_author'])) {
            $this->_plugin_author = $options['plugin_author'];
        }
        if (isset($options['plugin_description'])) {
            $this->_plugin_description = $options['plugin_description'];
        }

        // Apply additional options
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get plugin data lazily (loaded only when needed)
     *
     * @return array|null
     */
    private function _get_plugin_data(): ?array {
        static $plugin_data = null;

        if ($plugin_data === null && function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data($this->plugin_file);
        }

        return $plugin_data;
    }

    /**
     * Get plugin name
     *
     * @return string
     */
    public function get_plugin_name(): string {
        if ($this->_plugin_name === null) {
            $plugin_data = $this->_get_plugin_data();
            $this->_plugin_name = $plugin_data['Name'] ?? 'MXP Password Manager';
        }
        return $this->_plugin_name;
    }

    /**
     * Get plugin homepage
     *
     * @return string
     */
    public function get_plugin_homepage(): string {
        if ($this->_plugin_homepage === null) {
            $plugin_data = $this->_get_plugin_data();
            $this->_plugin_homepage = $plugin_data['PluginURI'] ?? '';
        }
        return $this->_plugin_homepage;
    }

    /**
     * Get plugin author
     *
     * @return string
     */
    public function get_plugin_author(): string {
        if ($this->_plugin_author === null) {
            $plugin_data = $this->_get_plugin_data();
            $this->_plugin_author = $plugin_data['Author'] ?? '';
        }
        return $this->_plugin_author;
    }

    /**
     * Get plugin description
     *
     * @return string
     */
    public function get_plugin_description(): string {
        if ($this->_plugin_description === null) {
            $plugin_data = $this->_get_plugin_data();
            $this->_plugin_description = $plugin_data['Description'] ?? '';
        }
        return $this->_plugin_description;
    }

    /**
     * Magic getter for backward compatibility
     *
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'plugin_name':
                return $this->get_plugin_name();
            case 'plugin_homepage':
                return $this->get_plugin_homepage();
            case 'plugin_author':
                return $this->get_plugin_author();
            case 'plugin_description':
                return $this->get_plugin_description();
            default:
                trigger_error("Undefined property: MXP_GitHub_Updater_Config::$name", E_USER_NOTICE);
                return null;
        }
    }

    /**
     * Magic setter for backward compatibility
     *
     * @param string $name Property name
     * @param mixed  $value Property value
     */
    public function __set($name, $value) {
        $property_name = '_' . $name;
        if (property_exists($this, $property_name)) {
            $this->$property_name = $value;
        }
    }

    /**
     * Magic isset for backward compatibility
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset($name) {
        $property_name = '_' . $name;
        if (property_exists($this, $property_name)) {
            return isset($this->$property_name);
        }
        return false;
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
