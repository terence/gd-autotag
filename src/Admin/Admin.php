<?php
namespace WpPlugin\Admin;

class Admin
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function enqueue_admin_assets(): void
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_style('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/css/admin' . $suffix . '.css', [], WP_PLUGIN_VERSION);
        wp_enqueue_script('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/js/admin.js', ['jquery'], WP_PLUGIN_VERSION, true);
    }

    public function add_admin_menu(): void
    {
        add_menu_page(
            'wp_plugin',
            'wp_plugin',
            'manage_options',
            'wp-plugin',
            [$this, 'render_admin_page'],
            'dashicons-admin-plugins'
        );
        
        add_submenu_page(
            'wp-plugin',
            'Settings',
            'Settings',
            'manage_options',
            'wp-plugin-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        // Register settings group
        register_setting('wp_plugin_settings', 'wp_plugin_options', [$this, 'sanitize_settings']);
        
        // General Settings Section
        add_settings_section(
            'wp_plugin_general_section',
            'General Settings',
            [$this, 'render_general_section'],
            'wp-plugin-settings'
        );
        
        // Example setting: Enable feature
        add_settings_field(
            'enable_feature',
            'Enable Feature',
            [$this, 'render_enable_feature_field'],
            'wp-plugin-settings',
            'wp_plugin_general_section'
        );
        
        // Example setting: API Key
        add_settings_field(
            'api_key',
            'API Key',
            [$this, 'render_api_key_field'],
            'wp-plugin-settings',
            'wp_plugin_general_section'
        );
        
        // Advanced Settings Section
        add_settings_section(
            'wp_plugin_advanced_section',
            'Advanced Settings',
            [$this, 'render_advanced_section'],
            'wp-plugin-settings'
        );
        
        // Example setting: Debug mode
        add_settings_field(
            'debug_mode',
            'Debug Mode',
            [$this, 'render_debug_mode_field'],
            'wp-plugin-settings',
            'wp_plugin_advanced_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        
        if (isset($input['enable_feature'])) {
            $sanitized['enable_feature'] = (bool) $input['enable_feature'];
        }
        
        if (isset($input['api_key'])) {
            $sanitized['api_key'] = sanitize_text_field($input['api_key']);
        }
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }
        
        return $sanitized;
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show success message if settings saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error('wp_plugin_messages', 'wp_plugin_message', 'Settings Saved', 'updated');
        }
        
        settings_errors('wp_plugin_messages');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_plugin_settings');
                do_settings_sections('wp-plugin-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }

    public function render_general_section(): void
    {
        echo '<p>Configure general plugin settings below.</p>';
    }

    public function render_advanced_section(): void
    {
        echo '<p>Advanced configuration options.</p>';
    }

    public function render_enable_feature_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['enable_feature']) ? $options['enable_feature'] : false;
        ?>
        <label>
            <input type="checkbox" name="wp_plugin_options[enable_feature]" value="1" <?php checked($enabled, true); ?> />
            Enable this feature
        </label>
        <p class="description">Check to enable the main plugin feature.</p>
        <?php
    }

    public function render_api_key_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        ?>
        <input type="text" 
               name="wp_plugin_options[api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               placeholder="Enter your API key" />
        <p class="description">Your API key for external services.</p>
        <?php
    }

    public function render_debug_mode_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        ?>
        <label>
            <input type="checkbox" name="wp_plugin_options[debug_mode]" value="1" <?php checked($debug, true); ?> />
            Enable debug mode
        </label>
        <p class="description">Enable debug logging for troubleshooting.</p>
        <?php
    }

    public function render_admin_page(): void
    {
        // Check if user can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle manual update check
        $manualCheckPerformed = false;
        if (isset($_POST['check_for_updates']) && check_admin_referer('wp_plugin_check_updates')) {
            $this->force_update_check();
            $manualCheckPerformed = true;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card">
                <h2>Plugin Information</h2>
                <p><strong>Version:</strong> <?php echo esc_html(WP_PLUGIN_VERSION); ?></p>
                <p><strong>Update Source:</strong> GitHub Releases</p>
                <p><strong>Repository:</strong> <a href="https://github.com/terence/wp-plugin" target="_blank">terence/wp-plugin</a></p>
            </div>

            <div class="card">
                <h2>Update Status</h2>
                <?php if ($manualCheckPerformed): ?>
                    <div class="notice notice-success inline">
                        <p>✓ Checked for updates. Visit the <a href="<?php echo admin_url('plugins.php'); ?>">Plugins page</a> to see if updates are available.</p>
                    </div>
                <?php endif; ?>
                
                <?php $this->display_update_info(); ?>
                
                <form method="post" style="margin-top: 20px;">
                    <?php wp_nonce_field('wp_plugin_check_updates'); ?>
                    <button type="submit" name="check_for_updates" class="button button-primary">
                        Check for Updates Now
                    </button>
                </form>
                
                <p style="margin-top: 15px;">
                    <em>Automatic checks run every 12 hours. Last check: <?php echo esc_html($this->get_last_check_time()); ?></em>
                </p>
            </div>

            <div class="card">
                <h2>About Updates</h2>
                <p>This plugin automatically checks for updates from GitHub. When a new release is published, you'll see an update notification in WordPress.</p>
                <p><strong>How to create a release:</strong></p>
                <ol>
                    <li>Update the version number in <code>wp-plugin.php</code></li>
                    <li>Create a new tag: <code>git tag v1.0.1</code></li>
                    <li>Push the tag: <code>git push origin --tags</code></li>
                    <li>Create a GitHub Release with a ZIP asset</li>
                </ol>
            </div>
        </div>
        <?php
    }

    private function display_update_info(): void
    {
        // Get update checker instance if available
        $updateChecker = $this->get_update_checker();
        
        if (!$updateChecker) {
            echo '<div class="notice notice-warning inline"><p>Update checker not initialized.</p></div>';
            return;
        }

        // Try to get update state
        try {
            $state = $updateChecker->getUpdateState();
            if ($state && isset($state->update) && !empty($state->update)) {
                $update = $state->update;
                echo '<div class="notice notice-info inline">';
                echo '<p><strong>Update Available:</strong> Version ' . esc_html($update->version) . '</p>';
                if (!empty($update->download_url)) {
                    echo '<p><a href="' . admin_url('plugins.php') . '" class="button button-primary">Go to Updates</a></p>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-success inline"><p>✓ Plugin is up to date.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-warning inline"><p>Unable to check update status.</p></div>';
        }
    }

    private function force_update_check(): void
    {
        $updateChecker = $this->get_update_checker();
        if ($updateChecker && method_exists($updateChecker, 'checkForUpdates')) {
            $updateChecker->checkForUpdates();
        }
    }

    private function get_last_check_time(): string
    {
        $updateChecker = $this->get_update_checker();
        if (!$updateChecker) {
            return 'Never';
        }

        try {
            $state = $updateChecker->getUpdateState();
            if ($state && isset($state->lastCheck) && $state->lastCheck > 0) {
                return human_time_diff($state->lastCheck) . ' ago';
            }
        } catch (Exception $e) {
            // Ignore
        }

        return 'Never';
    }

    private function get_update_checker()
    {
        // Access global update checker instance if stored
        global $wp_plugin_update_checker;
        return $wp_plugin_update_checker ?? null;
    }
}
