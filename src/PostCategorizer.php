<?php
namespace WpPlugin;

class PostCategorizer
{
    private ?array $categoryTerms = null;
    private ?array $categoryLookup = null;

    public function register(): void
    {
        add_filter('bulk_actions-edit-post', [$this, 'add_bulk_action']);
        add_filter('handle_bulk_actions-edit-post', [$this, 'handle_bulk_action'], 10, 3);
        add_filter('post_row_actions', [$this, 'add_row_action'], 10, 2);
        add_action('admin_action_gd_autotag_auto_categorize_post', [$this, 'handle_single_auto_categorization']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('wp_ajax_gd_autotag_generate_categories', [$this, 'ajax_generate_categories']);
        add_action('save_post_post', [$this, 'maybe_sync_on_save'], 20, 3);
    }

    public function add_bulk_action($bulk_actions): array
    {
        if ($this->is_enabled()) {
            $bulk_actions['gd_autotag_auto_categorize'] = __('Auto Categorize', 'gd-autotag');
        }

        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $action, $post_ids)
    {
        if ($action !== 'gd_autotag_auto_categorize') {
            return $redirect_to;
        }

        $processed = 0;
        foreach ($post_ids as $post_id) {
            if ($this->generate_categories_for_post((int) $post_id)) {
                $processed++;
            }
        }

        return add_query_arg('gd_autotag_auto_categorized', $processed, $redirect_to);
    }

    public function add_row_action($actions, $post)
    {
        if (!$this->is_enabled() || $post->post_type !== 'post') {
            return $actions;
        }

        $url = wp_nonce_url(
            admin_url('admin.php?action=gd_autotag_auto_categorize_post&post=' . $post->ID),
            'gd_autotag_auto_categorize_post_' . $post->ID
        );

        $actions['gd_autotag_auto_categorize'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url($url),
            esc_attr__('Automatically assign categories based on tags/content', 'gd-autotag'),
            esc_html__('Auto Categorize', 'gd-autotag')
        );

        return $actions;
    }

    public function handle_single_auto_categorization(): void
    {
        if (!$this->is_enabled() || !isset($_GET['post'])) {
            wp_die(__('Auto categorization is disabled or post missing.', 'gd-autotag'));
        }

        $post_id = intval($_GET['post']);

        if (!wp_verify_nonce($_GET['_wpnonce'], 'gd_autotag_auto_categorize_post_' . $post_id)) {
            wp_die(__('Security check failed.', 'gd-autotag'));
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_die(__('You do not have permission to edit this post.', 'gd-autotag'));
        }

        $success = $this->generate_categories_for_post($post_id);
        $redirect = admin_url('edit.php?post_type=post');

        if ($success) {
            $redirect = add_query_arg('gd_autotag_auto_categorized_single', '1', $redirect);
        } else {
            $redirect = add_query_arg('gd_autotag_auto_categorized_failed', '1', $redirect);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function admin_notices(): void
    {
        if (!empty($_REQUEST['gd_autotag_auto_categorized'])) {
            $count = intval($_REQUEST['gd_autotag_auto_categorized']);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html(sprintf(__('Auto-categorized %d post(s).', 'gd-autotag'), $count))
            );
        }

        if (!empty($_REQUEST['gd_autotag_auto_categorized_single'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Categories updated for the post.', 'gd-autotag') . '</p></div>';
        }

        if (!empty($_REQUEST['gd_autotag_auto_categorized_failed'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('No categories could be assigned.', 'gd-autotag') . '</p></div>';
        }
    }

    public function add_meta_box(): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        add_meta_box(
            'gd_autotag_auto_categorizer',
            __('Auto Categories', 'gd-autotag'),
            [$this, 'render_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    public function render_meta_box($post): void
    {
        ?>
        <div class="gd-autotag-auto-categorizer">
            <p><?php esc_html_e('Suggest categories based on existing tags and content signals.', 'gd-autotag'); ?></p>
            <button type="button" class="button button-secondary gd-autotag-generate-categories-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                <?php esc_html_e('Suggest Categories', 'gd-autotag'); ?>
            </button>
            <span class="spinner" style="float: none; margin: 0 10px;"></span>
            <div class="gd-autotag-categories-result" style="margin-top: 10px;"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.gd-autotag-generate-categories-btn').on('click', function() {
                var btn = $(this);
                var postId = btn.data('post-id');
                var spinner = btn.siblings('.spinner');
                var result = btn.closest('.gd-autotag-auto-categorizer').find('.gd-autotag-categories-result');

                btn.prop('disabled', true);
                spinner.addClass('is-active');
                result.html('');

                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'gd_autotag_generate_categories',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('gd_autotag_generate_categories'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.html('<span style="color: green;">' + response.data.message + '</span>');
                        } else {
                            result.html('<span style="color: red;">' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        result.html('<span style="color: red;">' + '<?php echo esc_js(__('Error assigning categories.', 'gd-autotag')); ?>' + '</span>');
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

    public function ajax_generate_categories(): void
    {
        check_ajax_referer('gd_autotag_generate_categories', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'gd-autotag')]);
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID.', 'gd-autotag')]);
        }

        if ($this->generate_categories_for_post($post_id)) {
            wp_send_json_success(['message' => __('Categories updated.', 'gd-autotag')]);
        }

        wp_send_json_error(['message' => __('No categories detected for this post.', 'gd-autotag')]);
    }

    public function maybe_sync_on_save($post_id, $post, $update): void
    {
        if (!$this->is_enabled()) {
            return;
        }

        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        $options = get_option('gd_autotag_options', []);
        if (empty($options['auto_category_sync_on_save'])) {
            return;
        }

        $this->generate_categories_for_post($post_id);
    }

    private function is_enabled(): bool
    {
        $options = get_option('gd_autotag_options', []);
        return !empty($options['auto_category_enabled']);
    }

    public function generate_categories_for_post(int $post_id): bool
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'post') {
            return false;
        }

        $options = get_option('gd_autotag_options', []);
        $limit = isset($options['auto_category_max_categories']) ? max(1, (int) $options['auto_category_max_categories']) : 3;
        $strategy = $options['auto_category_strategy'] ?? 'tag-match';

        $categories = [];
        if ($strategy === 'tag-match') {
            $categories = $this->match_categories_by_tags($post_id, $limit);
            if (empty($categories)) {
                $categories = $this->match_categories_by_content($post, $limit);
            }
        } else {
            $categories = $this->match_categories_by_content($post, $limit);
            if (empty($categories)) {
                $categories = $this->match_categories_by_tags($post_id, $limit);
            }
        }

        if (empty($categories) && !empty($options['auto_category_fallback'])) {
            $categories[] = (int) $options['auto_category_fallback'];
        }

        $categories = array_values(array_unique(array_map('intval', $categories)));
        if (empty($categories)) {
            return false;
        }

        if ($limit > 0) {
            $categories = array_slice($categories, 0, $limit);
        }

        wp_set_post_categories($post_id, $categories, true);
        return true;
    }

    private function match_categories_by_tags(int $post_id, int $limit): array
    {
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
        if (empty($tags)) {
            return [];
        }

        $lookup = $this->get_category_lookup();
        $matches = [];
        foreach ($tags as $tag_name) {
            $key = strtolower($tag_name);
            if (isset($lookup[$key])) {
                $matches[] = $lookup[$key];
            }
            if (count($matches) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($matches));
    }

    private function match_categories_by_content($post, int $limit): array
    {
        $content = strtolower($post->post_title . ' ' . strip_tags($post->post_content));
        $matches = [];

        foreach ($this->get_category_terms() as $term) {
            $needleName = strtolower($term->name);
            $needleSlug = strtolower(str_replace('-', ' ', $term->slug));

            if ($needleName && str_contains($content, $needleName)) {
                $matches[] = (int) $term->term_id;
            } elseif ($needleSlug && str_contains($content, $needleSlug)) {
                $matches[] = (int) $term->term_id;
            }

            if (count($matches) >= $limit) {
                break;
            }
        }

        return array_values(array_unique($matches));
    }

    private function get_category_terms(): array
    {
        if ($this->categoryTerms !== null) {
            return $this->categoryTerms;
        }

        $terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            $terms = [];
        }

        $this->categoryTerms = $terms;
        return $this->categoryTerms;
    }

    private function get_category_lookup(): array
    {
        if ($this->categoryLookup !== null) {
            return $this->categoryLookup;
        }

        $lookup = [];
        foreach ($this->get_category_terms() as $term) {
            $lookup[strtolower($term->name)] = (int) $term->term_id;
            $lookup[strtolower($term->slug)] = (int) $term->term_id;
        }

        $this->categoryLookup = $lookup;
        return $this->categoryLookup;
    }
}
