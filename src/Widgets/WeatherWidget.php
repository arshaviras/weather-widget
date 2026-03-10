<?php

namespace Arshaviras\WeatherWidget\Widgets;

use Filament\Widgets\Widget;
use Arshaviras\WeatherWidget\Services\OpenWeatherClient;

class WeatherWidget extends Widget
{
    public string $view = 'weather-widget::widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $city = config('weather-widget.city');
        $units = config('weather-widget.units', 'metric');
        $apiKey = config('weather-widget.api_key');
        $refreshMinutes = (int) config('weather-widget.refresh_minutes', 30);

        $client = app(OpenWeatherClient::class);

        $current = $client->current($city, $units, $apiKey, $refreshMinutes * 60);
        $forecast = $client->forecast($city, $units, $apiKey, $refreshMinutes * 60);

        if (!$current) {
            return [
                'error' => 'Failed to fetch weather data. Check API key/city.',
            ];
        }

        $isDay = str_ends_with($current->iconCode, 'd');

        $timezoneOffset = $current->timezone ?? 0;

        $forecastItems = collect($forecast)
            ->take(10)
            ->map(function ($item) use ($timezoneOffset) {
                $dt = \Carbon\Carbon::parse($item->datetime, 'UTC')->addSeconds($timezoneOffset);
                $isDay = str_ends_with($item->iconCode, 'd');
                return [
                    'time' => $dt->format('H:i'),
                    'dt_unix' => $dt->timestamp,
                    'forecast_icon' => $item->conditionEnum
                        ? 'weather-' . ($isDay
                            ? $item->conditionEnum->dayIcon()
                            : $item->conditionEnum->nightIcon())
                        : 'weather-not-available',
                    'forecast_condition' => $item->conditionEnum?->label() ?? 'N/A',
                    'temp' => round($item->temperature),
                ];
            })
            ->values();

        $sunrise = $current->sunrise ? \Carbon\Carbon::createFromTimestamp($current->sunrise + $timezoneOffset) : null;
        $sunset = $current->sunset ? \Carbon\Carbon::createFromTimestamp($current->sunset + $timezoneOffset) : null;

        $sunriseItem = null;
        $sunsetItem = null;

        if ($sunrise) {
            $sunriseItem = [
                'time' => $sunrise->format('H:i'),
                'dt_unix' => $sunrise->timestamp,
                'forecast_icon' => 'weather-sunrise',
                'forecast_condition' => __('weather-widget::weather.labels.sunrise'),
                'temp' => null,
                'is_sunrise' => true,
                'is_sunset' => false,
            ];
        }
        if ($sunset) {
            $sunsetItem = [
                'time' => $sunset->format('H:i'),
                'dt_unix' => $sunset->timestamp,
                'forecast_icon' => 'weather-sunset',
                'forecast_condition' => __('weather-widget::weather.labels.sunset'),
                'temp' => null,
                'is_sunrise' => false,
                'is_sunset' => true,
            ];
        }

        $allItems = $forecastItems;
        if ($sunriseItem) $allItems = $allItems->push($sunriseItem);
        if ($sunsetItem) $allItems = $allItems->push($sunsetItem);

        $allItems = $allItems
            ->sortBy('dt_unix')
            ->values();

        $now = now()->timestamp;
        $allItems = $allItems->filter(fn($item) => $item['dt_unix'] >= $now)->values();

        $allItems = $allItems->take(10);

        return [
            'city' => $current->city,
            'temperature' => round($current->temperature),
            'temp_symbol' => $current->unitEnum->tempSymbol(),
            'condition' => $current->conditionEnum?->label() ?? 'N/A',
            'wind_speed' => $current->wind_speed,
            'wind_deg' => $current->wind_deg,
            'wind_unit' => $current->unitEnum->windUnit(),
            'humidity' => $current->humidity,
            'pressure' => $current->pressure,
            'temp_min' => round($current->raw['main']['temp_min'] ?? 0),
            'temp_max' => round($current->raw['main']['temp_max'] ?? 0),
            'feels_like' => round($current->raw['main']['feels_like'] ?? 0),
            'visibility' => $current->raw['visibility'] ?? null, // meters
            'clouds' => $current->raw['clouds']['all'] ?? null, // percent
            'rain' => $current->raw['rain']['1h'] ?? 0, // mm last hour
            'pollInterval' => $refreshMinutes * 60 . 's',
            'current_icon' => $current->conditionEnum
                ? 'weather-' . ($isDay
                    ? $current->conditionEnum->dayIcon()
                    : $current->conditionEnum->nightIcon())
                : 'weather-not-available',
            'hourlyForecast' => $allItems,
        ];
    }
}
