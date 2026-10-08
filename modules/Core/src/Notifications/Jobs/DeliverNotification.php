<?php

declare(strict_types=1);

namespace Modules\Core\Notifications\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Events\NotificationFailed;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Notifications\Contracts\WhatsAppSender;
use Modules\Core\Notifications\Models\NotificationDelivery;
use Throwable;

/**
 * Sends one delivery on its channel and records the outcome and estimated cost.
 * An important WhatsApp message that fails is re-sent by SMS.
 */
final class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $deliveryId) {}

    public function handle(WhatsAppSender $whatsapp, SmsSender $sms): void
    {
        $delivery = NotificationDelivery::query()->with('user')->find($this->deliveryId);

        if ($delivery === null || ! in_array($delivery->status, [NotificationDelivery::QUEUED, NotificationDelivery::HELD], true) || $delivery->user === null) {
            return;
        }

        $user = $delivery->user;
        $payload = $delivery->payload;
        $text = trim($payload['title'].'. '.$payload['body'].($payload['url'] !== null ? ' '.url($payload['url']) : ''));

        try {
            $reference = match ($delivery->channel) {
                'whatsapp' => $whatsapp->send($user->phone, (string) $payload['template'], $user->preferred_locale, $payload['params']),
                'sms' => $this->sendSms($sms, $payload['to'] ?? $user->phone, $text),
                'email' => $this->sendEmail((string) $user->email, $payload['title'], $text),
                default => throw new \RuntimeException("Unknown channel [{$delivery->channel}]."),
            };

            $delivery->update([
                'status' => NotificationDelivery::SENT,
                'sent_at' => now(),
                'provider_reference' => $reference,
                'cost_cents' => (int) config('kasi.notifications.cost_cents.'.$delivery->channel, 0),
            ]);
        } catch (Throwable $e) {
            $delivery->update(['status' => NotificationDelivery::FAILED, 'error' => mb_substr($e->getMessage(), 0, 250)]);
            event(new NotificationFailed($delivery));

            if ($delivery->channel === 'whatsapp' && $payload['important']) {
                $fallback = NotificationDelivery::query()->create([
                    'user_id' => $delivery->user_id,
                    'notification' => $delivery->notification,
                    'category' => $delivery->category,
                    'channel' => 'sms',
                    'status' => NotificationDelivery::QUEUED,
                    'payload' => [...$payload, 'to' => $user->phone],
                    'fallback_for' => $delivery->id,
                ]);
                self::dispatch($fallback->id)->onQueue('notifications');
            }
        }
    }

    private function sendSms(SmsSender $sms, string $to, string $text): string
    {
        $sms->send($to, mb_substr($text, 0, 300));

        return 'sms-'.$this->deliveryId;
    }

    private function sendEmail(string $to, string $subject, string $text): string
    {
        Mail::raw($text, static fn ($message) => $message->to($to)->subject($subject));

        return 'email-'.$this->deliveryId;
    }
}
