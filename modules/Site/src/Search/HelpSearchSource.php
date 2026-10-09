<?php

declare(strict_types=1);

namespace Modules\Site\Search;

use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;

/** Help questions and answers (indexed in the platform's default language). */
final class HelpSearchSource implements SearchSource
{
    public const QUESTIONS = ['free', 'sign_in', 'forgot_pin', 'no_smartphone', 'documents', 'privacy', 'whatsapp', 'hub', 'age', 'delete'];

    public function type(): string
    {
        return 'help';
    }

    public function all(): iterable
    {
        foreach (self::QUESTIONS as $q) {
            yield $this->document($q);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        return in_array($ref, self::QUESTIONS, true) ? $this->document($ref) : null;
    }

    private function document(string $q): SearchDocument
    {
        return new SearchDocument('help', $q, __("site.help.q.{$q}", [], 'en'), __("site.help.a.{$q}", [], 'en'), "/help#q-{$q}");
    }
}
