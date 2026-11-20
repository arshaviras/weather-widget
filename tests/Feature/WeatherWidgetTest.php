<?php

use Arshaviras\WeatherWidget\Widgets\WeatherWidget;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

it('shows current weather and the hourly forecast', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class)
        ->assertSee('9°C')
        ->assertSee('Overcast clouds')
        ->assertSee('Wind: 3.09 m/s N')
        ->assertSee('1014 hPa')
        ->assertSeeText('Updated 17:30')
        ->assertSeeHtml('x-text="new Date('.Carbon::now()->getTimestamp().' * 1000).toLocaleTimeString(');
});

it('shows forecast slots in the city\'s local time, east and west of UTC', function (int $offset, string $firstSlot) {
    fakeWeather($offset);

    $slots = Livewire::test(WeatherWidget::class)->viewData('hourlyForecast');

    expect($slots->first()['time'])->toBe($firstSlot);
})->with([
    'Yerevan (+4h)' => [14400, '22:00'],
    'New York (-5h)' => [-18000, '13:00'],
]);

it('keeps the slot in progress until it ends', function () {
    fakeWeather(0, '2026-11-19 16:30:00');
    Carbon::setTestNow('2026-11-19 19:30:00');

    $times = Livewire::test(WeatherWidget::class)->viewData('hourlyForecast')->pluck('time');

    expect($times->first())->toBe('18:00');
});

it('marks the first slot of a new day', function () {
    fakeWeather();

    $slots = Livewire::test(WeatherWidget::class)->viewData('hourlyForecast');

    expect($slots->firstWhere('time', '01:00')['day'])->toBe('Fri')
        ->and($slots->first()['day'])->toBeNull();
});

it('shows the chance of precipitation for every slot and day, including 0%', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class)->call('setForecastView', 'both');
    $hourly = $widget->viewData('hourlyForecast')->reject(fn ($slot) => ! empty($slot['is_sunrise']) || ! empty($slot['is_sunset']));

    expect($hourly->pluck('pop')->unique()->sort()->values()->all())->toBe(['0%', '35%', '5%', '80%'])
        ->and($widget->viewData('dailyForecast')->pluck('pop')->filter(fn ($pop) => $pop === null))->toBeEmpty();
});

it('switches between hourly, daily and both, and ignores unknown views', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class)
        ->call('setForecastView', 'daily')
        ->assertSee('Today')
        ->assertSee('Fri');

    expect($widget->viewData('hourlyForecast'))->toBeEmpty()
        ->and($widget->viewData('dailyForecast'))->toHaveCount(6);

    $widget->call('setForecastView', 'nonsense')->assertSet('activeForecastView', 'daily');
});

it('shows the divider and highlights the tab in "Both", also when forecastView is not set as an option', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class)->call('setForecastView', 'both');

    expect($widget->html())->toContain('ww-forecast-divider')
        ->and(substr_count($widget->html(), 'fi-active'))->toBe(1);

    $widget->call('setForecastView', 'hourly');

    expect($widget->html())->not->toContain('ww-forecast-divider')
        ->and($widget->viewData('dailyForecast'))->toBeEmpty();
});

it('never shows "-0" degrees', function () {
    fakeWeather(current: ['main' => ['temp' => -0.3, 'feels_like' => -0.4]]);

    Livewire::test(WeatherWidget::class)
        ->assertSee('0°C')
        ->assertDontSee('-0°');
});

it('refreshes on demand, at most once a minute', function () {
    fakeWeather();
    $widget = Livewire::test(WeatherWidget::class);
    $before = count(Http::recorded());

    $widget->call('refreshWeather');
    $afterFirst = count(Http::recorded());

    $widget->call('refreshWeather');
    $afterSecond = count(Http::recorded());

    expect($afterFirst - $before)->toBe(2)
        ->and($afterSecond)->toBe($afterFirst);
});

it('lets each widget refresh on its own and moves "Updated" forward', function () {
    fakeWeather();
    $yerevan = Livewire::test(WeatherWidget::class)->assertSeeText('Updated 17:30');
    $london = Livewire::test(WeatherWidget::class, ['city' => 'London']);

    Carbon::setTestNow('2026-11-19 17:45:00');
    $before = count(Http::recorded());

    $yerevan->call('refreshWeather')->assertSeeText('Updated 17:45');
    $london->call('refreshWeather')->assertSeeText('Updated 17:45');

    expect(count(Http::recorded()) - $before)->toBe(4);
});

it('converts units for imperial', function () {
    fakeWeather(current: ['rain' => ['1h' => 2.54]]);

    Livewire::test(WeatherWidget::class, ['units' => 'imperial'])
        ->assertSee('mph')
        ->assertSee('29.94 inHg')
        ->assertSee('6.2 mi')
        ->assertSee('0.1 in/h');
});

