<?php
namespace WpPlugin;

class PostTagger
{
    public function register(): void
    {
        // Add bulk action to posts list
        add_filter('bulk_actions-edit-post', [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_action'], 10, 3);
        
        // Add admin notice
        add_action('admin_notices', [$this, 'bulk_action_admin_notice']);
        
        // Add meta box to individual posts
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        
        // Add AJAX handler for generating tags
        add_action('wp_ajax_wp_plugin_generate_tags', [$this, 'ajax_generate_tags']);
    }

    public function add_bulk_action($bulk_actions): array
    {
        $bulk_actions['wp_plugin_generate_tags'] = 'Generate Tags';
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $action, $post_ids)
    {
        if ($action !== 'wp_plugin_generate_tags') {
            return $redirect_to;
        }

        $processed = 0;
        foreach ($post_ids as $post_id) {
            if ($this->generate_tags_for_post($post_id)) {
                $processed++;
            }
        }

        $redirect_to = add_query_arg('wp_plugin_tags_generated', $processed, $redirect_to);
        return $redirect_to;
    }

    public function bulk_action_admin_notice(): void
    {
        if (!empty($_REQUEST['wp_plugin_tags_generated'])) {
            $count = intval($_REQUEST['wp_plugin_tags_generated']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>Generated tags for %d post(s).</p></div>',
                $count
            );
        }
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'wp_plugin_tag_generator',
            'Auto Tag Generator',
            [$this, 'render_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    public function render_meta_box($post): void
    {
        ?>
        <div class="wp-plugin-tag-generator">
            <p>Automatically generate tags based on post content.</p>
            <button type="button" class="button button-secondary wp-plugin-generate-tags-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                Generate Tags
            </button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <div class="wp-plugin-tags-result" style="margin-top: 10px;"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.wp-plugin-generate-tags-btn').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post-id');
                var spinner = btn.siblings('.spinner');
                var result = $('.wp-plugin-tags-result');
                
                btn.prop('disabled', true);
                spinner.addClass('is-active');
                result.html('');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wp_plugin_generate_tags',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('wp_plugin_generate_tags'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                            // Reload tags in the post editor
                            if (typeof wp !== 'undefined' && wp.data) {
                                location.reload();
                            }
                        } else {
                            result.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;">✗ Error generating tags</span>');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_generate_tags(): void
    {
        check_ajax_referer('wp_plugin_generate_tags', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
        }

        $success = $this->generate_tags_for_post($post_id);
        
        if ($success) {
            wp_send_json_success(['message' => 'Tags generated successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to generate tags']);
        }
    }

    private function generate_tags_for_post(int $post_id): bool
    {
        $post = get_post($post_id);
        
        if (!$post || $post->post_type !== 'post') {
            return false;
        }

        // Get plugin options
        $options = get_option('wp_plugin_options', []);
        
        // Extract potential tags from title and content
        $tags = $this->extract_tags($post);
        
        if (empty($tags)) {
            return false;
        }

        // Set tags for the post
        wp_set_post_tags($post_id, $tags, false);
        
        return true;
    }

    private function extract_tags($post): array
    {
        $text = $post->post_title . ' ' . strip_tags($post->post_content);
        
        // Remove common words
        $common_words = [
            'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
            'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
            'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
            'would', 'could', 'should', 'may', 'might', 'must', 'can', 'this',
            'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they'
        ];
        
        // Extract words (2+ characters)
        preg_match_all('/\b[a-z]{2,}\b/i', $text, $matches);
        $words = $matches[0];
        
        // Count word frequency
        $word_freq = array_count_values(array_map('strtolower', $words));
        
        // Remove common words
        foreach ($common_words as $common) {
            unset($word_freq[$common]);
        }
        
        // Sort by frequency
        arsort($word_freq);
        
        // Get top 10 words
        $tags = array_slice(array_keys($word_freq), 0, 10);
        
        // Capitalize first letter
        $tags = array_map('ucfirst', $tags);
        
        return $tags;
    }
}
