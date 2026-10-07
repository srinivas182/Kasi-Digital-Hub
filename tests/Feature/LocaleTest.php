<?php

declare(strict_types=1);

use App\Support\Locale\Languages;
use Inertia\Testing\AssertableInertia as Assert;

it('switches the interface language and remembers it', function (): void {
    $response = $this->from('/')->post('/locale', ['locale' => 'zu']);

    $response->assertRedirect('/')->assertCookie(Languages::COOKIE, 'zu', encrypted: false);

    $this->withUnencryptedCookie(Languages::COOKIE, 'zu')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'zu')
            ->where('i18n.strings', fn ($strings) => $strings['nav.hub.home'] === 'Ekhaya'
                && $strings['common.skip_to_content'] === 'Skip to main content' // falls back to English
                && ! isset($strings['_note']))
        );
});

it('rejects languages that are not offered', function (): void {
    $this->from('/')->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it('ignores an unknown language cookie', function (): void {
    $this->withUnencryptedCookie(Languages::COOKIE, 'xx')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en'));
});

it('hides draft languages in production unless explicitly enabled', function (): void {
    app()->detectEnvironment(fn () => 'production');
    config(['kasi.locale.show_drafts' => null]);
    expect(array_keys(Languages::available()))->toBe(['en']);

    config(['kasi.locale.show_drafts' => 'true']);
    expect(array_keys(Languages::available()))->toBe(['en', 'zu', 'ts']);
});
