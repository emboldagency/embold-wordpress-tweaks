<?php

/**
 * @wordpress-plugin
 * Plugin Name:        emBold Wordpress Tweaks
 * Plugin URI:         https://embold.com
 * Description:        A collection of our common tweaks and upgrades to WordPress.
 * Version:            1.6.0
 * Author:             emBold
 * Author URI:         https://embold.com/
 * Primary Branch:     master
 */

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Include plugin classes
require_once plugin_dir_path(__FILE__) . 'includes/EmboldWordpressTweaks.php';
require_once plugin_dir_path(__FILE__) . 'includes/Utilities/Environment.php';
require_once plugin_dir_path(__FILE__) . 'includes/Services/DisableMailService.php';
require_once plugin_dir_path(__FILE__) . 'includes/Admin/SettingsPage.php';

require 'plugin-update-checker/plugin-update-checker.php';

use App\Admin\SettingsPage;
use App\Services\DisableMailService;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$embold_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/emboldagency/embold-wordpress-tweaks/',
    __FILE__,
    'embold-wordpress-tweaks'
);

// Set authentication and enable release assets
$embold_update_checker->getVcsApi()->enableReleaseAssets();

// Register deactivation hook
register_deactivation_hook(__FILE__, '\App\EmboldWordpressTweaks::onDeactivation');

// Plugin initialization
function embold_wordpress_tweaks_init()
{
    // Create an instance of the plugin class
    $plugin = new \App\EmboldWordpressTweaks();

    // Apply user restrictions unless explicitly disabled
    if (is_admin() && !embold_tweaks_should_disable_restrictions()) {
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

    // Notice Suppression
    $plugin->enableNoticeSuppression();

    // Register mail control service
    $mailService = new DisableMailService();
    $mailService->register();

    // Settings page (admin only)
    if (is_admin()) {
        $settings = new SettingsPage();
        $settings->register();
    }
}

/**
 * Determine if user restrictions should be disabled
 * Checks constant first, then option, defaults to false (restrictions enabled)
 *
 * @return bool True if restrictions should be disabled
 */
function embold_tweaks_should_disable_restrictions()
{
    // Constant takes priority
    if (defined('LOOSE_USER_RESTRICTIONS')) {
        return (bool) constant('LOOSE_USER_RESTRICTIONS');
    }

    // Plugin option
    $opts = get_option('embold_tweaks_options', []);
    if (isset($opts['loose_user_restrictions'])) {
        return (bool) $opts['loose_user_restrictions'];
    }

    // Default: restrictions are enabled (return false)
    return false;
}

add_action('plugins_loaded', 'embold_wordpress_tweaks_init', 0);
