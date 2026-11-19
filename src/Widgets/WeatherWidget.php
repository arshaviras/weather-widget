<?php

namespace Arshaviras\WeatherWidget\Widgets;

use Arshaviras\WeatherWidget\DTO\WeatherForecastResource;
use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Arshaviras\WeatherWidget\Enums\WeatherUnit;
use Arshaviras\WeatherWidget\Services\OpenWeatherClient;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

class WeatherWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    public string $view = 'weather-widget::widget';

    protected int | string | array $columnSpan = 'full';

    public array $data = [];

    public function mount(): void
    {
        $this->data = [
            'city' => $this->resolveCity(session('weather-widget.city')),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('city')
                    ->options(__('weather-widget::weather.cities'))
                    ->prefixIcon(Heroicon::OutlinedMapPin)
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->hiddenLabel()
                    ->live()
                    ->extraAttributes(['style' => 'min-width:200px'])
                    ->afterStateUpdated(function ($state): void {
                        $city = $this->resolveCity($state);
                        $this->data['city'] = $city;
                        session(['weather-widget.city' => $city]);
                    }),
            ])
            ->statePath('data');
    }

    /**
     * Only cities from the list (or the configured default) are accepted.
     * `$data` is a public Livewire property, so the browser can send any value.
     */
    protected function resolveCity(mixed $city): string
    {
        $default = (string) config('weather-widget.city');

        if (! is_string($city) || $city === '' || $city === $default) {
            return $default;
        }

        $cities = __('weather-widget::weather.cities');

        return is_array($cities) && array_key_exists($city, $cities) ? $city : $default;
    }

    protected function getViewData(): array
    {
        $city = $this->resolveCity($this->data['city'] ?? null);
        $units = (WeatherUnit::tryFrom((string) config('weather-widget.units', 'metric')) ?? WeatherUnit::Metric)->value;
        $apiKey = (string) config('weather-widget.api_key');
        $refreshSeconds = max(60, (int) config('weather-widget.refresh_minutes', 30) * 60);
        $locale = config('weather-widget.locale') ?: app()->getLocale();
        $useApiDescription = OpenWeatherClient::supportsLocale($locale);

        if ($apiKey === '') {
            return [
                'error' => __('weather-widget::weather.errors.missing_api_key'),
                'pollInterval' => null,
            ];
        }

        $client = app(OpenWeatherClient::class);

        $current = $client->current($city, $units, $apiKey, $refreshSeconds);

        if (! $current) {
            // Poll sooner than usual so the widget recovers once the API answers again.
            $retrySeconds = min($refreshSeconds, max(60, (int) config('weather-widget.failure_cache_seconds', 60)));

            return [
                'error' => __('weather-widget::weather.errors.unavailable'),
                'pollInterval' => $retrySeconds . 's',
            ];
        }

        $forecast = $client->forecast($city, $units, $apiKey, $refreshSeconds);

        // Keep real UTC timestamps for sorting/filtering; only shift to the city's offset for display.
        $offsetMinutes = intdiv($current->timezone, 60);
        $localTime = fn (int $timestamp): Carbon => Carbon::createFromTimestamp($timestamp)->utcOffset($offsetMinutes);
        $now = time();

        $forecastItems = collect($forecast)
            ->map(fn (WeatherForecastResource $item) => [
                'time' => $localTime($item->timestamp)->format('H:i'),
                'dt_unix' => $item->timestamp,
                'forecast_icon' => $this->iconFor($item->conditionEnum, $item->iconCode),
                'forecast_condition' => $this->conditionLabel($item->conditionEnum, $item->description, $useApiDescription),
                'temp' => round($item->temperature),
            ]);

        $sunEvents = collect(['sunrise' => $current->sunrise, 'sunset' => $current->sunset])
            ->filter()
            ->map(fn (int $timestamp, string $event) => [
                'time' => $localTime($timestamp)->format('H:i'),
                'dt_unix' => $timestamp,
                'forecast_icon' => "weather-{$event}",
                'forecast_condition' => __("weather-widget::weather.labels.{$event}"),
                'temp' => null,
                'is_sunrise' => $event === 'sunrise',
                'is_sunset' => $event === 'sunset',
            ])
            ->values();

        $hourlyForecast = $forecastItems
            ->concat($sunEvents)
            ->filter(fn (array $item) => $item['dt_unix'] >= $now)
            ->sortBy('dt_unix')
            ->values()
            ->take(10);

        // OpenWeatherMap's current temp_min/temp_max is the spread across the city right now,
        // so combine it with today's forecast slots to get a closer "today" low/high.
        $today = $localTime($now)->toDateString();
        $todayTemps = collect($forecast)
            ->filter(fn (WeatherForecastResource $item) => $localTime($item->timestamp)->toDateString() === $today)
            ->pluck('temperature')
            ->push($current->raw['main']['temp_min'] ?? $current->temperature)
            ->push($current->raw['main']['temp_max'] ?? $current->temperature);

        return [
            'temperature' => round($current->temperature),
            'temp_symbol' => $current->unitEnum->tempSymbol(),
            'condition' => $this->conditionLabel($current->conditionEnum, $current->description, $useApiDescription),
            'wind_speed' => $current->wind_speed,
            // The API gives the direction the wind comes from; the arrow points where it blows to.
            'wind_deg' => ($current->wind_deg + 180) % 360,
            'wind_unit' => $current->unitEnum->windUnit(),
            'humidity' => $current->humidity,
            'pressure' => $current->pressure,
            'temp_min' => round($todayTemps->min()),
            'temp_max' => round($todayTemps->max()),
            'feels_like' => round($current->raw['main']['feels_like'] ?? $current->temperature),
            'visibility' => $current->raw['visibility'] ?? null,
            'clouds' => $current->raw['clouds']['all'] ?? null,
            'rain' => $current->raw['rain']['1h'] ?? 0,
            'pollInterval' => $refreshSeconds . 's',
            'current_icon' => $this->iconFor($current->conditionEnum, $current->iconCode),
            'hourlyForecast' => $hourlyForecast,
        ];
    }

    protected function iconFor(?WeatherCondition $condition, string $iconCode): string
    {
        if (! $condition) {
            return 'weather-not-available';
        }

        return 'weather-' . (str_ends_with($iconCode, 'd') ? $condition->dayIcon() : $condition->nightIcon());
    }

    protected function conditionLabel(?WeatherCondition $condition, string $description, bool $useApiDescription): string
    {
        return $useApiDescription
            ? ($description ?: 'N/A')
            : ($condition?->label() ?? 'N/A');
    }
}
