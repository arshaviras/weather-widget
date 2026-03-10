<?php

namespace Arshaviras\WeatherWidget\DTO;

use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Illuminate\Support\Str;

class WeatherForecastResource
{
    public function __construct(
        public string $datetime,
        public float $temperature,
        public WeatherCondition|null $conditionEnum,
        public string $description,
        public string $iconCode,
        public array $raw = []
    ) {}

    public static function fromApi(array $data): self
    {
        $weatherCode = $data['weather'][0]['id'] ?? null;
        $conditionEnum = $weatherCode ? WeatherCondition::fromCode($weatherCode) : null;

        return new self(
            datetime: $data['dt_txt'] ?? '',
            temperature: $data['main']['temp'] ?? 0.0,
            conditionEnum: $conditionEnum,
            description: Str::ucfirst($data['weather'][0]['description'] ?? ''),
            iconCode: $data['weather'][0]['icon'] ?? '',
            raw: $data,
        );
    }
}
