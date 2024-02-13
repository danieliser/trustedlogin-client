<?php
/**
 * Class Utils
 *
 * @package GravityView\TrustedLogin\Client
 *
 * @copyright 2024 Katz Web Services, Inc.
 */
namespace TrustedLogin;

class Utils {

	/**
	 * Wrapper around {@see get_transient()}. Transient is stored as an option {@see self::set_transient()} in order to avoid object caching issues.
	 * Raw SQL query (taken from WordPress core) is used in order to avoid object caching issues, such as with the Redis Object Cache plugin.
	 *
	 * @since 1.7.0
	 *
	 * @param string $transient Transient name.
	 *
	 * @return mixed|false Transient value or false if not set.
	 */
	public static function get_transient( $transient ) {
		global $wpdb;

		if ( ! is_string( $transient ) ) {
			return false;
		}

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$pre = apply_filters( "pre_transient_{$transient}", false, $transient );

		if ( false !== $pre ) {
			return $pre;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s LIMIT 1", $transient ) );

		if ( ! is_object( $row ) ) {
			return false;
		}

		$data = maybe_unserialize( $row->option_value );

		$value = self::retrieve_value_and_maybe_expire_transient( $transient, $data );

		return apply_filters( "transient_{$transient}", $value, $transient );
	}

	/**
	 * Wrapper around {@see set_transient()}. Transient is stored as an option in order to avoid object caching issues.
	 * Raw SQL query (taken from WordPress core) is used in order to avoid object caching issues, such as with the Redis Object Cache plugin.
	 *
	 * @since 1.7.0
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration (optional) Time until expiration in seconds. Default: 0 (no expiration).
	 *
	 * @return bool True if the value was set, false otherwise.
	 */
	public static function set_transient( $transient, $value, $expiration = 0 ) {
		global $wpdb;

		if ( ! is_string( $transient ) ) {
			return false;
		}

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		wp_protect_special_option( $transient );

		$expiration = (int) $expiration;

		$value = apply_filters( "pre_set_transient_{$transient}", $value, $expiration, $transient );

		$expiration = apply_filters( "expiration_of_transient_{$transient}", $expiration, $value, $transient );

		$data = self::format_transient_data( $value, $expiration );

		// Insert or update the option.
		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", $transient, maybe_serialize( $data ), true ) );

		if ( $result ) {
			do_action( "set_transient_{$transient}", $data['value'], $data['expiration'], $transient );
			do_action( 'setted_transient', $transient, $data['value'], $data['expiration'] );
		}

		return $result;
	}

	/**
	 * Retrieves a value from the transient data and conditionally deletes transient if expired.
	 *
	 * @since 1.7.0
	 *
	 * @param string $transient      Transient name.
	 * @param mixed  $transient_data Transient data as stored in the database (unserialized).
	 *
	 * @return false|mixed
	 */
	private static function retrieve_value_and_maybe_expire_transient( $transient, $transient_data ) {

		if ( ! is_array( $transient_data ) ) {
			return false;
		}

		// If the transient lacks an expiration time or value, it's not a valid transient.
		if ( ! array_key_exists( 'expiration', $transient_data ) || ! array_key_exists( 'value', $transient_data ) ) {
			return false;
		}

		// If the transient has a non-zero expiration and has expired, delete it and return false.
		if ( 0 !== ( isset( $transient_data['expiration'] ) ? $transient_data['expiration'] : 0 ) && time() > $transient_data['expiration'] ) {
			delete_option( $transient );

			return false;
		}

		return $transient_data['value'];
	}

	/**
	 * Formats transient data for storage in the database.
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $value      Transient value.
	 * @param int   $expiration (optional) Time until expiration in seconds. Default: 0 (no expiration).
	 *
	 * @return array
	 */
	private static function format_transient_data( $value, $expiration = 0 ) {
		return array(
			'expiration' => 0 === $expiration ? $expiration : time() + $expiration,
			'value'      => $value,
		);
	}

	/**
	 * Returns the HTTP user agent, sanitized.
	 *
	 * @param int  $max_length The maximum length of the returned user agent string.
	 * @param bool $sanitize Whether to sanitize the user agent string.
	 *
	 * @return string The user agent string, sanitized. Truncated at $max_length, if set.
	 */
	public static function get_user_agent( $max_length = 0 ) {

		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$user_agent = wp_unslash( $_SERVER['HTTP_USER_AGENT'] );

		$user_agent = sanitize_text_field( $user_agent );

		if ( ! $max_length ) {
			return $user_agent;
		}

		return substr( esc_attr( $user_agent ), 0, (int) $max_length );
	}

	/**
	 * Returns the IP address of the requester
	 *
	 * @since 1.7.1 Moved from SecurityChecks class to Utils class.
	 *
	 * @return null|string Returns null if REMOTE_ADDR isn't set, string IP address otherwise.
	 */
	public static function get_ip() {

		if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
			return null;
		}

		$ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );

		$ip = trim( $ip );

		$ip = sanitize_text_field( $ip );

		if ( ! defined( 'TL_DOING_TESTS' ) ) {
			$ip = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_NO_PRIV_RANGE );
		}

		return (string) $ip;
	}
}
