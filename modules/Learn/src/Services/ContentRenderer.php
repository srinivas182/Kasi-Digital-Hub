<?php

declare(strict_types=1);

namespace Modules\Learn\Services;

/**
 * Turns the editor's structured document into safe HTML by walking an allow-list of node types and
 * marks. Anything else - scripts, styles, event handlers, unknown nodes, links to javascript: - is
 * simply never written out. Images must point at KasiLearn media (/learn/media/{id}).
 */
final class ContentRenderer
{
    private const IMAGE_SRC = '#^/learn/media/[0-9A-HJKMNP-TV-Z]{26}$#i';

    /** @param array<string, mixed>|null $doc */
    public function html(?array $doc): string
    {
        return $doc === null ? '' : $this->nodes((array) ($doc['content'] ?? []));
    }

    /**
     * Media ids used by images in the document.
     *
     * @param  array<string, mixed>|null  $doc
     * @return list<string>
     */
    public function imageIds(?array $doc): array
    {
        $ids = [];
        $this->walk($doc ?? [], function (array $node) use (&$ids): void {
            if (($node['type'] ?? null) === 'image' && preg_match(self::IMAGE_SRC, (string) ($node['attrs']['src'] ?? '')) === 1) {
                $ids[] = basename((string) $node['attrs']['src']);
            }
        });

        return array_values(array_unique($ids));
    }

    /**
     * Accessibility and readability problems in a document.
     *
     * @param  array<string, mixed>|null  $doc
     * @return list<string> problem codes: image_alt, heading_order, link_text, long_sentences, empty
     */
    public function problems(?array $doc): array
    {
        $problems = [];
        $lastHeading = 1;
        $text = '';

        $this->walk($doc ?? [], function (array $node) use (&$problems, &$lastHeading, &$text): void {
            $type = $node['type'] ?? null;
            if ($type === 'image' && trim((string) ($node['attrs']['alt'] ?? '')) === '') {
                $problems[] = 'image_alt';
            }
            if ($type === 'heading') {
                $level = (int) ($node['attrs']['level'] ?? 2);
                if ($level > $lastHeading + 1) {
                    $problems[] = 'heading_order';
                }
                $lastHeading = $level;
            }
            if ($type === 'text') {
                $text .= (string) ($node['text'] ?? '').' ';
                foreach ((array) ($node['marks'] ?? []) as $mark) {
                    if (($mark['type'] ?? null) === 'link' && in_array(mb_strtolower(trim((string) ($node['text'] ?? ''))), ['click here', 'here', 'link', 'read more', 'more'], true)) {
                        $problems[] = 'link_text';
                    }
                }
            }
        });

        if (trim($text) === '' && $this->imageIds($doc) === []) {
            $problems[] = 'empty';
        }

        $sentences = array_filter(preg_split('/[.!?]+\s/u', trim($text)) ?: [], static fn (string $s): bool => trim($s) !== '');
        $words = str_word_count($text);
        if ($sentences !== [] && $words / count($sentences) > 22) {
            $problems[] = 'long_sentences';
        }

        return array_values(array_unique($problems));
    }

    /** @param array<int, mixed> $nodes */
    private function nodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            if (is_array($node)) {
                $html .= $this->node($node);
            }
        }

        return $html;
    }

    /** @param array<string, mixed> $node */
    private function node(array $node): string
    {
        $children = fn (): string => $this->nodes((array) ($node['content'] ?? []));
        $attrs = (array) ($node['attrs'] ?? []);

        return match ($node['type'] ?? null) {
            'paragraph' => '<p>'.$children().'</p>',
            'heading' => (static function (int $level, string $inner): string {
                $level = max(2, min(4, $level));

                return "<h{$level}>{$inner}</h{$level}>";
            })((int) ($attrs['level'] ?? 2), $children()),
            'bulletList' => '<ul>'.$children().'</ul>',
            'orderedList' => '<ol>'.$children().'</ol>',
            'listItem' => '<li>'.$children().'</li>',
            'blockquote' => '<aside class="callout">'.$children().'</aside>',
            'horizontalRule' => '<hr>',
            'hardBreak' => '<br>',
            'table' => '<div class="table-wrap"><table>'.$children().'</table></div>',
            'tableRow' => '<tr>'.$children().'</tr>',
            'tableHeader' => '<th scope="col">'.$children().'</th>',
            'tableCell' => '<td>'.$children().'</td>',
            'image' => preg_match(self::IMAGE_SRC, (string) ($attrs['src'] ?? '')) === 1
                ? '<img src="'.e((string) $attrs['src']).'" alt="'.e((string) ($attrs['alt'] ?? '')).'" loading="lazy">'
                : '',
            'text' => $this->text($node),
            default => '',
        };
    }

    /** @param array<string, mixed> $node */
    private function text(array $node): string
    {
        $html = e((string) ($node['text'] ?? ''));
        foreach ((array) ($node['marks'] ?? []) as $mark) {
            $html = match ($mark['type'] ?? null) {
                'bold' => "<strong>{$html}</strong>",
                'italic' => "<em>{$html}</em>",
                'link' => $this->link((string) ($mark['attrs']['href'] ?? ''), $html),
                default => $html,
            };
        }

        return $html;
    }

    private function link(string $href, string $inner): string
    {
        $href = trim($href);
        if (preg_match('#^(https?://|mailto:|/)#i', $href) !== 1 || str_starts_with($href, '//')) {
            return $inner; // javascript:, data: and anything else become plain text
        }

        return '<a href="'.e($href).'" rel="noopener nofollow" target="_blank">'.$inner.'</a>';
    }

    /** @param array<string, mixed> $node */
    private function walk(array $node, callable $visit): void
    {
        $visit($node);
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $this->walk($child, $visit);
            }
        }
    }
}
