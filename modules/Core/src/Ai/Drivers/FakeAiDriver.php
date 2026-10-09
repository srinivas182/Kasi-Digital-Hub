<?php

declare(strict_types=1);

namespace Modules\Core\Ai\Drivers;

use Modules\Core\Ai\AiDriver;
use Modules\Core\Ai\AiRequest;
use Modules\Core\Ai\AiResponse;
use Modules\Core\Ai\AiUnavailable;

/**
 * Deterministic answers for demos and CI - never calls a provider. Tests can queue answers
 * (or failures) with respondWith()/fail(), and read what was sent with sent().
 */
final class FakeAiDriver implements AiDriver
{
    /** @var list<string|null> */
    private static array $queue = [];

    /** @var list<AiRequest> */
    private static array $sent = [];

    public static function respondWith(string ...$answers): void
    {
        array_push(self::$queue, ...$answers);
    }

    /** The next call fails as if the provider were down. */
    public static function fail(): void
    {
        self::$queue[] = null;
    }

    /** @return list<AiRequest> */
    public static function sent(): array
    {
        return self::$sent;
    }

    public static function reset(): void
    {
        self::$queue = [];
        self::$sent = [];
    }

    public function complete(AiRequest $request): AiResponse
    {
        self::$sent[] = $request;

        if (self::$queue !== []) {
            $answer = array_shift(self::$queue);
            if ($answer === null) {
                throw new AiUnavailable('Fake provider failure');
            }
        } else {
            $answer = $this->generated($request);
        }

        return new AiResponse($answer, (int) ceil(strlen($request->system.$request->user) / 4), (int) ceil(strlen($answer) / 4), $request->model);
    }

    /** A plausible answer built from the request, clearly marked as demo text. */
    private function generated(AiRequest $request): string
    {
        $words = array_slice(preg_split('/\s+/', trim($request->user)) ?: [], 0, 60);
        $summary = trim(implode(' ', $words));

        if ($request->outputFields === []) {
            return '(Demo AI) '.$summary;
        }

        $object = [];
        foreach ($request->outputFields as $field) {
            $object[$field] = match (true) {
                $field === 'verdict' => 'allow',
                $field === 'reasons' => [],
                // List fields (bullets, questions, must_skills...) get short demo items.
                in_array($field, ['bullets', 'questions', 'must_skills', 'nice_skills', 'skills'], true) => ['(Demo AI) '.ucfirst(str_replace('_', ' ', rtrim($field, 's')))],
                default => '(Demo AI) '.$summary,
            };
        }

        return (string) json_encode($object);
    }
}
