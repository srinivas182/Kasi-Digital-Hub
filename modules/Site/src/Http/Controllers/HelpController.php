<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\AuditLogger;
use Modules\Site\Help\HelpText;

/**
 * Help centre (ADR-029): public articles with "was this helpful?", and the content team's editing and review
 * (an article is published only after a second person reviews it).
 */
final class HelpController
{
    public const CATEGORIES = ['start', 'account', 'work', 'learn', 'business', 'mentoring', 'hubs', 'privacy'];

    public function article(string $slug): Response
    {
        $a = DB::table('help_articles')->where('slug', $slug)->where('status', 'published')->first();
        abort_if($a === null, 404);

        return Inertia::render('Site/HelpArticle', [
            'article' => ['slug' => (string) $a->slug, 'title' => (string) $a->title, 'category' => (string) $a->category, 'html' => HelpText::html((string) $a->body),
                'lastChecked' => $a->last_checked_on],
            'related' => DB::table('help_articles')->where('category', $a->category)->where('status', 'published')->where('slug', '!=', $slug)->limit(5)->get(['slug', 'title']),
            'seo' => Seo::page((string) $a->title, (string) $a->summary, '/help/'.$a->slug),
        ]);
    }

    public function feedback(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        DB::table('help_articles')->where('slug', $slug)->where('status', 'published')->increment($data['helpful'] ? 'helpful_yes' : 'helpful_no');

        return response()->json(['ok' => true]);
    }

    // --- Content team -----------------------------------------------------------------------

    public function manage(): Response
    {
        return Inertia::render('Site/HelpAdmin', [
            'articles' => DB::table('help_articles')->orderBy('category')->orderBy('title')
                ->get(['id', 'slug', 'category', 'title', 'summary', 'body', 'status', 'needs_review', 'last_checked_on', 'helpful_yes', 'helpful_no', 'author_id', 'reviewer_id']),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function save(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:help_articles,id'], 'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('help_articles', 'slug')->ignore($request->input('id'))],
            'category' => ['required', Rule::in(self::CATEGORIES)], 'title' => ['required', 'string', 'max:160'], 'summary' => ['required', 'string', 'max:300'], 'body' => ['required', 'string', 'max:20000'],
        ]);
        $values = [...array_intersect_key($data, array_flip(['slug', 'category', 'title', 'summary', 'body'])), 'status' => 'review', 'author_id' => $user->id, 'updated_at' => now()];
        if (isset($data['id'])) {
            DB::table('help_articles')->where('id', $data['id'])->update($values);
        } else {
            DB::table('help_articles')->insert([...$values, 'created_at' => now()]);
        }
        $audit->record('help.article_saved', meta: ['slug' => $data['slug']], actor: $user);

        return back()->with('status', __('site.help_admin.sent_for_review'));
    }

    /** A second person reviews and publishes (or sends back); publishing sets the "last checked" date. */
    public function review(Request $request, int $article, AuditLogger $audit): RedirectResponse
    {
        $user = $this->user($request);
        $a = DB::table('help_articles')->where('id', $article)->first();
        abort_if($a === null, 404);
        $data = $request->validate(['publish' => ['required', 'boolean']]);
        if ($data['publish'] && $a->author_id === $user->id) {
            return back()->withErrors(['help' => __('site.help_admin.not_own')]);
        }
        DB::table('help_articles')->where('id', $article)->update($data['publish']
            ? ['status' => 'published', 'needs_review' => false, 'reviewer_id' => $user->id, 'last_checked_on' => now()->toDateString(), 'updated_at' => now()]
            : ['status' => 'draft', 'updated_at' => now()]);
        $audit->record('help.article_'.($data['publish'] ? 'published' : 'returned'), meta: ['slug' => $a->slug], actor: $user);

        return back()->with('status', __('work.saved'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
