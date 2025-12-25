<x-filament::widget>
    <x-filament::card>
        @if (isset($error))
            <div>
                {{ $error }}
            </div>
        @else
            <div wire:poll.{{ $pollInterval }}>
                <div class="flex flex-col lg:flex-row lg:items-center gap-x-8">
                    {{-- Main Icon + Temp + Condition + City --}}
                    <div class="flex items-center gap-4 flex-shrink-0">
                        <x-filament::icon :icon="$current_icon" title="{{ $condition }}" class="h-32 w-32" />
                        <div class="flex flex-col justify-center">
                            <span class="text-3xl font-bold">{{ $temperature }}{{ $temp_symbol }}</span>
                            <span class="text-base font-medium mt-1">{{ $condition }}</span>
                            <div class="flex items-center gap-1 mt-1">
                                <x-filament::icon icon="heroicon-o-map-pin"
                                    class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <span
                                    class="text-sm text-gray-500 dark:text-gray-400">{{ ($translation = __($key = 'weather-widget::weather.cities.' . $city)) === $key ? $city : $translation }}</span>
                            </div>
                        </div>
                    </div>

                    <hr class="mb-4 mt-3 block lg:hidden text-gray-400" style="opacity: 0.2;">

                    <div class="w-10"></div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 flex-1">
                        {{-- Wind Speed --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-wind" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">
                                {{ __('Wind') }}: {{ $wind_speed }} {{ $wind_unit }}
                            </span>
                            <x-filament::icon icon="heroicon-o-arrow-up"
                                class="h-4 w-4 text-gray-500 dark:text-gray-400" :style="'transform: rotate(' . $wind_deg . 'deg);'" />
                        </div>

                        {{-- Humidity --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-humidity"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Humidity') }}: {{ $humidity }}% RH</span>
                        </div>

                        {{-- Pressure --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-barometer"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Pressure') }}: {{ $pressure }} hPa</span>
                        </div>

                        {{-- Min Temp --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-thermometer-colder"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Min temp') }}: {{ $temp_min }}{{ $temp_symbol }}</span>
                        </div>

                        {{-- Max Temp --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-thermometer-warmer"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Max temp') }}: {{ $temp_max }}{{ $temp_symbol }}</span>
                        </div>

                        {{-- Feels Like --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-thermometer"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">
                                {{ __('Feels like') }}: {{ $feels_like }}{{ $temp_symbol }}
                            </span>
                        </div>

                        {{-- Visibility --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-mist" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">
                                {{ __('Visibility') }}: {{ $visibility ? round($visibility / 1000, 1) . ' km' : '—' }}
                            </span>
                        </div>

                        {{-- Clouds --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-cloudy" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Clouds') }}: {{ $clouds ?? '—' }}%</span>
                        </div>

                        {{-- Rain --}}
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="weather-umbrella"
                                class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                            <span class="text-sm font-semibold">{{ __('Rain') }}: {{ $rain . ' mm/h' }}</span>
                        </div>
                    </div>

                </div>

                <hr class="mb-5 mt-3 text-gray-400" style="opacity: 0.2;">

                {{-- Hourly Forecast --}}
                <div class="flex flex-wrap justify-between">
                    @foreach ($hourlyForecast as $hour)
                        <div class="flex flex-col items-center">
                            <span class="font-semibold mt-1 text-md text-gray-500 dark:text-gray-400">
                                {{ $hour['time'] }}
                            </span>
                            <x-filament::icon :icon="$hour['forecast_icon']" :title="$hour['forecast_condition']" class="h-16 w-16 mt-2 mb-2" />
                            <span class="font-semibold text-lg text-gray-500 dark:text-gray-400">
                                {{-- Show Sunrise/Sunset instead of temperature if applicable --}}
                                @if (!empty($hour['is_sunrise']))
                                    {{ __('Sunrise') }}
                                @elseif (!empty($hour['is_sunset']))
                                    {{ __('Sunset') }}
                                @else
                                    {{ $hour['temp'] }}{{ $temp_symbol }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::card>
</x-filament::widget>
