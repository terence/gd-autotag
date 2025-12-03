<?php
/**
 * Plugin Name: GD AutoTag
 * Plugin URI:  https://github.com/terence/gd-autotag
 * Description: Plugin for Content automation using AI.
 * Version:     0.1.5
 * Author:      Trevor Rock
 * Author URI:  https://github.com/terence/
 * Text Domain: gd-autotag
 * Domain Path: /languages
 * License:     MIT
 */

declare(strict_types=1);

if (! defined('WPINC')) {
    die;
}

// Plugin constants (guarded to avoid redefinition in MU/loader contexts)
if (! defined('GD_AUTOTAG_VERSION')) {
    define('GD_AUTOTAG_VERSION', '0.1.5');
}

if (! defined('GD_AUTOTAG_DIR')) {
    define('GD_AUTOTAG_DIR', plugin_dir_path(__FILE__));
}

if (! defined('GD_AUTOTAG_FILE')) {
    define('GD_AUTOTAG_FILE', __FILE__);
}

if (! defined('GD_AUTOTAG_ROOT')) {
    define('GD_AUTOTAG_ROOT', __DIR__);
}

// Load composer autoloader when present
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Load the plugin bootstrap
if (! class_exists('WpPlugin\\Plugin')) {
    require_once GD_AUTOTAG_ROOT . '/src/Plugin.php';
}

// Fallback includes when Composer autoloader isn't available (e.g., zipped deployments)
if (! class_exists('WpPlugin\\Admin\\Admin')) {
    require_once GD_AUTOTAG_ROOT . '/src/Admin/Admin.php';
}

if (! class_exists('WpPlugin\\Frontend\\Frontend')) {
    require_once GD_AUTOTAG_ROOT . '/src/Frontend/Frontend.php';
}

if (! class_exists('WpPlugin\\PostTagger')) {
    require_once GD_AUTOTAG_ROOT . '/src/PostTagger.php';
}

if (! class_exists('WpPlugin\\PostCategorizer')) {
    require_once GD_AUTOTAG_ROOT . '/src/PostCategorizer.php';
}

if (! class_exists('WpPlugin\\Scheduler')) {
    require_once GD_AUTOTAG_ROOT . '/src/Scheduler.php';
}

if (! class_exists('WpPlugin\\AITagOptimizer')) {
    require_once GD_AUTOTAG_ROOT . '/src/AITagOptimizer.php';
}

// Load plugin-update-checker via submodule if not already available through Composer
if (! class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    $pucFile = __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
    if (file_exists($pucFile)) {
        require_once $pucFile;
    }
}

// GitHub update checker via plugin-update-checker v5
// Checks GitHub Releases for new versions and provides update information to WordPress.
if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    try {
        $updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            // Replace with your public repo URL, e.g. https://github.com/owner/gd-autotag
            'https://github.com/terence/gd-autotag',
            GD_AUTOTAG_FILE,
            'gd-autotag'
        );
        
        // Enable GitHub Releases - tells the checker to look for release assets (ZIP files)
        if (method_exists($updateChecker, 'getVcsApi')) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
        
        // Optional: Set branch if not using main/master
        // $updateChecker->setBranch('main');
        
        // Optional: For private repos, set authentication token
        // $updateChecker->setAuthentication('your_github_token_here');
    } catch (Exception $e) {
        // Log error but don't break plugin initialization
        error_log('gd-autotag update checker failed: ' . $e->getMessage());
    }
}

// Activation and deactivation hooks
register_activation_hook(__FILE__, ['WpPlugin\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['WpPlugin\\Plugin', 'deactivate']);

/**
 * Start plugin
 */
function gd_autotag_run(): void
{
    $plugin = new WpPlugin\Plugin(GD_AUTOTAG_FILE);
    $plugin->run();
}

gd_autotag_run();