it('saves settings per user and can reset them', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class)
        ->mountAction('settings')
        ->set('mountedActions.0.data.units', 'imperial')
        ->set('mountedActions.0.data.icon_set', 'monochrome')
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertSee('°F');

    $widget = Livewire::test(WeatherWidget::class)->assertSee('°F');
    expect($widget->viewData('iconPrefix'))->toBe('weathermonochrome');

    $widget->mountAction('settings')->callMountedAction(['reset' => true])->assertSee('°C');
});

it('shows the settings as toggle buttons, keeping a non-preset refresh interval from the config', function () {
    fakeWeather();
    config(['weather-widget.refresh_minutes' => 100]);

    $widget = Livewire::test(WeatherWidget::class)->mountAction('settings');
    $schema = $widget->instance()->getSchema('mountedActionSchema0');
    $field = fn (string $name) => collect($schema->getFlatFields(withHidden: true))->first(fn ($f) => $f->getName() === $name);

    expect($field('units'))->toBeInstanceOf(ToggleButtons::class)
        ->and($field('units')->getIcons()['metric'])->toBe(Heroicon::OutlinedGlobeEuropeAfrica)
        ->and($field('refresh_minutes'))->toBeInstanceOf(ToggleButtons::class)
        ->and(array_values($field('refresh_minutes')->getOptions()))->toBe(['1', '5', '10', '20', '30', '100'])
        ->and($field('refresh_minutes')->getLabel())->toBe('Refresh every (min)')
        ->and($field('refresh_minutes')->getState())->toBe(100)
        ->and($field('icon_set')->getIcons()['line'])->toBe(Heroicon::OutlinedCloud)
        ->and($widget->instance()->getMountedAction()->getModalSubmitActionLabel())->toBe('Save');

    $widget->set('mountedActions.0.data.refresh_minutes', 30)->callMountedAction()->assertHasNoErrors();

    expect(Livewire::test(WeatherWidget::class)->viewData('pollInterval'))->toBe('1800s');
});

it('only accepts the offered refresh intervals', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class)
        ->mountAction('settings')
        ->set('mountedActions.0.data.refresh_minutes', 2)
        ->callMountedAction()
        ->assertHasErrors(['mountedActions.0.data.refresh_minutes']);

    $widget = Livewire::test(WeatherWidget::class)
        ->mountAction('settings')
        ->set('mountedActions.0.data.refresh_minutes', 1)
        ->callMountedAction()
        ->assertHasNoErrors();

    expect($widget->viewData('pollInterval'))->toBe('60s');
});

it('hides the gear when user settings are disabled', function () {
    fakeWeather();

    expect(Livewire::test(WeatherWidget::class, ['userSettings' => false])->instance()->settingsAction->isVisible())->toBeFalse();
});

function saveLocation(Testable $widget, string $value): Testable
{
    return $widget->mountAction('settings')->set('mountedActions.0.data.city', $value)->callMountedAction();
}

function locationField(Testable $widget): Select
{
    return collect($widget->instance()->getSchema('mountedActionSchema0')->getFlatFields(withHidden: true))
        ->first(fn ($field) => $field->getName() === 'city');
}

it('shows the location as text on the widget, not as a field', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class)
        ->assertSee('Yerevan')
        ->assertDontSeeHtml('fi-fo-select');
});

it('finds places by name in the settings, naming the region only in the results', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class)->mountAction('settings');
    $results = locationField($widget)->getSearchResults('Gyu');

    expect($results)->toBe(['geo:40.79,43.85|Gyumri, AM' => 'Gyumri, Shirak, AM']);

    $widget->set('mountedActions.0.data.city', array_key_first($results))->callMountedAction();

    Livewire::test(WeatherWidget::class)->assertSee('Gyumri, AM');
    Http::assertSent(fn ($request) => str_contains($request->url(), 'data/2.5/weather') && ($request->data()['lat'] ?? null) == 40.79);
});

it('names the browser location when "Use my location" fills in coordinates', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class)
        ->mountAction('settings')
        ->set('mountedActions.0.data.city', 'geo:40.74,44.86|40.74, 44.86')
        ->assertSet('mountedActions.0.data.city', 'geo:40.74,44.86|Dilijan, AM')
        ->callMountedAction();

    Livewire::test(WeatherWidget::class)->assertSee('Dilijan, AM');
});

it('rejects anything that is not a found place', function (string $value) {
    fakeWeather();

    saveLocation(Livewire::test(WeatherWidget::class), $value);

    Livewire::test(WeatherWidget::class)->assertSee('Yerevan');
})->with([
    'plain city name' => 'Paris',
    'bad coordinates' => 'geo:999,0|Nowhere',
]);

