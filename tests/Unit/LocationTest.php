<?php

use Arshaviras\WeatherWidget\Support\Location;

it('treats a plain value as a city name', function () {
    $location = Location::fromValue('Paris');

    expect($location->isCoordinates())->toBeFalse()
        ->and($location->query())->toBe(['q' => 'Paris'])
        ->and($location->value())->toBe('Paris');
});

it('round-trips coordinates with a label', function () {
    $location = Location::coordinates(40.78942, 43.84751, 'Gyumri, AM');

    expect($location->value())->toBe('geo:40.79,43.85|Gyumri, AM')
        ->and(Location::fromValue($location->value())->query())->toBe(['lat' => 40.79, 'lon' => 43.85])
        ->and(Location::fromValue($location->value())->label)->toBe('Gyumri, AM');
});

it('rejects malformed or out-of-range coordinates', function (string $value) {
    expect(Location::fromValue($value))->toBeNull();
})->with([
    'latitude too big' => 'geo:95,10|X',
    'longitude too big' => 'geo:10,200|X',
    'missing label' => 'geo:10,10',
    'not numbers' => 'geo:a,b|X',
]);

it('strips separators from labels and falls back to the coordinates', function () {
    expect(Location::coordinates(1, 2, "A|B\nC")->label)->toBe('A B C')
        ->and(Location::coordinates(1.5, 2.25, '')->label)->toBe('1.50, 2.25');
});
