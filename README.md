# Country Access Whitelist

## WordPress Plugin to Whitelist Country Access

Block traffic from countries that have no business on your site. Allow only the countries you choose — everyone else is denied. Useful when you only ship domestically, serve a regional audience, or want to reduce malicious traffic from countries you don't do business in.

## Features

* **Whitelist model** — allow specific countries, block all others
* **Multi-provider geolocation** — uses [country.is](https://country.is/) as primary API with [ip-api.com](http://ip-api.com/) as automatic fallback
* **Fail-open design** — if all geolocation providers are down, visitors are allowed through (availability over security)
* **24-hour IP caching** — each IP is looked up once per day via WordPress transients
* **Admin country protection** — your country is automatically detected and locked so you can never block yourself out
* **Visitor statistics** — tracks total visits and blocked visits per country with first/last visit timestamps
* **Sortable admin table** — sort by any column, filter to show only allowed or blocked countries
* **Must-Use plugin support** — can run as an MU plugin to load before all other plugins
* **Extensible provider system** — add new geolocation APIs by creating a single class file

## Installation

### Standard Plugin

Upload through WordPress `Plugins` > `Add New Plugin` > `Upload Plugin`.

The plugin is prefixed with `__` to take advantage of WordPress' alphabetical plugin loading order.

### Must-Use Plugin (Recommended)

MU plugins load before standard plugins and cannot be disabled via the admin UI.

1. Copy the `__country-access-whitelist/` folder into `wp-content/mu-plugins/`.
2. Copy `__country-access-whitelist.php` into `wp-content/mu-plugins/`.

## Adding Geolocation Providers

The plugin uses a provider chain — if one API fails, the next is tried automatically. To add a new provider:

1. Create `class-country-geo-provider-{name}.php` in the plugin folder, extending `CountryGeoProvider`.
2. Implement `parse_response()` to extract the two-letter country code from that API's JSON response.
3. Register it in `country_whitelist_register_geo_providers()` in the loader file.

See the existing `CountryGeoProviderCountryIs` and `CountryGeoProviderIpApi` classes for examples.

## Debugging

Add to `wp-config.php` to enable debug trace logging:

```php
define('COUNTRY_WHITELIST_DEBUG', true);
```

This logs provider registration, cache hits/misses, provider success/failure, and fallback activity to `debug.log`. Requires `WP_DEBUG` and `WP_DEBUG_LOG` to be enabled.

## Use At Your Own Risk

I wrote this plugin for a friend and myself. I'm making it available on GitHub if you want to use it or fork it. But don't expect support — I'm a retired guy who does this as a hobby.

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.
