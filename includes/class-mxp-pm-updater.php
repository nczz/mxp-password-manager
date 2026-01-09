<?php
if (!defined('ABSPATH')) {
    exit;
}

class Mxp_Pm_Updater {

    private $config;
    private $plugin_slug;
    private $plugin_basename;
    private $current_version;
    private $version_transient;
    private $plugin_info_transient;
    private $update_check_transient;

    public function __construct(string $plugin_file, string $github_repo, string $current_version) {
        $this->config = new MXP_GitHub_Updater_Config($plugin_file, $github_repo);
        $this->plugin_slug = $this->config->get_plugin_slug();
        $this->plugin_basename = $this->config->get_plugin_basename();
        $this->current_version = $current_version;

        $this->version_transient = $this->plugin_slug . '_version_check';
        $this->plugin_info_transient = $this->plugin_slug . '_plugin_info';
        $this->update_check_transient = $this->plugin_basename . '_update_check';

        $this->init_hooks();
    }

    /**
     * Get plugin name (lazy loaded to avoid early translation loading)
     * @return string
     */
    private function get_plugin_name(): string {
        return $this->config->get_plugin_name();
    }

    /**
     * Get plugin author (lazy loaded to avoid early translation loading)
     * @return string
     */
    private function get_plugin_author(): string {
        return $this->config->get_plugin_author();
    }

    /**
     * Get plugin description (lazy loaded to avoid early translation loading)
     * @return string
     */
    private function get_plugin_description(): string {
        return $this->config->get_plugin_description();
    }

    /**
     * Get plugin homepage (lazy loaded to avoid early translation loading)
     * @return string
     */
    private function get_plugin_homepage(): string {
        return $this->config->get_plugin_homepage();
    }

