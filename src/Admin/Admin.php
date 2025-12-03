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
        add_action('admin_post_gd_autotag_run_schedule_now', [$this, 'handle_manual_schedule_run']);
        add_filter('plugin_action_links_' . plugin_basename($this->file), [$this, 'add_action_links']);
    }

    public function enqueue_admin_assets(): void
    {
        $assets_url = plugin_dir_url($this->file) . 'assets/';
        wp_enqueue_style('gd-autotag-admin', $assets_url . 'css/admin.css', [], GD_AUTOTAG_VERSION);

        wp_enqueue_script('d3', 'https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js', [], '7.8.5', true);
        wp_enqueue_script('gd-autotag-admin', $assets_url . 'js/admin.js', ['jquery', 'd3'], GD_AUTOTAG_VERSION, true);

        $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if ($current_page === 'gd-autotag') {
            $tagStats = $this->get_tag_usage_stats(8);
            wp_localize_script('gd-autotag-admin', 'gdAutotagDashboardData', [
                'postTimeline' => $this->get_monthly_post_stats(12),
                'topTags' => $tagStats['top_tags'] ?? [],
            ]);
        }
    }

    public function add_action_links($links): array
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=gd-autotag&tab=settings')) . '">Settings</a>';
        $dashboard_link = '<a href="' . esc_url(admin_url('admin.php?page=gd-autotag')) . '">Dashboard</a>';

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
            'GD AutoTag Dashboard',       // Page title
            'GD AutoTag',       // Menu title
            'manage_options',
            'gd-autotag',
            [$this, 'render_admin_page'],
            $icon_url
        );

        add_submenu_page(
            'gd-autotag',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'gd-autotag',
            [$this, 'render_admin_page']
        );
        
        add_submenu_page(
            'gd-autotag',
            'Settings',
            'Settings',
            'manage_options',
            'gd-autotag&tab=settings',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=gd-autotag&tab=settings'));
                exit;
            }
        );
    }

    public function register_settings(): void
    {
        // Register settings group
        register_setting('gd_autotag_settings', 'gd_autotag_options', [$this, 'sanitize_settings']);
        
        // General Settings Section
        add_settings_section(
            'gd_autotag_general_section',
            'General Settings',
            [$this, 'render_general_section'],
            'gd-autotag-settings'
        );
        
        // Example setting: API Key
        add_settings_field(
            'api_key',
            'API Key',
            [$this, 'render_api_key_field'],
            'gd-autotag-settings',
            'gd_autotag_general_section'
        );

        add_settings_field(
            'debug_mode',
            'Debug Mode',
            [$this, 'render_debug_mode_field'],
            'gd-autotag-settings',
            'gd_autotag_general_section'
        );
        
        // Auto Tag Settings Section
        add_settings_section(
            'gd_autotag_auto_tagging_section',
            'Auto Tag Settings',
            [$this, 'render_auto_tagging_section'],
            'gd-autotag-auto-tagging'
        );
        
        add_settings_field(
            'auto_tag_enabled',
            'Enable Auto-Tagging',
            [$this, 'render_auto_tag_field'],
            'gd-autotag-auto-tagging',
            'gd_autotag_auto_tagging_section'
        );
        
        add_settings_field(
            'max_tags_per_post',
            'Maximum Tags Per Post',
            [$this, 'render_max_tags_field'],
            'gd-autotag-auto-tagging',
            'gd_autotag_auto_tagging_section'
        );
        
        add_settings_field(
            'tag_exclusion_list',
            'Tag Exclusion List',
            [$this, 'render_tag_exclusion_field'],
            'gd-autotag-auto-tagging',
            'gd_autotag_auto_tagging_section'
        );

        // Auto Categories Settings Section
        add_settings_section(
            'gd_autotag_auto_categories_section',
            'Auto Category Settings',
            [$this, 'render_auto_categories_section'],
            'gd-autotag-auto-categories'
        );

        add_settings_field(
            'auto_category_enabled',
            'Enable Auto-Categorization',
            [$this, 'render_auto_category_field'],
            'gd-autotag-auto-categories',
            'gd_autotag_auto_categories_section'
        );

        add_settings_field(
            'auto_category_sync_on_save',
            'Sync Categories on Save',
            [$this, 'render_auto_category_sync_field'],
            'gd-autotag-auto-categories',
            'gd_autotag_auto_categories_section'
        );

        add_settings_field(
            'auto_category_strategy',
            'Categorization Strategy',
            [$this, 'render_auto_category_strategy_field'],
            'gd-autotag-auto-categories',
            'gd_autotag_auto_categories_section'
        );

        add_settings_field(
            'auto_category_max_categories',
            'Maximum Categories Per Post',
            [$this, 'render_auto_category_limit_field'],
            'gd-autotag-auto-categories',
            'gd_autotag_auto_categories_section'
        );

        add_settings_field(
            'auto_category_fallback',
            'Fallback Category',
            [$this, 'render_auto_category_fallback_field'],
            'gd-autotag-auto-categories',
            'gd_autotag_auto_categories_section'
        );
        
        // Schedule Settings Section (Settings tab)
        add_settings_section(
            'gd_autotag_schedule_section',
            'Schedule',
            [$this, 'render_schedule_section'],
            'gd-autotag-settings'
        );

        add_settings_field(
            'schedule_enabled',
            'Enable Schedule',
            [$this, 'render_schedule_toggle_field'],
            'gd-autotag-settings',
            'gd_autotag_schedule_section'
        );

        add_settings_field(
            'schedule_frequency',
            'Run Frequency',
            [$this, 'render_schedule_frequency_field'],
            'gd-autotag-settings',
            'gd_autotag_schedule_section'
        );

        add_settings_field(
            'schedule_time',
            'Preferred Run Time',
            [$this, 'render_schedule_time_field'],
            'gd-autotag-settings',
            'gd_autotag_schedule_section'
        );

        add_settings_field(
            'schedule_batch_size',
            'Posts Per Run',
            [$this, 'render_schedule_batch_size_field'],
            'gd-autotag-settings',
            'gd_autotag_schedule_section'
        );

        // Advanced Settings Section
        add_settings_section(
            'gd_autotag_advanced_section',
            'Advanced Settings',
            [$this, 'render_advanced_section'],
            'gd-autotag-advanced'
        );
        
        add_settings_field(
            'ai_optimization_enabled',
            'AI Tag Optimization',
            [$this, 'render_ai_optimization_field'],
            'gd-autotag-advanced',
            'gd_autotag_advanced_section'
        );
        
        add_settings_field(
            'ai_provider',
            'AI Provider',
            [$this, 'render_ai_provider_field'],
            'gd-autotag-advanced',
            'gd_autotag_advanced_section'
        );
        
        add_settings_field(
            'ai_api_key',
            'AI API Key',
            [$this, 'render_ai_api_key_field'],
            'gd-autotag-advanced',
            'gd_autotag_advanced_section'
        );
    }

    public function sanitize_settings($input)
    {
        $sanitized = [];
        
        if (isset($input['api_key'])) {
            $api_key = sanitize_text_field($input['api_key']);
            
            // Validate API key format if provided
            if (!empty($api_key)) {
                // Check minimum length
                if (strlen($api_key) < 20) {
                    add_settings_error(
                        'gd_autotag_messages',
                        'gd_autotag_api_key_error',
                        'API Key must be at least 20 characters long.',
                        'error'
                    );
                }
                // Check for valid characters (alphanumeric, dashes, underscores)
                elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $api_key)) {
                    add_settings_error(
                        'gd_autotag_messages',
                        'gd_autotag_api_key_error',
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
                            'gd_autotag_messages',
                            'gd_autotag_api_key_success',
                            'API Key validated successfully. License: ' . esc_html($license_check['data']['license_type'] ?? 'Active'),
                            'success'
                        );
                    } else {
                        $sanitized['api_key'] = $api_key;
                        $sanitized['api_key_license_status'] = 'invalid';
                        $sanitized['api_key_license_data'] = [];
                        $sanitized['api_key_last_checked'] = time();
                        
                        add_settings_error(
                            'gd_autotag_messages',
                            'gd_autotag_api_key_error',
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

        if (isset($input['schedule_enabled'])) {
            $sanitized['schedule_enabled'] = (bool) $input['schedule_enabled'];
        }

        if (isset($input['schedule_frequency'])) {
            $frequency = sanitize_text_field($input['schedule_frequency']);
            $allowed_freqs = ['hourly', 'twicedaily', 'daily'];
            if (!in_array($frequency, $allowed_freqs, true)) {
                $frequency = 'daily';
            }
            $sanitized['schedule_frequency'] = $frequency;
        }

        if (isset($input['schedule_time'])) {
            $time = sanitize_text_field($input['schedule_time']);
            if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
                $time = '02:00';
            }
            $sanitized['schedule_time'] = $time;
        }

        if (isset($input['schedule_batch_size'])) {
            $batch = absint($input['schedule_batch_size']);
            if ($batch < 1) {
                $batch = 5;
            } elseif ($batch > 50) {
                $batch = 50;
            }
            $sanitized['schedule_batch_size'] = $batch;
        }

        if (isset($input['auto_category_enabled'])) {
            $sanitized['auto_category_enabled'] = (bool) $input['auto_category_enabled'];
        }

        if (isset($input['auto_category_sync_on_save'])) {
            $sanitized['auto_category_sync_on_save'] = (bool) $input['auto_category_sync_on_save'];
        }

        if (isset($input['auto_category_strategy'])) {
            $strategy = sanitize_text_field($input['auto_category_strategy']);
            $allowed_strategies = ['tag-match', 'content-match'];
            if (!in_array($strategy, $allowed_strategies, true)) {
                $strategy = 'tag-match';
            }
            $sanitized['auto_category_strategy'] = $strategy;
        }

        if (isset($input['auto_category_max_categories'])) {
            $max_categories = absint($input['auto_category_max_categories']);
            if ($max_categories < 1) {
                $max_categories = 1;
            } elseif ($max_categories > 10) {
                $max_categories = 10;
            }
            $sanitized['auto_category_max_categories'] = $max_categories;
        }

        if (isset($input['auto_category_fallback'])) {
            $sanitized['auto_category_fallback'] = absint($input['auto_category_fallback']);
        }
        
        return $sanitized;
    }

    public function render_general_section(array $section = []): void
    {
        echo '<p>Configure the core plugin behavior and API access.</p>';
    }

    public function render_auto_tagging_section(array $section = []): void
    {
        echo '<p>Control how automatic tag generation behaves across your posts.</p>';
    }

    public function render_auto_categories_section(array $section = []): void
    {
        echo '<p>Configure how posts inherit categories automatically based on tags and content.</p>';
    }

    public function render_schedule_section(array $section = []): void
    {
        $last_run = get_option('gd_autotag_schedule_last_run');
        $last_run_text = $last_run
            ? sprintf('Last run %s ago', human_time_diff($last_run, time()))
            : 'Not run yet';
        echo '<p>Automatically run tagging/categorization tasks on a schedule. ' . esc_html($last_run_text) . '.</p>';
        $referer_path = esc_url_raw($_SERVER['REQUEST_URI'] ?? 'admin.php?page=gd-autotag&tab=settings');
        $run_now_url = add_query_arg(
            [
                'action' => 'gd_autotag_run_schedule_now',
                '_wp_http_referer' => rawurlencode($referer_path),
            ],
            wp_nonce_url(
                admin_url('admin-post.php'),
                'gd_autotag_manual_schedule_run'
            )
        );
        ?>
        <p>
            <a href="<?php echo esc_url($run_now_url); ?>" class="button">Run Now</a>
            <span class="description" style="margin-left: 8px;">Process the current batch immediately using the settings below.</span>
        </p>
        <?php
    }

    public function render_advanced_section(array $section = []): void
    {
        echo '<p>Advanced options for debugging, AI integrations, and power users.</p>';
    }

    public function render_api_key_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $is_valid = $this->validate_api_key_format($api_key);
        $status_id = 'gd-autotag-auto-save-api-key';
        ?>
        <input type="text" 
               name="gd_autotag_options[api_key]" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text" 
               placeholder="Enter your API key"
               data-auto-save="1"
               data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
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
        $options = get_option('gd_autotag_options', []);
        $debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
        $status_id = 'gd-autotag-auto-save-debug-mode';
        ?>
        <input type="hidden" name="gd_autotag_options[debug_mode]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[debug_mode]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($debug, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Enable debug mode</span>
        <p class="description">Enable debug logging for troubleshooting.</p>
        <?php
    }

    public function render_auto_tag_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $enabled = isset($options['auto_tag_enabled']) ? $options['auto_tag_enabled'] : false;
        $status_id = 'gd-autotag-auto-save-auto-tag';
        ?>
        <input type="hidden" name="gd_autotag_options[auto_tag_enabled]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[auto_tag_enabled]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($enabled, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Enable automatic tag generation</span>
        <p class="description">
            When enabled, adds the following features:<br>
            • <strong>Bulk Action:</strong> "Generate Tags" option in the Posts list for multiple posts<br>
            • <strong>Row Action:</strong> "Generate Tags" link on individual posts in the Posts list<br>
            • <strong>Meta Box:</strong> Tag generator in the post editor sidebar
        </p>
        <?php
    }

    public function render_schedule_toggle_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $enabled = isset($options['schedule_enabled']) ? (bool) $options['schedule_enabled'] : false;
        $status_id = 'gd-autotag-auto-save-schedule';
        ?>
        <input type="hidden" name="gd_autotag_options[schedule_enabled]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[schedule_enabled]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($enabled, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Run GD AutoTag automatically</span>
        <p class="description">When enabled, WordPress cron will execute the tagging/categorization jobs at the frequency you choose.</p>
        <?php
    }

    public function render_schedule_frequency_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $frequency = isset($options['schedule_frequency']) ? $options['schedule_frequency'] : 'daily';
        $status_id = 'gd-autotag-auto-save-schedule-frequency';
        ?>
        <select name="gd_autotag_options[schedule_frequency]"
                data-auto-save="1"
                data-auto-save-target="<?php echo esc_attr($status_id); ?>">
            <option value="hourly" <?php selected($frequency, 'hourly'); ?>>Hourly</option>
            <option value="twicedaily" <?php selected($frequency, 'twicedaily'); ?>>Twice Daily</option>
            <option value="daily" <?php selected($frequency, 'daily'); ?>>Daily</option>
        </select>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">Choose how often to process posts automatically.</p>
        <?php
    }

    public function render_schedule_time_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $time = isset($options['schedule_time']) ? $options['schedule_time'] : '02:00';
        $status_id = 'gd-autotag-auto-save-schedule-time';
        ?>
        <input type="time"
               name="gd_autotag_options[schedule_time]"
               value="<?php echo esc_attr($time); ?>"
               data-auto-save="1"
               data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">Used for daily schedules to specify the preferred start time (site timezone).</p>
        <?php
    }

    public function render_schedule_batch_size_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $batch = isset($options['schedule_batch_size']) ? $options['schedule_batch_size'] : 5;
        $status_id = 'gd-autotag-auto-save-schedule-batch';
        ?>
        <input type="number"
               name="gd_autotag_options[schedule_batch_size]"
               value="<?php echo esc_attr($batch); ?>"
               min="1"
               max="50"
               step="1"
               data-auto-save="1"
               data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">How many posts to attempt per run. Larger batches may impact performance.</p>
        <?php
    }

    public function render_max_tags_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $max_tags = isset($options['max_tags_per_post']) ? $options['max_tags_per_post'] : 10;
        $status_id = 'gd-autotag-auto-save-max-tags';
        ?>
        <input type="number" 
               name="gd_autotag_options[max_tags_per_post]" 
               value="<?php echo esc_attr($max_tags); ?>" 
               min="1" 
               max="50" 
               step="1"
               class="small-text"
               data-auto-save="1"
               data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">
            Maximum number of tags to generate per post (1-50). Default is 10.<br>
            The system analyzes post content by word frequency and will generate up to this many tags.
        </p>
        <?php
    }

    public function render_auto_category_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $enabled = isset($options['auto_category_enabled']) ? $options['auto_category_enabled'] : false;
        $status_id = 'gd-autotag-auto-save-auto-category';
        ?>
        <input type="hidden" name="gd_autotag_options[auto_category_enabled]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[auto_category_enabled]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($enabled, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Enable automatic category suggestions</span>
        <p class="description">
            Adds bulk/row actions and editor tools for assigning categories automatically based on your rules.
        </p>
        <?php
    }

    public function render_auto_category_sync_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $enabled = isset($options['auto_category_sync_on_save']) ? $options['auto_category_sync_on_save'] : false;
        $status_id = 'gd-autotag-auto-save-auto-category-sync';
        ?>
        <input type="hidden" name="gd_autotag_options[auto_category_sync_on_save]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[auto_category_sync_on_save]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($enabled, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Recalculate categories whenever a post is saved</span>
        <p class="description">Helpful when editors frequently change titles, tags, or content that affect category selection.</p>
        <?php
    }

    public function render_auto_category_strategy_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $strategy = isset($options['auto_category_strategy']) ? $options['auto_category_strategy'] : 'tag-match';
        $status_id = 'gd-autotag-auto-save-auto-category-strategy';
        ?>
        <select name="gd_autotag_options[auto_category_strategy]" class="regular-text"
                data-auto-save="1"
                data-auto-save-target="<?php echo esc_attr($status_id); ?>">
            <option value="tag-match" <?php selected($strategy, 'tag-match'); ?>>Match categories to existing tags</option>
            <option value="content-match" <?php selected($strategy, 'content-match'); ?>>Scan content for category keywords</option>
        </select>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">
            <strong>Tag match:</strong> Align categories to tags that share the same name.<br>
            <strong>Content match:</strong> Detect category names and slugs directly inside the post content.
        </p>
        <?php
    }

    public function render_auto_category_limit_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $limit = isset($options['auto_category_max_categories']) ? $options['auto_category_max_categories'] : 3;
         $status_id = 'gd-autotag-auto-save-auto-category-limit';
        ?>
        <input type="number"
               name="gd_autotag_options[auto_category_max_categories]"
               value="<?php echo esc_attr($limit); ?>"
               min="1"
               max="10"
               step="1"
             class="small-text"
             data-auto-save="1"
             data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
         <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">Upper bound on the number of categories added per post (1-10). Default is 3.</p>
        <?php
    }

    public function render_auto_category_fallback_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $selected = isset($options['auto_category_fallback']) ? (int) $options['auto_category_fallback'] : 0;
        $status_id = 'gd-autotag-auto-save-auto-category-fallback';
        $dropdown = wp_dropdown_categories([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'name' => 'gd_autotag_options[auto_category_fallback]',
            'orderby' => 'name',
            'hierarchical' => true,
            'show_option_none' => '— None —',
            'option_none_value' => '0',
            'selected' => $selected,
            'echo' => false,
        ]);

        $dropdown = str_replace('<select', '<select data-auto-save="1" data-auto-save-target="' . esc_attr($status_id) . '"', $dropdown);

        echo $dropdown;
        ?>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">Used when no categories are detected. Leave as “None” to skip fallback assignment.</p>
        <?php
    }

    public function render_tag_exclusion_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $exclusion_list = isset($options['tag_exclusion_list']) ? $options['tag_exclusion_list'] : '';
        $status_id = 'gd-autotag-auto-save-tag-exclusion';
        ?>
        <textarea name="gd_autotag_options[tag_exclusion_list]" 
                  rows="8" 
                  cols="50" 
                  class="large-text code"
                  placeholder="Enter words to exclude, one per line"
                  data-auto-save="1"
                  data-auto-save-target="<?php echo esc_attr($status_id); ?>"
        ><?php echo esc_textarea($exclusion_list); ?></textarea>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <p class="description">
            Enter words that should <strong>never</strong> be used as tags, one per line. These words will be excluded when automatically generating tags.<br>
            <strong>Common words already excluded:</strong> the, and, or, but, in, on, at, to, for, of, with, by, from, as, is, was, are, were, be, been, being, have, has, had, do, does, did, will, would, could, should, may, might, must, can, this, that, these, those, i, you, he, she, it, we, they<br>
            <em>Example custom exclusions: wordpress, plugin, website, content, page, article, blog, post</em>
        </p>
        <?php
    }

    public function render_ai_optimization_field(): void
    {
        $options = get_option('gd_autotag_options', []);
        $enabled = isset($options['ai_optimization_enabled']) ? $options['ai_optimization_enabled'] : false;
        $status_id = 'gd-autotag-auto-save-ai-optimization';
        ?>
        <input type="hidden" name="gd_autotag_options[ai_optimization_enabled]" value="0" />
        <label class="gd-autotag-toggle-switch">
            <input type="checkbox"
                   name="gd_autotag_options[ai_optimization_enabled]"
                   value="1"
                   data-auto-save="1"
                   data-auto-save-target="<?php echo esc_attr($status_id); ?>"
                   <?php checked($enabled, true); ?> />
            <span class="gd-autotag-toggle-slider"></span>
        </label>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
        <span class="gd-autotag-setting-label">Enable AI-powered tag optimization</span>
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
        $options = get_option('gd_autotag_options', []);
        $provider = isset($options['ai_provider']) ? $options['ai_provider'] : 'openai';
        $status_id = 'gd-autotag-auto-save-ai-provider';
        ?>
        <select name="gd_autotag_options[ai_provider]" class="regular-text"
                data-auto-save="1"
                data-auto-save-target="<?php echo esc_attr($status_id); ?>">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI (GPT-3.5/GPT-4)</option>
            <option value="anthropic" <?php selected($provider, 'anthropic'); ?>>Anthropic (Claude)</option>
            <option value="google" <?php selected($provider, 'google'); ?>>Google (Gemini)</option>
            <option value="custom" <?php selected($provider, 'custom'); ?>>Custom API Endpoint</option>
        </select>
        <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
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
        $options = get_option('gd_autotag_options', []);
        $ai_api_key = isset($options['ai_api_key']) ? $options['ai_api_key'] : '';
        $provider = isset($options['ai_provider']) ? $options['ai_provider'] : 'openai';
         $status_id = 'gd-autotag-auto-save-ai-api-key';
        ?>
        <input type="password" 
               name="gd_autotag_options[ai_api_key]" 
               value="<?php echo esc_attr($ai_api_key); ?>" 
               class="regular-text" 
             placeholder="Enter your AI provider API key"
             data-auto-save="1"
             data-auto-save-target="<?php echo esc_attr($status_id); ?>" />
         <span id="<?php echo esc_attr($status_id); ?>" class="gd-autotag-auto-save-status" aria-live="polite"></span>
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
        if (isset($_POST['check_for_updates']) && check_admin_referer('gd_autotag_check_updates')) {
            $this->force_update_check();
            $manualCheckPerformed = true;
        }

        $scheduleRunStatus = isset($_GET['gd_autotag_schedule_run']) ? sanitize_text_field($_GET['gd_autotag_schedule_run']) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if ($scheduleRunStatus === 'success'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>✓ Scheduled tagging/categorization job completed successfully.</p>
                </div>
            <?php elseif ($scheduleRunStatus === 'error'): ?>
                <div class="notice notice-error is-dismissible">
                    <p>Unable to run the scheduled job. Please check logs and try again.</p>
                </div>
            <?php endif; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=gd-autotag&tab=dashboard" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'dashboard') ? 'nav-tab-active' : ''; ?>">Dashboard</a>
                <a href="?page=gd-autotag&tab=settings" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=gd-autotag&tab=auto-tagging" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'auto-tagging') ? 'nav-tab-active' : ''; ?>">Auto Tag</a>
                <a href="?page=gd-autotag&tab=auto-categories" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'auto-categories') ? 'nav-tab-active' : ''; ?>">Auto Categories</a>
                <a href="?page=gd-autotag&tab=advanced" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'advanced') ? 'nav-tab-active' : ''; ?>">Advanced</a>
            </h2>

            <?php
            $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
            if ($tab === 'dashboard') {
                ?>
                <div class="gd-autotag-dashboard-grid">
                    <div class="gd-autotag-dashboard-column gd-autotag-dashboard-column--main">
                        <div class="card">
                            <h2>Posts Summary</h2>
                            <?php
                            $post_counts = wp_count_posts('post');
                            $published_posts = (int) ($post_counts->publish ?? 0);
                            $draft_posts = (int) ($post_counts->draft ?? 0);
                            $scheduled_posts = (int) ($post_counts->future ?? 0);
                            $posts_with_tags = $this->get_posts_with_tags_count();
                            $posts_without_tags = max(0, $published_posts - $posts_with_tags);
                            $tag_coverage = $published_posts > 0 ? round(($posts_with_tags / $published_posts) * 100) : 0;
                            $total_tags = wp_count_terms('post_tag', ['hide_empty' => false]);

                            $timeline = $this->get_monthly_post_stats(12);
                            $timelineMax = 0;
                            foreach ($timeline as $point) {
                                $timelineMax = max($timelineMax, $point['total']);
                            }
                            $timelineMax = max(1, $timelineMax);
                            $tagStats = $this->get_tag_usage_stats(8);
                            ?>
                            <div class="gd-autotag-summary-grid">
                                <div class="summary-item">
                                    <span class="label">Published Posts</span>
                                    <span class="value"><?php echo number_format_i18n($published_posts); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Drafts</span>
                                    <span class="value"><?php echo number_format_i18n($draft_posts); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Scheduled</span>
                                    <span class="value"><?php echo number_format_i18n($scheduled_posts); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Total Tags</span>
                                    <span class="value"><?php echo number_format_i18n($total_tags); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Tagged Posts</span>
                                    <span class="value success"><?php echo number_format_i18n($posts_with_tags); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Untagged Posts</span>
                                    <span class="value warning"><?php echo number_format_i18n($posts_without_tags); ?></span>
                                </div>
                                <div class="summary-item wide">
                                    <span class="label">Tag Coverage</span>
                                    <span class="value"><?php echo esc_html($tag_coverage); ?>%</span>
                                </div>
                            </div>

                            <h3 style="margin-top: 20px;">Post Analytics (Last 12 Months)</h3>
                            <div id="gd-autotag-post-timeline" class="gd-autotag-line-chart" aria-label="Posts over time">
                                <noscript>Enable JavaScript to view the post analytics line chart.</noscript>
                            </div>
                        </div>

                        <div class="card">
                            <h2>Tag Summary</h2>
                            <p style="margin-top: -10px; color: #666;">Review tag coverage and the tags appearing most across your posts.</p>
                            <div class="gd-autotag-summary-grid">
                                <div class="summary-item">
                                    <span class="label">Avg Tags / Tagged Post</span>
                                    <span class="value"><?php echo esc_html(number_format_i18n($tagStats['average_tags_per_tagged_post'] ?? 0, 1)); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Total Tag Assignments</span>
                                    <span class="value"><?php echo number_format_i18n($tagStats['total_assignments'] ?? 0); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Unused Tags</span>
                                    <span class="value warning"><?php echo number_format_i18n($tagStats['unused_tags'] ?? 0); ?></span>
                                </div>
                            </div>

                            <h3 style="margin-top: 20px;">Top Tags</h3>
                            <div class="gd-autotag-tag-analytics">
                                <div class="gd-autotag-tag-analytics__list">
                            <?php if (!empty($tagStats['top_tags'])): ?>
                                <ul class="gd-autotag-top-tags-list">
                                    <?php foreach ($tagStats['top_tags'] as $tag): ?>
                                        <li>
                                            <span class="tag-name"><?php echo esc_html($tag['name']); ?></span>
                                            <span class="tag-count"><?php echo number_format_i18n($tag['count']); ?> posts</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No tag data available yet.</p>
                            <?php endif; ?>
                                </div>
                                <div class="gd-autotag-tag-analytics__chart">
                                    <div id="gd-autotag-top-tags-chart" class="gd-autotag-bar-chart" aria-label="Top tags bar chart">
                                        <noscript>Enable JavaScript to view the top tags bar chart.</noscript>
                                    </div>
                                </div>
                            </div>
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
                                <?php wp_nonce_field('gd_autotag_check_updates'); ?>
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
                                <li>Update the version number in <code>gd-autotag.php</code></li>
                                <li>Create a new tag: <code>git tag v1.0.1</code></li>
                                <li>Push the tag: <code>git push origin --tags</code></li>
                                <li>Create a GitHub Release with a ZIP asset</li>
                            </ol>
                        </div>
                    </div>

                    <div class="gd-autotag-dashboard-column gd-autotag-dashboard-column--sidebar">
                        <div class="card">
                            <h2>Current Settings</h2>
                            <?php
                            $options = get_option('gd_autotag_options', []);
                            $debug = isset($options['debug_mode']) ? $options['debug_mode'] : false;
                            $has_api_key = !empty($options['api_key']);
                            $api_key_valid = $this->validate_api_key_format($options['api_key'] ?? '');
                            $license_status = $options['api_key_license_status'] ?? '';
                            $license_data = $options['api_key_license_data'] ?? [];
                            $last_checked = $options['api_key_last_checked'] ?? 0;
                            ?>
                            <table class="form-table">
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
                            <h2>Plugin Information</h2>
                            <p><strong>Version:</strong> <?php echo esc_html(GD_AUTOTAG_VERSION); ?></p>
                            <p><strong>Update Source:</strong> GitHub Releases</p>
                            <p><strong>Repository:</strong> <a href="https://github.com/terence/gd-autotag" target="_blank">terence/gd-autotag</a></p>
                        </div>
                    </div>
                </div>
                <?php
            } elseif ($tab === 'settings') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('gd_autotag_settings');
                    do_settings_sections('gd-autotag-settings');
                    submit_button('Save Settings');
                    ?>
                </form>
                <?php
            } elseif ($tab === 'auto-tagging') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('gd_autotag_settings');
                    do_settings_sections('gd-autotag-auto-tagging');
                    submit_button('Save Auto Tag Settings');
                    ?>
                </form>
                <?php
            } elseif ($tab === 'auto-categories') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('gd_autotag_settings');
                    do_settings_sections('gd-autotag-auto-categories');
                    submit_button('Save Auto Category Settings');
                    ?>
                </form>
                <?php
            } elseif ($tab === 'advanced') {
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('gd_autotag_settings');
                    do_settings_sections('gd-autotag-advanced');
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
        global $gd_autotag_update_checker;
        return $gd_autotag_update_checker ?? null;
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

    private function get_monthly_post_stats(int $months = 12): array
    {
        global $wpdb;

        $months = max(1, min(24, $months));
        $startDate = date('Y-m-01 00:00:00', strtotime('-' . ($months - 1) . ' months'));

        $totals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT YEAR(post_date) AS y, MONTH(post_date) AS m, COUNT(*) AS cnt
                 FROM {$wpdb->posts}
                 WHERE post_type = 'post' AND post_status = 'publish' AND post_date >= %s
                 GROUP BY y, m",
                $startDate
            ),
            ARRAY_A
        );

        $tagged = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT YEAR(p.post_date) AS y, MONTH(p.post_date) AS m, COUNT(DISTINCT p.ID) AS cnt
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'post_tag'
                 WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.post_date >= %s
                 GROUP BY y, m",
                $startDate
            ),
            ARRAY_A
        );

        $totalMap = [];
        foreach ($totals ?? [] as $row) {
            $key = sprintf('%04d-%02d', $row['y'], $row['m']);
            $totalMap[$key] = (int) $row['cnt'];
        }

        $taggedMap = [];
        foreach ($tagged ?? [] as $row) {
            $key = sprintf('%04d-%02d', $row['y'], $row['m']);
            $taggedMap[$key] = (int) $row['cnt'];
        }

        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime("-{$i} months", strtotime(date('Y-m-01')));
            $key = date('Y-m', $ts);
            $total = $totalMap[$key] ?? 0;
            $taggedCount = min($total, $taggedMap[$key] ?? 0);
            $series[] = [
                'key' => $key,
                'label' => date('M', $ts),
                'year' => (int) date('Y', $ts),
                'total' => $total,
                'tagged' => $taggedCount,
                'untagged' => max(0, $total - $taggedCount),
            ];
        }

        return $series;
    }

    private function get_tag_usage_stats(int $limit = 8): array
    {
        global $wpdb;

        $limit = max(1, min(20, $limit));

        $totalAssignments = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             WHERE tt.taxonomy = 'post_tag'"
        );

        $unusedTags = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$wpdb->term_taxonomy}
             WHERE taxonomy = 'post_tag' AND count = 0"
        );

        $topTerms = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.name, tt.count
                 FROM {$wpdb->terms} t
                 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                 WHERE tt.taxonomy = 'post_tag'
                 ORDER BY tt.count DESC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        $topTags = array_map(
            function ($row) {
                return [
                    'name' => $row['name'],
                    'count' => (int) $row['count'],
                ];
            },
            $topTerms ?? []
        );

        $taggedPosts = $this->get_posts_with_tags_count();
        $average = $taggedPosts > 0 && $totalAssignments > 0
            ? round($totalAssignments / $taggedPosts, 1)
            : 0;

        return [
            'top_tags' => $topTags,
            'total_assignments' => $totalAssignments,
            'unused_tags' => $unusedTags,
            'average_tags_per_tagged_post' => $average,
        ];
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
        $license_server_url = apply_filters('gd_autotag_license_server_url', 'https://api.example.com/v1/verify-license');
        
        // Build the request
        $response = wp_remote_post($license_server_url, [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'api_key' => $api_key,
                'site_url' => get_site_url(),
                'plugin_version' => GD_AUTOTAG_VERSION,
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

    public function handle_manual_schedule_run(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to run this task.');
        }

        check_admin_referer('gd_autotag_manual_schedule_run');

        $redirect = wp_get_referer();
        if (empty($redirect) && !empty($_REQUEST['_wp_http_referer'])) {
            $ref = wp_unslash($_REQUEST['_wp_http_referer']);
            $redirect = esc_url_raw(rawurldecode($ref));
        }

        if (empty($redirect) || strpos($redirect, 'admin.php?page=gd-autotag') === false) {
            $redirect = admin_url('admin.php?page=gd-autotag&tab=settings');
        }

        try {
            $scheduler = new \WpPlugin\Scheduler();
            $scheduler->run_scheduled_tasks();
            $redirect = add_query_arg('gd_autotag_schedule_run', 'success', $redirect);
        } catch (\Throwable $e) {
            error_log('GD AutoTag manual schedule run failed: ' . $e->getMessage());
            $redirect = add_query_arg('gd_autotag_schedule_run', 'error', $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }
}
