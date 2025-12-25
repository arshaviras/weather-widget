<?php

namespace Arshaviras\WeatherWidget\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Arshaviras\WeatherWidget\DTO\WeatherCurrentResource;
use Arshaviras\WeatherWidget\DTO\WeatherForecastResource;

class OpenWeatherClient
{
    private function getWeatherData(
        string $endpoint,
        string $city,
        string $apiKey,
        string $units,
        int $cacheDuration
    ): array|null {
        $cacheKey = "weather-{$endpoint}-" . md5($city . $units . $apiKey);

        return Cache::remember($cacheKey, $cacheDuration, function () use ($endpoint, $city, $apiKey, $units) {
            $response = Http::get("https://api.openweathermap.org/data/2.5/{$endpoint}", [
                'q' => $city,
                'units' => $units,
                'appid' => $apiKey,
                'lang' => config('weather-widget.locale') ?: app()->getLocale(),
            ]);
            if (!$response->successful()) {
                return null;
            }
            return $response->json();
        });
    }

    public function current(string $city, string $units, string $apiKey, int $ttl = 300): ?WeatherCurrentResource
    {
        $data = $this->getWeatherData('weather', $city, $apiKey, $units, $ttl);
        return $data ? WeatherCurrentResource::fromApi($data, $units) : null;
    }

    public function forecast(string $city, string $units, string $apiKey, int $ttl = 300): array
    {
        $data = $this->getWeatherData('forecast', $city, $apiKey, $units, $ttl);
        $list = $data['list'] ?? [];
        return collect($list)
            ->map(fn($item) => WeatherForecastResource::fromApi($item))
            ->toArray();
    }
}
