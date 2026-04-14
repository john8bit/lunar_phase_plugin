# Release Checklist

## Before tagging a release

- [ ] Plugin activates without PHP warnings or fatal errors
- [ ] Settings page loads and saves correctly
- [ ] Shortcode renders correctly
- [ ] Gutenberg block renders correctly
- [ ] WeatherAPI request succeeds with a valid API key
- [ ] Cached response logic still works
- [ ] Plugin version updated in both PHP file and readme
- [ ] Changelog updated
- [ ] Screenshots and assets match current plugin behavior
- [ ] WordPress Plugin Check run successfully

## Build a ZIP locally

From the repository root:

```bash
cd "/g/My Drive/CelestialWebDevelopment/git_repo/lunar_phase_plugin"
mkdir -p ../build
powershell -Command "Compress-Archive -Path * -DestinationPath ../build/lunar-phase-stock-widget.zip -Force"
```

For WordPress.org submissions, confirm the ZIP contains the plugin files at the root level expected by WordPress.
