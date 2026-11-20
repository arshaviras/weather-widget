<?php

namespace Arshaviras\WeatherWidget\Widgets;

use Arshaviras\WeatherWidget\Support\WidgetList;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

class WeatherWidgets extends Widget
{
    protected string $view = 'weather-widget::widgets';

    protected int|string|array $columnSpan = 'full';

    #[Locked]
    public array $options = [];

    #[Locked]
    public string $listKey = 'default';

    private const CARD_OPTIONS = [
        'city', 'units', 'refreshMinutes', 'iconSet', 'iconVariant',
        'forecastView', 'userSettings', 'locationSearch', 'columnSpan',
    ];

    /**
     * @return array<int, string>
     */
    public function getCardIds(): array
    {
        return $this->list()->ids();
    }

    public function getCardProperties(string $id): array
    {
        return [
            ...Arr::only($this->options, self::CARD_OPTIONS),
            'storageKey' => $id,
            'managed' => true,
            'listKey' => $this->listKey,
        ];
    }

    #[On('weather-widget-duplicate')]
    public function duplicateCard(string $id): void
    {
        if ($this->list()->duplicate($id)) {
            $this->dispatch('weather-widgets-changed');
        }
    }

    #[On('weather-widget-remove')]
    public function removeCard(string $id): void
    {
        if ($this->list()->remove($id)) {
            $this->dispatch('weather-widgets-changed');
        }
    }

    private function list(): WidgetList
    {
        return new WidgetList(preg_replace('/[^A-Za-z0-9_-]/', '', $this->listKey) ?: 'default');
    }
}
