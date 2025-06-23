<?php

namespace App;

class EmboldWordpressTweaks
{
    public function allowSpecificUsersToEditFiles()
    {
        $default_emails = [
            'info@embold.com',
            'info@wphaven.app',
        ];

        $allowed_emails = $default_emails;

        if (defined('ELEVATED_EMAILS')) {
            $allowed_emails = array_merge($allowed_emails, ELEVATED_EMAILS);
        }

        $current_user = wp_get_current_user();

        if (!in_array($current_user->user_email, $allowed_emails)) {
            add_filter('all_plugins', function ($plugins) {
                if (isset($plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php'])) {
                    unset($plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php']);
                }
                return $plugins;
            });
        }

        // Filter to disallow file edits
        add_filter('user_has_cap', function ($all_capabilities, $caps, $args) use ($allowed_emails, $current_user) {
            if (!in_array($current_user->user_email, $allowed_emails)) {
                $all_capabilities['update_plugins'] = false;
                $all_capabilities['install_plugins'] = false;
                $all_capabilities['delete_plugins'] = false;
                $all_capabilities['switch_themes'] = false;
                $all_capabilities['install_themes'] = false;
                $all_capabilities['edit_themes'] = false;
                $all_capabilities['delete_themes'] = false;
                $all_capabilities['manage_options'] = true;
                $all_capabilities['import'] = false;
            }

            $all_capabilities['edit_themes'] = false;
            $all_capabilities['edit_files'] = false;
            $all_capabilities['edit_plugins'] = false;

            return $all_capabilities;
        }, 10, 3);
    }

    /**
     * Add SVG support.
     *
     * @return void
     */
    public function addSvgSupport()
    {
        add_filter('upload_mimes', function ($mimes) {
            $mimes['svg'] = 'image/svg+xml';

            return $mimes;
        });
    }

    /**
     * Disable XML-RPC.
     */
    public function disableXmlRpc()
    {
        add_filter('xmlrpc_enabled', '__return_false');
    }

    /**
     * Defer scripts to try to avoid Coders 502 errors.
     *
     * @return void
     */
    public function deferScripts()
    {
        add_filter('script_loader_tag', function ($tag, $handle) {
            $scripts_to_defer = [
                'common',
                'wp-menu',
                'post-edit',
            ];

            foreach ($scripts_to_defer as $defer_script) {
                if ($defer_script === $handle) {
                    return str_replace(' src', " defer='defer' src", $tag);
                }
            }

            return $tag;
        }, 10, 2);
    }

    /**
     * Async scripts to try to avoid Coders 502 errors.
     *
     * @return void
     */
    public function asyncScripts()
    {
        add_filter('script_loader_tag', function ($tag, $handle) {
            $scripts_to_async = [
                'admin-bar',
                'heartbeat',
                'mce-view',
                'image-edit',
                'quicktags',
                'wplink',
                'jquery-ui-autocomplete',
                'media-upload',
                // 'wp-block-styles',
                // 'wp-block-directory',
                // 'wp-format-library',
                'editor/0',
                'editor/1',
                // 'utils',
                'svg-painter',
                'wp-auth-check',
                'wordcount',
                'block-editor',
                'references',
                'style-engine',
            ];

            foreach ($scripts_to_async as $async_script) {
                if ($async_script === $handle) {
                    return str_replace(' src', ' async src', $tag);
                }
            }

            return $tag;
        }, 10, 2);
    }

    /**
     * Disable all known mail plugins.
     */
    public function disableAllKnownMailPlugins()
    {
        $plugins_to_disable = [
            'mailgun/mailgun.php',
            'sparkpost/sparkpost.php',
        ];

        foreach ($plugins_to_disable as $plugin_to_disable) {
            add_action('admin_init', function () use ($plugin_to_disable) {
                deactivate_plugins($plugin_to_disable);
            });
        }
    }

    /**
     * Remove line breaks from img tags if litespeed-cache is enabled
     */
    public function removeLineBreaksFromImgTags()
    {
        // Check if the is_plugin_active function exists and if the Litespeed Cache plugin is active
        if (function_exists('is_plugin_active') && is_plugin_active('litespeed-cache/litespeed-cache.php')) {
            // Define the content filter function inline
            add_filter('litespeed_buffer_before', function ($content) {
                // Remove extra spaces and newlines from img tags in the content
                preg_match_all('/<img[^>]*>/i', $content, $matches);
                foreach ($matches[0] as $match) {
                    $cleaned_tag = preg_replace("/\s+/", " ", $match);
                    $cleaned_tag = str_replace(array("\r", "\n"), '', $cleaned_tag);
                    $content = str_replace($match, $cleaned_tag, $content);
                }
                return $content;
            }, 0);
        }
    }

    /**
     * Show post/page slugs in the admin panel and enable slug search
     */
    public function addSlugSearchAndColumns()
    {
        // search by slug
        add_filter('posts_search', function ($search, \WP_Query $q) use (&$wpdb) {
            global $wpdb;
            // Nothing to do
            if (
                !did_action('load-edit.php')
                || !is_admin()
                || !$q->is_search()
                || !$q->is_main_query()
            )
                return $search;

            // Get the search input
            $s = $q->get('s');

            // Check for "slug:" part in the search input
            if ('slug:' === mb_substr(trim($s), 0, 5)) {
                // Override the search query
                $search = $wpdb->prepare(
                    " AND {$wpdb->posts}.post_name LIKE %s ",
                    str_replace(
                        ['**', '*'],
                        ['*',  '%'],
                        mb_strtolower(
                            $wpdb->esc_like(
                                trim(mb_substr($s, 5))
                            )
                        )
                    )
                );

                // Adjust the ordering
                $q->set('orderby', 'post_name');
                $q->set('order', 'ASC');
            }
            return $search;
        }, PHP_INT_MAX, 2);

        // Add the custom column to the given post type
        function addSlugColumn($post_type)
        {
            add_filter("manage_{$post_type}_posts_columns", function ($columns) {
                global $post_type; // declare the global variable
                $new = array();
                $slug = $columns["{$post_type}_slug"] = __('Slug', 'embold-wordpress-tweaks');
                // save the slug column
                unset($columns["{$post_type}_slug"]);
                // remove it from the columns list
                foreach ($columns as $key => $value) {
                    if ($key == 'title') {
                        // when we find the title column
                        $new['title'] = $value;
                        // put the title column first
                        $new["{$post_type}_slug"] = $slug;
                        // put the slug column after it
                    } else {
                        $new[$key] = $value;
                        // put the rest of the columns
                    }
                }
                return $new;
            });
        }

        // Display the slug in the custom column for the given post type
        function showSlugColumn($post_type)
        {
            add_action("manage_{$post_type}_posts_custom_column", function ($column, $post_id) use ($post_type) {
                if (
                    $column == "{$post_type}_slug"
                ) {
                    echo get_post_field('post_name', $post_id, 'raw');
                }
            }, 10, 2);
        }

        // Apply the functions for page and post post types
        $post_types = array('page', 'post');
        foreach ($post_types as $post_type) {
            addSlugColumn($post_type);
            showSlugColumn($post_type);
        }
    }

    /**
     * Disable escaping ACF shortcode content introduced in ACF 6.2.5
     */
    public function disableEscapingAcfShortcodes()
    {
        // Check if the is_plugin_active function exists and if the ACF plugin is active
        if (function_exists('is_plugin_active') && (is_plugin_active('advanced-custom-fields/acf.php') || is_plugin_active('advanced-custom-fields-pro/acf.php'))) {
            add_filter('acf/shortcode/allow_unsafe_html', function ($allowed, $atts) {
                // always return true, no matter which ACF shortcode is being used
                return true;
            }, 10, 2);

            // Disable the notice about this in the admin
            add_filter('acf/admin/prevent_escaped_html_notice', '__return_true');
        }
    }


    /**
     * Hide login URL if EMBOLD_ADMIN_URL is defined in wp-config.php
     */
    public function hideLoginUrl()
    {
        if (!defined('EMBOLD_ADMIN_URL') || empty(EMBOLD_ADMIN_URL)) {
            return;
        }
        $custom_login = trim(EMBOLD_ADMIN_URL, '/');

        // Redirect requests to the custom login URL to wp-login.php
        add_action('init', function () use ($custom_login) {
            global $pagenow;
            if (
                $pagenow !== 'wp-login.php' &&
                !is_admin() &&
                preg_match('#^/' . preg_quote($custom_login, '#') . '(/|$)#', $_SERVER['REQUEST_URI'])
            ) {
                $_SERVER['REQUEST_URI'] = '/wp-login.php';
                $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
                $_SERVER['PHP_SELF'] = '/wp-login.php';
                require_once ABSPATH . 'wp-login.php';
                exit;
            }
        });

        // Block access to wp-login.php and wp-admin (login form) if not using the custom URL
        add_action('init', function () use ($custom_login) {
            if (
                isset($_SERVER['REQUEST_URI']) &&
                (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false ||
                 (strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false && !is_user_logged_in())) &&
                !preg_match('#^/' . preg_quote($custom_login, '#') . '(/|$)#', $_SERVER['REQUEST_URI'])
            ) {
                status_header(404);
                exit;
            }
        }, 9);

        // Filter login_url to use the custom login path
        add_filter('login_url', function ($login_url, $redirect, $force_reauth) use ($custom_login) {
            $url = home_url('/' . $custom_login . '/');
            if (!empty($redirect)) {
                $url = add_query_arg('redirect_to', urlencode($redirect), $url);
            }
            if ($force_reauth) {
                $url = add_query_arg('reauth', '1', $url);
            }
            return $url;
        }, 10, 3);
    }

    /**
     * Remove the "Howdy" greeting from the admin bar
     */
    public function removeHowdy() {
        add_action('wp_before_admin_bar_render', function() {
            global $wp_admin_bar;
            $my_account = $wp_admin_bar->get_node('my-account');
            if ($my_account) {
                $greeting = str_replace('Howdy, ', '', $my_account->title);
                $wp_admin_bar->add_node([
                    'id' => 'my-account',
                    'title' => $greeting,
                ]);
            }
        }, 25);
    }
}
