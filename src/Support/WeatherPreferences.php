<?php

namespace Arshaviras\WeatherWidget\Support;

use Arshaviras\WeatherWidget\Enums\WeatherUnit;

class WeatherPreferences
{
    public const ICON_SETS = ['fill', 'line', 'monochrome'];

    public const ICON_VARIANTS = ['static', 'animated'];

    public const FORECAST_VIEWS = ['hourly', 'daily', 'both'];

    public const REFRESH_PRESETS = [1, 5, 10, 20, 30];

    public const LAYOUTS = ['auto', 'full', 'half'];

    public const SETTINGS = ['city', 'units', 'refresh_minutes', 'icon_set', 'icon_variant', 'layout'];

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function __construct(
        private readonly array $overrides = [],
        private readonly string $scope = 'default',
    ) {}

    public function userSettingsEnabled(): bool
    {
        return (bool) ($this->overrides['user_settings'] ?? config('weather-widget.user_settings', true));
    }

    public function locationSearchEnabled(): bool
    {
        return (bool) ($this->overrides['location_search'] ?? config('weather-widget.location_search', true));
    }

    /**
     * @return array{city: string, units: string, refresh_minutes: int, icon_set: string, icon_variant: string, forecast_view: string, layout: string}
     */
    public function defaults(): array
    {
        $option = fn (string $key, mixed $default = null) => $this->overrides[$key] ?? config("weather-widget.{$key}", $default);

        return $this->sanitize([
            'city' => (string) $option('city', ''),
            'units' => $option('units', 'metric'),
            'refresh_minutes' => $option('refresh_minutes', 30),
            'icon_set' => $option('icon_set', 'line'),
            'icon_variant' => $option('icon_variant', 'static'),
            'forecast_view' => $option('forecast_view', 'hourly'),
            'layout' => 'auto',
        ], fromConfig: true);
    }

    /**
     * @return array{city: string, units: string, refresh_minutes: int, icon_set: string, icon_variant: string, forecast_view: string, layout: string}
     */
    public function all(): array
    {
        $stored = $this->load();

        if (! $this->userSettingsEnabled()) {
            $stored = array_diff_key($stored, array_flip(self::SETTINGS));
        }

        return $this->sanitize(array_merge($this->defaults(), $stored));
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function update(array $values): void
    {
        $stored = array_merge($this->load(), $values);

        $sanitized = $this->sanitize(array_merge($this->defaults(), $stored));
        $this->save(array_diff_assoc($sanitized, $this->defaults()));
    }

    public function reset(array $keys): void
    {
        $this->save(array_diff_key($this->load(), array_flip($keys)));
    }

    public static function iconPrefix(string $set, string $variant): string
    {
        return 'weather'.$set.($variant === 'animated' && $set !== 'monochrome' ? 'animated' : '');
    }

    public function allowedLocation(mixed $value): string
    {
        $default = $this->defaults()['city'];

        if (! $this->locationSearchEnabled() || ! Location::isCoordinateValue($value)) {
            return $default;
        }

        return Location::fromValue($value)?->value() ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{city: string, units: string, refresh_minutes: int, icon_set: string, icon_variant: string, forecast_view: string, layout: string}
     */
    private function sanitize(array $values, bool $fromConfig = false): array
    {
        $iconSet = in_array($values['icon_set'] ?? null, self::ICON_SETS, true) ? $values['icon_set'] : 'line';
        $iconVariant = in_array($values['icon_variant'] ?? null, self::ICON_VARIANTS, true) ? $values['icon_variant'] : 'static';

        $refresh = min(1440, max(1, (int) ($values['refresh_minutes'] ?? 10)));

        return [
            'city' => $fromConfig ? (string) ($values['city'] ?? '') : $this->allowedLocation($values['city'] ?? null),
            'units' => (WeatherUnit::tryFrom((string) ($values['units'] ?? '')) ?? WeatherUnit::Metric)->value,
            'refresh_minutes' => $refresh,
            'icon_set' => $iconSet,
            'icon_variant' => $iconSet === 'monochrome' ? 'static' : $iconVariant,
            'forecast_view' => in_array($values['forecast_view'] ?? null, self::FORECAST_VIEWS, true) ? $values['forecast_view'] : 'hourly',
            'layout' => in_array($values['layout'] ?? null, self::LAYOUTS, true) ? $values['layout'] : 'auto',
        ];
    }

    public function stored(): array
    {
        return $this->load();
    }

    public function replaceStored(array $values): void
    {
        $this->save($values);
    }

    public function clear(): void
    {
        UserStore::forget($this->storeName());
        $this->loaded = [];
    }

    private ?array $loaded = null;

    private function load(): array
    {
        if ($this->loaded === null) {
            $stored = UserStore::get($this->storeName(), []);
            $this->loaded = is_array($stored) ? $stored : [];
        }

        return $this->loaded;
    }

    private function save(array $values): void
    {
        UserStore::put($this->storeName(), $values);
        $this->loaded = $values;
    }

    private function storeName(): string
    {
        return "preferences:{$this->scope}";
    }
}
