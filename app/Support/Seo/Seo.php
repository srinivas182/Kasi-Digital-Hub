<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Search-engine and link-preview details for a page. Passed to the page as the "seo"
 * prop and rendered into <head> by the root Blade view, so crawlers and WhatsApp/Facebook
 * link previews see them even without JavaScript.
 */
final class Seo
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd  Structured data (schema.org) blocks
     * @return array{title: string, description: string, canonical: string, image: string, type: string, noindex: bool, jsonLd: list<array<string, mixed>>}
     */
    public static function page(string $title, string $description, ?string $path = null, array $jsonLd = [], bool $noindex = false): array
    {
        return [
            'title' => $title,
            'description' => mb_substr($description, 0, 300),
            'canonical' => rtrim((string) config('kasi.brand.url'), '/').'/'.ltrim($path ?? request()->path(), '/'),
            'image' => rtrim((string) config('kasi.brand.url'), '/').'/icons/icon-512.png',
            'type' => 'website',
            'noindex' => $noindex,
            'jsonLd' => $jsonLd,
        ];
    }

    /** @return array<string, mixed> */
    public static function organisation(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('kasi.brand.full_name'),
            'alternateName' => config('kasi.brand.name'),
            'url' => config('kasi.brand.url'),
            'logo' => rtrim((string) config('kasi.brand.url'), '/').'/icons/icon-512.png',
            'email' => config('kasi.brand.contact_email'),
            'parentOrganization' => ['@type' => 'Organization', 'name' => config('kasi.brand.owner')],
            'areaServed' => 'ZA',
        ];
    }
}
