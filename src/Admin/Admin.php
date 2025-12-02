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
            'WP Plugin',        // Page title
            'Dashboard',        // Menu title
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
        
        // Auto Tagging Settings Section
        add_settings_section(
            'wp_plugin_auto_tagging_section',
            'Auto Tagging Settings',
            [$this, 'render_auto_tagging_section'],
            'wp-plugin-auto-tagging'
        );
        
        add_settings_field(
            'auto_tag_enabled',
            'Enable Auto-Tagging',
            [$this, 'render_auto_tag_field'],
            'wp-plugin-auto-tagging',
            'wp_plugin_auto_tagging_section'
        );
        
        add_settings_field(
            'max_tags_per_post',
            'Maximum Tags Per Post',
            [$this, 'render_max_tags_field'],
            'wp-plugin-auto-tagging',
            'wp_plugin_auto_tagging_section'
        );
        
        add_settings_field(
            'tag_exclusion_list',
            'Tag Exclusion List',
            [$this, 'render_tag_exclusion_field'],
            'wp-plugin-auto-tagging',
            'wp_plugin_auto_tagging_section'
        );
        
        // Advanced Settings Section
        add_settings_section(
            'wp_plugin_advanced_section',
            'Advanced Settings',
            [$this, 'render_advanced_section'],
            'wp-plugin-advanced'
        );
        
        // Example setting: Debug mode
        add_settings_field(
            'debug_mode',
            'Debug Mode',
            [$this, 'render_debug_mode_field'],
            'wp-plugin-advanced',
            'wp_plugin_advanced_section'
        );

        add_settings_field(
            'ai_optimization_enabled',
            'AI Tag Optimization',
            [$this, 'render_ai_optimization_field'],
            'wp-plugin-advanced',
            'wp_plugin_advanced_section'
        );
        
        add_settings_field(
            'ai_provider',
            'AI Provider',
            [$this, 'render_ai_provider_field'],
            'wp-plugin-advanced',
            'wp_plugin_advanced_section'
        );
        
        add_settings_field(
            'ai_api_key',
            'AI API Key',
            [$this, 'render_ai_api_key_field'],
            'wp-plugin-advanced',
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
            $api_key = sanitize_text_field($input['api_key']);
            
            // Validate API key format if provided
            if (!empty($api_key)) {
                // Check minimum length
                if (strlen($api_key) < 20) {
                    add_settings_error(
                        'wp_plugin_messages',
                        'wp_plugin_api_key_error',
                        'API Key must be at least 20 characters long.',
                        'error'
                    );
                }
                // Check for valid characters (alphanumeric, dashes, underscores)
                elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
                    add_settings_error(
                        'wp_plugin_messages',
                        'wp_plugin_api_key_error',
                        'API Key can only contain letters, numbers, dashes, and underscores.',
                        'error'
                    );
                }
                else {
                    // Validate with external license server
                    $license_check = $this->verify_api_key_license($api_key);
                    
                    if ($license_check['valid']) {
                        $sanitized['api_key'] = $api_key;
                        $sanitized['api_key_license_status'] = 'valid';
                        $sanitized['api_key_license_data'] = $license_check['data'];
                        $sanitized['api_key_last_checked'] = time();
                        
                        add_settings_error(
                            'wp_plugin_messages',
                            'wp_plugin_api_key_success',
                            'API Key validated successfully. License: ' . esc_html($license_check['data']['license_type'] ?? 'Active'),
                            'success'
                        );
                    } else {
                        $sanitized['api_key'] = $api_key;
                        $sanitized['api_key_license_status'] = 'invalid';
                        $sanitized['api_key_license_data'] = [];
                        $sanitized['api_key_last_checked'] = time();
                        
                        add_settings_error(
                            'wp_plugin_messages',
                            'wp_plugin_api_key_error',
                            'API Key format is valid, but license verification failed: ' . esc_html($license_check['message']),
                            'error'
                        );
                    }
                }
            } else {
                $sanitized['api_key'] = '';
                $sanitized['api_key_license_status'] = '';
                $sanitized['api_key_license_data'] = [];
            }
        }
        
        if (isset($input['debug_mode'])) {
            $sanitized['debug_mode'] = (bool) $input['debug_mode'];
        }
        
        if (isset($input['auto_tag_enabled'])) {
            $sanitized['auto_tag_enabled'] = (bool) $input['auto_tag_enabled'];
        }
        
        if (isset($input['max_tags_per_post'])) {
            $max_tags = absint($input['max_tags_per_post']);
            if ($max_tags < 1) {
                $max_tags = 10;
            } elseif ($max_tags > 50) {
                $max_tags = 50;
            }
            $sanitized['max_tags_per_post'] = $max_tags;
        } else {
            $sanitized['max_tags_per_post'] = 10;
        }
        
        if (isset($input['tag_exclusion_list'])) {
            $sanitized['tag_exclusion_list'] = sanitize_textarea_field($input['tag_exclusion_list']);
        }
        
        if (isset($input['ai_optimization_enabled'])) {
            $sanitized['ai_optimization_enabled'] = (bool) $input['ai_optimization_enabled'];
        }
        
        if (isset($input['ai_provider'])) {
            $sanitized['ai_provider'] = sanitize_text_field($input['ai_provider']);
        }
        
        if (isset($input['ai_api_key'])) {
            $sanitized['ai_api_key'] = sanitize_text_field($input['ai_api_key']);
        }
        
        return $sanitized;
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Determine active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
        
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
                <a href="?page=wp-plugin-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=wp-plugin-settings&tab=auto-tagging" class="nav-tab <?php echo $active_tab === 'auto-tagging' ? 'nav-tab-active' : ''; ?>">Auto Tagging</a>
                <a href="?page=wp-plugin-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
            </h2>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wp_plugin_settings');
                
                if ($active_tab === 'general') {
                    do_settings_sections('wp-plugin-settings');
                } elseif ($active_tab === 'auto-tagging') {
                    do_settings_sections('wp-plugin-auto-tagging');
                } elseif ($active_tab === 'advanced') {
                    do_settings_sections('wp-plugin-advanced');
                }
                
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

    public function render_auto_tagging_section(): void
    {
        echo '<p>Configure automatic tag generation for posts. The system analyzes post content and generates relevant tags based on word frequency.</p>';
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
        $is_valid = $this->validate_api_key_format($api_key);
        ?>
        <input type="text" 
               name="wp_plugin_options[api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               placeholder="Enter your API key" />
        <?php if (!empty($api_key)): ?>
            <span style="margin-left: 10px;">
                <?php if ($is_valid): ?>
                    <span style="color: green;">✓ Valid format</span>
                <?php else: ?>
                    <span style="color: red;">✗ Invalid format</span>
                <?php endif; ?>
            </span>
        <?php endif; ?>
        <p class="description">
            Your API key for external services.<br>
            <strong>Format requirements:</strong>
            <ul style="margin: 5px 0 0 20px;">
                <li>Minimum 20 characters</li>
                <li>Only letters (a-z, A-Z), numbers (0-9), dashes (-), and underscores (_)</li>
                <li>No spaces or special characters</li>
            </ul>
            <em>Example: my-api-key-1234567890abcdef_xyz</em>
        </p>
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
        <p class="description">
            When enabled, adds the following features:<br>
            • <strong>Bulk Action:</strong> "Generate Tags" option in the Posts list for multiple posts<br>
            • <strong>Row Action:</strong> "Generate Tags" link on individual posts in the Posts list<br>
            • <strong>Meta Box:</strong> Tag generator in the post editor sidebar
        </p>
        <?php
    }

    public function render_max_tags_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $max_tags = isset($options['max_tags_per_post']) ? $options['max_tags_per_post'] : 10;
        ?>
        <input type="number" 
               name="wp_plugin_options[max_tags_per_post]" 
               value="<?php echo esc_attr($max_tags); ?>" 
               min="1" 
               max="50" 
               step="1"
               class="small-text" />
        <p class="description">
            Maximum number of tags to generate per post (1-50). Default is 10.<br>
            The system analyzes post content by word frequency and will generate up to this many tags.
        </p>
        <?php
    }

    public function render_tag_exclusion_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $exclusion_list = isset($options['tag_exclusion_list']) ? $options['tag_exclusion_list'] : '';
        ?>
        <textarea name="wp_plugin_options[tag_exclusion_list]" 
                  rows="8" 
                  cols="50" 
                  class="large-text code"
                  placeholder="Enter words to exclude, one per line"><?php echo esc_textarea($exclusion_list); ?></textarea>
        <p class="description">
            Enter words that should <strong>never</strong> be used as tags, one per line. These words will be excluded when automatically generating tags.<br>
            <strong>Common words already excluded:</strong> the, and, or, but, in, on, at, to, for, of, with, by, from, as, is, was, are, were, be, been, being, have, has, had, do, does, did, will, would, could, should, may, might, must, can, this, that, these, those, i, you, he, she, it, we, they<br>
            <em>Example custom exclusions: wordpress, plugin, website, content, page, article, blog, post</em>
        </p>
        <?php
    }

    public function render_ai_optimization_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $enabled = isset($options['ai_optimization_enabled']) ? $options['ai_optimization_enabled'] : false;
        ?>
        <label class="wp-plugin-toggle-switch">
            <input type="checkbox" name="wp_plugin_options[ai_optimization_enabled]" value="1" <?php checked($enabled, true); ?> />
            <span class="wp-plugin-toggle-slider"></span>
        </label>
        <span class="wp-plugin-setting-label">Enable AI-powered tag optimization</span>
        <p class="description">
            When enabled, uses AI to refine and optimize generated tags for better relevance and SEO.<br>
            <strong>Features:</strong><br>
            • Contextual understanding of content<br>
            • Semantic similarity analysis<br>
            • Industry-specific tag suggestions<br>
            • Duplicate and similar tag consolidation<br>
            <em>Requires an AI provider API key configured below.</em>
        </p>
        <?php
    }

    public function render_ai_provider_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $provider = isset($options['ai_provider']) ? $options['ai_provider'] : 'openai';
        ?>
        <select name="wp_plugin_options[ai_provider]" class="regular-text">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI (GPT-3.5/GPT-4)</option>
            <option value="anthropic" <?php selected($provider, 'anthropic'); ?>>Anthropic (Claude)</option>
            <option value="google" <?php selected($provider, 'google'); ?>>Google (Gemini)</option>
            <option value="custom" <?php selected($provider, 'custom'); ?>>Custom API Endpoint</option>
        </select>
        <p class="description">
            Select the AI provider for tag optimization.<br>
            <strong>OpenAI:</strong> Uses GPT models for intelligent tag generation<br>
            <strong>Anthropic:</strong> Uses Claude for contextual analysis<br>
            <strong>Google:</strong> Uses Gemini for semantic understanding<br>
            <strong>Custom:</strong> Configure your own API endpoint (requires filter hook)
        </p>
        <?php
    }

    public function render_ai_api_key_field(): void
    {
        $options = get_option('wp_plugin_options', []);
        $ai_api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        $provider = isset($options['ai_provider']) ? $options['ai_provider'] : 'openai';
        ?>
        <input type="password" 
               name="wp_plugin_options[ai_api_key]" 
               value="<?php echo esc_attr($ai_api_key); ?>" 
               class="regular-text" 
               placeholder="Enter your AI provider API key" />
        <?php if (!empty($ai_api_key)): ?>
            <span style="margin-left: 10px; color: green;">✓ Key configured</span>
        <?php endif; ?>
        <p class="description">
            Your API key for the selected AI provider.<br>
            <strong>Where to get API keys:</strong><br>
            • <strong>OpenAI:</strong> <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a><br>
            • <strong>Anthropic:</strong> <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com/settings/keys</a><br>
            • <strong>Google:</strong> <a href="https://makersuite.google.com/app/apikey" target="_blank">makersuite.google.com/app/apikey</a><br>
            <em>Keep your API key secure. It will be encrypted in the database.</em>
        </p>
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
                <a href="?page=wp-plugin&tab=dashboard" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'dashboard') ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                <a href="?page=wp-plugin&tab=settings" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=wp-plugin&tab=auto-tagging" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'auto-tagging') ? 'nav-tab-active' : ''; ?>">Auto Tagging</a>
                <a href="?page=wp-plugin&tab=advanced" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'advanced') ? 'nav-tab-active' : ''; ?>">Advanced</a>
            </h2>

            <?php
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
            if ($tab === 'dashboard') {
                // ...existing code for dashboard cards...
                ?>
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
                    $api_key_valid = $this->validate_api_key_format($options['api_key'] ?? '');
                    $license_status = $options['api_key_license_status'] ?? '';
                    $license_data = $options['api_key_license_data'] ?? [];
                    $last_checked = $options['api_key_last_checked'] ?? 0;
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
                                <?php if ($has_api_key && $api_key_valid): ?>
                                    <span style="color: green;">✓ Configured & Valid</span>
                                    <span style="color: #666; font-size: 12px;">(<?php echo esc_html(strlen($options['api_key'])); ?> characters)</span>
                                <?php elseif ($has_api_key && !$api_key_valid): ?>
                                    <span style="color: red;">✗ Invalid Format</span>
                                <?php else: ?>
                                    <span style="color: #999;">○ Not set</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($has_api_key && $api_key_valid): ?>
                        <tr>
                            <th>License Status:</th>
                            <td>
                                <?php if ($license_status === 'valid'): ?>
                                    <span style="color: green;">✓ Active License</span>
                                    <?php if (!empty($license_data['license_type'])): ?>
                                        <span style="color: #666; font-size: 12px;"> - <?php echo esc_html(ucfirst($license_data['license_type'])); ?></span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if (!empty($license_data['expires_at'])): ?>
                                        <span style="color: #666; font-size: 12px;">Expires: <?php echo esc_html(date('Y-m-d', strtotime($license_data['expires_at']))); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($license_data['customer_name'])): ?>
                                        <br><span style="color: #666; font-size: 12px;">Licensed to: <?php echo esc_html($license_data['customer_name']); ?></span>
                                    <?php endif; ?>
                                <?php elseif ($license_status === 'invalid'): ?>
                                    <span style="color: red;">✗ License Verification Failed</span>
                                <?php else: ?>
                                    <span style="color: #999;">○ Not Verified</span>
                                <?php endif; ?>
                                <?php if ($last_checked > 0): ?>
                                    <br><span style="color: #999; font-size: 11px;">Last checked: <?php echo human_time_diff($last_checked); ?> ago</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
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
                </div>

                <div class="card">
                    <h2>Posts Summary</h2>
                    <?php
                    // ...existing code for posts summary...
                    ?>
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
                <?php
            } elseif ($tab === 'settings') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_plugin_settings');
                    do_settings_sections('wp-plugin-settings');
                    submit_button('Save Settings');
                    ?>
                </form>
                <?php
            } elseif ($tab === 'auto-tagging') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_plugin_settings');
                    do_settings_sections('wp-plugin-auto-tagging');
                    submit_button('Save Auto Tagging Settings');
                    ?>
                </form>
                <?php
            } elseif ($tab === 'advanced') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_plugin_settings');
                    do_settings_sections('wp-plugin-advanced');
                    submit_button('Save Advanced Settings');
                    ?>
                </form>
                <?php
            }
            ?>
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

    private function validate_api_key_format(string $api_key): bool
    {
        if (empty($api_key)) {
            return false;
        }
        
        // Check minimum length
        if (strlen($api_key) < 20) {
            return false;
        }
        
        // Check for valid characters (alphanumeric, dashes, underscores)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
            return false;
        }
        
        return true;
    }

    private function verify_api_key_license(string $api_key): array
    {
        // Configure your license server endpoint
        $license_server_url = apply_filters('wp_plugin_license_server_url', 'https://api.example.com/v1/verify-license');
        
        // Build the request
        $response = wp_remote_post($license_server_url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'api_key' => $api_key,
                'site_url' => get_site_url(),
                'plugin_version' => WP_PLUGIN_VERSION,
            ]),
        ]);
        
        // Check for errors
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Could not connect to license server: ' . $response->get_error_message(),
                'data' => [],
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Check response
        if ($status_code === 200 && isset($data['valid']) && $data['valid'] === true) {
            return [
                'valid' => true,
                'message' => 'License verified successfully',
                'data' => [
                    'license_type' => $data['license_type'] ?? 'standard',
                    'expires_at' => $data['expires_at'] ?? null,
                    'customer_name' => $data['customer_name'] ?? '',
                    'max_sites' => $data['max_sites'] ?? 1,
                ],
            ];
        }
        
        // License validation failed
        $error_message = $data['message'] ?? 'Invalid license key';
        
        return [
            'valid' => false,
            'message' => $error_message,
            'data' => [],
        ];
    }
}
