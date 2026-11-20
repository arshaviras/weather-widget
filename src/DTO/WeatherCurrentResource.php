<?php

namespace Arshaviras\WeatherWidget\DTO;

use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Arshaviras\WeatherWidget\Enums\WeatherUnit;
use Illuminate\Support\Str;

class WeatherCurrentResource
{
    public function __construct(
        public string $city,
        public float $temperature,
        public ?WeatherCondition $conditionEnum,
        public string $description,
        public string $iconCode,
        public int $humidity,
        public int $pressure,
        public float $windSpeed,
        public int $windDegrees,
        public int $timezone,
        public ?int $sunrise,
        public ?int $sunset,
        public WeatherUnit $unitEnum,
        public array $raw = []
    ) {}

    public static function fromApi(array $data, string $unit): self
    {
        $weatherCode = $data['weather'][0]['id'] ?? null;
        $conditionEnum = $weatherCode ? WeatherCondition::fromCode($weatherCode) : null;
        $unitEnum = WeatherUnit::tryFrom($unit) ?? WeatherUnit::Metric;

        return new self(
            city: $data['name'] ?? '',
            temperature: $data['main']['temp'] ?? 0.0,
            conditionEnum: $conditionEnum,
            description: Str::ucfirst($data['weather'][0]['description'] ?? ''),
            iconCode: $data['weather'][0]['icon'] ?? '',
            humidity: $data['main']['humidity'] ?? 0,
            pressure: $data['main']['pressure'] ?? 0,
            windSpeed: $data['wind']['speed'] ?? 0.0,
            windDegrees: $data['wind']['deg'] ?? 0,
            timezone: $data['timezone'] ?? 0,
            sunrise: $data['sys']['sunrise'] ?? null,
            sunset: $data['sys']['sunset'] ?? null,
            unitEnum: $unitEnum,
            raw: $data,
        );
    }
}
