<?php

declare(strict_types=1);

namespace Modules\Core\Ai;

use App\Support\Modules\ModuleRegistry;
use InvalidArgumentException;

/**
 * Every prompt from every enabled module (modules/{Module}/resources/prompts/*.md).
 */
final class PromptRegistry
{
    /** @var array<string, Prompt>|null */
    private ?array $prompts = null;

    public const LOCK_FILE = 'docs/ai-prompts.lock.json';

    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return array<string, Prompt> */
    public function all(): array
    {
        if ($this->prompts === null) {
            $this->prompts = [];
            foreach ($this->modules->enabled() as $module) {
                foreach (glob($module->path('resources/prompts/*.md')) ?: [] as $file) {
                    $prompt = Prompt::fromFile($file);
                    $this->prompts[$prompt->key] = $prompt;
                }
            }
            ksort($this->prompts);
        }

        return $this->prompts;
    }

    public function get(string $key): Prompt
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException("Unknown prompt [{$key}].");
    }

    /** @return array<string, string> feature => label */
    public function features(): array
    {
        $features = [];
        foreach ($this->all() as $prompt) {
            $features[$prompt->feature] = $prompt->label;
        }

        return $features;
    }

    /** @return array<string, array{version: int, sha256: string}> */
    public function lock(): array
    {
        $path = base_path(self::LOCK_FILE);

        /** @var array<string, array{version: int, sha256: string}> */
        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
