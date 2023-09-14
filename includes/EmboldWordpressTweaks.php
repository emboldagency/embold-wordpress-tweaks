<?php

namespace App;

class EmboldWordpressTweaks {
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
        if (is_plugin_active('litespeed-cache/litespeed-cache.php')) {
            // Define the content filter function inline
            add_filter('litespeed_buffer_before', function ($content) {
                preg_match_all('/<img[^>]*>/i', $content, $matches);
                foreach ($matches[0] as $match) {
                    $cleaned_tag = preg_replace("/\s+/", " ", $match);
                    $cleaned_tag = str_replace(array("\r", "\n"), '', $cleaned_tag);
                    $content = str_replace($match, $cleaned_tag, $content);
                }
                return  $content;
            }, 0);
        }
    }
}
