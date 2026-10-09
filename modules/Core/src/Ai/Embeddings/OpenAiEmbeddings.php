<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Embeddings;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Core\Ai\AiUnavailable;

/** OpenAI embeddings (small model by default; see config kasi.embeddings). */
final class OpenAiEmbeddings implements EmbeddingProvider
{
    public function embed(array $texts): array
    {
        $key = (string) config('kasi.ai.keys.openai');
        if ($key === '') {
            throw new AiUnavailable('OPENAI_API_KEY is not set.');
        }

        try {
            $response = Http::timeout(20)->withToken($key)->post('https://api.openai.com/v1/embeddings', ['model' => $this->model(), 'input' => $texts]);
        } catch (ConnectionException $e) {
            throw new AiUnavailable($e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new AiUnavailable('Embeddings API returned '.$response->status());
        }

        /** @var list<array{embedding: list<float>, index: int}> $data */
        $data = (array) $response->json('data');
        usort($data, static fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return array_map(static fn (array $row): array => array_map('floatval', $row['embedding']), $data);
    }

    public function model(): string
    {
        return (string) config('kasi.embeddings.openai_model');
    }
}
