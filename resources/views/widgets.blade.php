<x-filament-widgets::widget>
    <div {{ (new \Illuminate\View\ComponentAttributeBag)->grid(['default' => 1, 'lg' => 2])->class(['fi-wi', 'ww-widgets']) }}>
        @foreach ($this->getCardIds() as $cardId)
            <div wire:key="weather-card-{{ $listKey }}-{{ $cardId }}" style="display: contents;">
                @livewire(
                    \Arshaviras\WeatherWidget\Widgets\WeatherWidget::class,
                    $this->getCardProperties($cardId),
                    key('weather-widget-' . $listKey . '-' . $cardId)
                )
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
