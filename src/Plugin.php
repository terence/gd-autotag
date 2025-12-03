<?php
namespace WpPlugin;

class Plugin
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public static function activate(): void
    {
        if (! function_exists('flush_rewrite_rules')) {
            return;
        }
        // Example: add_option('gd_autotag_options', []);
    }

    public static function deactivate(): void
    {
        // Example: flush_rewrite_rules();
    }

    public function run(): void
    {
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        add_action('init', [$this, 'init_scheduler']);

        if (is_admin()) {
            add_action('init', [$this, 'init_admin']);
        } else {
            add_action('wp_enqueue_scripts', [$this, 'init_public']);
        }
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain('gd-autotag', false, dirname(plugin_basename($this->file)) . '/languages');
    }

    public function init_admin(): void
    {
        $admin = new Admin\Admin($this->file);
        $admin->register();
        
        $postTagger = new PostTagger();
        $postTagger->register();

        $postCategorizer = new PostCategorizer();
        $postCategorizer->register();
    }

    public function init_scheduler(): void
    {
        $scheduler = new Scheduler();
        $scheduler->register();
    }

    public function init_public(): void
    {
        $frontend = new Frontend\Frontend($this->file);
        $frontend->register();
    }
}
