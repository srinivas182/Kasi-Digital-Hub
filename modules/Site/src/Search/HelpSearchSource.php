<?php

declare(strict_types=1);

namespace Modules\Site\Search;

use Illuminate\Support\Facades\DB;
use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\Site\Help\HelpText;

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
        foreach (DB::table('help_articles')->where('status', 'published')->get() as $a) {
            yield $this->article($a);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        if (in_array($ref, self::QUESTIONS, true)) {
            return $this->document($ref);
        }
        $a = DB::table('help_articles')->where('slug', $ref)->where('status', 'published')->first();

        return $a !== null ? $this->article($a) : null;
    }

    private function document(string $q): SearchDocument
    {
        return new SearchDocument('help', $q, __("site.help.q.{$q}", [], 'en'), __("site.help.a.{$q}", [], 'en'), "/help#q-{$q}");
    }

    private function article(\stdClass $a): SearchDocument
    {
        $v = (array) $a;

        return new SearchDocument('help', (string) $v['slug'], (string) $v['title'], (string) $v['summary'].' '.HelpText::plain((string) $v['body']), '/help/'.$v['slug']);
    }
}
