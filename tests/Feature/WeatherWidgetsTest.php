<?php

use Arshaviras\WeatherWidget\Support\WeatherPreferences;
use Arshaviras\WeatherWidget\Support\WidgetList;
use Arshaviras\WeatherWidget\Widgets\WeatherWidget;
use Arshaviras\WeatherWidget\Widgets\WeatherWidgets;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function card(string $id, array $extra = []): Testable
{
    return Livewire::test(WeatherWidget::class, ['storageKey' => $id, 'managed' => true, ...$extra]);
}

it('starts with one card that keeps the settings of a plain widget', function () {
    fakeWeather();
    (new WeatherPreferences(scope: 'default'))->update(['units' => 'imperial']);

    $group = Livewire::test(WeatherWidgets::class);

    expect($group->instance()->getCardIds())->toBe(['default'])
        ->and($group->html())->toContain('°F');
});

it('duplicates a card with its location and settings, right after it', function () {
    fakeWeather();
    (new WeatherPreferences(scope: 'default'))->update(['city' => 'geo:40.79,43.85|Gyumri, AM', 'units' => 'imperial']);

    $group = Livewire::test(WeatherWidgets::class)
        ->call('duplicateCard', 'default')
        ->assertDispatched('weather-widgets-changed');

    $ids = $group->instance()->getCardIds();

    expect($ids)->toHaveCount(2)
        ->and($ids[0])->toBe('default')
        ->and((new WeatherPreferences(scope: $ids[1]))->get('city'))->toBe('geo:40.79,43.85|Gyumri, AM')
        ->and((new WeatherPreferences(scope: $ids[1]))->get('units'))->toBe('imperial');

    (new WeatherPreferences(scope: $ids[1]))->update(['units' => 'metric']);
    expect((new WeatherPreferences(scope: 'default'))->get('units'))->toBe('imperial');
});

it('removes a card and its settings, but never the last one', function () {
    fakeWeather();
    $group = Livewire::test(WeatherWidgets::class)->call('duplicateCard', 'default');
    $copy = $group->instance()->getCardIds()[1];
    (new WeatherPreferences(scope: $copy))->update(['units' => 'imperial']);

    $group->call('removeCard', $copy)->assertDispatched('weather-widgets-changed');

    expect($group->instance()->getCardIds())->toBe(['default'])
        ->and((new WeatherPreferences(scope: $copy))->stored())->toBe([]);

    $group->call('removeCard', 'default')->assertNotDispatched('weather-widgets-changed');

    expect($group->instance()->getCardIds())->toBe(['default']);
});

it('wraps every card in a keyed element, in list order, so the page updates the right cards', function () {
    fakeWeather();
    $group = Livewire::test(WeatherWidgets::class)->call('duplicateCard', 'default');
    [$first, $second] = $group->instance()->getCardIds();

    $group->call('duplicateCard', $first);
    $ids = $group->instance()->getCardIds();

    preg_match_all('/wire:key="weather-card-default-([A-Za-z0-9_-]+)"/', $group->html(), $m);

    expect($m[1])->toBe($ids)
        ->and($ids[0])->toBe('default')
        ->and($ids[2])->toBe($second);

    $group->call('removeCard', $ids[1]);
    preg_match_all('/wire:key="weather-card-default-([A-Za-z0-9_-]+)"/', $group->html(), $m);

    expect($m[1])->toBe(['default', $second]);
});

it('stops at max_widgets', function () {
    fakeWeather();
    config(['weather-widget.max_widgets' => 2]);
    $group = Livewire::test(WeatherWidgets::class)->call('duplicateCard', 'default')->call('duplicateCard', 'default');

    expect($group->instance()->getCardIds())->toHaveCount(2);
});

it('ignores unknown cards', function () {
    fakeWeather();

    Livewire::test(WeatherWidgets::class)->call('duplicateCard', 'nope')->call('removeCard', 'nope')
        ->assertNotDispatched('weather-widgets-changed');
});

it('shows Duplicate and Remove only on managed cards, and hides Remove on the last card', function () {
    fakeWeather();

    $plain = Livewire::test(WeatherWidget::class)->instance();
    expect($plain->duplicateAction->isVisible())->toBeFalse()
        ->and($plain->removeAction->isVisible())->toBeFalse();

    $only = card('default')->instance();
    expect($only->duplicateAction->isVisible())->toBeTrue()
        ->and($only->removeAction->isVisible())->toBeFalse();

    (new WidgetList)->duplicate('default');

    $first = card('default')->instance();
    expect($first->removeAction->isVisible())->toBeTrue();
});

it('hides Duplicate when the limit is reached', function () {
    fakeWeather();
    config(['weather-widget.max_widgets' => 1]);

    expect(card('default')->instance()->duplicateAction->isVisible())->toBeFalse();
});

it('asks the group to duplicate or remove when the buttons are used', function () {
    fakeWeather();
    (new WidgetList)->duplicate('default');

    card('default')
        ->callAction('duplicate')
        ->assertDispatchedTo(WeatherWidgets::class, 'weather-widget-duplicate', id: 'default');

    card('default')
        ->callAction('remove')
        ->assertDispatchedTo(WeatherWidgets::class, 'weather-widget-remove', id: 'default');
});

it('passes group options to every card, but not storage or management settings', function () {
    fakeWeather();

    $group = Livewire::test(WeatherWidgets::class, ['options' => ['units' => 'imperial', 'storageKey' => 'hack', 'managed' => false]]);

    expect($group->instance()->getCardProperties('default'))->toBe([
        'units' => 'imperial',
        'storageKey' => 'default',
        'managed' => true,
        'listKey' => 'default',
    ]);
});
