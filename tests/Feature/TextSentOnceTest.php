<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Locale\Languages;

/*
| S22: the text (~180 KB) is sent once per language and text version, then the browser keeps it. A later
| Inertia visit that says it already has that version gets no text; another language gets its own text.
*/
it('sends the text once and skips it on later page visits', function (): void {
    $version = (string) app(HandleInertiaRequests::class)->version(request());
    $first = $this->get('/help', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest']);
    expect($first->json('props.i18nStrings'))->toBeArray()->not->toBeEmpty();
    $key = 'strings.en.'.Languages::version('en');

    $again = $this->get('/help', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Except-Once-Props' => $key]);
    expect($again->json('props.i18nStrings'))->toBeNull()
        ->and(strlen((string) $again->getContent()))->toBeLessThan(strlen((string) $first->getContent()) / 3);

    // A stale version (e.g. after a deployment) gets the text again.
    $stale = $this->get('/help', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Except-Once-Props' => 'strings.en.old']);
    expect($stale->json('props.i18nStrings'))->toBeArray()->not->toBeEmpty();
});
