<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

use Illuminate\Support\Facades\DB;
use Modules\Core\Identity\Models\User;

/**
 * Translates user content (cached). The "translate" button for users is switched on only after
 * native speakers have checked quality per language (see docs/content-inputs.md).
 */
final readonly class TranslationService
{
    private const LANGUAGES = ['en' => 'English', 'zu' => 'isiZulu', 'ts' => 'Xitsonga', 'nso' => 'Sepedi', 've' => 'Tshivenda', 'xh' => 'isiXhosa', 'af' => 'Afrikaans', 'st' => 'Sesotho'];

    public function __construct(private AiService $ai) {}

    public function translate(string $text, string $locale, ?User $for = null): ?string
    {
        $language = self::LANGUAGES[$locale] ?? null;
        if ($language === null || trim($text) === '') {
            return null;
        }

        $hash = hash('sha256', $text);
        $cached = DB::table('ai_translations')->where('source_hash', $hash)->where('locale', $locale)->value('text');
        if (is_string($cached)) {
            return $cached;
        }

        $result = $this->ai->run('core.translate', ['text' => $text, 'language' => $language], $for);
        if (! $result->ok) {
            return null;
        }

        $translation = (string) $result->data['translation'];
        DB::table('ai_translations')->insertOrIgnore(['source_hash' => $hash, 'locale' => $locale, 'text' => $translation, 'created_at' => now(), 'updated_at' => now()]);

        return $translation;
    }
}
