<?php

namespace Arshaviras\WeatherWidget\Enums;

enum WeatherCondition: int
{
    // Thunderstorm
    case Thunderstorm_200 = 200;
    case Thunderstorm_201 = 201;
    case Thunderstorm_202 = 202;
    case Thunderstorm_210 = 210;
    case Thunderstorm_211 = 211;
    case Thunderstorm_212 = 212;
    case Thunderstorm_221 = 221;
    case Thunderstorm_230 = 230;
    case Thunderstorm_231 = 231;
    case Thunderstorm_232 = 232;

    // Drizzle
    case Drizzle_300 = 300;
    case Drizzle_301 = 301;
    case Drizzle_302 = 302;
    case Drizzle_310 = 310;
    case Drizzle_311 = 311;
    case Drizzle_312 = 312;
    case Drizzle_313 = 313;
    case Drizzle_314 = 314;
    case Drizzle_321 = 321;

    // Rain
    case Rain_500 = 500;
    case Rain_501 = 501;
    case Rain_502 = 502;
    case Rain_503 = 503;
    case Rain_504 = 504;
    case Rain_511 = 511;
    case Rain_520 = 520;
    case Rain_521 = 521;
    case Rain_522 = 522;
    case Rain_531 = 531;

    // Snow
    case Snow_600 = 600;
    case Snow_601 = 601;
    case Snow_602 = 602;
    case Snow_611 = 611;
    case Snow_612 = 612;
    case Snow_613 = 613;
    case Snow_615 = 615;
    case Snow_616 = 616;
    case Snow_620 = 620;
    case Snow_621 = 621;
    case Snow_622 = 622;

    // Atmosphere
    case Atmosphere_701 = 701;
    case Atmosphere_711 = 711;
    case Atmosphere_721 = 721;
    case Atmosphere_731 = 731;
    case Atmosphere_741 = 741;
    case Atmosphere_751 = 751;
    case Atmosphere_761 = 761;
    case Atmosphere_762 = 762;
    case Atmosphere_771 = 771;
    case Atmosphere_781 = 781;

    // Clear
    case Clear_800 = 800;

    // Clouds
    case Clouds_801 = 801;
    case Clouds_802 = 802;
    case Clouds_803 = 803;
    case Clouds_804 = 804;

    // ---------- ICONS ----------

    public function dayIcon(): string
    {
        return match ($this) {
            // Thunderstorm
            self::Thunderstorm_200,
            self::Thunderstorm_201,
            self::Thunderstorm_230,
            self::Thunderstorm_231,
            self::Thunderstorm_232 => 'thunderstorms-day-rain',

            self::Thunderstorm_202 => 'thunderstorms-day-overcast-rain',
            self::Thunderstorm_210 => 'thunderstorms-day',
            self::Thunderstorm_211 => 'thunderstorms',
            self::Thunderstorm_212,
            self::Thunderstorm_221 => 'thunderstorms-overcast',

            // Drizzle
            self::Drizzle_300,
            self::Drizzle_301 => 'partly-cloudy-day-drizzle',
            self::Drizzle_302,
            self::Drizzle_310 => 'overcast-day-drizzle',
            self::Drizzle_311 => 'drizzle',
            self::Drizzle_312,
            self::Drizzle_313 => 'overcast-drizzle',
            self::Drizzle_314,
            self::Drizzle_321 => 'overcast-rain',

            // Rain
            self::Rain_500,
            self::Rain_501,
            self::Rain_520,
            self::Rain_521 => 'partly-cloudy-day-rain',
            self::Rain_502,
            self::Rain_503,
            self::Rain_522,
            self::Rain_531 => 'overcast-day-rain',
            self::Rain_504 => 'overcast-rain',
            self::Rain_511 => 'sleet',

            // Snow
            self::Snow_600,
            self::Snow_601,
            self::Snow_620,
            self::Snow_621 => 'partly-cloudy-day-snow',
            self::Snow_602,
            self::Snow_622 => 'overcast-day-snow',
            self::Snow_611,
            self::Snow_612,
            self::Snow_615,
            self::Snow_616 => 'partly-cloudy-day-sleet',
            self::Snow_613 => 'overcast-day-sleet',

            // Atmosphere
            self::Atmosphere_701 => 'mist',
            self::Atmosphere_711 => 'partly-cloudy-day-smoke',
            self::Atmosphere_721 => 'haze-day',
            self::Atmosphere_731,
            self::Atmosphere_751,
            self::Atmosphere_761 => 'dust-day',
            self::Atmosphere_741 => 'fog-day',
            self::Atmosphere_762 => 'overcast-smoke',
            self::Atmosphere_771 => 'wind',
            self::Atmosphere_781 => 'tornado',

            // Clear
            self::Clear_800 => 'clear-day',

            // Clouds
            self::Clouds_801,
            self::Clouds_802 => 'partly-cloudy-day',
            self::Clouds_803,
            self::Clouds_804 => 'overcast-day',
        };
    }

