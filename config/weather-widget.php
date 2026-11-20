<?php

return [

    /**
     * API Key for Current Weather Data
     * You can get your API key from OpenWeatherMap:
     * https://openweathermap.org/price
     */
    'api_key' => env('OPENWEATHER_API_KEY', ''),

    /**
     * Default city for weather data
     */
    'city' => 'Yerevan',

    /**
     * Units for temperature and other measurements
     * Options: 'metric', 'imperial', 'standard'
     * 'metric' - Celsius (°C), 'imperial' - Fahrenheit (°F), 'standard' - Kelvin (K)
     * Default is 'metric'
     */
    'units' => 'metric',

    /**
     * Refresh (Poll) interval in minutes
     * This determines how often the weather data will be refreshed.
     * This also factors into the cache duration.
     */
    'refresh_minutes' => 10,

    /**
     * Icon settings
     * You can customize the icon set and variant used in the widget.
     * 'icon_set' can be 'fill', 'line', or 'monochrome'.
     */
    'icon_set' => 'fill',

    /**
     * Icon variant
     * Options: 'static', 'animated' (only for fill/line icons)
     */
    'icon_variant' => 'animated',

    /**
     * Language for weather data.
     * If not specified, then `app()->getLocale()` will be used.
     * Full list of supported languages here: https://openweathermap.org/forecast5#multi
     */
    'locale' => null,

    /**
     * Forecast shown under the current weather.
     * Options: 'hourly', 'daily', 'both'
     * Users can switch it in the widget; their choice is remembered.
     */
    'forecast_view' => 'hourly',

    /**
     * Show the gear button that lets each user change units, refresh interval and icons.
     * Choices are saved per user (in the cache) and override the values above.
     * Set to false to always use the values from this file.
     */
    'user_settings' => true,

    /**
     * Let users change the location in the settings window: search for any place (OpenWeatherMap
     * geocoding, included in the free plan) or use their browser's location (needs HTTPS).
     * Set to false to always use the 'city' above.
     */
    'location_search' => true,

    /**
     * WeatherWidgets only: the most weather cards one user can have on the dashboard.
     * Each card fetches its own data, so this also limits API usage.
     */
    'max_widgets' => 6,

    /**
     * Timeouts, in seconds, for requests to OpenWeatherMap.
     * The widget is rendered together with the page, so the page waits for these requests.
     * 'connect_timeout' is the time allowed to reach the service,
     * 'timeout' is the time allowed for the whole request.
     */
    'connect_timeout' => 3,
    'timeout' => 5,

    /**
     * How long, in seconds, a failed request is remembered.
     * Until then the widget shows its "not available" message without asking again,
     * so an outage does not slow down every page load. Set to 0 to ask every time.
     */
    'failure_cache_seconds' => 60,
];
