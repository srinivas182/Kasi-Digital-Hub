<?php

declare(strict_types=1);

namespace Modules\Site\Http\Controllers;

use App\Support\Seo\Seo;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Site\Models\Enquiry;
use Modules\Site\Support\PublicHubs;

/**
 * Information pages of the public website. Text lives in the translation files (site.*).
 */
final class PageController
{
    public function employers(): Response
    {
        return $this->page('Site/Employers', 'employers', '/employers', ['form' => 'employer']);
    }

    public function funders(): Response
    {
        return $this->page('Site/Funders', 'funders', '/funders', ['form' => 'funder']);
    }

    public function about(): Response
    {
        return $this->page('Site/About', 'about', '/about');
    }

    public function help(): Response
    {
        $questions = ['free', 'sign_in', 'forgot_pin', 'no_smartphone', 'documents', 'privacy', 'whatsapp', 'hub', 'age', 'delete'];

        return $this->page('Site/Help', 'help', '/help', [
            'questions' => $questions,
        ], [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (string $q): array => [
                '@type' => 'Question',
                'name' => __("site.help.q.{$q}"),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => __("site.help.a.{$q}")],
            ], $questions),
        ]]);
    }

    public function contact(): Response
    {
        return $this->page('Site/Contact', 'contact', '/contact', [
            'topics' => Enquiry::TOPICS,
            'hubs' => array_map(static fn (array $hub): array => ['slug' => $hub['slug'], 'name' => $hub['name']], PublicHubs::all()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  list<array<string, mixed>>  $jsonLd
     */
    private function page(string $component, string $key, string $path, array $props = [], array $jsonLd = []): Response
    {
        return Inertia::render($component, [
            ...$props,
            'seo' => Seo::page(__("site.{$key}.title"), __("site.{$key}.seo_description"), $path, $jsonLd),
        ]);
    }
}
