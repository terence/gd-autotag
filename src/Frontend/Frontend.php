<?php
namespace WpPlugin\Frontend;

class Frontend
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
    }

    public function enqueue_public_assets(): void
    {
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
        wp_enqueue_style('gd-autotag-public', plugin_dir_url($this->file) . 'assets/css/public' . $suffix . '.css', [], GD_AUTOTAG_VERSION);
        wp_enqueue_script('gd-autotag-public', plugin_dir_url($this->file) . 'assets/js/public.js', [], GD_AUTOTAG_VERSION, true);
    }
}
