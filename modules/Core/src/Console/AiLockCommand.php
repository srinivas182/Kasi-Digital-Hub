<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Modules\Core\Ai\PromptRegistry;

/**
 * Records each prompt's version and wording fingerprint in docs/ai-prompts.lock.json.
 * Refuses when wording changed but the version did not - every AI result must say which
 * wording produced it.
 */
final class AiLockCommand extends Command
{
    protected $signature = 'kasi:ai:lock';

    protected $description = 'Update the prompt version lock file';

    public function handle(PromptRegistry $prompts): int
    {
        $lock = $prompts->lock();
        $new = [];

        foreach ($prompts->all() as $key => $prompt) {
            $old = $lock[$key] ?? null;
            if ($old !== null && $old['version'] === $prompt->version && $old['sha256'] !== $prompt->hash()) {
                $this->error("Prompt [{$key}] changed but its version is still {$prompt->version}. Increase the version in {$prompt->path}.");

                return self::FAILURE;
            }
            $new[$key] = ['version' => $prompt->version, 'sha256' => $prompt->hash()];
        }

        file_put_contents(base_path(PromptRegistry::LOCK_FILE), json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $this->info(count($new).' prompts locked.');

        return self::SUCCESS;
    }
}
