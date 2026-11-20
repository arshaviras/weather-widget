<?php

use Arshaviras\WeatherWidget\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);

function fakeWeather(int $timezoneOffset = 14400, string $nowUtc = '2026-11-19 17:30:00', array $current = []): void
{
    $now = Carbon::parse($nowUtc, 'UTC');
    Carbon::setTestNow($now);

    $start = intdiv($now->getTimestamp(), 10800) * 10800 + 10800;
    $list = [];

    for ($i = 0; $i < 40; $i++) {
        $dt = $start + $i * 10800;
        $hour = (int) gmdate('G', $dt + $timezoneOffset);

        $list[] = [
            'dt' => $dt,
            'dt_txt' => gmdate('Y-m-d H:i:s', $dt),
            'main' => ['temp' => 5 + ($i % 8), 'temp_min' => 4 + ($i % 8), 'temp_max' => 6 + ($i % 8)],
            'weather' => [['id' => 803, 'description' => 'broken clouds', 'icon' => ($hour >= 7 && $hour < 18) ? '04d' : '04n']],
            'pop' => [0, 0.05, 0.35, 0.8][$i % 4],
        ];
    }

    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response(array_replace_recursive([
            'dt' => $now->getTimestamp() - 600,
            'name' => 'Yerevan',
            'main' => ['temp' => 9.2, 'temp_min' => 8, 'temp_max' => 10, 'feels_like' => 7, 'humidity' => 81, 'pressure' => 1014],
            'weather' => [['id' => 804, 'description' => 'overcast clouds', 'icon' => '04n']],
            'wind' => ['speed' => 3.09, 'deg' => 350],
            'visibility' => 10000,
            'clouds' => ['all' => 100],
            'timezone' => $timezoneOffset,
            'sys' => ['sunrise' => $now->getTimestamp() + 10 * 3600, 'sunset' => $now->getTimestamp() + 20 * 3600],
        ], $current)),
        'api.openweathermap.org/data/2.5/forecast*' => Http::response(['list' => $list]),
        'api.openweathermap.org/geo/1.0/direct*' => Http::response([
            ['name' => 'Gyumri', 'lat' => 40.7894, 'lon' => 43.8475, 'country' => 'AM', 'state' => 'Shirak'],
        ]),
        'api.openweathermap.org/geo/1.0/reverse*' => Http::response([
            ['name' => 'Dilijan', 'lat' => 40.74, 'lon' => 44.86, 'country' => 'AM', 'state' => 'Tavush'],
        ]),
    ]);
}
