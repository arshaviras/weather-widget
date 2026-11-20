<?php

namespace Arshaviras\WeatherWidget\DTO;

use Arshaviras\WeatherWidget\Enums\WeatherCondition;
use Illuminate\Support\Str;

class WeatherForecastResource
{
    public function __construct(
        public string $datetime,
        public float $temperature,
        public ?WeatherCondition $conditionEnum,
        public string $description,
        public string $iconCode,
        public array $raw = [],
        public int $timestamp = 0,
        public ?float $pop = null,
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
            // `dt` is the real UTC unix timestamp; `dt_txt` is the same moment as a UTC string.
            timestamp: (int) ($data['dt'] ?? (isset($data['dt_txt']) ? strtotime($data['dt_txt'].' UTC') : 0)),
            pop: isset($data['pop']) ? (float) $data['pop'] : null,
        );
    }
}
