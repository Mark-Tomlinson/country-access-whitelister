<?php
/**
 * Country Geo Provider - country.is
 *
 * Free, open-source geolocation API. No API key required.
 * https://country.is/
 *
 * Response: {"country": "US", "ip": "8.8.8.8"}
 *
 * @package		WordPress
 * @subpackage	Security\CountryWhitelist
 * @since		2.0
 */

if (!defined('ABSPATH')) exit;

class CountryGeoProviderCountryIs extends CountryGeoProvider {

	protected $name    = 'country.is';
	protected $api_url = 'https://api.country.is/';

	protected function parse_response($data) {
		return isset($data->country) ? $data->country : false;
	}
}
