<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\User;
use Modules\Core\Identity\Services\ConsentService;
use Modules\Core\Notifications\Contracts\WhatsAppSender;
use Modules\Core\Notifications\Drivers\LogWhatsAppSender;
use Modules\Core\Notifications\Jobs\DeliverNotification;
use Modules\Core\Notifications\KasiNotification;
use Modules\Core\Notifications\Messages\SecurityAlertNotification;
use Modules\Core\Notifications\Messages\WelcomeNotification;
use Modules\Core\Notifications\Models\NotificationDelivery;
use Modules\Core\Notifications\NotificationPreferences;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Platform\Models\Update;
use Modules\Core\Tests\Helpers;

function sastTime(int $hour, int $minute = 0): CarbonImmutable
{
    return CarbonImmutable::now('Africa/Johannesburg')->setTime($hour, $minute)->utc();
}

class HubNewsNotification extends KasiNotification
{
    public const KEY = 'test_hub_news';

    public const CATEGORY = 'hub_news';

    public const WHATSAPP_TEMPLATE = 'kasihub_test';

    public function __construct(private readonly string $key = 'event-1') {}

    public function channels(): array
    {
        return ['in_app', 'whatsapp', 'sms', 'email'];
    }

    public function title(User $user): string
    {
        return 'CV workshop on Saturday';
    }

    public function body(User $user): string
    {
        return 'Bring your ID.';
    }

    public function dedupeKey(): ?string
    {
        return $this->key;
    }
}

final class MarketingNotification extends HubNewsNotification
{
    public const KEY = 'test_marketing';

    public const CATEGORY = 'marketing';
}

beforeEach(function (): void {
    $this->travelTo(sastTime(10));
    $this->user = User::factory()->create(['whatsapp_opt_in' => true, 'email' => 'thandi@example.co.za', 'email_verified_at' => now()]);
    Mail::fake();
});

function channelsSent(User $user): array
{
    return $user->notificationDeliveries()->get()->map(fn ($d) => "{$d->channel}:{$d->status}")->sort()->values()->all();
}

it('puts every notification in the updates feed and sends WhatsApp instead of SMS', function (): void {
    app(Notifier::class)->send($this->user, new HubNewsNotification);

    expect(Update::query()->where('user_id', $this->user->id)->count())->toBe(1)
        ->and(channelsSent($this->user))->toBe(['email:sent', 'whatsapp:sent'])
        ->and(LogWhatsAppSender::lastMessage($this->user->phone)['template'])->toBe('kasihub_test');
});

it('uses SMS when the person has not agreed to WhatsApp', function (): void {
    $this->user->forceFill(['whatsapp_opt_in' => false, 'email' => null])->save();
    app(NotificationPreferences::class)->update($this->user, ['hub_news' => ['sms' => true]]);

    app(Notifier::class)->send($this->user, new HubNewsNotification);

    expect(channelsSent($this->user))->toBe(['sms:sent']);
});

it('respects channel preferences per category', function (): void {
    app(NotificationPreferences::class)->update($this->user, ['hub_news' => ['whatsapp' => false, 'email' => false]]);

    app(Notifier::class)->send($this->user, new HubNewsNotification);

    expect(channelsSent($this->user))->toBe([]) // SMS for hub news is off by default
        ->and(Update::query()->where('user_id', $this->user->id)->count())->toBe(1); // the feed still shows it
});

it('never lets security alerts be switched off, and sends them during quiet hours', function (): void {
    $prefs = app(NotificationPreferences::class);
    $prefs->update($this->user, ['security' => ['sms' => false]]);
    expect($prefs->enabled($this->user, 'security', 'sms'))->toBeTrue();

    $this->travelTo(sastTime(23));
    app(Notifier::class)->send($this->user, new SecurityAlertNotification('pin_changed'));

    expect(channelsSent($this->user))->toBe(['sms:sent', 'whatsapp:sent'])
        ->and(LogSmsSender::lastMessage($this->user->phone))->toContain('PIN was changed');
});

