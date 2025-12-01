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
        add_filter('plugin_action_links_' . plugin_basename($this->file), [$this, 'add_action_links']);
    }

    public function enqueue_admin_assets(): void
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_style('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/css/admin' . $suffix . '.css', [], WP_PLUGIN_VERSION);
        wp_enqueue_script('wp-plugin-admin', plugin_dir_url($this->file) . 'assets/js/admin.js', ['jquery'], WP_PLUGIN_VERSION, true);
    }

    public function add_action_links($links): array
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wp-plugin-settings')) . '">Settings</a>';
        $dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=wp-plugin')) . '">Dashboard</a>';

        array_unshift($links, $settings_link, $dashboard_link);
        
        return $links;
    }

    public function add_admin_menu(): void
    {
        // Prefer crisp 20x20 icon if available, else fall back to original
        $menu_rel = 'assets/img/icons/menu-icon-20x20.png';
        $menu_path = plugin_dir_path($this->file) . $menu_rel;
        $icon_url = file_exists($menu_path)
            ? plugin_dir_url($this->file) . $menu_rel
            : plugin_dir_url($this->file) . 'assets/img/glitchdata_logo1.png';

        add_menu_page(
            'wp_plugin',
            'wp_plugin',
            'manage_options',
            'wp-plugin',
            [$this, 'render_admin_page'],
            $icon_url
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
        
        // Tag generation settings
        add_settings_field(
            'auto_tag_enabled',
            'Auto-Tag Posts',
            [$this, 'render_auto_tag_field'],
            'wp-plugin-settings',
            'wp_plugin_general_section'
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
        
        if (isset($input['auto_tag_enabled'])) {
            $sanitized['auto_tag_enabled'] = (bool) $input['auto_tag_enabled'];
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
            <img src="<?php echo esc_url( plugin_dir_url($this->file) . 'assets/img/glitchdata_logo1.png' ); ?>" alt="wp_plugin logo" class="wp-plugin-logo" />
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-plugin" class="nav-tab">Dashboard</a>
                <a href="?page=wp-plugin-settings" class="nav-tab nav-tab-active">Settings</a>
            </h2>
            
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
        <label class="wp-plugin-toggle-switch">
            <input type="checkbox" name="wp_plugin_options[enable_feature]" value="1" <?php checked($enabled, true); ?> />
            <span class="wp-plugin-toggle-slider"></span>
        </label>
        <span class="wp-plugin-setting-label">Enable this feature</span>
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
        <label class="wp-plugin-toggle-switch">
            <input type="checkbox" name="wp_plugin_options[debug_mode]" value="1" <?php checked($debug, true); ?> />
            <span class="wp-plugin-toggle-slider"></span>
        </label>
        <span class="wp-plugin-setting-label">Enable debug mode</span>
        <p class="description">Enable debug logging for troubleshooting.</p>
        <?php
    }

    public function render_auto_tag_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['auto_tag_enabled']) ? $options['auto_tag_enabled'] : false;
        ?>
        <label class="wp-plugin-toggle-switch">
            <input type="checkbox" name="wp_plugin_options[auto_tag_enabled]" value="1" <?php checked($enabled, true); ?> />
            <span class="wp-plugin-toggle-slider"></span>
        </label>
        <span class="wp-plugin-setting-label">Enable automatic tag generation</span>
        <p class="description">Allow bulk tag generation from the Posts list page. Also adds a meta box to individual posts.</p>
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
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-plugin" class="nav-tab nav-tab-active">Dashboard</a>
                <a href="?page=wp-plugin-settings" class="nav-tab">Settings</a>
            </h2>
            
            <div class="card">
                <h2>Plugin Information</h2>
                <p><strong>Version:</strong> <?php echo esc_html(WP_PLUGIN_VERSION); ?></p>
                <p><strong>Update Source:</strong> GitHub Releases</p>
                <p><strong>Repository:</strong> <a href="https://github.com/terence/wp-plugin" target="_blank">terence/wp-plugin</a></p>
            </div>

            <div class="card">
                <h2>Current Settings</h2>
                <?php
                $options = get_option('wp_plugin_options', []);
                $enabled = isset($options['enable_feature']) ? $options['enable_feature'] : false;
                $debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
                $has_api_key = !empty($options['api_key']);
                ?>
                <table class="form-table">
                    <tr>
                        <th>Feature Status:</th>
                        <td>
                            <?php if ($enabled): ?>
                                <span style="color: green;">✓ Enabled</span>
                            <?php else: ?>
                                <span style="color: #999;">○ Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>API Key:</th>
                        <td>
                            <?php if ($has_api_key): ?>
                                <span style="color: green;">✓ Configured</span>
                            <?php else: ?>
                                <span style="color: #999;">○ Not set</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Debug Mode:</th>
                        <td>
                            <?php if ($debug): ?>
                                <span style="color: orange;">⚠ Enabled</span>
                            <?php else: ?>
                                <span style="color: green;">✓ Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p><a href="?page=wp-plugin-settings" class="button button-secondary">Manage Settings</a></p>
            </div>

            <div class="card">
                <h2>Posts Summary</h2>
                <?php
                // Get post counts
                $post_counts = wp_count_posts('post');
                $total_posts = $post_counts->publish + $post_counts->draft + $post_counts->pending;
                $published_posts = $post_counts->publish;
                
                // Get posts with tags count
                $posts_with_tags = $this->get_posts_with_tags_count();
                $posts_without_tags = $published_posts - $posts_with_tags;
                
                // Get total tag count
                $total_tags = wp_count_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
                
                // Calculate percentages
                $with_tags_percent = $published_posts > 0 ? round(($posts_with_tags / $published_posts) * 100) : 0;
                $without_tags_percent = $published_posts > 0 ? round(($posts_without_tags / $published_posts) * 100) : 0;
                ?>
                <table class="form-table">
                    <tr>
                        <th>Total Posts:</th>
                        <td><?php echo esc_html($total_posts); ?></td>
                    </tr>
                    <tr>
                        <th>Published Posts:</th>
                        <td><?php echo esc_html($published_posts); ?></td>
                    </tr>
                    <tr>
                        <th>Posts with Tags:</th>
                        <td>
                            <span style="color: green;"><?php echo esc_html($posts_with_tags); ?></span>
                            <?php if ($published_posts > 0): ?>
                                (<?php echo esc_html($with_tags_percent); ?>%)
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Posts without Tags:</th>
                        <td>
                            <?php if ($posts_without_tags > 0): ?>
                                <span style="color: orange;"><?php echo esc_html($posts_without_tags); ?></span>
                                (<?php echo esc_html($without_tags_percent); ?>%)
                            <?php else: ?>
                                <span style="color: green;">0</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total Tags:</th>
                        <td><?php echo esc_html($total_tags); ?></td>
                    </tr>
                </table>
                
                <?php if ($published_posts > 0): ?>
                <div style="margin-top: 20px;">
                    <h3 style="margin-bottom: 10px;">Tag Coverage</h3>
                    <div class="wp-plugin-chart-container">
                        <div class="wp-plugin-chart-bar">
                            <div class="wp-plugin-chart-segment wp-plugin-chart-tagged" 
                                 style="width: <?php echo esc_attr($with_tags_percent); ?>%;"
                                 title="Posts with tags: <?php echo esc_attr($posts_with_tags); ?> (<?php echo esc_attr($with_tags_percent); ?>%)">
                                <?php if ($with_tags_percent > 10): ?>
                                    <span class="wp-plugin-chart-label"><?php echo esc_html($with_tags_percent); ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="wp-plugin-chart-segment wp-plugin-chart-untagged" 
                                 style="width: <?php echo esc_attr($without_tags_percent); ?>%;"
                                 title="Posts without tags: <?php echo esc_attr($posts_without_tags); ?> (<?php echo esc_attr($without_tags_percent); ?>%)">
                                <?php if ($without_tags_percent > 10): ?>
                                    <span class="wp-plugin-chart-label"><?php echo esc_html($without_tags_percent); ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="wp-plugin-chart-legend">
                            <div class="wp-plugin-legend-item">
                                <span class="wp-plugin-legend-color wp-plugin-legend-tagged"></span>
                                <span>Posts with Tags (<?php echo esc_html($posts_with_tags); ?>)</span>
                            </div>
                            <div class="wp-plugin-legend-item">
                                <span class="wp-plugin-legend-color wp-plugin-legend-untagged"></span>
                                <span>Posts without Tags (<?php echo esc_html($posts_without_tags); ?>)</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($posts_without_tags > 0): ?>
                    <p style="margin-top: 15px;">
                        <a href="<?php echo admin_url('edit.php?post_type=post'); ?>" class="button button-secondary">
                            View Posts
                        </a>
                        <em style="margin-left: 10px;">Use the "Generate Tags" bulk action to add tags automatically.</em>
                    </p>
                <?php endif; ?>
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

    private function get_posts_with_tags_count(): int
    {
        global $wpdb;
        
        // Query to count published posts that have at least one tag
        $query = "
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'post_tag'
        ";
        
        $count = $wpdb->get_var($query);
        return (int) $count;
    }
}
