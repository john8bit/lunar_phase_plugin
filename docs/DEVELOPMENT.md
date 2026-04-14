# Development Notes

## Main plugin file

- `celestial-lunar-phase.php`

## Primary areas of functionality

- WordPress plugin header and bootstrap
- Admin settings page
- Shortcode rendering
- Dynamic Gutenberg block registration
- WeatherAPI request handling and caching
- Front-end widget markup and styling

## Before making changes

1. Test the plugin in a staging WordPress instance.
2. Keep the plugin header, version number, and readme stable tag in sync.
3. Preserve backward compatibility for shortcode usage where possible.
4. Re-test settings save, shortcode output, block output, and external API responses.

## Versioning

Update the following together when making a release:

- `Version:` in `celestial-lunar-phase.php`
- `const VERSION`
- `Stable tag:` in `readme.txt`
- `Changelog` in `readme.txt`
