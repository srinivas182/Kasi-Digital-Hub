<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Embeddings;

/**
 * Deterministic embeddings for demos and CI: a hashed bag of word stems, so phrases that share
 * words ("cash handling" / "handling cash payments") are similar and unrelated ones are not.
 */
final class FakeEmbeddings implements EmbeddingProvider
{
    private const DIMENSIONS = 64;

    public function embed(array $texts): array
    {
        return array_map(function (string $text): array {
            $vector = array_fill(0, self::DIMENSIONS, 0.0);
            foreach (preg_split('/[^a-z0-9]+/', mb_strtolower($text)) ?: [] as $word) {
                if (strlen($word) < 3) {
                    continue;
                }
                $stem = substr($word, 0, 5);
                $vector[crc32($stem) % self::DIMENSIONS] += 1.0;
            }

            return array_values($vector);
        }, $texts);
    }

    public function model(): string
    {
        return 'fake-embeddings';
    }
}
