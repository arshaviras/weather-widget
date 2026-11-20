<?php

namespace Arshaviras\WeatherWidget\Support;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class UserStore
{
    public static function get(string $name, mixed $default = null): mixed
    {
        $key = self::cacheKey($name);
        $value = $key ? Cache::get($key) : session(self::sessionKey($name));

        return $value ?? $default;
    }

    public static function put(string $name, mixed $value): void
    {
        if ($key = self::cacheKey($name)) {
            Cache::forever($key, $value);
        } else {
            session([self::sessionKey($name) => $value]);
        }
    }

    public static function forget(string $name): void
    {
        if ($key = self::cacheKey($name)) {
            Cache::forget($key);
        } else {
            session()->forget(self::sessionKey($name));
        }
    }

    private static function sessionKey(string $name): string
    {
        return 'weather-widget.store.'.str_replace(['.', ':'], '_', $name);
    }

    private static function cacheKey(string $name): ?string
    {
        try {
            $guard = Filament::getCurrentOrDefaultPanel()?->getAuthGuard();
        } catch (Throwable) {
            $guard = null;
        }

        $id = auth($guard)->id();

        return $id === null ? null : "weather-widget:{$name}:{$guard}:{$id}";
    }
}
