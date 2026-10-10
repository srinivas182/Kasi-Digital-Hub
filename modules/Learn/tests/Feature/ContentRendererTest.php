<?php

declare(strict_types=1);

use Modules\Learn\Services\ContentRenderer;

function doc(array ...$nodes): array
{
    return ['type' => 'doc', 'content' => $nodes];
}

function para(string $text, array $marks = []): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, 'marks' => $marks]]];
}

it('renders the allowed nodes and marks', function (): void {
    $html = app(ContentRenderer::class)->html(doc(
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Title']]],
        para('Bold', [['type' => 'bold']]),
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [para('One')]]]],
        ['type' => 'blockquote', 'content' => [para('Tip')]],
        para('Site', [['type' => 'link', 'attrs' => ['href' => 'https://example.org']]]),
    ));

    expect($html)->toBe('<h2>Title</h2><p><strong>Bold</strong></p><ul><li><p>One</p></li></ul><aside class="callout"><p>Tip</p></aside><p><a href="https://example.org" rel="noopener nofollow" target="_blank">Site</a></p>');
});

it('never writes out scripts, unsafe links, unknown nodes or outside images', function (): void {
    $html = app(ContentRenderer::class)->html(doc(
        para('<script>alert(1)</script>'),
        para('click', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
        para('data', [['type' => 'link', 'attrs' => ['href' => '//evil.example']]]),
        ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.example']],
        ['type' => 'image', 'attrs' => ['src' => 'https://evil.example/x.png', 'alt' => 'x']],
        ['type' => 'image', 'attrs' => ['src' => '/learn/media/01J9ZZZZZZZZZZZZZZZZZZZZZZ" onerror="alert(1)', 'alt' => 'x']],
        ['type' => 'heading', 'attrs' => ['level' => 1, 'onclick' => 'x'], 'content' => [['type' => 'text', 'text' => 'H']]],
    ));

    expect($html)->not->toContain('<script')->not->toContain('javascript:')->not->toContain('evil.example')->not->toContain('onerror')
        ->not->toContain('<iframe')->not->toContain('<h1')->toContain('&lt;script&gt;')->toContain('<h2>H</h2>');
});

it('finds accessibility problems', function (): void {
    $problems = app(ContentRenderer::class)->problems(doc(
        ['type' => 'heading', 'attrs' => ['level' => 4], 'content' => [['type' => 'text', 'text' => 'Skipped levels']]],
        ['type' => 'image', 'attrs' => ['src' => '/learn/media/01J9ABCDEFGHJKMNPQRSTVWXYZ', 'alt' => '']],
        para('here', [['type' => 'link', 'attrs' => ['href' => 'https://example.org']]]),
    ));

    expect($problems)->toContain('heading_order', 'image_alt', 'link_text')
        ->and(app(ContentRenderer::class)->problems(doc()))->toBe(['empty']);
});
