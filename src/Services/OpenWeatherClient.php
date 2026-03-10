<?php

namespace Arshaviras\WeatherWidget\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Arshaviras\WeatherWidget\DTO\WeatherCurrentResource;
use Arshaviras\WeatherWidget\DTO\WeatherForecastResource;

class OpenWeatherClient
{
    private const SUPPORTED_LANGS = [
        'af', 'al', 'ar', 'az', 'bg', 'ca', 'cz', 'da', 'de', 'el', 'en',
        'eu', 'fa', 'fi', 'fr', 'gl', 'he', 'hi', 'hr', 'hu', 'id', 'it',
        'ja', 'kr', 'la', 'lt', 'mk', 'nl', 'no', 'pl', 'pt', 'pt_br', 'ro',
        'ru', 'se', 'sk', 'sl', 'sp', 'sr', 'sv', 'th', 'tr', 'ua', 'uk',
        'vi', 'zh_cn', 'zh_tw', 'zu',
    ];

    public static function supportsLocale(string $locale): bool
    {
        $normalised = strtolower(str_replace('-', '_', $locale));
        return in_array($normalised, self::SUPPORTED_LANGS, true);
    }

    private function resolveLanguage(): string
    {
        $locale = config('weather-widget.locale') ?: app()->getLocale();
        // Normalise e.g. "pt-BR" → "pt_br", "zh-CN" → "zh_cn"
        $normalised = strtolower(str_replace('-', '_', $locale));

        return in_array($normalised, self::SUPPORTED_LANGS, true) ? $normalised : 'en';
    }

    private function getWeatherData(
        string $endpoint,
        string $city,
        string $apiKey,
        string $units,
        int $cacheDuration
    ): array|null {
        $lang = $this->resolveLanguage();
        $cacheKey = "weather-{$endpoint}-" . md5($city . $units . $apiKey . $lang);

        return Cache::remember($cacheKey, $cacheDuration, function () use ($endpoint, $city, $apiKey, $units, $lang) {
            $response = Http::get("https://api.openweathermap.org/data/2.5/{$endpoint}", [
                'q' => $city,
                'units' => $units,
                'appid' => $apiKey,
                'lang' => $lang,
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
