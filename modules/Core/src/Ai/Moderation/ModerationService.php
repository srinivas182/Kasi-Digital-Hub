<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Moderation;

use Modules\Core\Ai\AiService;
use Modules\Core\Ai\Models\ModerationFlag;
use Modules\Core\Identity\Models\User;

/**
 * Checks user content: fast rules first (common South African job scams, spam, abuse), the AI
 * only for longer texts the rules let through. Anything doubtful goes to the review queue in
 * the admin console - this service never deletes content by itself.
 */
final readonly class ModerationService
{
    /** Patterns that always need a person to look (case-insensitive). */
    private const RULES = [
        'Asks applicants to pay' => '/\b(pay|send|deposit)\b.{0,40}\b(fee|money|R\s?\d+|rand)\b|\b(registration|admin|processing|uniform|training)\s+fee\b/i',
        'Pyramid or "get rich" scheme' => '/\b(pyramid|stokvel investment|double your money|guaranteed returns?|get rich)\b/i',
        'Asks for banking details or PINs' => '/\b(bank(ing)? details|card number|pin number|otp)\b/i',
        'Contact only by private message' => '/\bwhats\s?app\s+only\b|\bcontact\s+me\s+privately\b/i',
        'Shouting or spam' => '/(.)\1{9,}|(\b\w+\b)(\s+\2\b){4,}/i',
    ];

    public function __construct(private AiService $ai) {}

    /**
     * @param  list<string>  $extraReasons  Reasons a portal's own rules found (e.g. KasiWork fair-language rules)
     * @return array{verdict: 'allow'|'flag', reasons: list<string>, flag: ModerationFlag|null}
     */
    public function check(string $text, string $subjectType, string $subjectId, ?User $author = null, bool $useAi = true, array $extraReasons = []): array
    {
        $reasons = $extraReasons;
        foreach (self::RULES as $reason => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $reasons[] = $reason;
            }
        }
        $source = 'rules';

        if ($reasons === [] && $useAi && mb_strlen($text) >= 40) {
            $result = $this->ai->run('core.moderation', ['text' => $text], $author);
            if ($result->ok && ($result->data['verdict'] ?? 'allow') === 'flag') {
                $reasons = array_values(array_filter(array_map('strval', (array) ($result->data['reasons'] ?? ['Flagged by the automatic check']))));
                $reasons = $reasons ?: ['Flagged by the automatic check'];
                $source = 'ai';
            }
        }

        if ($reasons === []) {
            return ['verdict' => 'allow', 'reasons' => [], 'flag' => null];
        }

        $flag = ModerationFlag::query()->updateOrCreate(
            ['subject_type' => $subjectType, 'subject_id' => $subjectId, 'status' => 'pending'],
            ['author_id' => $author?->id, 'excerpt' => mb_substr($text, 0, 1000), 'reasons' => $reasons, 'source' => $source],
        );

        return ['verdict' => 'flag', 'reasons' => $reasons, 'flag' => $flag];
    }
}
