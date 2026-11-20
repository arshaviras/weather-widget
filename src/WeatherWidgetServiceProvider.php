<?php

namespace Arshaviras\WeatherWidget;

use Arshaviras\WeatherWidget\Support\WeatherPreferences;
use Arshaviras\WeatherWidget\Widgets\WeatherWidget;
use Arshaviras\WeatherWidget\Widgets\WeatherWidgets;
use BladeUI\Icons\Factory;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class WeatherWidgetServiceProvider extends PackageServiceProvider
{
    public static string $name = 'weather-widget';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations();
    }

    public function registeringPackage(): void
    {
        $this->registerIcons();
    }

    protected function registerIcons(): void
    {
        $this->callAfterResolving(Factory::class, function (Factory $factory) {
            $base = __DIR__.'/../resources/svg/weather-icons';
            $pathFor = fn (string $set, string $variant): string => match ($set) {
                'fill', 'line' => "{$base}/{$set}/svg".($variant === 'animated' ? '' : '-static'),
                'monochrome' => "{$base}/monochrome/svg-static",
                default => "{$base}/line/svg-static",
            };

            $factory->add('weather', [
                'path' => $pathFor(config('weather-widget.icon_set', 'line'), config('weather-widget.icon_variant', 'static')),
                'prefix' => 'weather',
            ]);

            $sets = [];

            foreach (WeatherPreferences::ICON_SETS as $set) {
                foreach (WeatherPreferences::ICON_VARIANTS as $variant) {
                    $sets[WeatherPreferences::iconPrefix($set, $variant)] = $pathFor($set, $variant);
                }
            }

            foreach ($sets as $prefix => $path) {
                $factory->add("weather-widget-{$prefix}", ['path' => $path, 'prefix' => $prefix]);
            }
        });
    }

    public function packageBooted(): void
    {
        Livewire::component('arshaviras.weather-widget.widgets.weather-widget', WeatherWidget::class);
        Livewire::component('arshaviras.weather-widget.widgets.weather-widgets', WeatherWidgets::class);

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );
    }

    protected function getAssetPackageName(): ?string
    {
        return 'arshaviras/weather-widget';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            Css::make('weather-widget', __DIR__.'/../resources/dist/weather-widget.css'),
        ];
    }
}
