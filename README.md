# Lunar Phase Stock Widget

A WordPress plugin that displays the current lunar phase with a bundled moon image, phase name, moonrise, moonset, and illumination using WeatherAPI astronomy data.

This repository is organized for GitHub version control and WordPress plugin development.

## Features

- Current lunar phase name
- Moonrise and moonset times
- Illumination percentage
- Bundled moon phase images
- Shortcode support: `[lunar_phase_widget]`
- Dynamic Gutenberg block
- Default settings page for API key, location, title, and time format
- Cached API responses for better performance

## Repository Layout

```text
lunar_phase_plugin/
├── .github/
│   └── workflows/
│       └── release-zip.yml
├── docs/
│   ├── DEVELOPMENT.md
│   ├── GIT-SETUP.md
│   ├── RELEASE-CHECKLIST.md
│   └── WORDPRESS-ORG-NOTES.md
├── assets/                  # Created by the plugin package when present
├── CREDITS.txt
├── lunar-phase-stock-widget.php
├── readme.txt               # WordPress.org readme
├── LICENSE
├── .gitignore
└── README.md                # GitHub repo documentation
```

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A WeatherAPI key

## Local Development

1. Copy this plugin folder into your local WordPress install under `wp-content/plugins/`.
2. Activate **Lunar Phase Stock Widget** in the WordPress admin.
3. Go to **Settings > Lunar Phase Widget**.
4. Add your WeatherAPI key and default location.

## GitHub Workflow

This repo includes a sample GitHub Actions workflow that creates a ZIP artifact from the repository contents when you push a tag beginning with `v`.

## Support

- Website: https://celestialwebdevelopment.com/
- Plugin page: https://celestialwebdevelopment.com/lunar-phase-plugin/

## License

GPL-2.0-or-later
