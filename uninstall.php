<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package gd_autotag
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up options, custom tables, etc. Example:
delete_option('gd_autotag_options');
