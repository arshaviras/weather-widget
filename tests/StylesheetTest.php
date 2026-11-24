<?php

it('styles only the widget, so it cannot override the classes of the rest of the page', function () {
    $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents(__DIR__.'/../resources/dist/weather-widget.css'));
    $css = preg_replace('~@[a-z-]+[^{;]*\{~', '', $css);

    preg_match_all('~([^{}]+)\{~', $css, $rules);

    $selectors = collect($rules[1])
        ->flatMap(fn (string $list) => explode(',', $list))
        ->map(fn (string $selector) => trim($selector));

    expect($selectors->count())->toBeGreaterThan(40);

    foreach ($selectors as $selector) {
        expect($selector)->toMatch('/^(\.dark )?\.ww-/');
    }
});
