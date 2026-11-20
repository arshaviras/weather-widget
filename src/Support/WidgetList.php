<?php

namespace Arshaviras\WeatherWidget\Support;

use Illuminate\Support\Str;

final class WidgetList
{
    public function __construct(private readonly string $key = 'default') {}

    public static function max(): int
    {
        return max(1, (int) config('weather-widget.max_widgets', 6));
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        $ids = UserStore::get($this->storeName());
        $ids = is_array($ids) ? array_values(array_unique(array_filter($ids, fn (mixed $id): bool => is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1))) : [];

        return $ids === [] ? ['default'] : array_slice($ids, 0, self::max());
    }

    public function count(): int
    {
        return count($this->ids());
    }

    public function canAdd(): bool
    {
        return $this->count() < self::max();
    }

    public function canRemove(): bool
    {
        return $this->count() > 1;
    }

    public function duplicate(string $id): ?string
    {
        $ids = $this->ids();
        $position = array_search($id, $ids, true);

        if ($position === false || ! $this->canAdd()) {
            return null;
        }

        $newId = 'w'.Str::lower(Str::random(10));

        (new WeatherPreferences(scope: $newId))->replaceStored((new WeatherPreferences(scope: $id))->stored());

        array_splice($ids, $position + 1, 0, [$newId]);
        UserStore::put($this->storeName(), $ids);

        return $newId;
    }

    public function remove(string $id): bool
    {
        $ids = $this->ids();

        if (! in_array($id, $ids, true) || ! $this->canRemove()) {
            return false;
        }

        (new WeatherPreferences(scope: $id))->clear();
        UserStore::put($this->storeName(), array_values(array_diff($ids, [$id])));

        return true;
    }

    private function storeName(): string
    {
        return "widgets:{$this->key}";
    }
}
