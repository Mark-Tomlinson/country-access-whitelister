<?php
/**
 * Country Access Whitelist Loader
 *
 * @package		WordPress
 * @subpackage	Security\CountryWhitelist
 * @since		2.0
 *
 * @wordpress-plugin
 * Plugin Name:	Country Access Whitelist
 * Description:	Blocks website access based on visitor country of origin using multiple geolocation
 *				APIs with automatic fallback. Maintains aggregate statistics of visits per country
 *				and allows administrators to block specific countries while protecting admin access.
 * Version:		2.0
 * Author:		Mark Tomlinson and Anthropic Claude
 * License:		GPLv2 or later
 * License URI:	https://www.gnu.org/licenses/gpl-2.0.html

 */

if (!defined('ABSPATH')) exit;

// Define plugin constants
define('COUNTRY_WHITELIST_VERSION', '2.0');
define('COUNTRY_WHITELIST_FOLDER', '/__country-access-whitelist');
define('COUNTRY_WHITELIST_PATH', __DIR__ . COUNTRY_WHITELIST_FOLDER);
define('COUNTRY_WHITELIST_OPTIONS', array(
    'allowed_countries' => 'country_whitelist_allowed_countries',
    'admin_country' => 'country_whitelist_admin_country'
));

// Load the database handler and geo provider base class
require_once COUNTRY_WHITELIST_PATH . '/class-country-whitelist-db.php';
require_once COUNTRY_WHITELIST_PATH . '/class-country-geo-provider.php';

/**
 * Registers geolocation API providers for country detection.
 * Providers are tried in the order registered; if one fails, the next is tried.
 *
 * To add a new provider:
 *   1. Create class-country-geo-provider-{name}.php extending CountryGeoProvider
 *   2. Implement parse_response() to extract the country code from that API's JSON
 *   3. Add a require_once and register() call below
 */
function country_whitelist_register_geo_providers() {
	// country.is — Free, no key required, no rate limit
	require_once COUNTRY_WHITELIST_PATH . '/class-country-geo-provider-countryis.php';
	CountryGeoProvider::register(new CountryGeoProviderCountryIs());

	// ip-api.com — Free for non-commercial use, 45 req/min
	require_once COUNTRY_WHITELIST_PATH . '/class-country-geo-provider-ipapi.php';
	CountryGeoProvider::register(new CountryGeoProviderIpApi());
}

function country_whitelist_update() {
    $blocked = get_option('country_whitelist_blocked_countries', array());
    if (!empty($blocked)) {
        // Get all country codes from your countries CSV
        $all_countries = array();
        // Load all country codes from CSV
        $csv_file = COUNTRY_WHITELIST_PATH . '/assets/country-access-whitelist-countries.csv';
        if (file_exists($csv_file)) {
            $handle = fopen($csv_file, 'r');
            if ($handle !== false) {
                while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                    if (isset($data[0])) {
                        $all_countries[] = $data[0];
                    }
                }
                fclose($handle);
            }
        }
        
        // Convert blocked list to allowed list by finding all non-blocked countries
        $allowed = array_diff($all_countries, $blocked);
        update_option('country_whitelist_allowed_countries', $allowed);
        
        // Keep the old option for backward compatibility
        // but don't use it anymore
    }
}

/**
 * Provides global access to the database handler instance
 * @return	CountryWhitelistDB	Database handler instance
 */
function country_whitelist_db() {
	return CountryWhitelistDB::get_instance();
}

// Initialize database if needed
$db = country_whitelist_db();
if (!$db->table_exists()) {
	$db->initialize_table();
} else {
	country_whitelist_update();
}

/**
 * Determines if an IP address is from a private network
 * @param	string	$ip	IP address to check
 * @return	boolean		True if IP is internal/private
 */
function is_internal_ip($ip) {
	// Check if it's a valid IP at all (v4 or v6)
	if (!filter_var($ip, FILTER_VALIDATE_IP)) {
		return true;	// Invalid IP format, treat as internal
	}
	// For both IPv4 and IPv6, check if it's not a public address
	return !filter_var($ip, 
		FILTER_VALIDATE_IP, 
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

// Get visitor's IP
$visitor_ip = !empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

// Only load frontend blocking for public IPs
if (!is_internal_ip($visitor_ip)) {
	country_whitelist_register_geo_providers();
	require_once COUNTRY_WHITELIST_PATH . '/class-country-whitelist.php';
	CountryWhitelist::get_instance($visitor_ip);
}

// Load admin functionality in admin area regardless of IP
if (is_admin()) {
	require_once COUNTRY_WHITELIST_PATH . '/class-country-whitelist-admin.php';
	new CountryWhitelistAdmin();
}