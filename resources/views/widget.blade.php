@php
    $hasSettings = $this->settingsAction->isVisible();
@endphp

<x-filament::widget>
    <x-filament::card>
        <div class="ww-root">
            <div
                class="ww-loading"
                style="display: none; position: absolute; inset: 0; z-index: 20; align-items: center; justify-content: center;"
                wire:loading.delay.flex
                wire:target="refreshWeather, setForecastView, callMountedAction"
            >
                <x-filament::loading-indicator class="ww-loading-spinner" style="width: 2.5rem; height: 2.5rem;" />
            </div>

            @if ($hasSettings)
                <div class="ww-settings">
                    {{ $this->settingsAction }}
                </div>
            @endif

            @if (isset($error))
                <div @if ($pollInterval) wire:poll.{{ $pollInterval }} @endif
                    @class(['flex flex-col gap-2', 'ww-main' => $hasSettings])>
                    @if (! empty($location))
                        <span class="ww-location" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                            <x-filament::icon icon="heroicon-m-map-pin" class="ww-location-icon" style="width: 1rem; height: 1rem; flex-shrink: 0;" />
                            {{ $location }}
                        </span>
                    @endif
                    <span>{{ $error }}</span>
                </div>

                @if ($this->managed)
                    <div class="ww-card-actions" style="display: flex; align-items: center; gap: 0.25rem; margin-top: 1rem;">
                        {{ $this->duplicateAction }}
                        {{ $this->removeAction }}
                    </div>
                @endif
            @else
                <div wire:poll.{{ $pollInterval }}>
                    <div @class(['ww-layout', 'ww-main' => $hasSettings])>
                        <div class="flex items-center gap-4 flex-shrink-0">
                            <x-filament::icon :icon="$current_icon" title="{{ $condition }}" class="h-32 w-32" />
                            <div class="flex flex-col justify-center">
                                <span class="text-3xl font-bold">{{ $temperature }}{{ $temp_symbol }}</span>
                                <span class="text-base font-medium mt-1">{{ $condition }}</span>
                                <span class="ww-location mt-2 text-gray-500 dark:text-gray-400" style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                    <x-filament::icon icon="heroicon-m-map-pin" class="ww-location-icon" style="width: 1rem; height: 1rem; flex-shrink: 0;" />
                                    {{ $location }}
                                </span>
                            </div>
                        </div>

                        <hr class="ww-layout-divider text-gray-400" style="opacity: 0.2;">

                        <div class="ww-stats">
                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-wind'" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">
                                    {{ __('weather-widget::weather.labels.wind') }}: {{ $wind_speed }} {{ $wind_unit }} {{ $wind_from }}
                                </span>
                                <x-filament::icon icon="heroicon-o-arrow-up"
                                    class="h-4 w-4 text-gray-500 dark:text-gray-400" :style="'transform: rotate(' . $wind_deg . 'deg);'" />
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-humidity'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.humidity') }}: {{ $humidity }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-barometer'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.pressure') }}: {{ $pressure }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-thermometer-colder'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.min_temp') }}: {{ $temp_min }}{{ $temp_symbol }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-thermometer-warmer'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.max_temp') }}: {{ $temp_max }}{{ $temp_symbol }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-thermometer'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">
                                    {{ __('weather-widget::weather.labels.feels_like') }}: {{ $feels_like }}{{ $temp_symbol }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-mist'" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">
                                    {{ __('weather-widget::weather.labels.visibility') }}: {{ $visibility }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-cloudy'" class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.clouds') }}: {{ $clouds ?? '—' }}%</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <x-filament::icon :icon="$iconPrefix . '-umbrella'"
                                    class="h-8 w-8 text-gray-500 dark:text-gray-400" />
                                <span class="text-sm font-semibold">{{ __('weather-widget::weather.labels.rain') }}: {{ $rain }}</span>
                            </div>
                        </div>

                    </div>

                    <hr class="mb-4 mt-3 text-gray-400" style="opacity: 0.2;">

                    <div class="flex justify-center mb-4">
                        <x-filament::tabs>
                            @foreach (['hourly', 'daily', 'both'] as $viewOption)
                                <x-filament::tabs.item
                                    :active="$currentView === $viewOption"
                                    wire:click="setForecastView('{{ $viewOption }}')"
                                >
                                    {{ __("weather-widget::weather.labels.{$viewOption}") }}
                                </x-filament::tabs.item>
                            @endforeach
                        </x-filament::tabs>
                    </div>

                    @if ($currentView !== 'daily')
                        <div class="ww-forecast-row">
                            @foreach ($hourlyForecast as $hour)
                                <div class="ww-forecast-item">
                                    <span class="ww-day-marker">{{ $hour['day'] ?? '' }}</span>
                                    <span class="font-semibold text-md text-gray-500 dark:text-gray-400">
                                        {{ $hour['time'] }}
                                    </span>
                                    <x-filament::icon :icon="$hour['forecast_icon']" :title="$hour['forecast_condition']" class="h-16 w-16 mt-2 mb-2" />
                                    <span class="font-semibold text-lg text-gray-500 dark:text-gray-400">
                                        @if (!empty($hour['is_sunrise']))
                                            {{ __('weather-widget::weather.labels.sunrise') }}
                                        @elseif (!empty($hour['is_sunset']))
                                            {{ __('weather-widget::weather.labels.sunset') }}
                                        @else
                                            {{ $hour['temp'] }}{{ $temp_symbol }}
                                        @endif
                                    </span>
                                    @if ($hour['pop'] !== null)
                                        <span class="ww-pop" title="{{ __('weather-widget::weather.labels.chance_of_precipitation') }}">
                                            <x-filament::icon :icon="$iconPrefix . '-raindrop'" class="ww-pop-icon" />
                                            {{ $hour['pop'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($currentView === 'both')
                        <hr class="ww-forecast-divider mb-4 mt-5 text-gray-400" style="opacity: 0.2;">
                    @endif

                    @if ($currentView !== 'hourly')
                        <div class="ww-forecast-row">
                            @foreach ($dailyForecast as $day)
                                <div class="ww-forecast-item" title="{{ $day['date'] }}">
                                    <span class="font-semibold mt-1 text-md text-gray-500 dark:text-gray-400">
                                        {{ $day['label'] }}
                                    </span>
                                    <x-filament::icon :icon="$day['icon']" :title="$day['condition']" class="h-16 w-16 mt-2 mb-2" />
                                    <span class="text-lg">
                                        <span class="font-bold">{{ $day['max'] }}{{ $temp_short }}</span>
                                        <span class="ww-temp-min text-gray-500 dark:text-gray-400">/ {{ $day['min'] }}{{ $temp_short }}</span>
                                    </span>
                                    @if ($day['pop'] !== null)
                                        <span class="ww-pop" title="{{ __('weather-widget::weather.labels.chance_of_precipitation') }}">
                                            <x-filament::icon :icon="$iconPrefix . '-raindrop'" class="ww-pop-icon" />
                                            {{ $day['pop'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="ww-footer" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-top: 1rem;">
                    <div class="ww-card-actions" style="display: flex; align-items: center; gap: 0.25rem;">
                        @if ($this->managed)
                            {{ $this->duplicateAction }}
                            {{ $this->removeAction }}
                        @endif
                    </div>
                    <div class="ww-updated text-gray-500 dark:text-gray-400" style="margin-top: 0;">
                        @if ($updated_at)
                            @php
                                [$updatedBefore, $updatedAfter] = array_pad(explode('__TIME__', __('weather-widget::weather.labels.updated', ['time' => '__TIME__']), 2), 2, '');
                            @endphp
                            <span>{{ $updatedBefore }}<time
                                wire:key="updated-{{ $updated_unix }}"
                                datetime="{{ \Illuminate\Support\Carbon::createFromTimestamp($updated_unix)->toIso8601String() }}"
                                x-data
                                x-text="new Date({{ $updated_unix }} * 1000).toLocaleTimeString({{ \Illuminate\Support\Js::from(str_replace('_', '-', app()->getLocale())) }}, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })"
                            >{{ $updated_at }}</time>{{ $updatedAfter }}</span>
                        @endif
                        <x-filament::icon-button
                            icon="heroicon-m-arrow-path"
                            color="gray"
                            size="sm"
                            wire:click="refreshWeather"
                            :label="__('weather-widget::weather.labels.refresh')"
                            :tooltip="__('weather-widget::weather.labels.refresh')"
                        />
                    </div>
                    </div>
                </div>
            @endif
        </div>
    </x-filament::card>

    <x-filament-actions::modals />
</x-filament::widget>
