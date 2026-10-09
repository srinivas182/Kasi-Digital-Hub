<?php

declare(strict_types=1);

use Modules\Core\Documents\Generation\GotenbergRenderer;
use Modules\Core\Search\Engines\MeilisearchEngine;
use Modules\Core\Search\SearchDocument;

/*
| Contract checks against the real Meilisearch and Gotenberg containers. They run in a separate
| CI job only when search or PDF code changes (KASI_INTEGRATION=1); everywhere else the fake
| drivers are used.
*/

beforeEach(function (): void {
    if (! env('KASI_INTEGRATION')) {
        $this->markTestSkipped('Set KASI_INTEGRATION=1 with Meilisearch and Gotenberg running.');
    }
});

it('indexes, filters by visibility and tolerates typos in Meilisearch', function (): void {
    config(['kasi.search.meilisearch.index' => 'kasi_ci_'.bin2hex(random_bytes(3))]);
    $engine = new MeilisearchEngine;
    $engine->upsert([
        new SearchDocument('hub', 'a', 'Giyani Central Hub', 'Greater Giyani, Limpopo', '/hubs/giyani-central'),
        new SearchDocument('event', 'b', 'Members coding club', 'Giyani', '/events/b', 'members'),
    ]);

    $deadline = time() + 20;
    do {
        try {
            $hits = $engine->search('Gyani', ['public']);
        } catch (RuntimeException) {
            $hits = []; // index still being created
        }
        usleep(300_000);
    } while ($hits === [] && time() < $deadline); // Meilisearch indexes asynchronously

    expect(array_column($hits, 'ref'))->toBe(['a']);
});

it('renders a real PDF with Gotenberg', function (): void {
    $pdf = (new GotenbergRenderer)->render('<html><body><h1>KasiHub test</h1></body></html>');

    expect($pdf)->toStartWith('%PDF')->and(strlen($pdf))->toBeGreaterThan(500);
});
