<?php

use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Arshaviras\WeatherWidget\Support\WeatherPreferences;

it('has every icon the widget uses in every icon set', function (string $set, string $variant) {
    $view = file_get_contents(__DIR__.'/../resources/views/widget.blade.php');
    preg_match_all("/\\\$iconPrefix \\. '-([a-z-]+)'/", $view, $fromView);

    $names = collect(WeatherCondition::cases())
        ->flatMap(fn (WeatherCondition $condition) => [$condition->dayIcon(), $condition->nightIcon()])
        ->merge($fromView[1])
        ->merge(['sunrise', 'sunset', 'not-available'])
        ->unique();

    $prefix = WeatherPreferences::iconPrefix($set, $variant);

    expect($names->count())->toBeGreaterThan(40);

    foreach ($names as $name) {
        expect(svg("{$prefix}-{$name}")->contents())->toContain('<path');
    }
})->with([
    ['fill', 'static'],
    ['fill', 'animated'],
    ['line', 'static'],
    ['line', 'animated'],
    ['monochrome', 'static'],
]);

it('gives every icon set its own svg ids', function (string $dir, string $tag) {
    foreach (glob(__DIR__."/../resources/svg/weather-icons/{$dir}/*.svg") as $file) {
        preg_match_all('/\bid="([^"]+)"/', file_get_contents($file), $m);

        foreach ($m[1] as $id) {
            expect($id)->toStartWith("{$tag}-");
        }
    }
})->with([
    ['fill/svg-static', 'wfs'],
    ['fill/svg', 'wfa'],
    ['line/svg-static', 'wls'],
    ['line/svg', 'wla'],
    ['monochrome/svg-static', 'wms'],
]);
