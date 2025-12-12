<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;

class EmboldWordpressTweaks
{
    /**
     * Option key matching the Settings Page
     */
    const OPTION_NAME = 'embold_tweaks_options';

    /**
     * Helper to check if a feature is enabled.
     * Priority: Constant > Option > Default (True)
     */
    private function isFeatureEnabled(string $key, ?string $constant = null): bool
    {
        // 1. Check Constant
        if ($constant && defined($constant)) {
            return (bool) constant($constant);
        }

        // 2. Retrieve Options
        $opts = get_option(self::OPTION_NAME, []);

        // Safety: Ensure we have an array
        if (!is_array($opts)) {
            $opts = [];
        }

        // 3. Check DB Option
        // If the specific key exists, return its value (cast to bool)
        if (array_key_exists($key, $opts)) {
            return (bool) $opts[$key];
        }

        // 4. Default: If key is missing from DB, feature is ENABLED.
        return true;
    }

    private function getOption(string $key, $default = '')
    {
        // Priority: EMBOLD_SUPPRESS_LOGS_EXTRA (constant) -> option -> default
        if ($key === 'suppress_notice_extra_strings') {
            if (defined('EMBOLD_SUPPRESS_LOGS_EXTRA')) {
                $const_val = constant('EMBOLD_SUPPRESS_LOGS_EXTRA');
                // Support both array and string formats
                if (is_array($const_val)) {
                    return implode("\n", $const_val);
                }
                return (string) $const_val;
            }
        }

        $opts = get_option(self::OPTION_NAME, []);
        return $opts[$key] ?? $default;
    }

