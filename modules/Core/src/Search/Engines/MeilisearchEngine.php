<?php

declare(strict_types=1);

namespace Modules\Core\Search\Engines;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchEngine;
use RuntimeException;

/**
 * Meilisearch over its REST API (typo tolerance, ranking). One index for all public content,
 * filtered by type and visibility.
 */
final class MeilisearchEngine implements SearchEngine
{
    public function upsert(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $this->ensureIndex();
        $this->send('post', "/indexes/{$this->index()}/documents", array_map(static fn (SearchDocument $d): array => [
            'id' => self::id($d->type, $d->ref), 'type' => $d->type, 'ref' => $d->ref, 'title' => $d->title, 'body' => $d->body,
            'url' => $d->url, 'visibility' => $d->visibility, 'hub_id' => $d->hubId,
        ], $documents));
    }

    public function delete(string $type, string $ref): void
    {
        $this->send('delete', "/indexes/{$this->index()}/documents/".self::id($type, $ref));
    }

    public function flush(string $type): void
    {
        $this->ensureIndex();
        $this->send('post', "/indexes/{$this->index()}/documents/delete", ['filter' => 'type = "'.addslashes($type).'"']);
    }

    public function search(string $query, array $visibilities, int $limit = 30): array
    {
        $filter = 'visibility IN ['.implode(', ', array_map(static fn (string $v): string => '"'.$v.'"', $visibilities)).']';
        $hits = (array) $this->send('post', "/indexes/{$this->index()}/search", ['q' => $query, 'filter' => $filter, 'limit' => $limit])['hits'];

        return array_values(array_map(static fn (array $h): array => [
            'type' => (string) $h['type'], 'ref' => (string) $h['ref'], 'title' => (string) $h['title'], 'body' => (string) ($h['body'] ?? ''), 'url' => (string) $h['url'],
        ], $hits));
    }

    private function ensureIndex(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $this->request()->post('/indexes', ['uid' => $this->index(), 'primaryKey' => 'id']); // 202 or "already exists"
        $this->send('patch', "/indexes/{$this->index()}/settings", [
            'searchableAttributes' => ['title', 'body'],
            'filterableAttributes' => ['type', 'visibility', 'hub_id'],
        ]);
        $done = true;
    }

    private static function id(string $type, string $ref): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '_', $type.'_'.$ref) ?? $type;
    }

    private function index(): string
    {
        return (string) config('kasi.search.meilisearch.index');
    }

    /**
     * @param  array<int|string, mixed>  $body
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $body = []): array
    {
        $response = $this->request()->{$method}($path, $body);
        if (! $response->successful()) {
            throw new RuntimeException("Meilisearch {$method} {$path} failed: ".$response->status());
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl((string) config('kasi.search.meilisearch.url'))->timeout(5)->acceptJson();
        $key = config('kasi.search.meilisearch.key');

        return is_string($key) && $key !== '' ? $request->withToken($key) : $request;
    }
}