it('holds WhatsApp and SMS during quiet hours and releases them at 07:00', function (): void {
    $this->travelTo(sastTime(21));
    app(Notifier::class)->send($this->user, new HubNewsNotification);

    $held = $this->user->notificationDeliveries()->where('channel', 'whatsapp')->first();
    expect($held->status)->toBe('held')
        ->and($held->scheduled_for->setTimezone('Africa/Johannesburg')->format('H:i'))->toBe('07:00')
        ->and($this->user->notificationDeliveries()->where('channel', 'email')->value('status'))->toBe('sent'); // email is not held

    $this->artisan('kasi:notifications:release')->assertSuccessful();
    expect($held->refresh()->status)->toBe('held');

    $this->travelTo(sastTime(7, 5)->addDay());
    $this->artisan('kasi:notifications:release')->assertSuccessful();
    expect($held->refresh()->status)->toBe('sent');
});

it('sends the same message only once', function (): void {
    app(Notifier::class)->send($this->user, new HubNewsNotification('event-9'));
    app(Notifier::class)->send($this->user, new HubNewsNotification('event-9'));

    expect($this->user->notificationDeliveries()->where('channel', 'whatsapp')->pluck('status')->all())->toBe(['sent', 'skipped']);
});

it('caps WhatsApp and SMS messages per person per day', function (): void {
    config(['kasi.notifications.max_messages_per_day' => 2]);

    foreach (range(1, 3) as $i) {
        app(Notifier::class)->send($this->user, new HubNewsNotification("event-{$i}"));
    }

    expect($this->user->notificationDeliveries()->where('channel', 'whatsapp')->pluck('skip_reason')->filter()->values()->all())->toBe(['daily_limit']);
});

it('only sends marketing to people who agreed to it', function (): void {
    app(Notifier::class)->send($this->user, new MarketingNotification);
    expect(channelsSent($this->user))->toBe([]);

    app(ConsentService::class)->record($this->user, ['marketing' => true]);
    app(NotificationPreferences::class)->update($this->user, ['marketing' => ['whatsapp' => true]]);
    app(Notifier::class)->send($this->user, new MarketingNotification('m-2'));
    expect(channelsSent($this->user))->toContain('whatsapp:sent');
});

it('only emails confirmed addresses', function (): void {
    $this->user->forceFill(['email_verified_at' => null])->save();
    app(Notifier::class)->send($this->user, new HubNewsNotification);

    expect(channelsSent($this->user))->not->toContain('email:sent');
});

it('records the estimated cost of every message', function (): void {
    app(Notifier::class)->send($this->user, new HubNewsNotification);

    expect($this->user->notificationDeliveries()->where('channel', 'whatsapp')->value('cost_cents'))->toBe(35)
        ->and($this->user->notificationDeliveries()->where('channel', 'email')->value('cost_cents'))->toBe(0);
});

it('writes notifications in the person\'s language', function (): void {
    $this->user->forceFill(['preferred_locale' => 'zu'])->save();
    app(Notifier::class)->send($this->user, new WelcomeNotification);

    // isiZulu draft has no welcome text yet, so English is used rather than a raw key.
    expect(Update::query()->where('user_id', $this->user->id)->value('title'))->toStartWith('Welcome to KasiHub');
});

it('lets people choose notification channels on their account page', function (): void {
    app(ConsentService::class)->record($this->user, ['platform' => true]);
    Helpers::signedIn($this, $this->user);

    $this->put('/account/notifications', ['preferences' => ['jobs' => ['sms' => true, 'whatsapp' => false], 'security' => ['sms' => false]]])
        ->assertSessionHasNoErrors();

    $prefs = app(NotificationPreferences::class);
    expect($prefs->enabled($this->user, 'jobs', 'sms'))->toBeTrue()
        ->and($prefs->enabled($this->user, 'jobs', 'whatsapp'))->toBeFalse()
        ->and($prefs->enabled($this->user, 'security', 'sms'))->toBeTrue();

    $this->get('/account?tab=notifications')->assertInertia(fn ($page) => $page->where('tab', 'notifications')->has('notificationPreferences', 8));
});

it('does nothing when a held message was already handled', function (): void {
    $delivery = NotificationDelivery::query()->create([
        'user_id' => $this->user->id, 'notification' => 'x', 'category' => 'jobs', 'channel' => 'sms', 'status' => 'sent',
        'payload' => ['title' => 'a', 'body' => 'b', 'url' => null, 'important' => false, 'to' => null, 'template' => null, 'params' => []],
    ]);

    (new DeliverNotification($delivery->id))->handle(app(WhatsAppSender::class), app(SmsSender::class));

    expect($delivery->refresh()->status)->toBe('sent');
});
