<?php

declare(strict_types=1);

namespace Modules\Core\Platform;

use Illuminate\Console\Command;
use Modules\Core\Notifications\NotificationCatalogue;

/**
 * Regenerates docs that are built from code: the event catalogue and the WhatsApp template list.
 * A test fails if the committed files are out of date.
 */
final class GenerateDocsCommand extends Command
{
    protected $signature = 'kasi:docs:generate';

    protected $description = 'Regenerate docs/event-catalogue.md and docs/whatsapp-templates.md';

    public function handle(EventCatalogue $events, NotificationCatalogue $notifications): int
    {
        foreach ($events->problems() as $problem) {
            $this->components->warn($problem);
        }

        file_put_contents(base_path('docs/event-catalogue.md'), $events->markdown());
        file_put_contents(base_path('docs/whatsapp-templates.md'), $notifications->whatsappMarkdown());
        $this->components->info('Docs regenerated.');

        return self::SUCCESS;
    }
}
