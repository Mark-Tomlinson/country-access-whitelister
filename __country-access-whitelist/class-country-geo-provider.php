<?php
/**
 * Country Geo Provider - Abstract Base Class
 *
 * Manages geolocation provider fallback chain with shared caching.
 * Subclasses implement the API-specific fetch and parse logic.
 *
 * @package		WordPress
 * @subpackage	Security\CountryWhitelist
 * @since		2.0
 */

if (!defined('ABSPATH')) exit;

abstract class CountryGeoProvider {

	/**
	 * Registered provider instances in priority order
	 * @var	CountryGeoProvider[]
	 */
	private static $providers = array();

	/**
	 * Human-readable provider name for logging
	 * @var	string
	 */
	protected $name;

	/**
	 * Base URL for this provider's API
	 * @var	string
	 */
	protected $api_url;

	/**
	 * Request timeout in seconds
	 * @var	float
	 */
	protected $timeout = 3.0;

	/**
	 * Register a provider in the fallback chain.
	 * Providers are tried in the order they are registered.
	 *
	 * @param	CountryGeoProvider	$provider
	 */
	public static function register(CountryGeoProvider $provider) {
		self::$providers[] = $provider;
		self::debug(sprintf('Registered provider: %s', $provider->name));
	}

	/**
	 * Look up a country code for an IP, trying each provider in order.
	 * Returns a cached result if available; caches successful lookups for 24 hours.
	 *
	 * @param	string		$ip		IP address to look up
	 * @return	string|false		Two-letter country code or false on total failure
	 */
	public static function get_country($ip) {
		// Check cache first
		$cache_key = 'country_whitelist_' . md5($ip);
		$cached = get_transient($cache_key);
		if ($cached !== false) {
			// Legacy transients stored the full API response object;
			// extract the country code string if we hit one of those.
			if (is_object($cached) && isset($cached->country)) {
				$cached = strtoupper($cached->country);
				set_transient($cache_key, $cached, DAY_IN_SECONDS);
				self::debug(sprintf('Cache hit for %s: %s (migrated from legacy object)', $ip, $cached));
			} else {
				self::debug(sprintf('Cache hit for %s: %s', $ip, $cached));
			}
			return $cached;
		}

		self::debug(sprintf('Cache miss for %s, querying %d provider(s)', $ip, count(self::$providers)));

		// Try each provider in order
		foreach (self::$providers as $provider) {
			$country = $provider->fetch_country($ip);
			if ($country !== false) {
				self::debug(sprintf('%s returned %s for %s', $provider->name, $country, $ip));
				set_transient($cache_key, $country, DAY_IN_SECONDS);
				return $country;
			}
			self::debug(sprintf('%s failed for %s, trying next', $provider->name, $ip));
		}

		self::debug(sprintf('All providers failed for %s — allowing access (fail-open)', $ip));
		return false;
	}

	/**
	 * Make the API request and extract the country code.
	 * Returns a two-letter country code or false on failure.
	 *
	 * @param	string		$ip
	 * @return	string|false
	 */
	public function fetch_country($ip) {
		$args = array(
			'timeout'     => $this->timeout,
			'httpversion' => '1.1',
			'headers'     => array('Accept' => 'application/json'),
		);

		$response = wp_remote_get($this->build_url($ip), $args);

		if (is_wp_error($response)) {
			$this->log_error(
				sprintf('API Error: %s', $response->get_error_message()),
				$ip
			);
			return false;
		}

		$response_code = wp_remote_retrieve_response_code($response);
		if ($response_code !== 200) {
			$this->log_error(
				sprintf('API returned status code: %d', $response_code),
				$ip
			);
			return false;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);

		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->log_error(
				sprintf('Invalid JSON response: %s', json_last_error_msg()),
				$ip
			);
			return false;
		}

		$country = $this->parse_response($data);
		if ($country === false) {
			$this->log_error('Response missing country code', $ip);
			return false;
		}

		return strtoupper($country);
	}

	/**
	 * Build the full API URL for the given IP.
	 * Override if the provider uses a different URL pattern.
	 *
	 * @param	string	$ip
	 * @return	string
	 */
	protected function build_url($ip) {
		return $this->api_url . $ip;
	}

	/**
	 * Extract the two-letter country code from the decoded JSON response.
	 *
	 * @param	object	$data	Decoded JSON response
	 * @return	string|false	Country code or false if not found
	 */
	abstract protected function parse_response($data);

	/**
	 * Log an error identifying which provider failed.
	 *
	 * @param	string	$message
	 * @param	string	$ip
	 */
	protected function log_error($message, $ip) {
		error_log(sprintf(
			'CountryGeoProvider[%s] - IP: %s - URI: %s - %s',
			$this->name,
			$ip,
			$_SERVER['REQUEST_URI'] ?? 'unknown',
			$message
		));
	}

	/**
	 * Write a debug trace line when COUNTRY_WHITELIST_DEBUG is true.
	 * Enable by adding: define('COUNTRY_WHITELIST_DEBUG', true); to wp-config.php
	 *
	 * @param	string	$message
	 */
	protected static function debug($message) {
		if (defined('COUNTRY_WHITELIST_DEBUG') && COUNTRY_WHITELIST_DEBUG) {
			error_log('CountryWhitelist DEBUG: ' . $message);
		}
	}
}