it('hides the location field and keeps the default when location search is off', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class, ['locationSearch' => false])->mountAction('settings');

    expect(locationField($widget)->isHidden())->toBeTrue();

    saveLocation(Livewire::test(WeatherWidget::class, ['locationSearch' => false]), 'geo:40.79,43.85|Gyumri, AM');

    Livewire::test(WeatherWidget::class, ['locationSearch' => false])->assertSee('Yerevan')->assertDontSee('Gyumri');
});

it('resets the location with the other settings', function () {
    fakeWeather();

    saveLocation(Livewire::test(WeatherWidget::class), 'geo:40.79,43.85|Gyumri, AM');
    Livewire::test(WeatherWidget::class)->mountAction('settings')->callMountedAction(['reset' => true]);

    Livewire::test(WeatherWidget::class)->assertSee('Yerevan')->assertDontSee('Gyumri');
});

it('keeps widget options locked and saved choices separate per widget', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class, ['city' => 'London'])->assertSee('London');
    saveLocation(Livewire::test(WeatherWidget::class, ['city' => 'London']), 'geo:40.79,43.85|Gyumri, AM');

    Livewire::test(WeatherWidget::class)->assertSee('Yerevan');
    Livewire::test(WeatherWidget::class, ['city' => 'London'])->assertSee('Gyumri, AM');

    Livewire::test(WeatherWidget::class)->set('units', 'imperial');
})->throws(CannotUpdateLockedPropertyException::class);

it('takes the full width by default and accepts a column span option', function () {
    fakeWeather();

    expect(Livewire::test(WeatherWidget::class)->instance()->getColumnSpan())->toBe('full')
        ->and(Livewire::test(WeatherWidget::class, ['columnSpan' => 1])->instance()->getColumnSpan())->toBe(1)
        ->and(Livewire::test(WeatherWidget::class, ['columnSpan' => ['md' => 1, 'xl' => 2]])->instance()->getColumnSpan())->toBe(['md' => 1, 'xl' => 2]);

    saveLocation(Livewire::test(WeatherWidget::class, ['columnSpan' => 1]), 'geo:40.79,43.85|Gyumri, AM');
    Livewire::test(WeatherWidget::class)->assertSee('Gyumri, AM');

    Livewire::test(WeatherWidget::class)->set('columnSpan', 2);
})->throws(CannotUpdateLockedPropertyException::class);

it('lets the user choose the widget width in the settings', function () {
    fakeWeather();

    $widget = Livewire::test(WeatherWidget::class, ['columnSpan' => ['md' => 1, 'xl' => 2]]);
    $setLayout = fn (string $layout) => $widget->mountAction('settings')->set('mountedActions.0.data.layout', $layout)->callMountedAction();

    expect($widget->instance()->getColumnSpan())->toBe(['md' => 1, 'xl' => 2]);

    $setLayout('half');
    expect($widget->instance()->getColumnSpan())->toBe(1)
        ->and($widget->html())->toContain('--col-span-lg: span 1 / span 1');

    $setLayout('full');
    expect($widget->instance()->getColumnSpan())->toBe('full');

    $setLayout('auto');
    expect($widget->instance()->getColumnSpan())->toBe(['md' => 1, 'xl' => 2]);

    $setLayout('half');
    $widget->mountAction('settings')->callMountedAction(['reset' => true]);
    expect($widget->instance()->getColumnSpan())->toBe(['md' => 1, 'xl' => 2]);
});

it('ignores the saved width when user settings are disabled', function () {
    fakeWeather();

    Livewire::test(WeatherWidget::class)->mountAction('settings')->set('mountedActions.0.data.layout', 'half')->callMountedAction();

    expect(Livewire::test(WeatherWidget::class)->instance()->getColumnSpan())->toBe(1);

    config(['weather-widget.user_settings' => false]);

    expect(Livewire::test(WeatherWidget::class)->instance()->getColumnSpan())->toBe('full');
});

it('covers the widget with a loader while new data loads, but not for background polling', function () {
    fakeWeather();

    $html = Livewire::test(WeatherWidget::class)->html();

    expect($html)
        ->toContain('class="ww-loading"')
        ->toContain('display: none; position: absolute; inset: 0;')
        ->toContain('wire:loading.delay.flex')
        ->toContain('wire:target="refreshWeather, setForecastView, callMountedAction"');
});

it('explains a missing API key without calling the API', function () {
    config(['weather-widget.api_key' => '']);
    Http::fake();

    Livewire::test(WeatherWidget::class)->assertSee('API key is not set');

    Http::assertNothingSent();
});

it('keeps the city selector visible when the weather is unavailable', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    Livewire::test(WeatherWidget::class)
        ->assertSee('not available right now')
        ->assertSeeHtml('wire:poll.60s');
});