    public function allowSpecificUsersToEditFiles()
    {
        // 1. Check if restrictions are globally disabled (Loose Mode)
        // Check Constant
        if (defined('LOOSE_USER_RESTRICTIONS') && constant('LOOSE_USER_RESTRICTIONS')) {
            return; // Exit early: Restrictions disabled by constant
        }

        // Check Option (Saved as boolean TRUE if loose/unsafe)
        $opts = get_option(self::OPTION_NAME, []);
        if (!empty($opts['loose_user_restrictions'])) {
            return; // Exit early: Restrictions disabled by settings checkbox
        }

        // 2. If we are here, restrictions are ACTIVE. Proceed to check emails.
        $default_emails = [
            'info@embold.com',
            'info@wphaven.app',
        ];

        $allowed_emails = $default_emails;

        // Priority: wphaven-connect option > constants > embold option
        $wph_opts_elevated = null;
        if (class_exists('WPHavenConnect\\Providers\\SettingsServiceProvider')) {
            $wph_opts = get_option('wphaven_connect_options', []);
            if (!empty($wph_opts['elevated_emails'])) {
                $allowed_emails = array_merge($allowed_emails, (array) $wph_opts['elevated_emails']);
                $wph_opts_elevated = true;
            }
        }

        // Check for constants (only if wphaven didn't provide emails)
        if (!$wph_opts_elevated) {
            if (defined('ELEVATED_EMAILS') && is_array(ELEVATED_EMAILS)) {
                $allowed_emails = array_merge($allowed_emails, ELEVATED_EMAILS);
            } else {
                // Fall back to embold option only if no wphaven or constants
                if (!empty($opts['elevated_emails']) && is_array($opts['elevated_emails'])) {
                    $allowed_emails = array_merge($allowed_emails, $opts['elevated_emails']);
                }
            }
        }

        // Ensure emails are unique and lowercase
        $allowed_emails = array_unique(array_map('strtolower', $allowed_emails));
        $current_user = wp_get_current_user();
        $user_email = strtolower($current_user->user_email);

        // If user is allowed, do nothing
        if (in_array($user_email, $allowed_emails)) {
            return;
        }

        // --- ENFORCE RESTRICTIONS ---

        // Hide this plugin from the plugins list
        add_filter('all_plugins', function ($plugins) {
            if (isset($plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php'])) {
                unset($plugins['embold-wordpress-tweaks/embold-wordpress-tweaks.php']);
            }
            return $plugins;
        });

        // Filter to disallow file/plugin/theme edits
        add_filter('user_has_cap', function ($all_capabilities, $caps, $args) {
            // PLUGINS
            $all_capabilities['update_plugins'] = false;
            $all_capabilities['install_plugins'] = false;
            $all_capabilities['delete_plugins'] = false;

            // THEMES
            $all_capabilities['update_themes'] = false;
            $all_capabilities['switch_themes'] = false;
            $all_capabilities['install_themes'] = false;
            $all_capabilities['edit_themes'] = false;
            $all_capabilities['delete_themes'] = false;

            // TOOLS
            // $all_capabilities['manage_options'] = true; // Don't force true, let WP decide, but removing it ensures we don't accidentally grant it.
            $all_capabilities['import'] = false;

            // CORE / FILES
            $all_capabilities['update_core'] = false;
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
        if (!$this->isFeatureEnabled('enable_svg', 'EMBOLD_ALLOW_SVG')) {
            return;
        }

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
        if (!$this->isFeatureEnabled('disable_xmlrpc', 'EMBOLD_DISABLE_XMLRPC')) {
            return;
        }

        add_filter('xmlrpc_enabled', '__return_false');
    }

    /**
     * Defer scripts to try to avoid Coders 502 errors.
     *
     * @return void
     */
    public function deferScripts()
    {
        if (!$this->isFeatureEnabled('defer_scripts')) {
            return;
        }

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
        if (!$this->isFeatureEnabled('async_scripts')) {
            return;
        }

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
        if (!$this->isFeatureEnabled('clean_img_tags')) {
            return;
        }

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
        // Enable Slug Search
        if ($this->isFeatureEnabled('enable_slug_search')) {
            add_filter('posts_search', function ($search, \WP_Query $q) use (&$wpdb) {
                global $wpdb;

                // Nothing to do
                if (
                    !did_action('load-edit.php')
                    || !is_admin()
                    || !$q->is_search()
                    || !$q->is_main_query()
                ) {
                    return $search;
                }

                $s = $q->get('s');

                // Check for "slug:" part in the search input
                if ('slug:' === mb_substr(trim($s), 0, 5)) {
                    // Override the search query
                    $search = $wpdb->prepare(
                        " AND {$wpdb->posts}.post_name LIKE %s ",
                        str_replace(
                            ['**', '*'],
                            ['*', '%'],
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
        }

        // Enable Slug Column
        if ($this->isFeatureEnabled('enable_slug_column')) {
            $post_types = ['page', 'post'];
            foreach ($post_types as $post_type) {
                add_filter("manage_{$post_type}_posts_columns", function ($columns) use ($post_type) {
                    $new = [];
                    $slug = $columns["{$post_type}_slug"] = __('Slug', 'embold-wordpress-tweaks');
                    unset($columns["{$post_type}_slug"]);

                    // Insert slug column after title
                    foreach ($columns as $key => $value) {
                        $new[$key] = $value;
                        if ($key == 'title') {
                            $new["{$post_type}_slug"] = $slug;
                        }
                    }
                    return $new;
                });

                add_action("manage_{$post_type}_posts_custom_column", function ($column, $post_id) use ($post_type) {
                    if ($column == "{$post_type}_slug") {
                        echo get_post_field('post_name', $post_id, 'raw');
                    }
                }, 10, 2);
            }
        }
    }

    /**
     * Disable escaping ACF shortcode content introduced in ACF 6.2.5
     */
    public function disableEscapingAcfShortcodes()
    {
        if (!$this->isFeatureEnabled('disable_acf_escaping')) {
            return;
        }

        if (function_exists('is_plugin_active') && (is_plugin_active('advanced-custom-fields/acf.php') || is_plugin_active('advanced-custom-fields-pro/acf.php'))) {
            // always return true, no matter which ACF shortcode is being used
            add_filter('acf/shortcode/allow_unsafe_html', '__return_true', 10, 2);

            // Disable the notice about this in the admin
            add_filter('acf/admin/prevent_escaped_html_notice', '__return_true');
        }
    }

    /**
     * Remove the "Howdy" greeting from the admin bar
     */
    public function removeHowdy()
    {
        if (!$this->isFeatureEnabled('remove_howdy')) {
            return;
        }

        add_action('wp_before_admin_bar_render', function () {
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

    /**
     * Manage the MU-plugin for notice suppression.
     * Ensures early loading to catch _doing_it_wrong notices.
     */
    public function enableNoticeSuppression()
    {
        $mu_path = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';
        $legacy_wphaven_path = WPMU_PLUGIN_DIR . '/00-suppress-textdomain-notices.php';
        $should_be_active = $this->resolveSuppressLogsConstant();

        // Only manage the file in the admin to avoid disk I/O on every frontend request
        // Unless it's a dev environment where we might be toggling things
        if (!is_admin() && !defined('WP_CLI')) {
            return;
        }

        // Cleanup legacy MU-plugin files
        if (file_exists($legacy_wphaven_path)) {
            @unlink($legacy_wphaven_path);
        }

        if ($should_be_active) {
            // If the file doesn't exist, create it
            // We can also check modification time or version if we update the logic often,
            // but for now, existence check is sufficient.
            if (!file_exists($mu_path)) {
                $this->createMuPlugin($mu_path);
            }
        } else {
            // If feature is disabled, cleanup the MU plugin
            $this->removeMuPlugin();
        }

        // Fallback: If writing to MU failed (permissions), apply late filters anyway
        if ($should_be_active && !file_exists($mu_path)) {
            $this->applyLateNoticeSuppression();
        }
    }

    /**
     * Resolve suppress logs constant with backwards compatibility.
     * Priority: EMBOLD_SUPPRESS_LOGS (new) -> WPH_SUPPRESS_TEXTDOMAIN_NOTICES (legacy) -> option -> default (true)
     */
    private function resolveSuppressLogsConstant(): bool
    {
        // 1. Check new constant first
        if (defined('EMBOLD_SUPPRESS_LOGS')) {
            return (bool) constant('EMBOLD_SUPPRESS_LOGS');
        }

        // 2. Fall back to legacy constant for backwards compatibility
        if (defined('WPH_SUPPRESS_TEXTDOMAIN_NOTICES')) {
            return (bool) constant('WPH_SUPPRESS_TEXTDOMAIN_NOTICES');
        }

        // 3. Check database option
        return $this->isFeatureEnabled('suppress_notices');
    }

    /**
     * Handle plugin deactivation
     * Cleans up the MU-plugin file when plugin is deactivated
     */
    public static function onDeactivation()
    {
        $mu_path = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';
        
        if (file_exists($mu_path)) {
            if (@unlink($mu_path)) {
                error_log('[Embold] MU-plugin cleaned up');
            } else {
                error_log('[Embold] Failed to delete MU-plugin: ' . $mu_path);
            }
        }
    }

    /**
     * Removes the MU-plugin file if it exists.
     */
    private function removeMuPlugin()
    {
        $mu_path = WPMU_PLUGIN_DIR . '/00-suppress-logs.php';
        if (file_exists($mu_path)) {
            @unlink($mu_path);
        }
    }

    private function createMuPlugin($path)
    {
        // Ensure directory exists
        if (!is_dir(dirname($path))) {
            if (!mkdir(dirname($path), 0755, true)) {
                error_log('[Embold] Failed to create mu-plugins directory: ' . dirname($path));
                return;
            }
        }

        // Resolve source path using plugin_dir_path for proper plugin-relative path
        $source = plugin_dir_path(dirname(__FILE__)) . 'templates/00-suppress-logs.php';

        if (!file_exists($source)) {
            error_log('[Embold] MU-Plugin Template missing at: ' . $source);
            return;
        }

        if (!copy($source, $path)) {
            error_log('[Embold] Failed to copy MU-Plugin to: ' . $path);
        }
    }

    /**
     * Fallback method if MU plugin cannot be written.
     */
    private function applyLateNoticeSuppression()
    {
        $strings_to_check = [
            '_load_textdomain_just_in_time',
            'Translation loading',
            'automatic_feed_links',
            'wp_deregister_script',
            'wp_register_script',
            'wp_enqueue_script',
            'Scripts and styles should not be registered or enqueued until the',
        ];

        // Add custom strings
        $extra = $this->getOption('suppress_notice_extra_strings');
        if (!empty($extra)) {
            $custom_strings = preg_split('/[\r\n]+/', $extra);
            if (is_array($custom_strings)) {
                $strings_to_check = array_merge($strings_to_check, array_filter(array_map('trim', $custom_strings)));
            }
        }

        add_filter('doing_it_wrong_trigger_error', function ($trigger, $function_name, $message, $version) use ($strings_to_check) {
            foreach ($strings_to_check as $s) {
                if (empty($s))
                    continue;
                if ($function_name === $s || strpos($message, $s) !== false) {
                    return false;
                }
            }
            return $trigger;
        }, 10, 4);
    }

    /**
     * Configure Mail Behavior (Block / SMTP Override)
     */
    public function configureMailBehavior()
    {
        // Resolve Mode
        // We replicate the resolver logic here to keep it self-contained in the main class
        // or check if we can reuse the SettingsPage static helper if accessible.
        // For simplicity, let's implement the resolver logic directly here.
        $mode = 'auto';
        $locked_by = null;

        // Constants
        if (defined('DISABLE_MAIL') && constant('DISABLE_MAIL')) {
            $mode = 'block_all';
        } else {
            $opts = get_option(self::OPTION_NAME, []);
            $mode = $opts['mail_mode'] ?? 'auto';
        }

        // Auto Logic
        if ($mode === 'auto') {
            $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
            if ($env === 'local')
                $mode = 'smtp_override';
            elseif (in_array($env, ['development', 'staging'], true))
                $mode = 'block_all';
            else
                $mode = 'no_override';
        }

        // Handle Modes
        switch ($mode) {
            case 'block_all':
                add_filter('pre_wp_mail', function ($result, $args = []) {
                    $error = new \WP_Error('embold_mail_blocked', 'Mail sending is blocked by Embold Tweaks.');
                    do_action('wp_mail_failed', $error);
                    return false;
                }, 9999, 2);
                break;

            case 'smtp_override':
                // Override From Addresses
                add_filter('wp_mail_from', function () {
                    return $this->getOption('smtp_from_email', 'admin@wordpress.local');
                }, 9999);
                add_filter('wp_mail_from_name', function () {
                    return $this->getOption('smtp_from_name', 'WordPress');
                }, 9999);

                // Configure PHPMailer
                add_action('phpmailer_init', function (PHPMailer $phpmailer) {
                    $phpmailer->isSMTP();
                    $phpmailer->Host = $this->getOption('smtp_host', 'mailpit');
                    $phpmailer->Port = (int) $this->getOption('smtp_port', 1025);
                    $phpmailer->SMTPAuth = false;
                    $phpmailer->SMTPSecure = '';
                }, 9999);

                // Disable conflicting plugins
                $this->disableConflictingMailPlugins();
                break;
        }
    }

    private function disableConflictingMailPlugins()
    {
        if (!is_admin())
            return;

        $plugins = ['mailgun/mailgun.php', 'sparkpost/sparkpost.php', 'wp-mail-smtp/wp_mail_smtp.php', 'easy-wp-smtp/easy-wp-smtp.php'];
        $active = array_filter($plugins, 'is_plugin_active');
        if (!empty($active)) {
            deactivate_plugins($active);
        }
    }
}