    private function init_hooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update'], 10, 1);
        add_filter('plugins_api', [$this, 'filter_plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'clear_cache_after_update'], 10, 2);
        add_action('wp_ajax_mxp_check_updates', [$this, 'ajax_manual_check']);
        add_action('wp_ajax_mxp_pm_dismiss_update_notice', [$this, 'ajax_dismiss_notice']);
        add_action('admin_notices', [$this, 'show_update_notice']);
    }

    public function check_for_update($transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }

        $current_version = $transient->checked[$this->plugin_basename] ?? $this->current_version;
        $release = $this->get_latest_release();
        
        if (is_wp_error($release)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'MXP Updater: Failed to fetch GitHub release for %s: %s',
                    $this->config->github_repo,
                    $release->get_error_message()
                ));
            }
            return $transient;
        }

        $new_version = $this->parse_version_from_tag($release['tag_name'] ?? '');
        $is_update_available = version_compare($new_version, $current_version, '>');

        mxp_pm_update_option($this->update_check_transient, [
            'last_check' => current_time('mysql'),
            'latest_version' => $new_version,
            'update_available' => $is_update_available,
            'check_time' => time(),
        ]);

        if ($is_update_available) {
            $transient->response[$this->plugin_basename] = (object) [
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_basename,
                'new_version' => $new_version,
                'url' => $release['html_url'] ?? '',
                'package' => $this->get_release_zip_url($release),
                'tested' => $this->get_tested_wp_version($release),
                'requires_php' => $this->config->requires_php,
                'compatibility' => new stdClass(),
            ];

            set_transient(
                'mxp_pm_update_notice_' . $this->plugin_basename,
                [
                    'plugin_name' => $this->get_plugin_name(),
                    'current_version' => $current_version,
                    'latest_version' => $new_version,
                    'release_url' => $release['html_url'],
                    'dismissed' => false,
                ],
                DAY_IN_SECONDS
            );
        }

        return $transient;
    }

    public function filter_plugin_info($result, string $action, object $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_basename) {
            return $result;
        }

        $release = $this->get_latest_release();
        
        if (is_wp_error($release)) {
            return $result;
        }

        $new_version = $this->parse_version_from_tag($release['tag_name'] ?? '');
        $changelog = $this->parse_release_notes($release);

        return (object) [
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_basename,
            'version' => $new_version ?: $this->current_version,
            'author' => $this->get_plugin_author(),
            'author_profile' => $this->get_plugin_homepage(),
            'requires' => $this->config->requires_wordpress,
            'tested' => $this->get_tested_wp_version($release),
            'requires_php' => $this->config->requires_php,
            'name' => $this->get_plugin_name(),
            'homepage' => $this->get_plugin_homepage(),
            'sections' => [
                'description' => $this->get_plugin_description(),
                'changelog' => $changelog,
            ],
            'download_link' => $this->get_release_zip_url($release),
            'last_updated' => $release['published_at'] ?? '',
            'external' => true,
        ];
    }

    private function get_latest_release() {
        $cache_key = 'mxp_pm_github_release_' . md5($this->config->github_repo);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        if (!$this->is_auto_update_enabled()) {
            return new WP_Error(
                'auto_update_disabled',
                __('Automatic updates are disabled in settings.', 'mxp-password-manager')
            );
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            $this->config->github_repo
        );

        $args = [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; MXP-Password-Manager/' . $this->current_version,
            ],
            'timeout' => 15,
        ];

        $token = $this->config->get_github_token();
        if ($token) {
            $args['headers']['Authorization'] = 'token ' . $token;
        }

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MXP Updater: HTTP request failed: ' . $response->get_error_message());
            }
            return new WP_Error(
                'api_request_failed',
                sprintf(__('Failed to connect to GitHub: %s', 'mxp-password-manager'), $response->get_error_message())
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 403 || $response_code === 429) {
            $retry_after = $this->extract_retry_after($response);
            
            return new WP_Error(
                'rate_limited',
                sprintf(
                    __('GitHub API rate limit exceeded. Please try again after %s.', 'mxp-password-manager'),
                    date_i18n(__('Y-m-d H:i:s'), $retry_after)
                )
            );
        }

        if ($response_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MXP Updater: GitHub API returned status ' . $response_code);
            }
            return new WP_Error(
                'api_error',
                sprintf(__('GitHub API returned status code %d', 'mxp-password-manager'), $response_code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);

        if (!$release) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from GitHub API', 'mxp-password-manager')
            );
        }

        if (!$this->config->allow_beta_updates() && !empty($release['prerelease'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MXP Updater: Pre-release version available but beta updates disabled');
            }
            return new WP_Error(
                'prerelease_disabled',
                __('Pre-release version available but beta updates are disabled', 'mxp-password-manager')
            );
        }

        set_transient($cache_key, $release, $this->config->cache_duration);

        return $release;
    }

    private function parse_version_from_tag(string $tag): string {
        return ltrim($tag, 'v');
    }

    private function get_release_zip_url(array $release): string {
        if (empty($release['assets'])) {
            return '';
        }

        $zip_asset = null;
        $max_size = 0;

        foreach ($release['assets'] as $asset) {
            $ext = pathinfo($asset['name'], PATHINFO_EXTENSION);
            if ($ext === 'zip' && $asset['size'] > $max_size) {
                $max_size = $asset['size'];
                $zip_asset = $asset;
            }
        }

        return $zip_asset ? $zip_asset['browser_download_url'] : '';
    }

    private function parse_release_notes(array $release): string {
        $notes = $release['body'] ?? '';
        $version = $this->parse_version_from_tag($release['tag_name'] ?? '');

        if (empty($notes)) {
            return '<p>' . __('No release notes provided.', 'mxp-password-manager') . '</p>';
        }

        $date = date_i18n(__('Y-m-d'), strtotime($release['published_at'] ?? ''));
        
        $html = '<h4>' . sprintf(
            __('Version %s (%s)', 'mxp-password-manager'),
            $version,
            $date
        ) . '</h4>';

        $formatted_notes = $notes;
        $formatted_notes = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted_notes);
        $formatted_notes = nl2br($formatted_notes);
        
        $html .= '<div class="mxp-changelog">';
        $html .= wp_kses_post($formatted_notes, [
            'a' => ['href' => true, 'target' => true],
            'strong' => [],
            'em' => [],
            'p' => [],
            'ul' => [],
            'li' => [],
            'ol' => [],
            'code' => [],
            'pre' => [],
            'br' => [],
        ]);
        $html .= '</div>';

        return $html;
    }

    private function get_tested_wp_version(array $release): string {
        if (preg_match('/Tested up to WordPress\s*([\d.]+)/i', $release['body'] ?? '', $matches)) {
            return $matches[1];
        }
        return '6.5';
    }

    private function extract_retry_after($response): ?int {
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-ratelimit-reset'])) {
            return (int) $headers['x-ratelimit-reset'];
        }
        return null;
    }

    private function is_auto_update_enabled(): bool {
        return (bool) mxp_pm_get_option('mxp_auto_update_enabled', true);
    }

    public function clear_cache_after_update($upgrader, $hook_extra): void {
        if (!isset($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return;
        }

        if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
            return;
        }

        if (!isset($hook_extra['plugins']) || !in_array($this->plugin_basename, $hook_extra['plugins'])) {
            return;
        }

        delete_transient($this->version_transient);
        delete_transient($this->plugin_info_transient);
        delete_site_transient('update_plugins');
        delete_transient('mxp_pm_update_notice_' . $this->plugin_basename);
        mxp_pm_delete_option($this->update_check_transient);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MXP Updater: Cleared cache for ' . $this->plugin_basename);
        }
    }

    public function ajax_manual_check(): void {
        check_ajax_referer($this->config->ajax_nonce, 'nonce');
        
        if (!current_user_can('update_plugins')) {
            wp_send_json_error([
                'message' => __('You do not have permission to check for updates.', 'mxp-password-manager'),
                'code' => 'insufficient_permissions'
            ]);
        }

        try {
            $release = $this->get_latest_release();
            
            if (is_wp_error($release)) {
                wp_send_json_error([
                    'message' => $release->get_error_message(),
                    'code' => $release->get_error_code()
                ]);
            }

            $new_version = $this->parse_version_from_tag($release['tag_name'] ?? '');
            $is_update_available = version_compare($new_version, $this->current_version, '>');

            wp_send_json_success([
                'current_version' => $this->current_version,
                'latest_version' => $new_version,
                'update_available' => $is_update_available,
                'release_url' => $release['html_url'] ?? '',
            ]);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MXP Updater: Exception in manual check: ' . $e->getMessage());
            }
            wp_send_json_error([
                'message' => __('Failed to check for updates.', 'mxp-password-manager'),
                'code' => 'check_failed'
            ]);
        }
    }

    public function ajax_dismiss_notice(): void {
        // Verify nonce first
        if (!check_ajax_referer($this->config->ajax_nonce, 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Invalid nonce. Please refresh the page and try again.', 'mxp-password-manager'),
                'code' => 'invalid_nonce'
            ], 403);
        }

        // Check permissions
        if (!current_user_can('update_plugins')) {
            wp_send_json_error([
                'message' => __('You do not have permission to dismiss notices.', 'mxp-password-manager'),
                'code' => 'insufficient_permissions'
            ], 403);
        }

        delete_transient('mxp_pm_update_notice_' . $this->plugin_basename);

        wp_send_json_success([
            'message' => __('Update notice dismissed.', 'mxp-password-manager')
        ]);
    }

    public function show_update_notice(): void {
        $notice = get_transient('mxp_pm_update_notice_' . $this->plugin_basename);
        
        if (!$notice || $notice['dismissed']) {
            return;
        }

        if (!current_user_can('update_plugins')) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible mxp_pm-update-notice" data-nonce="<?php echo esc_attr(wp_create_nonce($this->config->ajax_nonce)); ?>">
            <p>
                <strong><?php echo esc_html($this->get_plugin_name()); ?></strong> - 
                <?php
                printf(
                    esc_html__('New version %s available! You have %s.', 'mxp-password-manager'),
                    '<strong>' . esc_html($notice['latest_version']) . '</strong>',
                    esc_html($notice['current_version'])
                );
                ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="button button-primary">
                    <?php esc_html_e('Update Now', 'mxp-password-manager'); ?>
                </a>
                <a href="<?php echo esc_url($notice['release_url']); ?>" target="_blank">
                    <?php esc_html_e('View Release Notes', 'mxp-password-manager'); ?>
                </a>
            </p>
            <button type="button" class="notice-dismiss mxp-dismiss-button">
                <span class="screen-reader-text"><?php esc_html_e('Dismiss this notice', 'mxp-password-manager'); ?></span>
            </button>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.mxp_pm-update-notice .mxp-dismiss-button').on('click', function(e) {
                e.preventDefault();
                var nonce = $(this).closest('.mxp_pm-update-notice').data('nonce');
                console.log('Dismissing notice, nonce:', nonce);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mxp_pm_dismiss_update_notice',
                        nonce: nonce
                    },
                    success: function(response) {
                        console.log('Dismiss successful:', response);
                        $('.mxp_pm-update-notice').fadeOut();
                    },
                    error: function(xhr, status, error) {
                        console.log('Dismiss error:', xhr.status, xhr.responseText);
                        alert('Failed to dismiss notice. Status: ' + xhr.status);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
