<?php

namespace Arshaviras\WeatherWidget\Services;

use Arshaviras\WeatherWidget\DTO\WeatherCurrentResource;
use Arshaviras\WeatherWidget\DTO\WeatherForecastResource;
use Arshaviras\WeatherWidget\Support\Location;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

    private function cacheHash(Location $location, string $units, string $apiKey): string
    {
        return md5(json_encode($location->query()).$units.$apiKey.$this->resolveLanguage());
    }

    public function forget(Location|string $location, string $units, string $apiKey): void
    {
        $location = is_string($location) ? Location::city($location) : $location;
        $hash = $this->cacheHash($location, $units, $apiKey);

        Cache::forget("weather-weather-{$hash}");
        Cache::forget("weather-forecast-{$hash}");
        Cache::forget("weather-failed-{$hash}");
    }

    private function getWeatherData(
        string $endpoint,
        Location $location,
        string $apiKey,
        string $units,
        int $cacheDuration
    ): ?array {
        $lang = $this->resolveLanguage();
        $hash = $this->cacheHash($location, $units, $apiKey);
        $cacheKey = "weather-{$endpoint}-{$hash}";
        // Shared by both endpoints: when one of them fails, the other one is not asked either.
        $failureKey = "weather-failed-{$hash}";

        $data = Cache::get($cacheKey);

        if ($data !== null || Cache::has($failureKey)) {
            return $data;
        }

        $data = $this->fetch("data/2.5/{$endpoint}", $location->query() + [
            'units' => $units,
            'appid' => $apiKey,
            'lang' => $lang,
        ]);

        if ($data === null) {
            Cache::put($failureKey, true, (int) config('weather-widget.failure_cache_seconds', 60));

            return null;
        }

        $data['_fetched_at'] = now()->getTimestamp();

        Cache::put($cacheKey, $data, $cacheDuration);

        return $data;
    }

    /**
     * Returns null when OpenWeatherMap cannot be reached in time or answers with an error.
     */
    private function fetch(string $path, array $query): ?array
    {
        try {
            $response = Http::connectTimeout((int) config('weather-widget.connect_timeout', 3))
                ->timeout((int) config('weather-widget.timeout', 5))
                ->get("https://api.openweathermap.org/{$path}", $query);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return is_array($json) ? $json : null;
    }

    public function current(Location|string $location, string $units, string $apiKey, int $ttl = 300): ?WeatherCurrentResource
    {
        $location = is_string($location) ? Location::city($location) : $location;
        $data = $this->getWeatherData('weather', $location, $apiKey, $units, $ttl);

        return $data ? WeatherCurrentResource::fromApi($data, $units) : null;
    }

    /**
     * @return array<int, WeatherForecastResource>
     */
    public function forecast(Location|string $location, string $units, string $apiKey, int $ttl = 300): array
    {
        $location = is_string($location) ? Location::city($location) : $location;
        $data = $this->getWeatherData('forecast', $location, $apiKey, $units, $ttl);

        return collect($data['list'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): WeatherForecastResource => WeatherForecastResource::fromApi($item))
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, Location>
     */
    public function searchLocations(string $query, string $apiKey, int $limit = 5): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2 || $apiKey === '') {
            return [];
        }

        $lang = $this->resolveLanguage();
        $results = $this->rememberLookup(
            'weather-geo-'.md5(mb_strtolower($query).$limit.$lang),
            fn () => $this->fetch('geo/1.0/direct', ['q' => $query, 'limit' => $limit, 'appid' => $apiKey]),
        );

        return collect($results)
            ->map(fn (mixed $place): ?Location => is_array($place) ? $this->locationFromPlace($place, $lang) : null)
            ->filter()
            ->unique(fn (Location $location) => mb_strtolower($location->description))
            ->values()
            ->all();
    }

    public function reverseLocation(float $lat, float $lon, string $apiKey): ?Location
    {
        if ($apiKey === '') {
            return null;
        }

        $lang = $this->resolveLanguage();
        $places = $this->rememberLookup(
            'weather-geo-reverse-'.md5(round($lat, 2).','.round($lon, 2).$lang),
            fn () => $this->fetch('geo/1.0/reverse', ['lat' => round($lat, 2), 'lon' => round($lon, 2), 'limit' => 1, 'appid' => $apiKey]),
        );

        $place = $places[0] ?? null;

        return is_array($place) ? $this->locationFromPlace(['lat' => $lat, 'lon' => $lon] + $place, $lang) : null;
    }

    private function rememberLookup(string $key, \Closure $fetch): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = $fetch();

        if ($result === null) {
            return [];
        }

        Cache::put($key, $result, now()->addDay());

        return $result;
    }

    private function locationFromPlace(array $place, string $lang): ?Location
    {
        if (! isset($place['lat'], $place['lon'])) {
            return null;
        }

        $name = $place['local_names'][$lang] ?? $place['local_names']['en'] ?? $place['name'] ?? '';
        $country = $place['country'] ?? null;

        $label = implode(', ', array_filter([$name, $country]));
        $description = implode(', ', array_filter([$name, $place['state'] ?? null, $country]));

        return Location::coordinates((float) $place['lat'], (float) $place['lon'], $label, $description);
    }
}
