=== Celestial Lunar Phase Widget ===
Contributors: johnfoo
Tags: moon phase, astronomy, moonrise, moonset, lunar phase
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display the Moon phase, illumination, moonrise, moonset, and astronomical twilight using local calculations.

== Description ==

Celestial Lunar Phase Widget helps WordPress sites display the current Moon phase in a polished astronomy card.

The plugin shows:

* Current lunar phase name
* Moonrise time
* Moonset time
* Astronomical dawn time
* Astronomical dusk time
* Illumination percentage
* Bundled Moon phase image matched to the phase
* Optional location label
* Optional calculation/source note

Version 2.2 removes the WeatherAPI dependency and calculates lunar and twilight information locally using the observing location configured in WordPress admin. It also improves setup with a location lookup tool, browser geolocation helper, and timezone dropdown.

= Key features =

* Local astronomy calculations; no API key required
* Moonrise and moonset times
* Astronomical dawn and dusk display
* Current lunar phase and illumination percentage
* Bundled Moon phase photos packaged locally
* Location lookup helper for admin setup
* Browser geolocation helper for admin setup
* Timezone dropdown to prevent typos and date-shift errors
* Latitude, longitude, timezone, and optional elevation settings
* Shortcode: `[celestial_lunar_phase_widget]`
* Gutenberg block: **Celestial Lunar Phase Widget**
* 12-hour or 24-hour time display
* Per-location and per-date caching for better performance
* Admin notice after update if location settings need to be completed

= Support =

Support and documentation are available at:
https://celestialwebdevelopment.com/lunar-phase-plugin/

== External Services ==

This plugin performs all lunar phase, moonrise, moonset, astronomical dawn, and astronomical dusk calculations locally. It does not send visitor data to an astronomy API.

The optional **Lookup Location** button on the admin settings page uses the OpenStreetMap Nominatim geocoding service to convert a place name into latitude and longitude. This lookup is only triggered by an administrator clicking the button in wp-admin. The query entered by the administrator is sent to Nominatim for geocoding. The service may receive the searched location text, your site's server IP address, and standard request metadata.

Service provider: OpenStreetMap Nominatim
Terms: https://operations.osmfoundation.org/policies/nominatim/
Privacy: https://osmfoundation.org/wiki/Privacy_Policy

The **Use My Current Location** button uses the browser's built-in geolocation prompt. Coordinates are only saved after the administrator clicks **Save Changes**.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP from **Plugins > Add New > Upload Plugin**.
2. Activate the plugin.
3. Go to **Settings > Celestial Lunar Phase Widget**.
4. Enter a city, state, address, or place name and click **Lookup Location**, or use **Use My Current Location**.
5. Verify the timezone dropdown and save changes.
6. Add the **Celestial Lunar Phase Widget** block or place `[celestial_lunar_phase_widget]` into a post, page, or widget area.

== Frequently Asked Questions ==

= Does the plugin require an API key? =

No. Version 2.2 calculates the Moon phase, moonrise, moonset, astronomical dawn, and astronomical dusk locally. No WeatherAPI key or external astronomy data service is required.

= Why do I need latitude and longitude? =

Moonrise, moonset, dawn, and dusk are location-specific. Version 2.2 uses the configured latitude, longitude, and timezone to calculate the correct local values. The settings page includes Location Lookup and Use My Current Location helpers to make this easier.

= What happens when upgrading from an older API-based version? =

After the update, WordPress admins will see a notice asking them to complete the new location settings. The widget will display a safe setup message until latitude, longitude, and timezone are saved. Use Location Lookup or Use My Current Location from the settings page.

= Why might times differ from Timeanddate.com or other services? =

Moonrise and moonset times can differ between services because of location precision, elevation, terrain/horizon assumptions, atmospheric refraction, and calculation methods.

= Does the plugin include Moon images? =

Yes. Eight Moon phase images are bundled locally inside the plugin package.

= Does the plugin include a Gutenberg block? =

Yes. The plugin includes a dynamic Gutenberg block with editor controls for location label, date, title, and display options.

== Screenshots ==

1. Frontend lunar phase widget display.
2. Plugin settings screen.

== Upgrade Notice ==

= 2.2.0 =
WeatherAPI has been removed. After updating, visit Settings > Celestial Lunar Phase Widget and use Location Lookup or Use My Current Location, then verify timezone and save settings.

= 2.1.0 =
WeatherAPI has been removed. After updating, visit Settings > Celestial Lunar Phase Widget and save latitude, longitude, and timezone for your observing location.

== Changelog ==

= 2.2.3 =
* Updated WordPress compatibility and plugin header metadata.
* Removed production debug logging flagged by the Plugin Check tool.
* Added translator context for the localized calculation credit.
* Limited directory tags and shortened the plugin description.

= 2.2.0 =
* Added Location Lookup helper using OpenStreetMap Nominatim from the admin settings page.
* Added timezone dropdown with common timezones and all IANA timezone identifiers.
* Improved setup guidance and admin notice language for upgrades from API-based versions.
* Kept local astronomy calculations only; WeatherAPI remains removed.

= 2.1.0 =
* Removed WeatherAPI mode and API key settings.
* Added local-only astronomy calculations.
* Added admin notice when latitude, longitude, or timezone settings are missing after update.
* Kept browser geolocation helper for easier setup.
* Updated branding and documentation for Celestial Web Development.

= 2.0.9 =
* Fixed local date handling for the configured observing timezone.
* Shortened astronomical twilight labels to Dawn and Dusk.

= 2.0.8 =
* Improved moonrise and moonset calculations using a SunCalc-style algorithm.
* Improved phase and illumination calculation.

= 2.0.2 =
* Added local calculation diagnostics and safer timezone handling.

= 2.0.0 =
* Added local astronomy calculation mode for moonrise and moonset.
* Changed local sun times display to astronomical dawn and astronomical dusk.
* Added latitude, longitude, timezone, and elevation settings.
* Added browser geolocation helper in admin.

= 1.1.0 =
* Added Gutenberg block support.
* Added bundled WordPress.org assets and screenshots.

= 1.0.0 =
* Initial release.
