<?php

namespace App\Services;

use App\Utilities\Environment;
use WP_Error;

class DisableMailService
{
    private const OPTION_NAME = 'embold_tweaks_options';

    public function register(): void
    {
        add_action('plugins_loaded', [$this, 'maybeControlMail']);
    }

    /**
     * Determine and apply effective mail behavior.
     */
    public function maybeControlMail(): void
    {
        $resolution = $this->resolveMailMode();

        // If another plugin (wphaven-connect) is managing mail, do nothing
        if ($resolution['locked_by'] === 'wphaven_connect') {
            return;
        }

        switch ($resolution['mode']) {
            case 'block_all':
                $this->blockMail();
                break;

            case 'smtp_override':
                $this->configureSMTP();
                break;

            case 'allow_all':
            case 'no_override':
            default:
                // No intervention needed
                break;
        }
    }

    /**
     * Block all mail in this request.
     */
    private function blockMail(): void
    {
        add_filter('pre_wp_mail', function ($result, $args = []) {
            error_log('[embold-wordpress-tweaks] Mail blocked by plugin settings.');

            $error = new WP_Error('embold_mail_blocked', 'Mail sending is blocked by embold-wordpress-tweaks.');
            do_action('wp_mail_failed', $error);

            return false;
        }, 9999, 2);

        add_filter('wp_mail', function ($return) {
            return false;
        }, 9999);

        // Deactivate conflicting mail plugins (mirrors previous behavior)
        add_action('admin_init', [$this, 'disableKnownMailPlugins']);
    }

    /**
     * Configure SMTP for email delivery.
     */
    private function configureSMTP(): void
    {
        $self = $this;

        // Override From Addresses
        add_filter('wp_mail_from', function () use ($self) {
            return $self->getOption('smtp_from_email', 'admin@wordpress.local');
        }, 9999);

        add_filter('wp_mail_from_name', function () use ($self) {
            return $self->getOption('smtp_from_name', 'WordPress');
        }, 9999);

        // Configure PHPMailer
        add_action('phpmailer_init', function ($phpmailer) use ($self) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $self->getOption('smtp_host', 'mailpit');
            $phpmailer->Port = (int) $self->getOption('smtp_port', 1025);
            $phpmailer->SMTPAuth = false;
            $phpmailer->SMTPSecure = '';
        }, 9999);

        // Disable conflicting plugins
        $this->disableKnownMailPlugins();
    }

    public function disableKnownMailPlugins(): void
    {
        $plugins_to_disable = [
            'mailgun/mailgun.php',
            'sparkpost/sparkpost.php',
        ];

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach ($plugins_to_disable as $plugin_to_disable) {
            deactivate_plugins($plugin_to_disable);
        }
    }

    /**
     * Get an option value with fallback to constant.
     */
    public function getOption(string $key, $default = null)
    {
        $const_map = [
            'smtp_host' => 'EMBOLD_SMTP_HOST',
            'smtp_port' => 'EMBOLD_SMTP_PORT',
            'smtp_from_email' => 'EMBOLD_SMTP_FROM_EMAIL',
            'smtp_from_name' => 'EMBOLD_SMTP_FROM_NAME',
        ];

        // Check for constant first
        if (isset($const_map[$key]) && defined($const_map[$key])) {
            return constant($const_map[$key]);
        }

        // Check option
        $opts = get_option(self::OPTION_NAME, []);
        if (isset($opts[$key])) {
            return $opts[$key];
        }

        return $default;
    }

    /**
     * Resolve effective mail mode and metadata.
     *
     * @return array{mode:string, locked_by:?string, source:string}
     */
    public function resolveMailMode(): array
    {

        // Constants
        if (defined('DISABLE_MAIL')) {
            return [
                'mode' => constant('DISABLE_MAIL') ? 'block_all' : 'no_override',
                'locked_by' => 'DISABLE_MAIL',
                'source' => 'constant',
            ];
        }

        // Plugin option
        $opts = get_option(self::OPTION_NAME, []);
        if (!empty($opts['mail_mode'])) {
            $mode = $opts['mail_mode'];
            if (in_array($mode, ['auto', 'block_all', 'allow_all', 'smtp_override'], true)) {

                if ($mode === 'auto') {
                    return $this->resolveEnvironmentDefault();
                }

                return [
                    'mode' => $mode,
                    'locked_by' => null,
                    'source' => 'option',
                ];
            }
        }

        // Default to environment based
        return $this->resolveEnvironmentDefault();
    }

    /**
     * Environment-based default: block mail on non-production.
     */
    private function resolveEnvironmentDefault(): array
    {
        $env = Environment::get();

        // 1. Check for *.embold.dev domain in non-production -> SMTP Override
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($env !== 'production' && (substr($host, -11) === '.embold.dev' || $host === 'embold.dev')) {
            return [
                'mode' => 'smtp_override',
                'locked_by' => null,
                'source' => 'environment (embold.dev)',
            ];
        }

        if (in_array($env, ['development', 'staging'], true)) {
            return [
                'mode' => 'block_all',
                'locked_by' => null,
                'source' => 'environment',
            ];
        }

        return [
            'mode' => 'no_override',
            'locked_by' => null,
            'source' => 'environment',
        ];
    }
}
