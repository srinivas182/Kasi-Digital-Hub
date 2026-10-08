<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\User;

/**
 * Six months of demo history: platform events for registrations, updates in demo
 * accounts' feeds, and message deliveries with estimated costs (for the S21 cost dashboard).
 */
final class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('platform_events')->where('name', 'core.user.registered')->where('payload', 'like', '%demo-history%')->exists()) {
            return;
        }

        $now = now();
        $events = $deliveries = [];

        // Registration events for demo citizens, spread over 6 months.
        $citizens = DB::table('users')->where('phone', 'like', '+2784000%')->get(['id', 'home_hub_id', 'created_at']);
        foreach ($citizens as $i => $citizen) {
            $when = $now->copy()->subDays($i % 180)->subHours($i % 24);
            $events[] = [
                'id' => (string) Str::ulid(), 'name' => 'core.user.registered', 'actor_id' => null, 'subject_type' => null, 'subject_id' => null,
                'user_id' => $citizen->id, 'hub_id' => $citizen->home_hub_id,
                'payload' => json_encode(['age_band' => 'adult', 'channel' => $i % 4 === 0 ? 'assisted' : 'self', 'source' => 'demo-history']),
                'occurred_at' => $when,
            ];

            $channel = $i % 5 === 0 ? 'sms' : 'whatsapp';
            $deliveries[] = [
                'id' => (string) Str::ulid(), 'user_id' => $citizen->id, 'notification' => 'welcome', 'category' => 'account', 'channel' => $channel,
                'status' => $i % 37 === 0 ? 'failed' : 'sent', 'skip_reason' => null, 'dedupe_key' => 'welcome',
                'payload' => json_encode(['title' => 'Welcome to KasiHub', 'body' => 'Your account is ready.', 'url' => '/home', 'important' => false, 'to' => null, 'template' => 'kasihub_welcome', 'params' => []]),
                'scheduled_for' => null, 'sent_at' => $i % 37 === 0 ? null : $when, 'provider_reference' => 'demo-'.$i,
                'error' => $i % 37 === 0 ? 'Recipient is not on WhatsApp' : null,
                'cost_cents' => $i % 37 === 0 ? 0 : (int) config("kasi.notifications.cost_cents.{$channel}"), 'fallback_for' => null,
                'created_at' => $when, 'updated_at' => $when,
            ];
        }

        DB::transaction(function () use ($events, $deliveries): void {
            foreach (array_chunk($events, 250) as $chunk) {
                DB::table('platform_events')->insert($chunk);
            }
            foreach (array_chunk($deliveries, 250) as $chunk) {
                DB::table('notification_deliveries')->insert($chunk);
            }
        });

        $this->feeds();
    }

    /** A believable updates feed for the main demo accounts. */
    private function feeds(): void
    {
        $feeds = [
            '+27720000001' => [
                ['Hub', 'account', 'Welcome to KasiHub, Thandi', 'Your account is ready.', 'You created your account', 170, true],
                ['Hub', 'account', 'Your ID document is verified', "You won't need to upload it again.", 'A KasiHub reviewer checked your document', 150, true],
                ['Hub', 'account', 'Your matric certificate is verified', 'Every KasiHub service can now use it.', 'A KasiHub reviewer checked your document', 120, true],
                ['Hub', 'hub_news', 'CV workshop at Tsutsumani hub on Saturday', 'Bring your ID - facilitators will help you finish your CV.', 'Your hub posted an event', 3, false],
            ],
            '+27720000002' => [
                ['Hub', 'account', "We couldn't accept your proof of address", 'Reason: the photo is too blurry. Please upload a clear copy.', 'A KasiHub reviewer checked your document', 5, false],
            ],
            '+27720000020' => [
                ['Hub', 'account', 'You are now a Hub facilitator', 'You have the Hub facilitator role at Tsutsumani Digital Hub.', 'An administrator gave you a new role', 60, true],
            ],
        ];

        foreach ($feeds as $phone => $items) {
            $user = User::query()->where('phone', $phone)->first();
            if ($user === null) {
                continue;
            }

            foreach ($items as [$module, $category, $title, $body, $cause, $daysAgo, $read]) {
                DB::table('updates')->insert([
                    'id' => (string) Str::ulid(), 'user_id' => $user->id, 'module' => $module, 'category' => $category,
                    'title' => $title, 'body' => $body, 'cause' => $cause, 'url' => '/account?tab=documents', 'event_id' => null,
                    'read_at' => $read ? now()->subDays($daysAgo) : null, 'created_at' => now()->subDays($daysAgo),
                ]);
            }
        }
    }
}
