<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

/**
 * A versioned prompt, loaded from a module's resources/prompts/*.md file:
 *
 *     ---
 *     key: hubops.event_description
 *     feature: hubops.event_description
 *     label: Event description writer
 *     version: 1
 *     tier: fast
 *     max_tokens: 600
 *     output: description
 *     ---
 *     (system prompt)
 *     <<<INPUT>>>
 *     (user message template with {{placeholders}})
 */
final readonly class Prompt
{
    /** @param list<string> $outputFields */
    public function __construct(
        public string $key,
        public string $feature,
        public string $label,
        public int $version,
        public string $tier,
        public int $maxTokens,
        public array $outputFields,
        public string $system,
        public string $userTemplate,
        public string $path,
    ) {}

    public static function fromFile(string $path): self
    {
        $raw = (string) file_get_contents($path);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $raw, $m)) {
            throw new \InvalidArgumentException("Prompt file {$path} has no front matter.");
        }

        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $meta[$k] = $v;
            }
        }

        [$system, $user] = array_pad(explode('<<<INPUT>>>', $m[2], 2), 2, '');

        return new self(
            key: $meta['key'] ?? throw new \InvalidArgumentException("Prompt {$path}: key missing"),
            feature: $meta['feature'] ?? $meta['key'],
            label: $meta['label'] ?? $meta['key'],
            version: (int) ($meta['version'] ?? 1),
            tier: in_array($meta['tier'] ?? 'fast', ['fast', 'strong'], true) ? $meta['tier'] : 'fast',
            maxTokens: (int) ($meta['max_tokens'] ?? 500),
            outputFields: array_values(array_filter(array_map('trim', explode(',', $meta['output'] ?? '')))),
            system: trim($system),
            userTemplate: trim($user),
            path: $path,
        );
    }

    /** @param array<string, string> $vars */
    public function render(array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static fn (array $m): string => $vars[$m[1]] ?? '', $this->userTemplate);
    }

    /** Fingerprint of the wording; a change must come with a new version (see kasi:ai:lock). */
    public function hash(): string
    {
        return hash('sha256', $this->system."\n<<<INPUT>>>\n".$this->userTemplate.'|'.$this->tier.'|'.$this->maxTokens.'|'.implode(',', $this->outputFields));
    }
}
