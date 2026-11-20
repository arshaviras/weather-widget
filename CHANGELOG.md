# Changelog

All notable changes to `weather-widget` will be documented in this file.
Release notes for every version are on the [GitHub releases page](https://github.com/arshaviras/weather-widget/releases).

## 2.0.0 - 2026-11-20

### Added
- Daily forecast, with an **Hourly / Daily / Both** switch remembered per user
- Settings window (gear button) for units, refresh interval, icon set and variant, and width, saved per user
- `columnSpan` widget option; the layout adapts to the widget's own width
- Location in the settings window: search for any place (OpenWeatherMap geocoding) or **Use my location**; the widget shows the location as text
- Chance of precipitation in the hourly and daily forecast
- Day name on the first hourly slot after midnight
- "Updated HH:MM" note (when the data was fetched, in the viewer's own time) with a refresh button, rate-limited per widget
- Loading overlay while the widget updates
- Wind direction as compass text (N, NE, …)
- Several widgets with their own options via `WeatherWidget::make([...])`
- `WeatherWidgets`: each user can duplicate and remove their own weather cards (at least one, at most `max_widgets`)
- Test suite and GitHub Actions (PHP 8.2–8.4, Laravel 12–13, Filament 4–5, Pint, `composer audit`)

### Fixed
- Icons from different sets (fill, line, monochrome, static/animated) no longer break each other when shown on the same page
- No more `-0°`

### Changed
- Visibility, rain and pressure follow the units setting (km/mi, mm/in, hPa/inHg)
- Daily temperatures show as `12° / 6°`
- Unit labels and "N/A" moved to the translation files
- `WeatherCurrentResource`: `$wind_speed` and `$wind_deg` renamed to `$windSpeed` and `$windDegrees`
- The widget's `$view` property is now `protected`

### Removed
- Laravel 11 support
- The built-in city list and the city dropdown on the widget

## 1.7.1

- Keep the forecast slot in progress visible until it ends (#5)

## 1.7.0

- Fix forecast slots shifted by the city's UTC offset
- Keep the city selector and polling when weather is unavailable
- Spanish and more locale mappings, clearer missing-API-key message, missing `line` icons

## 1.0.0 - 2025-11-04

- Initial release
