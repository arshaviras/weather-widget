<?php

namespace Arshaviras\WeatherWidget\Enums;

enum WeatherUnit: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';
    case Standard = 'standard';

    public function tempSymbol(): string
    {
        return match ($this) {
            self::Metric => '°C',
            self::Imperial => '°F',
            self::Standard => 'K',
        };
    }

    public function windUnit(): string
    {
        return match ($this) {
            self::Metric => 'm/s',
            self::Imperial => 'mph',
            self::Standard => 'm/s',
        };
    }
}