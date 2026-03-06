<?php
/**
 * Country Geo Provider - ip-api.com
 *
 * Free for non-commercial use. No API key required.
 * Rate limit: 45 requests/minute on free tier.
 * http://ip-api.com/docs/api:json
 *
 * Response: {"status": "success", "countryCode": "US", ...}
 *
 * NOTE: Free tier requires HTTP (not HTTPS). HTTPS requires a paid plan.
 *
 * @package		WordPress
 * @subpackage	Security\CountryWhitelist
 * @since		2.0
 */

if (!defined('ABSPATH')) exit;

class CountryGeoProviderIpApi extends CountryGeoProvider {

	protected $name    = 'ip-api.com';
	protected $api_url = 'http://ip-api.com/json/';

	/**
	 * Only request the countryCode field to minimize response size.
	 */
	protected function build_url($ip) {
		return $this->api_url . $ip . '?fields=status,countryCode';
	}

	protected function parse_response($data) {
		if (!isset($data->status) || $data->status !== 'success') {
			return false;
		}
		return isset($data->countryCode) ? $data->countryCode : false;
	}
}
