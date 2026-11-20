<?php

namespace Arshaviras\WeatherWidget\Widgets;

use Arshaviras\WeatherWidget\DTO\WeatherCurrentResource;
use Arshaviras\WeatherWidget\DTO\WeatherForecastResource;
use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Arshaviras\WeatherWidget\Enums\WeatherUnit;
use Arshaviras\WeatherWidget\Services\OpenWeatherClient;
use Arshaviras\WeatherWidget\Support\Location;
use Arshaviras\WeatherWidget\Support\WeatherPreferences;
use Arshaviras\WeatherWidget\Support\WidgetList;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Js;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class WeatherWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private const HOVER_ONLY_TOOLTIP = [
        'x-init' => "\$nextTick(() => \$el.__x_tippy?.setProps({ trigger: 'mouseenter' }))",
    ];

    protected string $view = 'weather-widget::widget';

    #[Locked]
    public int|string|array $columnSpan = 'full';

    #[Locked]
    public ?string $city = null;

    #[Locked]
    public ?string $units = null;

    #[Locked]
    public ?int $refreshMinutes = null;

    #[Locked]
    public ?string $iconSet = null;

    #[Locked]
    public ?string $iconVariant = null;

    #[Locked]
    public ?string $forecastView = null;

    #[Locked]
    public ?bool $userSettings = null;

    #[Locked]
    public ?bool $locationSearch = null;

    #[Locked]
    public ?string $storageKey = null;

    #[Locked]
    public bool $managed = false;

    #[Locked]
    public string $listKey = 'default';

    public string $activeForecastView = 'hourly';

    public function mount(): void
    {
        $this->activeForecastView = $this->preferences()->get('forecast_view');
    }

    protected function locationField(): Select
    {
        return Select::make('city')
            ->label(__('weather-widget::weather.settings.location'))
            ->placeholder(__('weather-widget::weather.settings.location_placeholder'))
            ->searchPrompt(__('weather-widget::weather.settings.location_placeholder'))
            ->prefixIcon(Heroicon::OutlinedMapPin)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => collect(
                app(OpenWeatherClient::class)->searchLocations($search, (string) config('weather-widget.api_key'))
            )->mapWithKeys(fn (Location $location) => [$location->value() => $location->description])->all())
            ->getOptionLabelUsing(fn (mixed $value): ?string => is_string($value) ? Location::fromValue($value)?->label : null)
            ->selectablePlaceholder(false)
            ->required()
            ->live()
            ->afterStateUpdated(function (mixed $state, Select $component): void {
                $location = is_string($state) ? Location::fromValue($state) : null;

                if ($location?->isCoordinates() && $location->label === Location::coordinates($location->lat, $location->lon, '')?->label) {
                    $named = app(OpenWeatherClient::class)->reverseLocation($location->lat, $location->lon, (string) config('weather-widget.api_key'));

                    if ($named) {
                        $component->state($named->value());
                    }
                }
            })
            ->suffixAction(fn (Select $component): Action => $this->locateAction($component->getStatePath()));
    }

    protected function locateAction(string $statePath): Action
    {
        $label = __('weather-widget::weather.labels.use_my_location');
        $denied = Js::from(__('weather-widget::weather.errors.location_unavailable'));
        $path = Js::from($statePath);

        return Action::make('locate')
            ->label($label)
            ->tooltip($label)
            ->icon(Heroicon::OutlinedViewfinderCircle)
            ->alpineClickHandler(<<<JS
                const fail = () => new FilamentNotification().title({$denied}).danger().send();
                if (! navigator.geolocation) { fail(); return; }
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude.toFixed(2);
                        const lon = position.coords.longitude.toFixed(2);
                        \$wire.set({$path}, `geo:\${lat},\${lon}|\${lat}, \${lon}`);
                    },
                    fail,
                    { timeout: 10000, maximumAge: 600000 },
                );
            JS);
    }

    public function refreshWeather(): void
    {
        $preferences = $this->preferences();
        $location = Location::fromValue($preferences->get('city'));

        if (! $location) {
            return;
        }

        $limiterKey = 'weather-widget-refresh:'
            .(auth()->id() ?? session()->getId()).':'
            .md5($location->value().$preferences->get('units'));

        if (RateLimiter::tooManyAttempts($limiterKey, 1)) {
            return;
        }

        RateLimiter::hit($limiterKey, 60);

        app(OpenWeatherClient::class)->forget($location, $preferences->get('units'), (string) config('weather-widget.api_key'));
    }

    public function duplicateAction(): Action
    {
        $label = __('weather-widget::weather.labels.duplicate');

        return Action::make('duplicate')
            ->label($label)
            ->tooltip($label)
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->managed && $this->widgetList()->canAdd())
            ->action(fn () => $this->dispatch('weather-widget-duplicate', id: $this->storageKey)->to(WeatherWidgets::class));
    }

    public function removeAction(): Action
    {
        $label = __('weather-widget::weather.labels.remove');

        return Action::make('remove')
            ->label($label)
            ->tooltip($label)
            ->extraAttributes(self::HOVER_ONLY_TOOLTIP)
            ->icon(Heroicon::OutlinedTrash)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->managed && $this->widgetList()->canRemove())
            ->requiresConfirmation()
            ->modalHeading(__('weather-widget::weather.labels.remove_heading'))
            ->modalDescription(__('weather-widget::weather.labels.remove_description'))
            ->modalSubmitActionLabel($label)
            ->modalIconColor('danger')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->action(fn () => $this->dispatch('weather-widget-remove', id: $this->storageKey)->to(WeatherWidgets::class));
    }

    #[On('weather-widgets-changed')]
    public function widgetsChanged(): void {}

    protected function widgetList(): WidgetList
    {
        return new WidgetList(preg_replace('/[^A-Za-z0-9_-]/', '', $this->listKey) ?: 'default');
    }

    public function setForecastView(string $view): void
    {
        if (! in_array($view, WeatherPreferences::FORECAST_VIEWS, true)) {
            return;
        }

        $this->preferences()->update(['forecast_view' => $view]);
        $this->activeForecastView = $this->preferences()->get('forecast_view');
    }

    public function settingsAction(): Action
    {
        $label = fn (string $key): string => __("weather-widget::weather.settings.{$key}");

        return Action::make('settings')
            ->label($label('title'))
            ->tooltip($label('title'))
            ->extraAttributes(self::HOVER_ONLY_TOOLTIP)
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => $this->preferences()->userSettingsEnabled())
            ->modalHeading($label('title'))
            ->modalIcon(Heroicon::OutlinedCog6Tooth)
            ->modalWidth('lg')
            ->modalSubmitActionLabel($label('save'))
            ->fillForm(fn (): array => Arr::only($this->preferences()->all(), WeatherPreferences::SETTINGS))
            ->schema([
                $this->locationField()
                    ->visible(fn (): bool => $this->preferences()->locationSearchEnabled()),
                ToggleButtons::make('units')
                    ->label($label('units'))
                    ->options([
                        'metric' => $label('units_metric'),
                        'imperial' => $label('units_imperial'),
                        'standard' => $label('units_standard'),
                    ])
                    ->icons([
                        'metric' => Heroicon::OutlinedGlobeEuropeAfrica,
                        'imperial' => Heroicon::OutlinedGlobeAmericas,
                        'standard' => Heroicon::OutlinedBeaker,
                    ])
                    ->helperText($label('units_help'))
                    ->inline()
                    ->grouped(),
                ToggleButtons::make('refresh_minutes')
                    ->label($label('refresh_minutes'))
                    ->options(fn (): array => $this->refreshOptions())
                    ->icons(fn (): array => array_fill_keys(array_keys($this->refreshOptions()), Heroicon::OutlinedClock))
                    ->inline()
                    ->grouped(),
                ToggleButtons::make('icon_set')
                    ->label($label('icon_set'))
                    ->options([
                        'fill' => $label('icon_set_fill'),
                        'line' => $label('icon_set_line'),
                        'monochrome' => $label('icon_set_monochrome'),
                    ])
                    ->icons([
                        'fill' => Heroicon::Cloud,
                        'line' => Heroicon::OutlinedCloud,
                        'monochrome' => Heroicon::OutlinedSwatch,
                    ])
                    ->inline()
                    ->grouped()
                    ->live(),
                ToggleButtons::make('icon_variant')
                    ->label($label('icon_variant'))
                    ->options([
                        'static' => $label('icon_variant_static'),
                        'animated' => $label('icon_variant_animated'),
                    ])
                    ->icons([
                        'static' => Heroicon::OutlinedPauseCircle,
                        'animated' => Heroicon::OutlinedPlayCircle,
                    ])
                    ->inline()
                    ->grouped()
                    ->hidden(fn (Get $get): bool => $get('icon_set') === 'monochrome'),
                ToggleButtons::make('layout')
                    ->label($label('layout'))
                    ->options([
                        'auto' => $label('layout_auto'),
                        'full' => $label('layout_full'),
                        'half' => $label('layout_half'),
                    ])
                    ->icons([
                        'auto' => Heroicon::OutlinedSquares2x2,
                        'full' => Heroicon::OutlinedArrowsPointingOut,
                        'half' => Heroicon::OutlinedViewColumns,
                    ])
                    ->inline()
                    ->grouped(),
            ])
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('reset', arguments: ['reset' => true])
                    ->label($label('reset'))
                    ->color('gray'),
            ])
            ->action(function (array $data, array $arguments): void {
                if ($arguments['reset'] ?? false) {
                    $this->preferences()->reset(WeatherPreferences::SETTINGS);

                    return;
                }

                $this->preferences()->update(Arr::only($data, WeatherPreferences::SETTINGS));
            });
    }

    /**
     * @return array<int, string>
     */
    protected function refreshOptions(): array
    {
        $minutes = WeatherPreferences::REFRESH_PRESETS;
        $default = (int) $this->preferences()->defaults()['refresh_minutes'];

        if (! in_array($default, $minutes, true)) {
            $minutes[] = $default;
            sort($minutes);
        }

        return collect($minutes)
            ->mapWithKeys(fn (int $value) => [$value => (string) $value])
            ->all();
    }

    public function getColumnSpan(): int|string|array
    {
        return match ($this->preferences()->get('layout')) {
            'full' => 'full',
            'half' => 1,
            default => $this->columnSpan,
        };
    }

    protected ?WeatherPreferences $preferencesInstance = null;

    protected function preferences(): WeatherPreferences
    {
        return $this->preferencesInstance ??= $this->makePreferences();
    }

    protected function makePreferences(): WeatherPreferences
    {
        $overrides = array_filter([
            'city' => $this->city,
            'units' => $this->units,
            'refresh_minutes' => $this->refreshMinutes,
            'icon_set' => $this->iconSet,
            'icon_variant' => $this->iconVariant,
            'forecast_view' => $this->forecastView,
            'user_settings' => $this->userSettings,
            'location_search' => $this->locationSearch,
        ], fn (mixed $value): bool => $value !== null);

        $scope = $this->storageKey !== null
            ? (preg_replace('/[^A-Za-z0-9_-]/', '', $this->storageKey) ?: 'default')
            : ($overrides ? substr(md5(json_encode($overrides)), 0, 12) : 'default');

        return new WeatherPreferences($overrides, $scope);
    }

    protected function getViewData(): array
    {
        $preferences = $this->preferences();
        $settings = $preferences->all();
        $location = Location::fromValue($settings['city']);
        $units = $settings['units'];
        $unit = WeatherUnit::from($units);
        $apiKey = (string) config('weather-widget.api_key');
        $refreshSeconds = $settings['refresh_minutes'] * 60;
        $locale = config('weather-widget.locale') ?: app()->getLocale();
        $useApiDescription = OpenWeatherClient::supportsLocale($locale);
        $iconPrefix = WeatherPreferences::iconPrefix($settings['icon_set'], $settings['icon_variant']);
        $forecastView = in_array($this->activeForecastView, WeatherPreferences::FORECAST_VIEWS, true) ? $this->activeForecastView : 'hourly';

        if ($apiKey === '') {
            return [
                'error' => __('weather-widget::weather.errors.missing_api_key'),
                'pollInterval' => null,
            ];
        }

        $client = app(OpenWeatherClient::class);

        $current = $location ? $client->current($location, $units, $apiKey, $refreshSeconds) : null;

        if (! $current) {
            // Poll sooner than usual so the widget recovers once the API answers again.
            $retrySeconds = min($refreshSeconds, max(60, (int) config('weather-widget.failure_cache_seconds', 60)));

            return [
                'error' => __('weather-widget::weather.errors.unavailable'),
                'pollInterval' => $retrySeconds.'s',
                'location' => $location?->label,
            ];
        }

        $forecast = collect($client->forecast($location, $units, $apiKey, $refreshSeconds));

        // Keep real UTC timestamps for sorting/filtering; only shift to the city's offset for display.
        $offsetMinutes = intdiv($current->timezone, 60);
        $localTime = fn (int $timestamp): Carbon => Carbon::createFromTimestamp($timestamp)
            ->utcOffset($offsetMinutes)
            ->locale(app()->getLocale());
        $now = now()->getTimestamp();
        $today = $localTime($now)->toDateString();

        $todaySlots = $forecast->filter(fn (WeatherForecastResource $item) => $localTime($item->timestamp)->toDateString() === $today);

        $todayTemps = $todaySlots
            ->pluck('temperature')
            ->push($current->raw['main']['temp_min'] ?? $current->temperature)
            ->push($current->raw['main']['temp_max'] ?? $current->temperature);

        $imperial = $unit === WeatherUnit::Imperial;
        $unitLabel = fn (string $key): string => __("weather-widget::weather.units.{$key}");
        $visibility = $current->raw['visibility'] ?? null;
        $rain = (float) ($current->raw['rain']['1h'] ?? 0);
        $updatedAt = $current->raw['_fetched_at'] ?? $current->raw['dt'] ?? null;

        return [
            'iconPrefix' => $iconPrefix,
            'currentView' => $forecastView,
            'location' => $location->label,
            'temperature' => $this->wholeDegrees($current->temperature),
            'temp_symbol' => $unit->tempSymbol(),
            'temp_short' => $unit->shortTempSymbol(),
            'condition' => $this->conditionLabel($current->conditionEnum, $current->description, $useApiDescription),
            'wind_speed' => $current->windSpeed,
            'wind_unit' => $unitLabel($imperial ? 'mph' : 'm_s'),
            'wind_from' => $this->compassPoint($current->windDegrees),
            'wind_deg' => ($current->windDegrees + 180) % 360,
            'humidity' => $current->humidity.'% '.$unitLabel('rh'),
            'pressure' => $imperial
                ? round($current->pressure * 0.02953, 2).' '.$unitLabel('inhg')
                : $current->pressure.' '.$unitLabel('hpa'),
            'temp_min' => $this->wholeDegrees($todayTemps->min()),
            'temp_max' => $this->wholeDegrees($todayTemps->max()),
            'feels_like' => $this->wholeDegrees($current->raw['main']['feels_like'] ?? $current->temperature),
            'visibility' => $visibility === null ? '—' : ($imperial
                ? round($visibility / 1609.344, 1).' '.$unitLabel('mi')
                : round($visibility / 1000, 1).' '.$unitLabel('km')),
            'clouds' => $current->raw['clouds']['all'] ?? null,
            'rain' => $imperial
                ? round($rain / 25.4, 2).' '.$unitLabel('in_h')
                : $rain.' '.$unitLabel('mm_h'),
            'updated_unix' => $updatedAt ? (int) $updatedAt : null,
            'updated_at' => $updatedAt ? Carbon::createFromTimestamp((int) $updatedAt)->setTimezone(config('app.timezone'))->format('H:i') : null,
            'pollInterval' => $refreshSeconds.'s',
            'current_icon' => $this->iconFor($iconPrefix, $current->conditionEnum, $current->iconCode),
            'hourlyForecast' => $forecastView === 'daily'
                ? collect()
                : $this->hourlyForecast($forecast, $current, $localTime, $now, $today, $iconPrefix, $useApiDescription),
            'dailyForecast' => $forecastView === 'hourly'
                ? collect()
                : $this->dailyForecast($forecast, $current, $localTime, $today, $todaySlots, $todayTemps, $iconPrefix, $useApiDescription),
        ];
    }

    /**
     * @param  Collection<int, WeatherForecastResource>  $forecast
     */
    protected function hourlyForecast(Collection $forecast, WeatherCurrentResource $current, Closure $localTime, int $now, string $today, string $iconPrefix, bool $useApiDescription): Collection
    {
        $slots = $forecast->map(fn (WeatherForecastResource $item) => [
            'time' => $localTime($item->timestamp)->format('H:i'),
            'dt_unix' => $item->timestamp,
            'visible_until' => $item->timestamp + 3 * 3600,
            'forecast_icon' => $this->iconFor($iconPrefix, $item->conditionEnum, $item->iconCode),
            'forecast_condition' => $this->conditionLabel($item->conditionEnum, $item->description, $useApiDescription),
            'temp' => $this->wholeDegrees($item->temperature),
            'pop' => $this->precipitationChance($item->pop),
        ]);

        $sunEvents = collect(['sunrise' => $current->sunrise, 'sunset' => $current->sunset])
            ->filter()
            ->map(fn (int $timestamp, string $event) => [
                'time' => $localTime($timestamp)->format('H:i'),
                'dt_unix' => $timestamp,
                'forecast_icon' => "{$iconPrefix}-{$event}",
                'forecast_condition' => __("weather-widget::weather.labels.{$event}"),
                'temp' => null,
                'pop' => null,
                'is_sunrise' => $event === 'sunrise',
                'is_sunset' => $event === 'sunset',
            ])
            ->values();

        $previousDate = $today;

        return $slots
            ->concat($sunEvents)
            ->filter(fn (array $item) => ($item['visible_until'] ?? $item['dt_unix']) >= $now)
            ->sortBy('dt_unix')
            ->values()
            ->take(10)
            ->map(function (array $item) use ($localTime, &$previousDate) {
                $time = $localTime($item['dt_unix']);
                $item['day'] = $time->toDateString() !== $previousDate ? $time->translatedFormat('D') : null;
                $previousDate = $time->toDateString();

                return $item;
            });
    }

    /**
     * @param  Collection<int, WeatherForecastResource>  $forecast
     */
    protected function dailyForecast(Collection $forecast, WeatherCurrentResource $current, Closure $localTime, string $today, Collection $todaySlots, Collection $todayTemps, string $iconPrefix, bool $useApiDescription): Collection
    {
        $days = $forecast
            ->groupBy(fn (WeatherForecastResource $item) => $localTime($item->timestamp)->toDateString())
            ->reject(fn (Collection $slots, string $date) => $date <= $today)
            ->filter(fn (Collection $slots) => $slots->count() >= 4)
            ->map(function (Collection $slots) use ($localTime, $iconPrefix, $useApiDescription) {
                $midday = $slots->sortBy(fn (WeatherForecastResource $item) => abs((int) $localTime($item->timestamp)->format('G') - 12))->first();
                $date = $localTime($midday->timestamp);

                return [
                    'label' => $date->translatedFormat('D'),
                    'date' => $date->translatedFormat('j M'),
                    'icon' => $this->iconFor($iconPrefix, $midday->conditionEnum, 'd'),
                    'condition' => $this->conditionLabel($midday->conditionEnum, $midday->description, $useApiDescription),
                    'min' => $this->wholeDegrees($slots->min(fn (WeatherForecastResource $item) => $item->raw['main']['temp_min'] ?? $item->temperature)),
                    'max' => $this->wholeDegrees($slots->max(fn (WeatherForecastResource $item) => $item->raw['main']['temp_max'] ?? $item->temperature)),
                    'pop' => $this->precipitationChance($slots->max('pop')),
                ];
            })
            ->values();

        return $days
            ->prepend([
                'label' => __('weather-widget::weather.labels.today'),
                'date' => $localTime(now()->getTimestamp())->translatedFormat('j M'),
                'icon' => $this->iconFor($iconPrefix, $current->conditionEnum, 'd'),
                'condition' => $this->conditionLabel($current->conditionEnum, $current->description, $useApiDescription),
                'min' => $this->wholeDegrees($todayTemps->min()),
                'max' => $this->wholeDegrees($todayTemps->max()),
                'pop' => $this->precipitationChance($todaySlots->max('pop') ?? $forecast->first()?->pop),
            ])
            ->take(6);
    }

    protected function wholeDegrees(float|int|null $value): int
    {
        return (int) round((float) $value);
    }

    protected function precipitationChance(?float $pop): ?string
    {
        return $pop === null ? null : round($pop * 100).'%';
    }

    protected function compassPoint(int $degrees): string
    {
        $points = __('weather-widget::weather.compass');

        if (! is_array($points) || count($points) !== 16) {
            return '';
        }

        return $points[(int) round(($degrees % 360) / 22.5) % 16];
    }

    protected function iconFor(string $iconPrefix, ?WeatherCondition $condition, string $iconCode): string
    {
        if (! $condition) {
            return "{$iconPrefix}-not-available";
        }

        return "{$iconPrefix}-".(str_ends_with($iconCode, 'd') ? $condition->dayIcon() : $condition->nightIcon());
    }

    protected function conditionLabel(?WeatherCondition $condition, string $description, bool $useApiDescription): string
    {
        return $useApiDescription
            ? ($description ?: __('weather-widget::weather.labels.not_available'))
            : ($condition?->label() ?? __('weather-widget::weather.labels.not_available'));
    }
}
