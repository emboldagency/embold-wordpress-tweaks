<?php

/**
 * @wordpress-plugin
 * Plugin Name:        emBold Wordpress Tweaks
 * Plugin URI:         https://embold.com
 * Description:        A collection of our common tweaks and upgrades to WordPress.
 * Version:            1.5.0
 * Author:             emBold
 * Author URI:         https://embold.com/
 * Primary Branch:     master
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Include the main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/EmboldWordpressTweaks.php';

require 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$embold_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/emboldagency/embold-wordpress-tweaks/',
    __FILE__,
    'embold-wordpress-tweaks'
);

// Set authentication and enable release assets
$embold_update_checker->getVcsApi()->enableReleaseAssets();

// Plugin initialization
function embold_wordpress_tweaks_init()
{
    // Create an instance of your plugin class
    $plugin = new \App\EmboldWordpressTweaks();

    if (is_admin() && !defined('LOOSE_USER_RESTRICTIONS') || (defined('LOOSE_USER_RESTRICTIONS') && LOOSE_USER_RESTRICTIONS == false)) {
        // Allow specific users to edit files
        $plugin->allowSpecificUsersToEditFiles();
    }

    // Allow SVG uploads
    $plugin->addSvgSupport();

    // Disable XML-RPC
    $plugin->disableXmlRpc();

    // Remove line breaks from img tags if litespeed is enabled
    $plugin->removeLineBreaksFromImgTags();

    // Show post/page slugs in the admin panel and enable slug search
    $plugin->addSlugSearchAndColumns();

    $plugin->disableEscapingAcfShortcodes();

    $plugin->removeHowdy();

    $environmentsToDisableMail = ['development', 'staging', 'local', 'maintenance'];

    if (!defined('DISABLE_MAIL') || DISABLE_MAIL !== false) {
        if (in_array(wp_get_environment_type(), $environmentsToDisableMail)) {
            // Disable an array of mail plugins
            $plugin->disableAllKnownMailPlugins();
        }
    }
}

add_action('plugins_loaded', 'embold_wordpress_tweaks_init', 0);

/**
 * Determine if mail blocking should be handled by this plugin.
 * Skip if wphaven-connect is active (it handles mail management).
 */
function embold_should_handle_mail(): bool
{
    // If wphaven-connect is active, let it handle mail management
    if (class_exists('WPHavenConnect\Providers\DisableMailServiceProvider')) {
        return false;
    }

    return true;
}

// This function must be global, if we put it in our class it won't override the core function
// Only disable mail if wphaven-connect isn't handling it
if (embold_should_handle_mail()) {
    $environmentsToDisableMail = ['development', 'staging', 'local', 'maintenance'];

    // Block mail in non-production environments unless DISABLE_MAIL is explicitly set to false
    if (!defined('DISABLE_MAIL') || DISABLE_MAIL !== false) {
        if (in_array(wp_get_environment_type(), $environmentsToDisableMail)) {
            if (!function_exists('wp_mail')) {
                function wp_mail()
                {
                    return false;
                }
            }
        }
    }
}
