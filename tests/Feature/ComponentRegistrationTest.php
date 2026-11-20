<?php

use Arshaviras\WeatherWidget\Widgets\WeatherWidget;
use Arshaviras\WeatherWidget\Widgets\WeatherWidgets;

it('registers its Livewire components so browser requests can find them by name', function (string $name, string $class) {
    expect(app('livewire')->new($name))->toBeInstanceOf($class);
})->with([
    'card' => ['arshaviras.weather-widget.widgets.weather-widget', WeatherWidget::class],
    'card group' => ['arshaviras.weather-widget.widgets.weather-widgets', WeatherWidgets::class],
]);
