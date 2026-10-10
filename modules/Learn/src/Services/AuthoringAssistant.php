<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

use Modules\Core\Ai\AiService;
use Modules\Core\Identity\Models\User;

/**
 * AI writing help for course authors. Drafts are returned as editor documents; the lesson is marked
 * "AI-drafted" until the author edits it, and reviewers can see that.
 */
final readonly class AuthoringAssistant
{
    public function __construct(private AiService $ai) {}

    /** @return array{ok: bool, doc?: array<string, mixed>, reason?: string|null} */
    public function draftLesson(string $course, string $title, string $outline, User $by): array
    {
        $result = $this->ai->run('learn.lesson_draft', ['course' => $course, 'title' => $title, 'outline' => $outline], by: $by);
        if (! $result->ok) {
            return ['ok' => false, 'reason' => $result->reason];
        }

        $content = [];
        foreach ((array) ($result->data['blocks'] ?? []) as $block) {
            $block = (array) $block;
            $text = trim((string) ($block['text'] ?? ''));
            $content[] = match ($block['type'] ?? 'paragraph') {
                'heading' => ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => $text !== '' ? $text : '...']]],
                'bullets' => ['type' => 'bulletList', 'content' => array_values(array_map(
                    static fn ($item): array => ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => trim((string) $item) ?: '...']]]]],
                    array_filter((array) ($block['items'] ?? []), static fn ($i): bool => trim((string) $i) !== ''),
                ))],
                default => ['type' => 'paragraph', 'content' => $text === '' ? [] : [['type' => 'text', 'text' => $text]]],
            };
        }

        return $content === [] ? ['ok' => false, 'reason' => 'invalid'] : ['ok' => true, 'doc' => ['type' => 'doc', 'content' => $content]];
    }

    /**
     * Draft quiz questions from lesson text (marked AI-drafted for reviewers).
     *
     * @return array{ok: bool, questions?: list<array{kind: string, prompt: string, options: list<array{text: string, correct: bool}>, explanation: string|null}>, reason?: string|null}
     */
    public function quizQuestions(string $text, User $by): array
    {
        $result = $this->ai->run('learn.quiz_questions', ['text' => mb_substr($text, 0, 12000)], by: $by);
        $questions = [];
        foreach ($result->ok ? (array) ($result->data['questions'] ?? []) : [] as $q) {
            $q = (array) $q;
            $options = array_values(array_filter(array_map(static fn ($o): array => ['text' => mb_substr(trim((string) ((array) $o)['text']), 0, 200), 'correct' => (bool) (((array) $o)['correct'] ?? false)], (array) ($q['options'] ?? [])),
                static fn (array $o): bool => $o['text'] !== ''));
            if (trim((string) ($q['prompt'] ?? '')) === '' || count($options) < 2 || count(array_filter($options, static fn (array $o): bool => $o['correct'])) !== 1) {
                continue; // drop malformed questions rather than show something wrong
            }
            $questions[] = ['kind' => 'single', 'prompt' => mb_substr(trim((string) $q['prompt']), 0, 500), 'options' => $options, 'explanation' => isset($q['explanation']) ? mb_substr(trim((string) $q['explanation']), 0, 500) : null];
        }

        return $questions === [] ? ['ok' => false, 'reason' => $result->reason ?? 'invalid'] : ['ok' => true, 'questions' => array_slice($questions, 0, 6)];
    }

    /** @return array{ok: bool, outcomes?: list<string>, reason?: string|null} */
    public function outcomes(string $title, string $summary, User $by): array
    {
        $result = $this->ai->run('learn.outcomes', ['title' => $title, 'summary' => $summary], by: $by);
        $outcomes = $result->ok ? array_slice(array_values(array_filter(array_map(static fn ($o): string => mb_substr(trim((string) $o), 0, 200), (array) ($result->data['outcomes'] ?? [])))), 0, 6) : [];

        return $outcomes === [] ? ['ok' => false, 'reason' => $result->reason ?? 'invalid'] : ['ok' => true, 'outcomes' => $outcomes];
    }

    /** @return array{ok: bool, text?: string, reason?: string|null} */
    public function plainLanguage(string $text, User $by): array
    {
        $result = $this->ai->run('learn.plain_language', ['text' => $text], by: $by);

        return $result->ok && trim((string) ($result->data['text'] ?? '')) !== '' ? ['ok' => true, 'text' => trim((string) $result->data['text'])] : ['ok' => false, 'reason' => $result->reason ?? 'invalid'];
    }
}
