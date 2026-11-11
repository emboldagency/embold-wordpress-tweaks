<?php

namespace App\Utilities;

class Environment {
	private const ALLOWED_ENVS          = [ 'development', 'staging', 'production' ];
	private static ?string $environment = null;

	public static function get(): string {
		if ( self::$environment !== null ) {
			return self::$environment;
		}

		$env = self::configured() ?? self::inferFromHost();

		self::$environment = $env;
		return self::$environment;
	}

	public static function isProduction(): bool {
		return self::get() === 'production';
	}

	/**
	 * The first valid WP_ENVIRONMENT_TYPE or legacy WP_ENV, as a constant or
	 * environment variable. Read directly: wp_get_environment_type() ignores
	 * WP_ENV and reports its own 'production' default like a real setting.
	 */
	private static function configured(): ?string {
		foreach ( [ 'WP_ENVIRONMENT_TYPE', 'WP_ENV' ] as $name ) {
			$value = defined( $name ) ? constant( $name ) : getenv( $name );
			$env   = $value === 'local' ? 'development' : $value;

			if ( in_array( $env, self::ALLOWED_ENVS, true ) ) {
				return $env;
			}
		}

		return null;
	}

	/**
	 * Best guess from the site's own home URL, defaulting to production.
	 * The request Host header is never used, since a catch-all vhost would
	 * let any client pick the environment.
	 */
	private static function inferFromHost(): string {
		$host = self::getHost();

		if ( $host === '' ) {
			return 'production';
		}

		if ( $host === 'localhost' || self::endsWithAny( $host, [ '.local', '.localhost', '.embold.dev' ] ) ) {
			return 'development';
		}

		if ( str_starts_with( $host, 'staging.' ) || self::endsWithAny( $host, [ '.embold.net', '.wphaven.dev' ] ) ) {
			return 'staging';
		}

		return 'production';
	}

	private static function getHost(): string {
		if ( ! function_exists( 'get_option' ) ) {
			return '';
		}

		$host = (string) wp_parse_url( (string) get_option( 'home', '' ), PHP_URL_HOST );

		return trim( strtolower( $host ) );
	}

	private static function endsWithAny( string $host, array $suffixes ): bool {
		foreach ( $suffixes as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		return false;
	}
}
