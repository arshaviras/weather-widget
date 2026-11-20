<?php

namespace Arshaviras\WeatherWidget\Support;

final class Location
{
    private const GEO_PATTERN = '/^geo:(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)\|(.{1,120})$/u';

    private function __construct(
        public readonly ?string $city,
        public readonly ?float $lat,
        public readonly ?float $lon,
        public readonly string $label,
        public readonly ?string $description = null,
    ) {}

    public static function city(string $name): self
    {
        return new self($name, null, null, $name);
    }

    public static function coordinates(float $lat, float $lon, string $label, ?string $description = null): ?self
    {
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        $label = trim(str_replace(["\n", "\r", '|'], ' ', $label));
        $label = $label !== '' ? $label : sprintf('%.2f, %.2f', $lat, $lon);

        return new self(null, round($lat, 2), round($lon, 2), $label, $description ?? $label);
    }

    public static function fromValue(string $value): ?self
    {
        if (! str_starts_with($value, 'geo:')) {
            return $value === '' ? null : self::city($value);
        }

        if (! preg_match(self::GEO_PATTERN, $value, $m)) {
            return null;
        }

        return self::coordinates((float) $m[1], (float) $m[2], $m[3]);
    }

    public static function isCoordinateValue(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, 'geo:');
    }

    public function isCoordinates(): bool
    {
        return $this->city === null;
    }

    public function value(): string
    {
        return $this->isCoordinates()
            ? sprintf('geo:%s,%s|%s', $this->lat, $this->lon, $this->label)
            : $this->city;
    }

    /**
     * @return array{q: string}|array{lat: float, lon: float}
     */
    public function query(): array
    {
        return $this->isCoordinates()
            ? ['lat' => $this->lat, 'lon' => $this->lon]
            : ['q' => $this->city];
    }
}
