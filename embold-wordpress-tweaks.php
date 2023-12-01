<?php
/**
 * @wordpress-plugin
 * Plugin Name:        emBold Wordpress Tweaks
 * Plugin URI:         https://embold.com
 * Description:        A collection of our common tweaks and upgrades to WordPress.
 * Version:            0.4.0
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

$update_key_url = 'https://embold.net/api/wp-plugin-key';
$update_key = @trim(file_get_contents($update_key_url));

if ($update_key) {
    $embold_update_checker->setAuthentication($update_key);
    $embold_update_checker->getVcsApi()->enableReleaseAssets();
}

// Plugin initialization
function embold_wordpress_tweaks_init() {
    // Create an instance of your plugin class
    $plugin = new \App\EmboldWordpressTweaks();

    // Allow SVG uploads
    $plugin->addSvgSupport();

    // Disable XML-RPC
    $plugin->disableXmlRpc();

    // Remove line breaks from img tags if litespeed is enabled
    $plugin->removeLineBreaksFromImgTags();

    // Show post/page slugs in the admin panel and enable slug search
    $plugin->addSlugSearchAndColumns();

    if (wp_get_environment_type() == 'development') {
        // Defer scripts to try to avoid Coders 502 errors
        $plugin->deferScripts();

        // Async scripts to try to avoid Coders 502 errors
        $plugin->asyncScripts();

        // Disable an array of mail plugins
        $plugin->disableAllKnownMailPlugins();
    }
}

add_action('plugins_loaded', 'embold_wordpress_tweaks_init');

// This function must be global, if we put it in our class it won't override the core function
if (wp_get_environment_type() == 'development') {
    if (!function_exists('wp_mail')) {
        function wp_mail() {
            return false;
        }
    }
}