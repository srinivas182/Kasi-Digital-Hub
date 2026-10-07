<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Route::middleware('web')->get('/_test/abort/{status}', fn (int $status) => abort($status));
});

it('renders a branded page for common errors', function (int $status): void {
    $this->get("/_test/abort/{$status}")
        ->assertStatus($status)
        ->assertInertia(fn (Assert $page) => $page->component('Core/Error')->where('status', $status));
})->with([403, 404, 419, 429, 503]);

it('renders a branded 404 for unknown pages', function (): void {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Core/Error')->where('status', 404));
});

it('renders a branded 500 when debug mode is off', function (): void {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $this->get('/_test/boom')
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page->component('Core/Error')->where('status', 500));
});

it('keeps JSON errors for API clients', function (): void {
    $this->getJson('/this-page-does-not-exist')->assertNotFound()->assertJsonStructure(['message']);
});
