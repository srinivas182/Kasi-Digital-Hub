<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Access\Scope;
use Modules\Core\Events\DocumentRejected;
use Modules\Core\Events\DocumentVerified;
use Modules\Core\Events\RoleAssigned;
use Modules\Core\Events\UserRegistered;
use Modules\Core\Notifications\Messages\DocumentRejectedNotification;
use Modules\Core\Notifications\Messages\DocumentVerifiedNotification;
use Modules\Core\Notifications\Messages\RoleAssignedNotification;
use Modules\Core\Notifications\Messages\WelcomeNotification;
use Modules\Core\Notifications\Notifier;

/**
 * Turns core events into notifications (queued on the "events" queue).
 */
final class SendCoreNotifications implements ShouldQueue
{
    public string $queue = 'events';

    public function __construct(private readonly Notifier $notifier, private readonly RoleRegistry $roles) {}

    public function welcome(UserRegistered $event): void
    {
        $this->notifier->send($event->user, new WelcomeNotification);
    }

    public function documentVerified(DocumentVerified $event): void
    {
        if ($event->document->owner !== null) {
            $this->notifier->send($event->document->owner, new DocumentVerifiedNotification($event->document));
        }
    }

    public function documentRejected(DocumentRejected $event): void
    {
        if ($event->document->owner !== null) {
            $this->notifier->send($event->document->owner, new DocumentRejectedNotification($event->document));
        }
    }

    public function roleAssigned(RoleAssigned $event): void
    {
        // Citizens pick their own roles by using a service; only tell people about roles others gave them.
        if ($event->scopeType === 'self') {
            return;
        }

        $label = $this->roles->find($event->role)->label ?? $event->role;
        $this->notifier->send($event->user, new RoleAssignedNotification($label, (new Scope($event->scopeType, $event->scopeId))->describe()));
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserRegistered::class => 'welcome',
            DocumentVerified::class => 'documentVerified',
            DocumentRejected::class => 'documentRejected',
            RoleAssigned::class => 'roleAssigned',
        ];
    }
}
