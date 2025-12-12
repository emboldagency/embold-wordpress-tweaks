<?php

namespace App\Utilities;

class Environment
{
    private const ALLOWED_ENVS = ['development', 'staging', 'production'];
    private static ?string $environment = null;

    public static function get(): string
    {
        if (self::$environment !== null) {
            return self::$environment;
        }

        $env = 'production';

        if (function_exists('wp_get_environment_type')) {
            $env = wp_get_environment_type();
        } elseif (defined('WP_ENVIRONMENT_TYPE')) {
            $env = WP_ENVIRONMENT_TYPE;
        } elseif (defined('WP_ENV')) {
            $env = WP_ENV;
        }

        // Normalize common aliases
        switch ($env) {
            case 'local':
                $env = 'development';
                break;
            case 'maintenance':
                $env = 'staging';
                break;
            default:
                break;
        }

        if (!in_array($env, self::ALLOWED_ENVS, true)) {
            $host = self::getHost();
            if (!empty($host)) {
                if (self::hostMatches($host, ['.local', 'localhost', '.embold.dev'])) {
                    $env = 'development';
                } elseif (self::hostMatches($host, ['.net', 'staging.', '.wphaven.dev'])) {
                    $env = 'staging';
                }
            }
        }

        if (!in_array($env, self::ALLOWED_ENVS, true)) {
            $env = 'production';
        }

        self::$environment = $env;
        return self::$environment;
    }

    public static function isProduction(): bool
    {
        return self::get() === 'production';
    }

    private static function getHost(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        return trim(strtolower($host));
    }

    private static function hostMatches(string $host, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern[0] === '.' && str_ends_with($host, $pattern)) {
                return true;
            }
            if ($pattern[0] !== '.' && str_contains($host, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
