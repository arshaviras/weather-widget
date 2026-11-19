<?php

namespace Arshaviras\WeatherWidget\Services;

use Illuminate\Http\Client\ConnectionException;
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
        'ru', 'se', 'sk', 'sl', 'sp', 'es', 'sr', 'sv', 'th', 'tr', 'ua', 'uk',
        'vi', 'zh_cn', 'zh_tw', 'zu',
    ];

    /**
     * Laravel / ISO locale codes that OpenWeatherMap knows under a different code.
     */
    private const LOCALE_ALIASES = [
        'cs' => 'cz',
        'ko' => 'kr',
        'sq' => 'al',
        'nb' => 'no',
        'nn' => 'no',
        'zh' => 'zh_cn',
        'zh_hk' => 'zh_tw',
    ];

    /**
     * Maps an app locale to an OpenWeatherMap language code, or null when it is not supported.
     * Tries the full locale first ("pt-BR" → "pt_br"), then the base language ("de_AT" → "de").
     */
    public static function languageFor(string $locale): ?string
    {
        $normalised = strtolower(str_replace('-', '_', $locale));
        $base = explode('_', $normalised)[0];

        foreach ([$normalised, $base] as $candidate) {
            $candidate = self::LOCALE_ALIASES[$candidate] ?? $candidate;

            if (in_array($candidate, self::SUPPORTED_LANGS, true)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function supportsLocale(string $locale): bool
    {
        return self::languageFor($locale) !== null;
    }

    private function resolveLanguage(): string
    {
        $locale = config('weather-widget.locale') ?: app()->getLocale();

        return self::languageFor($locale) ?? 'en';
    }

    private function getWeatherData(
        string $endpoint,
        string $city,
        string $apiKey,
        string $units,
        int $cacheDuration
    ): array|null {
        $lang = $this->resolveLanguage();
        $hash = md5($city . $units . $apiKey . $lang);
        $cacheKey = "weather-{$endpoint}-{$hash}";
        // Shared by both endpoints: when one of them fails, the other one is not asked either.
        $failureKey = "weather-failed-{$hash}";

        $data = Cache::get($cacheKey);

        if ($data !== null || Cache::has($failureKey)) {
            return $data;
        }

        $data = $this->fetch($endpoint, [
            'q' => $city,
            'units' => $units,
            'appid' => $apiKey,
            'lang' => $lang,
        ]);

        if ($data === null) {
            Cache::put($failureKey, true, (int) config('weather-widget.failure_cache_seconds', 60));

            return null;
        }

        Cache::put($cacheKey, $data, $cacheDuration);

        return $data;
    }

    /**
     * Returns null when OpenWeatherMap cannot be reached in time or answers with an error.
     */
    private function fetch(string $endpoint, array $query): array|null
    {
        try {
            $response = Http::connectTimeout((int) config('weather-widget.connect_timeout', 3))
                ->timeout((int) config('weather-widget.timeout', 5))
                ->get("https://api.openweathermap.org/data/2.5/{$endpoint}", $query);
        } catch (ConnectionException) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
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
