<?php

namespace App\Admin;

use App\Services\DisableMailService;

class SettingsPage
{
    private const OPTION_NAME = 'embold_tweaks_options';
    private DisableMailService $mailService;

    public function __construct()
    {
        $this->mailService = new DisableMailService();
    }

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_init', [$this, 'handleReset']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Handle test email
        add_action('admin_post_embold_send_test_email', [$this, 'handleSendTestEmail']);
    }

    public function enqueueAssets($hook): void
    {
        if ($hook !== 'settings_page_embold-wordpress-tweaks') {
            return;
        }

        $plugin_data = get_file_data(
            dirname(dirname(dirname(__FILE__))) . '/embold-wordpress-tweaks.php',
            ['Version' => 'Version']
        );

        $version = $plugin_data['Version'] ?? '1.6.0';

        wp_enqueue_style(
            'embold-tweaks-settings',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/settings-page.css',
            [],
            $version
        );

        wp_enqueue_script(
            'embold-tweaks-settings',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/settings-page.js',
            [],
            $version,
            true
        );
    }

    public function handleSendTestEmail()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized.', 'embold-wordpress-tweaks'));
        }

        check_admin_referer('embold_send_test_email', 'embold_test_nonce');

        $to = sanitize_email($_POST['embold_test_email']);
        $subject = 'Embold Tweaks: Test Email';
        $body = "This is a test email sent from Embold WordPress Tweaks to verify your mail configuration.";

        // Capture errors
        $mail_failed_msg = '';
        $failed_cb = function ($wp_error) use (&$mail_failed_msg) {
            if (is_wp_error($wp_error)) {
                $mail_failed_msg = $wp_error->get_error_message();
            }
        };
        add_action('wp_mail_failed', $failed_cb);

        $sent = wp_mail($to, $subject, $body);

        remove_action('wp_mail_failed', $failed_cb);

        if ($sent) {
            $msg = rawurlencode(sprintf(__('Test email sent to %s.', 'embold-wordpress-tweaks'), $to));
            $redirect = add_query_arg(['page' => 'embold-wordpress-tweaks', 'settings-updated' => 'true', 'embold_msg' => $msg], admin_url('options-general.php'));
        } else {
            $error = $mail_failed_msg ?: __('wp_mail returned false.', 'embold-wordpress-tweaks');
            $msg = rawurlencode(sprintf(__('Test email failed: %s', 'embold-wordpress-tweaks'), $error));
            $redirect = add_query_arg(['page' => 'embold-wordpress-tweaks', 'error' => 'true', 'embold_err' => $msg], admin_url('options-general.php'));
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public function handleReset(): void
    {
        if (isset($_POST['embold_reset_settings'])) {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (!check_admin_referer('embold_reset_settings_action', 'embold_reset_nonce')) {
                return;
            }

            delete_option(self::OPTION_NAME);

            add_settings_error(
                self::OPTION_NAME,
                'embold_reset',
                __('Settings reset to defaults.', 'embold-wordpress-tweaks'),
                'updated'
            );

            // Schedule cleanup of the notice after this request
            add_action('shutdown', function () {
                global $wp_settings_errors;
                if (isset($wp_settings_errors)) {
                    $wp_settings_errors = array_filter($wp_settings_errors, function ($error) {
                        return $error['setting'] !== self::OPTION_NAME || $error['code'] !== 'embold_reset';
                    });
                }
            });
        }
    }

    public function addSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $should_render = false;

        // Check if restrictions are globally disabled (Loose Mode)
        $restrictions = $this->resolveUserRestrictions();
        if ($restrictions['restrictions_disabled']) {
            $should_render = true;
        } else {
            // Restrictions are ACTIVE. User MUST be Elevated to see this page.

            // Use unified elevation logic (Defaults + Resolved Priority Source)
            $current_user = wp_get_current_user();
            $user_email = strtolower($current_user->user_email);

            // Default hardcoded emails
            $elevated_emails = [
                'info@embold.com',
                'info@wphaven.app',
            ];

            // Add configured emails (Constants > Options)
            $resolution = $this->resolveElevatedEmails();
            $elevated_emails = array_merge($elevated_emails, $resolution['emails']);

            // Normalize and check
            $elevated_emails = array_unique(array_map('strtolower', $elevated_emails));

            if (in_array($user_email, $elevated_emails)) {
                $should_render = true;
            }
        }

        if (!$should_render) {
            return;
        }

        add_options_page(
            __('Embold Tweaks', 'embold-wordpress-tweaks'),
            __('Embold Tweaks', 'embold-wordpress-tweaks'),
            'manage_options',
            'embold-wordpress-tweaks',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_NAME, self::OPTION_NAME, [$this, 'sanitize']);

        // --- SECTION: General Tweaks ---
        add_settings_section(
            'embold_tweaks_general',
            __('General Tweaks', 'embold-wordpress-tweaks'),
            null,
            'embold-wordpress-tweaks'
        );

        $tweaks = [
            // Core Features & Security
            'enable_svg' => [
                'label' => __('Enable SVG Uploads', 'embold-wordpress-tweaks'),
                'const' => 'EMBOLD_ALLOW_SVG',
                'desc' => __('Allows SVG files to be uploaded to the Media Library.', 'embold-wordpress-tweaks') .
                    '<div style="margin-top: 1em; padding: 8px 12px; background-color: #fcf9e8; border-left: 4px solid #F1C21B;">' .
                    '<strong style="display: block; color: #b07b06; margin-bottom: 2px; padding-right: .5em;">' . __('Security Warning', 'embold-wordpress-tweaks') . '</strong>' .
                    '<span style="color: #646970;">' . __('SVGs can contain malicious code; ensure only trusted users have upload permissions.', 'embold-wordpress-tweaks') . '</span>' .
                    '</div>'
            ],
            'disable_xmlrpc' => [
                'label' => __('Disable XML-RPC', 'embold-wordpress-tweaks'),
                'const' => 'EMBOLD_DISABLE_XMLRPC',
                'desc' => __('Disables the XML-RPC API to protect against brute-force attacks and DDoS.', 'embold-wordpress-tweaks')
            ],

            // Admin UX
            'enable_slug_search' => [
                'label' => __('Enable Slug Search', 'embold-wordpress-tweaks'),
                'desc' => __('Allows searching by slug using the prefix <code>slug:your-slug</code>.', 'embold-wordpress-tweaks')
            ],
            'enable_slug_column' => [
                'label' => __('Enable Slug Column', 'embold-wordpress-tweaks'),
                'desc' => __('Adds a "Slug" column to post lists.', 'embold-wordpress-tweaks')
            ],
            'remove_howdy' => [
                'label' => __('Remove "Howdy"', 'embold-wordpress-tweaks'),
                'desc' => __('Removes the "Howdy" text from the admin bar greeting.', 'embold-wordpress-tweaks')
            ],

            // Stability & Performance (502 Error Prevention)
            'defer_scripts' => [
                'label' => __('Defer Non-Critical Scripts', 'embold-wordpress-tweaks'),
                'desc' => __('Defers common scripts (like admin bar JS) to improve load times and prevent 502 errors on local environments.', 'embold-wordpress-tweaks')
            ],
            'async_scripts' => [
                'label' => __('Async Admin Scripts', 'embold-wordpress-tweaks'),
                'desc' => __('Loads admin bar, heartbeat, and other admin scripts asynchronously.', 'embold-wordpress-tweaks')
            ],

            // Specific Compatibility Fixes
            'disable_acf_escaping' => [
                'label' => __('Disable ACF Shortcode Escaping', 'embold-wordpress-tweaks'),
                'desc' => __('Reverts the ACF 6.2.5 security change to allow HTML in shortcode content.', 'embold-wordpress-tweaks')
            ],
            'clean_img_tags' => [
                'label' => __('LiteSpeed Image Cleanup', 'embold-wordpress-tweaks'),
                'desc' => __('Removes line breaks from img tags to ensure compatibility with LiteSpeed Cache.', 'embold-wordpress-tweaks')
            ],
        ];

        foreach ($tweaks as $key => $args) {
            add_settings_field(
                $key,
                $args['label'],
                [$this, 'renderCheckboxField'],
                'embold-wordpress-tweaks',
                'embold_tweaks_general',
                array_merge(['key' => $key], $args)
            );
        }

        // Manually register Suppress Notices so they are grouped
        add_settings_field(
            'suppress_notices',
            __('Suppress Debug Notices', 'embold-wordpress-tweaks'),
            [$this, 'renderCheckboxField'],
            'embold-wordpress-tweaks',
            'embold_tweaks_general',
            [
                'key' => 'suppress_notices',
                'const' => 'EMBOLD_SUPPRESS_LOGS',
                'legacy_const' => 'WPH_SUPPRESS_TEXTDOMAIN_NOTICES',
                'desc' => __('Suppresses noisy <code>_doing_it_wrong</code> notices (e.g. textdomain loading) to keep logs clean.', 'embold-wordpress-tweaks'),
                'class' => 'embold-suppress-toggle' // Helper class for JS
            ]
        );

        add_settings_field(
            'suppress_notice_extra_strings',
            __('Extra Suppression Strings', 'embold-wordpress-tweaks'),
            [$this, 'renderTextareaField'],
            'embold-wordpress-tweaks',
            'embold_tweaks_general',
            [
                'key' => 'suppress_notice_extra_strings',
                'const' => 'EMBOLD_SUPPRESS_LOGS_EXTRA',
                'desc' => __('Add additional partial strings to suppress from <code>_doing_it_wrong</code> notices, one per line.', 'embold-wordpress-tweaks'),
                'row_class' => 'embold-suppress-strings-row' // Helper class for JS target
            ]
        );

        // --- SECTION: User Restrictions ---
        add_settings_section(
            'embold_tweaks_user_restrictions',
            __('User Restrictions', 'embold-wordpress-tweaks'),
            function () {
                echo '<p>' . esc_html__('Control which users can manage plugins, themes, and files.', 'embold-wordpress-tweaks') . '</p>';
            },
            'embold-wordpress-tweaks'
        );

        add_settings_field(
            'elevated_emails',
            __('Elevated Admin Emails', 'embold-wordpress-tweaks'),
            [$this, 'renderElevatedEmailsField'],
            'embold-wordpress-tweaks',
            'embold_tweaks_user_restrictions'
        );

        add_settings_field(
            'loose_user_restrictions',
            __('Enforce User Restrictions', 'embold-wordpress-tweaks'),
            [$this, 'renderLooseUserRestrictionsField'],
            'embold-wordpress-tweaks',
            'embold_tweaks_user_restrictions'
        );

        // --- SECTION: Mail Behavior ---
        add_settings_section(
            'embold_tweaks_mail',
            __('Mail Behavior', 'embold-wordpress-tweaks'),
            function () {
                echo '<p>' . esc_html__('Control how mail behaves per environment.', 'embold-wordpress-tweaks') . '</p>';
            },
            'embold-wordpress-tweaks'
        );

        add_settings_field(
            'mail_mode',
            __('Mail Mode', 'embold-wordpress-tweaks'),
            [$this, 'renderMailModeField'],
            'embold-wordpress-tweaks',
            'embold_tweaks_mail'
        );

        // SMTP Fields
        $smtp_fields = [
            'smtp_host' => [
                'label' => __('SMTP Host', 'embold-wordpress-tweaks'),
                'default' => 'mailpit',
                'const' => 'EMBOLD_SMTP_HOST'
            ],
            'smtp_port' => [
                'label' => __('SMTP Port', 'embold-wordpress-tweaks'),
                'default' => '1025',
                'type' => 'number',
                'const' => 'EMBOLD_SMTP_PORT'
            ],
            'smtp_from_email' => [
                'label' => __('From Email', 'embold-wordpress-tweaks'),
                'default' => 'admin@wordpress.local',
                'type' => 'email',
                'const' => 'EMBOLD_SMTP_FROM_EMAIL'
            ],
            'smtp_from_name' => [
                'label' => __('From Name', 'embold-wordpress-tweaks'),
                'default' => 'WordPress',
                'const' => 'EMBOLD_SMTP_FROM_NAME'
            ],
        ];

        foreach ($smtp_fields as $key => $args) {
            add_settings_field(
                $key,
                $args['label'],
                [$this, 'renderTextField'],
                'embold-wordpress-tweaks',
                'embold_tweaks_mail',
                array_merge(['key' => $key, 'wrapper_class' => 'embold-smtp-field'], $args)
            );
        }
    }

    /**
     * Generic renderer for boolean checkbox fields
     */
    public function renderCheckboxField($args): void
    {
        $key = $args['key'];
        $const = $args['const'] ?? null;
        $legacy_const = $args['legacy_const'] ?? null;
        $desc = $args['desc'] ?? '';
        $extra_class = $args['class'] ?? '';

        $opts = $this->getOptions();

        // Determine effective value and lock state
        $is_locked = false;
        $locked_val = null;
        $locked_const_name = null;
        $is_checked = false;

        // Check primary constant first, then legacy constant
        if ($const && defined($const)) {
            $is_locked = true;
            $locked_val = constant($const);
            $locked_const_name = $const;
            $is_checked = (bool) $locked_val;
        } elseif ($legacy_const && defined($legacy_const)) {
            $is_locked = true;
            $locked_val = constant($legacy_const);
            $locked_const_name = $legacy_const;
            $is_checked = (bool) $locked_val;
        } else {
            // Force boolean logic: if not empty/false/0, it is true.
            $is_checked = !empty($opts[$key]);
        }

        $name = self::OPTION_NAME . "[$key]";
        $disabled_attr = $is_locked ? 'disabled' : '';

        echo '<label>';
        echo sprintf(
            '<input type="checkbox" name="%s" value="1" %s %s class="%s"> ',
            esc_attr($name),
            checked(1, $is_checked ? 1 : 0, false),
            $disabled_attr,
            esc_attr($extra_class)
        );

        if ($desc) {
            echo wp_kses_post($desc);
        }
        echo '</label>';

        if ($is_locked && $locked_const_name) {
            echo '<p class="description wph-const-override">' .
                sprintf(__('Locked by constant: <code>%s</code>', 'embold-wordpress-tweaks'), $locked_const_name) .
                '</p>';
        }
    }

    public function renderTextField($args): void
    {
        $key = $args['key'];
        $type = $args['type'] ?? 'text';
        $desc = $args['desc'] ?? '';
        $default = $args['default'] ?? '';
        $const = $args['const'] ?? null;
        $wrapper_class = $args['wrapper_class'] ?? '';

        $opts = $this->getOptions();

        // Determine if locked by constant
        $is_locked = false;
        $locked_const_name = null;
        if ($const && defined($const)) {
            $is_locked = true;
            $locked_const_name = $const;
            $value = (string) constant($const);
        } else {
            $value = $opts[$key] ?? '';
        }

        // Show default placeholder if empty
        $placeholder = $default ? 'placeholder="' . esc_attr($default) . '"' : '';
        $readonly_attr = $is_locked ? 'readonly' : '';

        $name = self::OPTION_NAME . "[$key]";

        if ($wrapper_class) {
            echo '<div class="' . esc_attr($wrapper_class) . '">';
        }

        echo sprintf(
            '<input type="%s" name="%s" value="%s" class="regular-text" %s %s>',
            esc_attr($type),
            esc_attr($name),
            esc_attr($value),
            $placeholder,
            $readonly_attr
        );

        if ($desc) {
            echo '<p class="description">' . wp_kses_post($desc) . '</p>';
        }

        if ($is_locked && $locked_const_name) {
            echo $this->getConstantOverrideHtml($locked_const_name);
        }

        if ($wrapper_class) {
            echo '</div>';
        }
    }

    public function renderTextareaField($args): void
    {
        $key = $args['key'];
        $const = $args['const'] ?? null;
        $desc = $args['desc'] ?? '';
        $row_class = $args['row_class'] ?? '';

        $opts = $this->getOptions();

        // Determine if locked by constant
        $is_locked = false;
        $locked_const_name = null;
        if ($const && defined($const)) {
            $is_locked = true;
            $locked_const_name = $const;
            $const_val = constant($const);
            // Support both array and string formats
            if (is_array($const_val)) {
                $value = implode("\n", $const_val);
            } else {
                $value = (string) $const_val;
            }
        } else {
            $value = $opts[$key] ?? '';
        }

        $name = self::OPTION_NAME . "[$key]";
        $readonly_attr = $is_locked ? 'readonly' : '';

        // Hacky way to add a class to the TR via the field callback output? 
        // WP doesn't let us easily add class to the TR from add_settings_field.
        // We will wrap this in a div that our JS can find to hide the parent TR.
        if ($row_class) {
            echo '<div class="' . esc_attr($row_class) . '">';
        }

        echo sprintf(
            '<textarea name="%s" rows="3" class="large-text" %s>%s</textarea>',
            esc_attr($name),
            $readonly_attr,
            esc_textarea($value)
        );

        if ($desc) {
            echo '<p class="description">' . wp_kses_post($desc) . '</p>';
        }

        if ($is_locked && $locked_const_name) {
            echo $this->getConstantOverrideHtml($locked_const_name);
        }

        if ($row_class) {
            echo '</div>';
        }
    }

    public function renderMailModeField(): void
    {
        $opts = $this->getOptions();
        $mode = $opts['mail_mode'];
        $resolution = $this->mailService->resolveMailMode();
        $effective = $resolution['mode'];
        $locked_by = $resolution['locked_by'];
        $source = $resolution['source'];

        // Lock if CONSTANT is set.
        $is_readonly = !empty($locked_by);

        echo '<select name="' . esc_attr(self::OPTION_NAME) . '[mail_mode]" data-effective-mode="' . esc_attr($effective) . '" ' . ($is_readonly ? 'disabled' : '') . '>';
        $this->renderOption('auto', __('Auto (environment-based)', 'embold-wordpress-tweaks'), $mode);
        $this->renderOption('block_all', __('Block', 'embold-wordpress-tweaks'), $mode);
        $this->renderOption('smtp_override', __('SMTP Override (Mailpit/Custom)', 'embold-wordpress-tweaks'), $mode);
        $this->renderOption('allow_all', __('Allow (No Override)', 'embold-wordpress-tweaks'), $mode);
        echo '</select>';

        if (!$is_readonly) {
            echo '<p class="description">' . esc_html__('Auto: blocks mail on development/staging; allows in production. SMTP Override: uses settings below.', 'embold-wordpress-tweaks') . '</p>';
        }

        $status_label = $this->formatModeLabel($effective);

        echo '<div style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-left: 4px solid #72aee6;">';
        echo '<strong>' . esc_html__('Effective Status:', 'embold-wordpress-tweaks') . '</strong> ';
        echo '<span>' . esc_html($status_label) . '</span>';
        echo '</div>';

        if (!empty($locked_by)) {
            echo $this->getConstantOverrideHtml($locked_by);
        } else {
            echo '<p class="description" style="color: #646970;">' . esc_html(sprintf(__('Source: %s', 'embold-wordpress-tweaks'), $source)) . '</p>';
        }
    }

    public function renderSettingsPage(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Embold Tweaks Settings', 'embold-wordpress-tweaks'); ?></h1>

            <?php if (isset($_GET['embold_msg'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html(urldecode($_GET['embold_msg'])); ?></p>
                </div>
                <?php
                // Clear settings-updated to avoid duplicate message
                unset($_GET['settings-updated']);
            endif; ?>
            <?php if (isset($_GET['embold_err'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html(urldecode($_GET['embold_err'])); ?></p>
                </div>
            <?php endif; ?>

            <?php 
            settings_errors(self::OPTION_NAME);
            // Clear the notice queue after rendering so it doesn't persist on reload
            add_action('shutdown', function () {
                global $wp_settings_errors;
                if (isset($wp_settings_errors)) {
                    $wp_settings_errors = [];
                }
            });
            ?>

            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_NAME);
                do_settings_sections('embold-wordpress-tweaks');
                submit_button();
                ?>
            </form>

            <hr style="margin-top: 40px; margin-bottom: 20px; border-color: #dcdcde;">

            <h2><?php echo esc_html__('Test Configuration', 'embold-wordpress-tweaks'); ?></h2>
            <p class="description">
                <?php echo esc_html__('This sends a test email using the currently active mail configuration.', 'embold-wordpress-tweaks'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('embold_send_test_email', 'embold_test_nonce'); ?>
                <input type="hidden" name="action" value="embold_send_test_email">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label
                                for="embold_test_email"><?php echo esc_html__('Recipient Email', 'embold-wordpress-tweaks'); ?></label>
                        </th>
                        <td>
                            <input id="embold_test_email" name="embold_test_email" type="email" class="regular-text"
                                value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" required>
                            <?php submit_button(__('Send test email', 'embold-wordpress-tweaks'), 'secondary', 'embold_send_test_email', false); ?>
                        </td>
                    </tr>
                </table>
            </form>

            <hr style="margin-top: 40px; margin-bottom: 20px; border-color: #dcdcde;">

            <h2><?php echo esc_html__('Reset Settings', 'embold-wordpress-tweaks'); ?></h2>
            <p><?php echo esc_html__('This will delete plugin options, reverting settings to defaults.', 'embold-wordpress-tweaks'); ?>
            </p>
            <form method="post" action="">
                <?php wp_nonce_field('embold_reset_settings_action', 'embold_reset_nonce'); ?>
                <input type="hidden" name="embold_reset_settings" value="1">
                <?php
                submit_button(
                    __('Reset to Defaults', 'embold-wordpress-tweaks'),
                    'delete',
                    'submit',
                    true,
                    ['onclick' => "return confirm('" . esc_js(__('Are you sure you want to reset all Embold WordPress Tweaks settings?', 'embold-wordpress-tweaks')) . "');"]
                );
                ?>
            </form>
        </div>
        <?php
    }
    private function renderOption(string $value, string $label, string $current): void
    {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($value),
            selected($current, $value, false),
            esc_html($label)
        );
    }

    private function formatModeLabel(string $mode): string
    {
        switch ($mode) {
            case 'block_all':
                return __('Block all mail', 'embold-wordpress-tweaks');
            case 'smtp_override':
                return __('SMTP Override', 'embold-wordpress-tweaks');
            case 'allow_all':
            case 'no_override':
                return __('Allow all mail (no override)', 'embold-wordpress-tweaks');
            default:
                return __('Auto (environment-based)', 'embold-wordpress-tweaks');
        }
    }

    public function renderElevatedEmailsField(): void
    {
        $opts = $this->getOptions();
        $name = self::OPTION_NAME . '[elevated_emails]';
        $resolution = $this->resolveElevatedEmails();

        $is_const_controlled = $resolution['managed_by'] === 'ELEVATED_EMAILS';

        // Determine display value
        if ($is_const_controlled) {
            $value = is_array($resolution['emails']) ? implode("\n", $resolution['emails']) : '';
        } else {
            $value = is_array($opts['elevated_emails']) ? implode("\n", $opts['elevated_emails']) : '';
        }

        echo sprintf(
            '<textarea name="%s" rows="4" class="large-text" %s>%s</textarea>',
            esc_attr($name),
            $is_const_controlled ? 'disabled' : '',
            esc_textarea($value)
        );
        echo '<p class="description">' . esc_html__('Additional email addresses that can manage plugins and themes. Enter one email per line. Users with info@embold.com or info@wphaven.app are automatically elevated.', 'embold-wordpress-tweaks') . '</p>';

        if ($is_const_controlled) {
            echo $this->getConstantOverrideHtml($resolution['managed_by']);
        }
    }

    public function renderLooseUserRestrictionsField(): void
    {
        $opts = $this->getOptions();
        $name = self::OPTION_NAME . '[loose_user_restrictions]';
        $resolution = $this->resolveUserRestrictions();

        $is_const = $resolution['locked_by'] === 'LOOSE_USER_RESTRICTIONS';
        $current_db_value = $resolution['restrictions_disabled'];
        $source = $resolution['source'];

        $checked = checked(0, (int) $current_db_value, false);
        $readonly = $is_const ? 'disabled' : '';
        $extra = $is_const ? ' ' . $this->getConstantOverrideHtml('LOOSE_USER_RESTRICTIONS') : '';

        echo '<label>';
        echo sprintf(
            '<input type="checkbox" name="%s" value="1" %s %s> ',
            esc_attr($name),
            $checked,
            $readonly
        );

        echo esc_html__('When checked, only elevated users can manage plugins, themes, and files. Uncheck this box to temporarily allow full access to all administrators.', 'embold-wordpress-tweaks');
        echo '</label>';

        if ($extra) {
            echo $extra;
        }

        if (!$is_const && $source === 'default') {
            echo '<p class="description" style="color: #666;"><em>' .
                esc_html__('Default Status: Restrictions are active (Safe).', 'embold-wordpress-tweaks') .
                '</em></p>';
        }
    }

    /**
     * Resolve effective user restrictions setting
     */
    public function resolveUserRestrictions(): array
    {
        // Constant takes priority
        if (defined('LOOSE_USER_RESTRICTIONS')) {
            return [
                'restrictions_disabled' => (bool) constant('LOOSE_USER_RESTRICTIONS'),
                'locked_by' => 'LOOSE_USER_RESTRICTIONS',
                'source' => 'constant',
            ];
        }

        // Plugin option
        $opts = $this->getOptions();
        if (isset($opts['loose_user_restrictions'])) {
            return [
                'restrictions_disabled' => (bool) $opts['loose_user_restrictions'],
                'locked_by' => null,
                'source' => 'option',
            ];
        }

        // Default: restrictions are enabled (loose_user_restrictions = false)
        return [
            'restrictions_disabled' => false,
            'locked_by' => null,
            'source' => 'default',
        ];
    }

    /**
     * Resolve effective elevated emails setting
     */
    public function resolveElevatedEmails(): array
    {
        // Check if wphaven-connect is managing this
        if (class_exists('WPHavenConnect\\Providers\\SettingsServiceProvider')) {
            $wph_opts = get_option('wphaven_connect_options', []);
            if (!empty($wph_opts['elevated_emails'])) {
                return [
                    'emails' => (array) $wph_opts['elevated_emails'],
                    'managed_by' => 'wphaven_connect',
                    'source' => 'wphaven_connect_option',
                ];
            }
        }

        // Check for constants
        if (defined('ELEVATED_EMAILS') && constant('ELEVATED_EMAILS')) {
            $const_val = constant('ELEVATED_EMAILS');
            return [
                'emails' => is_array($const_val) ? $const_val : [],
                'managed_by' => 'ELEVATED_EMAILS',
                'source' => 'constant',
            ];
        }

        // Fall back to embold option
        $opts = $this->getOptions();
        if (!empty($opts['elevated_emails'])) {
            return [
                'emails' => (array) $opts['elevated_emails'],
                'managed_by' => null,
                'source' => 'embold_option',
            ];
        }

        return [
            'emails' => [],
            'managed_by' => null,
            'source' => 'default',
        ];
    }

    /**
     * Sanitize settings input
     */
    public function sanitize($input): array
    {
        $output = get_option(self::OPTION_NAME, []);

        // --- General Tweaks ---
        $booleans = [
            'enable_svg',
            'disable_xmlrpc',
            'defer_scripts',
            'async_scripts',
            'clean_img_tags',
            'enable_slug_search',
            'enable_slug_column',
            'disable_acf_escaping',
            'remove_howdy',
            'suppress_notices'
        ];

        foreach ($booleans as $key) {
            $output[$key] = isset($input[$key]);
        }

        // Textarea: Suppress Extra Strings (only if not locked by constant)
        if (!defined('EMBOLD_SUPPRESS_LOGS_EXTRA')) {
            if (isset($input['suppress_notice_extra_strings'])) {
                $output['suppress_notice_extra_strings'] = sanitize_textarea_field($input['suppress_notice_extra_strings']);
            }
        }

        // --- Mail Mode ---
        if (isset($input['mail_mode'])) {
            $mode = sanitize_text_field($input['mail_mode']);
            $allowed = ['auto', 'block_all', 'allow_all', 'smtp_override'];
            if (in_array($mode, $allowed, true)) {
                $output['mail_mode'] = $mode;
            }
        }

        // --- SMTP Settings ---
        $smtp_keys = ['smtp_host', 'smtp_from_name'];
        foreach ($smtp_keys as $key) {
            if (isset($input[$key])) {
                $output[$key] = sanitize_text_field($input[$key]);
            }
        }
        if (isset($input['smtp_port'])) {
            $output['smtp_port'] = absint($input['smtp_port']);
        }
        if (isset($input['smtp_from_email'])) {
            $output['smtp_from_email'] = sanitize_email($input['smtp_from_email']);
        }

        // --- User Restrictions (Inverted Logic) ---
        $output['loose_user_restrictions'] = !isset($input['loose_user_restrictions']);

        // --- Elevated Emails ---
        // Only sanitize/store if wphaven-connect is NOT managing this setting
        if (!class_exists('WPHavenConnect\\Providers\\SettingsServiceProvider')) {
            $emails = [];
            if (isset($input['elevated_emails'])) {
                if (is_array($input['elevated_emails'])) {
                    $emails = array_map('sanitize_email', $input['elevated_emails']);
                } else {
                    $raw = is_string($input['elevated_emails']) ? $input['elevated_emails'] : '';
                    $parts = preg_split('/[\r\n,;]+/', $raw);
                    foreach ($parts as $p) {
                        $p = trim($p);
                        if (!empty($p)) {
                            $emails[] = sanitize_email($p);
                        }
                    }
                }
            }
            $output['elevated_emails'] = array_values(array_filter($emails, 'is_email'));
        }

        return $output;
    }

    /**
     * Retrieves the plugin options with defaults applied.
     *
     */
    private function getOptions(): array
    {
        $defaults = [
            'elevated_emails' => [],
            'loose_user_restrictions' => false,
            'mail_mode' => 'auto',
            'enable_svg' => true,
            'disable_xmlrpc' => true,
            'defer_scripts' => true,
            'async_scripts' => true,
            'clean_img_tags' => true,
            'enable_slug_search' => true,
            'enable_slug_column' => true,
            'disable_acf_escaping' => true,
            'remove_howdy' => true,
            'suppress_notices' => true,
            'suppress_notice_extra_strings' => '',
            'smtp_host' => 'mailpit',
            'smtp_port' => '1025',
            'smtp_from_email' => 'admin@wordpress.local',
            'smtp_from_name' => 'WordPress',
        ];

        return wp_parse_args(get_option(self::OPTION_NAME, []), $defaults);
    }

    /**
     * Generate HTML snippet indicating a constant override
     */
    private function getConstantOverrideHtml($constant_name, $is_inline = false)
    {
        $message = wp_kses_post(
            sprintf(
                __('Locked by constant: <code>%s</code>', 'embold-wordpress-tweaks'),
                $constant_name
            )
        );
        $class = 'description wph-const-override';

        if ($is_inline) {
            return '<span class="' . esc_attr($class) . '">' . $message . '</span>';
        }

        return '<p class="' . esc_attr($class) . '">' . $message . '</p>';
    }

    private function getWPHavenSettingsLink(): string
    {
        return admin_url('options-general.php?page=wphaven-connect');
    }
}