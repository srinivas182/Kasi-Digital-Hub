<?php

declare(strict_types=1);

namespace Modules\Core\Documents;

use Illuminate\Console\Command;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Notifications\Messages\DocumentExpiringNotification;
use Modules\Core\Notifications\Notifier;

/**
 * Daily: remind people about documents expiring within the reminder window (once per document).
 */
final class RemindExpiringDocuments extends Command
{
    protected $signature = 'kasi:documents:remind-expiring';

    protected $description = 'Remind people about documents that are about to expire';

    public function handle(Notifier $notifier): int
    {
        $count = 0;

        Document::query()
            ->with('owner')
            ->whereIn('status', [Document::UPLOADED, Document::VERIFIED])
            ->whereNull('expiry_reminded_at')
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays((int) config('kasi.documents.expiry_reminder_days'))->toDateString()])
            ->chunkById(500, function ($documents) use ($notifier, &$count): void {
                foreach ($documents as $document) {
                    if ($document->owner !== null) {
                        $notifier->send($document->owner, new DocumentExpiringNotification($document));
                    }
                    $document->update(['expiry_reminded_at' => now()]);
                    $count++;
                }
            });

        $this->components->info("Sent {$count} expiry reminders.");

        return self::SUCCESS;
    }
}
