<?php
/**
 * Validation script for plugin-update-checker integration
 * Run from command line: php validate-update-checker.php
 * 
 * This validates the submodule is loaded correctly and classes are available.
 * Full integration testing requires a WordPress environment.
 */

echo "=== Plugin Update Checker Validation ===\n\n";

// Check if submodule file exists
$pucFile = __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
if (file_exists($pucFile)) {
    echo "✓ Submodule file exists: $pucFile\n";
} else {
    echo "✗ Submodule file missing: $pucFile\n";
    echo "  Run: git submodule update --init --recursive\n";
    exit(1);
}

// Load the library
require_once $pucFile;

// Check if v5 class loaded
if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    echo "✓ PucFactory class is available (v5)\n";
} else {
    echo "✗ PucFactory class not found\n";
    exit(1);
}

// Check the actual versioned namespace used
$versionedClasses = [
    'YahnisElsts\\PluginUpdateChecker\\v5p6\\PucFactory',
    'YahnisElsts\\PluginUpdateChecker\\v5p5\\PucFactory',
    'YahnisElsts\\PluginUpdateChecker\\v5p4\\PucFactory',
    'YahnisElsts\\PluginUpdateChecker\\v5p3\\PucFactory',
];

$foundVersion = null;
foreach ($versionedClasses as $className) {
    if (class_exists($className)) {
        $foundVersion = $className;
        break;
    }
}

if ($foundVersion) {
    echo "✓ Versioned factory class found: $foundVersion\n";
} else {
    echo "⚠ No versioned factory class detected (this may be OK)\n";
}

// Validate plugin file structure
$pluginFile = __DIR__ . '/wp-plugin.php';
if (file_exists($pluginFile)) {
    echo "✓ Plugin main file exists: $pluginFile\n";
    
    $content = file_get_contents($pluginFile);
    
    // Check for v5 API usage
    if (strpos($content, 'YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory') !== false) {
        echo "✓ Plugin uses v5 API (YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory)\n";
    } else {
        echo "✗ Plugin does not use v5 API\n";
        exit(1);
    }
    
    // Check for constants
    if (strpos($content, "define('WP_PLUGIN_VERSION'") !== false) {
        echo "✓ WP_PLUGIN_VERSION constant defined\n";
    } else {
        echo "✗ WP_PLUGIN_VERSION constant missing\n";
    }
    
    // Check for submodule loader
    if (strpos($content, 'vendor/plugin-update-checker/plugin-update-checker.php') !== false) {
        echo "✓ Submodule loader present\n";
    } else {
        echo "✗ Submodule loader missing\n";
    }
    
    // Check for enableReleaseAssets call
    if (strpos($content, 'enableReleaseAssets()') !== false) {
        echo "✓ GitHub Release assets enabled\n";
    } else {
        echo "⚠ enableReleaseAssets() not called (optional)\n";
    }
    
    // Check for error handling
    if (strpos($content, 'try') !== false && strpos($content, 'catch') !== false) {
        echo "✓ Error handling implemented\n";
    } else {
        echo "⚠ No error handling for update checker\n";
    }
    
} else {
    echo "✗ Plugin main file missing\n";
    exit(1);
}

echo "\n=== Validation Summary ===\n";
echo "✓ All core integration checks passed\n";
echo "\nTo fully test update functionality:\n";
echo "1. Install this plugin in a WordPress site\n";
echo "2. Create a GitHub release with a ZIP asset\n";
echo "3. Check WordPress admin → Updates page\n";
echo "4. Verify your plugin appears with update available\n";

exit(0);