    public function nightIcon(): string
    {
        return match ($this) {
            // Thunderstorm
            self::Thunderstorm_200,
            self::Thunderstorm_201,
            self::Thunderstorm_230,
            self::Thunderstorm_231,
            self::Thunderstorm_232 => 'thunderstorms-night-rain',

            self::Thunderstorm_202 => 'thunderstorms-night-overcast-rain',
            self::Thunderstorm_210 => 'thunderstorms-night',
            self::Thunderstorm_211 => 'thunderstorms',
            self::Thunderstorm_212,
            self::Thunderstorm_221 => 'thunderstorms-overcast',

            // Drizzle
            self::Drizzle_300,
            self::Drizzle_301 => 'partly-cloudy-night-drizzle',
            self::Drizzle_302,
            self::Drizzle_310 => 'overcast-night-drizzle',
            self::Drizzle_311 => 'drizzle',
            self::Drizzle_312,
            self::Drizzle_313 => 'overcast-drizzle',
            self::Drizzle_314,
            self::Drizzle_321 => 'overcast-rain',

            // Rain
            self::Rain_500,
            self::Rain_501,
            self::Rain_520,
            self::Rain_521 => 'partly-cloudy-night-rain',
            self::Rain_502,
            self::Rain_503,
            self::Rain_522,
            self::Rain_531 => 'overcast-night-rain',
            self::Rain_504 => 'overcast-rain',
            self::Rain_511 => 'sleet',

            // Snow
            self::Snow_600,
            self::Snow_601,
            self::Snow_620,
            self::Snow_621 => 'partly-cloudy-night-snow',
            self::Snow_602,
            self::Snow_622 => 'overcast-night-snow',
            self::Snow_611,
            self::Snow_612,
            self::Snow_615,
            self::Snow_616 => 'partly-cloudy-night-sleet',
            self::Snow_613 => 'overcast-night-sleet',

            // Atmosphere
            self::Atmosphere_701 => 'mist',
            self::Atmosphere_711 => 'partly-cloudy-night-smoke',
            self::Atmosphere_721 => 'haze-night',
            self::Atmosphere_731,
            self::Atmosphere_751,
            self::Atmosphere_761 => 'dust-night',
            self::Atmosphere_741 => 'fog-night',
            self::Atmosphere_762 => 'overcast-smoke',
            self::Atmosphere_771 => 'wind',
            self::Atmosphere_781 => 'tornado',

            // Clear
            self::Clear_800 => 'clear-night',

            // Clouds
            self::Clouds_801,
            self::Clouds_802 => 'partly-cloudy-night',
            self::Clouds_803,
            self::Clouds_804 => 'overcast-night',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Thunderstorm_200 => __('weather-widget::weather.conditions.thunderstorm_with_light_rain'),
            self::Thunderstorm_201 => __('weather-widget::weather.conditions.thunderstorm_with_rain'),
            self::Thunderstorm_202 => __('weather-widget::weather.conditions.thunderstorm_with_heavy_rain'),
            self::Thunderstorm_210 => __('weather-widget::weather.conditions.light_thunderstorm'),
            self::Thunderstorm_211 => __('weather-widget::weather.conditions.thunderstorm'),
            self::Thunderstorm_212 => __('weather-widget::weather.conditions.heavy_thunderstorm'),
            self::Thunderstorm_221 => __('weather-widget::weather.conditions.ragged_thunderstorm'),
            self::Thunderstorm_230 => __('weather-widget::weather.conditions.thunderstorm_with_light_drizzle'),
            self::Thunderstorm_231 => __('weather-widget::weather.conditions.thunderstorm_with_drizzle'),
            self::Thunderstorm_232 => __('weather-widget::weather.conditions.thunderstorm_with_heavy_drizzle'),

            self::Drizzle_300 => __('weather-widget::weather.conditions.light_intensity_drizzle'),
            self::Drizzle_301 => __('weather-widget::weather.conditions.drizzle'),
            self::Drizzle_302 => __('weather-widget::weather.conditions.heavy_intensity_drizzle'),
            self::Drizzle_310 => __('weather-widget::weather.conditions.light_intensity_drizzle_rain'),
            self::Drizzle_311 => __('weather-widget::weather.conditions.drizzle_rain'),
            self::Drizzle_312 => __('weather-widget::weather.conditions.heavy_intensity_drizzle_rain'),
            self::Drizzle_313 => __('weather-widget::weather.conditions.shower_rain_and_drizzle'),
            self::Drizzle_314 => __('weather-widget::weather.conditions.heavy_shower_rain_and_drizzle'),
            self::Drizzle_321 => __('weather-widget::weather.conditions.shower_drizzle'),

            self::Rain_500 => __('weather-widget::weather.conditions.light_rain'),
            self::Rain_501 => __('weather-widget::weather.conditions.moderate_rain'),
            self::Rain_502 => __('weather-widget::weather.conditions.heavy_intensity_rain'),
            self::Rain_503 => __('weather-widget::weather.conditions.very_heavy_rain'),
            self::Rain_504 => __('weather-widget::weather.conditions.extreme_rain'),
            self::Rain_511 => __('weather-widget::weather.conditions.freezing_rain'),
            self::Rain_520 => __('weather-widget::weather.conditions.light_intensity_shower_rain'),
            self::Rain_521 => __('weather-widget::weather.conditions.shower_rain'),
            self::Rain_522 => __('weather-widget::weather.conditions.heavy_intensity_shower_rain'),
            self::Rain_531 => __('weather-widget::weather.conditions.ragged_shower_rain'),

            self::Snow_600 => __('weather-widget::weather.conditions.light_snow'),
            self::Snow_601 => __('weather-widget::weather.conditions.snow'),
            self::Snow_602 => __('weather-widget::weather.conditions.heavy_snow'),
            self::Snow_611 => __('weather-widget::weather.conditions.sleet'),
            self::Snow_612 => __('weather-widget::weather.conditions.light_shower_sleet'),
            self::Snow_613 => __('weather-widget::weather.conditions.shower_sleet'),
            self::Snow_615 => __('weather-widget::weather.conditions.light_rain_and_snow'),
            self::Snow_616 => __('weather-widget::weather.conditions.rain_and_snow'),
            self::Snow_620 => __('weather-widget::weather.conditions.light_shower_snow'),
            self::Snow_621 => __('weather-widget::weather.conditions.shower_snow'),
            self::Snow_622 => __('weather-widget::weather.conditions.heavy_shower_snow'),

            self::Atmosphere_701 => __('weather-widget::weather.conditions.mist'),
            self::Atmosphere_711 => __('weather-widget::weather.conditions.smoke'),
            self::Atmosphere_721 => __('weather-widget::weather.conditions.haze'),
            self::Atmosphere_731 => __('weather-widget::weather.conditions.sand_dust_whirls'),
            self::Atmosphere_741 => __('weather-widget::weather.conditions.fog'),
            self::Atmosphere_751 => __('weather-widget::weather.conditions.sand'),
            self::Atmosphere_761 => __('weather-widget::weather.conditions.dust'),
            self::Atmosphere_762 => __('weather-widget::weather.conditions.volcanic_ash'),
            self::Atmosphere_771 => __('weather-widget::weather.conditions.squalls'),
            self::Atmosphere_781 => __('weather-widget::weather.conditions.tornado'),

            self::Clear_800 => __('weather-widget::weather.conditions.clear_sky'),

            self::Clouds_801 => __('weather-widget::weather.conditions.few_clouds_11_25'),
            self::Clouds_802 => __('weather-widget::weather.conditions.scattered_clouds_25_50'),
            self::Clouds_803 => __('weather-widget::weather.conditions.broken_clouds_51_84'),
            self::Clouds_804 => __('weather-widget::weather.conditions.overcast_clouds_85_100'),
        };
    }

    // Optional: Utility to get enum from raw OWM code
    public static function fromCode(int $code): ?self
    {
        return self::tryFrom($code);
    }
}
