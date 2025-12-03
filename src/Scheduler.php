<?php
namespace WpPlugin;

class Scheduler
{
    private const CRON_HOOK = 'gd_autotag_run_scheduled_tasks';

    public function register(): void
    {
        add_action('init', [$this, 'maybe_schedule_event']);
        add_action(self::CRON_HOOK, [$this, 'run_scheduled_tasks']);
        add_action('update_option_gd_autotag_options', [$this, 'handle_option_update'], 10, 3);
    }

    public function maybe_schedule_event(): void
    {
        if (! $this->is_enabled()) {
            $this->clear_scheduled_event();
            return;
        }

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event($this->resolve_start_timestamp(), $this->get_frequency_slug(), self::CRON_HOOK);
        }
    }

    public function handle_option_update($old_value, $new_value, $option_name): void
    {
        if ($option_name !== 'gd_autotag_options') {
            return;
        }

        $this->clear_scheduled_event();

        if ($this->is_enabled($new_value)) {
            wp_schedule_event(
                $this->resolve_start_timestamp($new_value),
                $this->get_frequency_slug($new_value),
                self::CRON_HOOK
            );
        }
    }

    public function run_scheduled_tasks(): void
    {
        $options = get_option('gd_autotag_options', []);

        $batch_size = isset($options['schedule_batch_size']) ? max(1, min(50, (int) $options['schedule_batch_size'])) : 5;
        $auto_tag_enabled = !empty($options['auto_tag_enabled']);
        $auto_category_enabled = !empty($options['auto_category_enabled']);

        if (! $auto_tag_enabled && ! $auto_category_enabled) {
            return;
        }

        $post_ids = get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $batch_size * 3,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ]);

        if (empty($post_ids)) {
            update_option('gd_autotag_schedule_last_run', time());
            return;
        }

        $tagger = new PostTagger();
        $categorizer = new PostCategorizer();

        $processed = 0;
        foreach ($post_ids as $post_id) {
            $did_work = false;

            $processedPostId = (int) $post_id;

            if ($auto_tag_enabled && ! $this->post_has_tags($processedPostId)) {
                if ($tagger->generate_tags_for_post($processedPostId)) {
                    $did_work = true;
                }
            }

            if ($auto_category_enabled && ! $this->post_has_meaningful_category($processedPostId)) {
                if ($categorizer->generate_categories_for_post($processedPostId)) {
                    $did_work = true;
                }
            }

            if ($did_work) {
                $processed++;
            }

            if ($processed >= $batch_size) {
                break;
            }
        }

        update_option('gd_autotag_schedule_last_run', time());

        /**
         * Fires after GD AutoTag completes its scheduled cron run.
         *
         * @param int $processed Number of posts updated during this run.
         */
        do_action('gd_autotag_after_scheduled_run', $processed);
    }

    private function is_enabled(?array $options = null): bool
    {
        $options = $options ?? get_option('gd_autotag_options', []);
        return !empty($options['schedule_enabled']);
    }

    private function clear_scheduled_event(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    private function get_frequency_slug(?array $options = null): string
    {
        $options = $options ?? get_option('gd_autotag_options', []);
        $frequency = $options['schedule_frequency'] ?? 'daily';
        $allowed = ['hourly', 'twicedaily', 'daily'];
        if (! in_array($frequency, $allowed, true)) {
            $frequency = 'daily';
        }
        return $frequency;
    }

    private function resolve_start_timestamp(?array $options = null): int
    {
        $options = $options ?? get_option('gd_autotag_options', []);
        $frequency = $this->get_frequency_slug($options);

        if ($frequency !== 'daily') {
            return time() + MINUTE_IN_SECONDS;
        }

        $time_string = $options['schedule_time'] ?? '02:00';
        if (! preg_match('/^(\d{2}):(\d{2})$/', $time_string, $matches)) {
            $matches = [0, '02', '00'];
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        $now = current_time('timestamp');
        $target = mktime($hour, $minute, 0, (int) date('n', $now), (int) date('j', $now), (int) date('Y', $now));

        if ($target <= $now) {
            $target = strtotime('+1 day', $target);
        }

        return $target;
    }

    private function post_has_tags(int $post_id): bool
    {
        return has_term('', 'post_tag', $post_id);
    }

    private function post_has_meaningful_category(int $post_id): bool
    {
        $categories = wp_get_post_categories($post_id, ['fields' => 'ids']);
        if (empty($categories)) {
            return false;
        }

        if (count($categories) === 1) {
            $term = get_term($categories[0]);
            if ($term instanceof \WP_Term && $term->slug === 'uncategorized') {
                return false;
            }
        }

        return true;
    }
}
