<?php

use Arshaviras\WeatherWidget\Services\OpenWeatherClient;
use Arshaviras\WeatherWidget\Support\Location;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('maps app locales to OpenWeatherMap language codes', function (string $locale, ?string $expected) {
    expect(OpenWeatherClient::languageFor($locale))->toBe($expected);
})->with([
    ['en', 'en'],
    ['es', 'es'],
    ['pt-BR', 'pt_br'],
    ['cs', 'cz'],
    ['ko', 'kr'],
    ['nb', 'no'],
    ['de_AT', 'de'],
    ['zh', 'zh_cn'],
    ['zh-HK', 'zh_tw'],
    ['hy', null],
]);

it('requests weather by coordinates for a searched location', function () {
    fakeWeather();

    app(OpenWeatherClient::class)->current(Location::coordinates(40.79, 43.85, 'Gyumri'), 'metric', 'key');

    Http::assertSent(fn (Request $request) => $request['lat'] == 40.79 && $request['lon'] == 43.85 && ! isset($request['q']));
});

it('remembers a failure so an outage does not slow down every page', function () {
    Http::fake(['*' => Http::response('down', 503)]);
    $client = app(OpenWeatherClient::class);

    expect($client->current('Yerevan', 'metric', 'key'))->toBeNull()
        ->and($client->forecast('Yerevan', 'metric', 'key'))->toBe([]);

    Http::assertSentCount(1);
});

it('treats an unreachable API like an error response and remembers it', function () {
    Http::fake(['*' => Http::failedConnection()]);
    $client = app(OpenWeatherClient::class);

    expect($client->current('Yerevan', 'metric', 'key'))->toBeNull()
        ->and($client->forecast('Yerevan', 'metric', 'key'))->toBe([])
        ->and($client->searchLocations('Yerevan', 'key'))->toBe([]);

    Http::assertSentCount(2);
});

it('does not cache failed place searches', function () {
    Http::fakeSequence('api.openweathermap.org/*')
        ->push('down', 503)
        ->push([['name' => 'Gyumri', 'lat' => 40.79, 'lon' => 43.85, 'country' => 'AM']]);
    $client = app(OpenWeatherClient::class);

    expect($client->searchLocations('Gyumri', 'key'))->toBe([])
        ->and($client->searchLocations('Gyumri', 'key'))->toHaveCount(1)
        ->and($client->searchLocations('Gyumri', 'key'))->toHaveCount(1);

    Http::assertSentCount(2);
});

it('lists a town once when the API returns it twice, but keeps same-named places in different regions', function () {
    Http::fake(['api.openweathermap.org/geo/1.0/direct*' => Http::response([
        ['name' => 'Gyumri', 'lat' => 40.7894, 'lon' => 43.8475, 'country' => 'AM', 'state' => 'Shirak Province'],
        ['name' => 'Gyumri', 'lat' => 40.7998, 'lon' => 43.8401, 'country' => 'AM', 'state' => 'Shirak Province'],
        ['name' => 'Springfield', 'lat' => 39.80, 'lon' => -89.64, 'country' => 'US', 'state' => 'Illinois'],
        ['name' => 'Springfield', 'lat' => 37.21, 'lon' => -93.29, 'country' => 'US', 'state' => 'Missouri'],
    ])]);

    $found = collect(app(OpenWeatherClient::class)->searchLocations('Gyumri', 'key'))->pluck('description')->all();

    expect($found)->toBe([
        'Gyumri, Shirak Province, AM',
        'Springfield, Illinois, US',
        'Springfield, Missouri, US',
    ]);
});

it('names places in the app language, then English, then the local name', function () {
    app()->setLocale('el');
    Http::fake(['api.openweathermap.org/geo/1.0/direct*' => Http::response([
        ['name' => 'Αθήνα', 'local_names' => ['el' => 'Αθήνα', 'en' => 'Athens'], 'lat' => 37.98, 'lon' => 23.73, 'country' => 'GR'],
        ['name' => 'თბილისი', 'local_names' => ['ka' => 'თბილისი', 'en' => 'Tbilisi'], 'lat' => 41.69, 'lon' => 44.80, 'country' => 'GE'],
        ['name' => 'ზღვისუბანი', 'lat' => 41.77, 'lon' => 44.81, 'country' => 'GE'],
    ])]);

    expect(collect(app(OpenWeatherClient::class)->searchLocations('ab', 'key'))->pluck('label')->all())
        ->toBe(['Αθήνα, GR', 'Tbilisi, GE', 'ზღვისუბანი, GE']);
});

it('keeps the browser coordinates when naming a position', function () {
    fakeWeather();

    $location = app(OpenWeatherClient::class)->reverseLocation(40.7412, 44.8623, 'key');

    expect($location->label)->toBe('Dilijan, AM')
        ->and($location->query())->toBe(['lat' => 40.74, 'lon' => 44.86]);
});
