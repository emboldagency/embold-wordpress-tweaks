<?php

/**
 * @wordpress-plugin
 * Plugin Name:        emBold Wordpress Tweaks
 * Plugin URI:         https://embold.com
 * Description:        A collection of our common tweaks and upgrades to WordPress.
 * Version:            1.1.4
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

    if (is_admin() && ! defined('LOOSE_USER_RESTRICTIONS') || (defined('LOOSE_USER_RESTRICTIONS') && LOOSE_USER_RESTRICTIONS == false)) {
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

    $environmentsToDisableMail = ['development', 'staging', 'local', 'maintenance'];

    if (in_array(wp_get_environment_type(), $environmentsToDisableMail)) {
        // Disable an array of mail plugins
        $plugin->disableAllKnownMailPlugins();
    }
}

add_action('plugins_loaded', 'embold_wordpress_tweaks_init', 0);

$environmentsToDisableMail = ['development', 'staging', 'local', 'maintenance'];

// This function must be global, if we put it in our class it won't override the core function
if (in_array(wp_get_environment_type(), $environmentsToDisableMail)) {
    if (!function_exists('wp_mail')) {
        function wp_mail()
        {
            return false;
        }
    }
}
