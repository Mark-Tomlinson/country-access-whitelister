<?php
/**
 * Country Access Whitelist
 *
 * Core class that handles visitor country detection and access control.
 * Uses the country.is API for geolocation and maintains visitor statistics.
 *
 * @package		WordPress
 * @subpackage	Security\CountryWhitelist
 * @since		1.10
 */

if (!defined('ABSPATH')) exit;

class CountryWhitelist {
	/**
	 * The visitor's ip address
	 * @var	string
	 */
	private $visitor_ip;

	/**
	 * Blocking message seen when access is denied
	 * @var	string
	 */
	private $block_message = 'Sorry, you are not allowed to access this page.';

	/**
	 * Static array to track processed IPs within a request
	 * @var	array
	 */
	private static $processed_ips = array();

	/**
	 * Singleton instance
	 * @var	CountryWhitelist
	 */
	private static $instance = null;

	/**
	 * Initialize the plugin by setting up WordPress hooks
	 * 
	 * @param	string	$ip	Visitor's IP address
	 */
	private function __construct($ip) {
		$this->visitor_ip = $ip;
		
		// Initialize options if in admin area
		if (is_admin()) {
			$this->init_admin_settings();
		}
		
		// Add visitor check to init hook
		add_action('init', array($this, 'check_visitor'));
	}

	/**
	 * Get singleton instance
	 *
	 * @param	string	$ip	IP address for initialization
	 * @return	CountryWhitelist
	 */
	public static function get_instance($ip = null) {
		if (self::$instance === null) {
			self::$instance = new self($ip);
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin options
	 */
	private function init_admin_settings() {
		// Initialize the blocked countries option if it doesn't exist
		if (get_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries']) === false) {
			add_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries'], array());
		}
	}

	/**
	 * Retrieves the list of allowed countries from WordPress options.
	 * @return array List of allowed country codes
	 */
	private function get_allowed_countries() {
		$allowed = get_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries']);
		if (!is_array($allowed)) {
			$allowed = array();
			update_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries'], $allowed);
		}
		
		// Always include admin country for safety
		$admin_country = get_option(COUNTRY_WHITELIST_OPTIONS['admin_country']);
		if ($admin_country && !in_array($admin_country, $allowed)) {
			$allowed[] = $admin_country;
		}
		
		return $allowed;
	}

	/**
	 * Checks visitor's country and blocks access if necessary.
	 * This is the single place where country checking occurs.
	 */
	public function check_visitor() {
		// Skip if we've already processed this IP in this request
		if (in_array($this->visitor_ip, self::$processed_ips)) {
			return;
		}
		self::$processed_ips[] = $this->visitor_ip;

		$country_code = CountryGeoProvider::get_country($this->visitor_ip);

		// If all providers fail, allow access (fail-open)
		if (!$country_code) {
			return;
		}

		// Update visit statistics using database handler
		COUNTRY_WHITELIST_db()->update_visit_stats($country_code);

		// If we're an admin user and admin country isn't set, set it now
		if (current_user_can('manage_options')) {
			if (!get_option(COUNTRY_WHITELIST_OPTIONS['admin_country'])) {
				add_option(COUNTRY_WHITELIST_OPTIONS['admin_country'], $country_code);
				// Ensure it's also in the allowed list
				$allowed = get_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries'], array());
				$allowed[] = $country_code;
				update_option(COUNTRY_WHITELIST_OPTIONS['allowed_countries'], $allowed);
			}
			return; // Don't block admin no matter what
		}

		// Check if visitor's country is allowed
		$allowed_countries = $this->get_allowed_countries();
		if (!in_array($country_code, $allowed_countries)) {
			// Update blocked visit statistics using database handler
			COUNTRY_WHITELIST_db()->update_blocked_stats($country_code);
			wp_die($this->block_message, 'Access Denied', array('response' => 403));
		}
	
	}
}