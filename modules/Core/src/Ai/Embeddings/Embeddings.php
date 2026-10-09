<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Embeddings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Ai\AiUnavailable;

/**
 * Cached embeddings and cosine similarity. Each phrase is embedded once per model and stored in
 * `ai_embeddings` (no personal data). If the provider is down, similarity is 0 - callers treat
 * embeddings as a small extra signal only.
 */
final class Embeddings
{
    /** @var array<string, list<float>> */
    private array $memory = [];

    public function __construct(private readonly EmbeddingProvider $provider) {}

    public function similarity(string $a, string $b): float
    {
        $vectors = $this->vectors([$a, $b]);
        $va = $vectors[self::normal($a)] ?? null;
        $vb = $vectors[self::normal($b)] ?? null;

        return $va === null || $vb === null ? 0.0 : self::cosine($va, $vb);
    }

    /**
     * @param  list<string>  $texts
     * @return array<string, list<float>> normalised text => vector
     */
    public function vectors(array $texts): array
    {
        $wanted = array_values(array_unique(array_filter(array_map(self::normal(...), $texts))));
        $missing = array_values(array_diff($wanted, array_keys($this->memory)));

        if ($missing !== []) {
            $model = $this->provider->model();
            $rows = DB::table('ai_embeddings')->where('model', $model)->whereIn('text', $missing)->pluck('vector', 'text');
            foreach ($rows as $text => $vector) {
                $this->memory[(string) $text] = array_values(array_map('floatval', (array) json_decode((string) $vector, true)));
            }

            $missing = array_values(array_diff($missing, array_keys($this->memory)));
            if ($missing !== []) {
                try {
                    foreach (array_chunk($missing, 100) as $chunk) {
                        $vectors = $this->provider->embed($chunk);
                        foreach ($chunk as $i => $text) {
                            $this->memory[$text] = $vectors[$i] ?? [];
                            DB::table('ai_embeddings')->insertOrIgnore(['model' => $model, 'text' => $text, 'vector' => json_encode($this->memory[$text]), 'created_at' => now()]);
                        }
                    }
                } catch (AiUnavailable $e) {
                    Log::warning('Embeddings unavailable', ['error' => $e->getMessage()]);
                }
            }
        }

        return array_intersect_key($this->memory, array_flip($wanted));
    }

    public static function normal(string $text): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', mb_strtolower($text))), 0, 120);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }

        return $na > 0 && $nb > 0 ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }
}
