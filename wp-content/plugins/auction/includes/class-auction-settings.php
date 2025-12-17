<?php
/**
 * Wrapper for plugin settings.
 *
 * @package Auction
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides access to persisted settings.
 */
class Auction_Settings {

	/**
	 * Cached settings.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Retrieve all settings.
	 *
	 * @return array
	 */
	public static function all(): array {
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$stored = get_option( 'auction_settings', array() );

		self::$settings = is_array( $stored ) ? $stored : array();

		return self::$settings;
	}

	/**
	 * Retrieve a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::all();

		return $settings[ $key ] ?? $default;
	}

	/**
	 * Helper to determine if a checkbox is enabled.
	 *
	 * @param string $key     Setting key.
	 * @param bool   $default Default boolean value when setting is missing.
	 *
	 * @return bool
	 */
	public static function is_enabled( string $key, bool $default = false ): bool {
		$fallback = $default ? 'yes' : 'no';

		return 'yes' === self::get( $key, $fallback );
	}
}

