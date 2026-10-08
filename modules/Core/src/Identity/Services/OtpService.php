<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Identity\Contracts\SmsSender;
use Modules\Core\Identity\Models\OtpChallenge;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Issues and checks one-time SMS codes with layered abuse protection:
 * per-number, per-IP and per-device limits, a number-range anomaly guard,
 * a platform-wide daily SMS cap and mobile-only numbers.
 */
final readonly class OtpService
{
    public const PURPOSES = ['login', 'reset_pin', 'change_phone', 'guardian'];

    public function __construct(
        private SmsSender $sms,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{sent: bool, reason: string|null, retry_after: int|null, demo_code: string|null}
     */
    public function request(string $phone, string $purpose, ?string $ip, ?string $deviceHash): array
    {
        if (! PhoneNumbers::canReceiveCodes($phone)) {
            return $this->refused('invalid_number');
        }

        $limits = [
            ['otp:phone15:'.$this->key($phone), (int) config('kasi.identity.otp.per_phone_15_min'), 900],
            ['otp:phoneday:'.$this->key($phone), (int) config('kasi.identity.otp.per_phone_day'), 86400],
            ['otp:ip:'.($ip ?? 'none'), $this->ipLimit($ip), 3600],
            ['otp:device:'.($deviceHash ?? 'none'), (int) config('kasi.identity.otp.per_device_hour'), 3600],
        ];

        foreach ($limits as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $this->audit->record('otp.rate_limited', outcome: 'blocked', meta: ['purpose' => $purpose, 'limit' => explode(':', $key)[1]]);

                return $this->refused('rate_limited', RateLimiter::availableIn($key));
            }
        }

        $rangeKey = 'otp:range:'.PhoneNumbers::range($phone);
        if (RateLimiter::tooManyAttempts($rangeKey, (int) config('kasi.identity.otp.per_range_hour'))) {
            Log::critical('OTP range anomaly - possible SMS pumping', ['range' => PhoneNumbers::range($phone)]);
            $this->audit->record('otp.range_blocked', outcome: 'blocked', meta: ['range' => PhoneNumbers::range($phone)]);

            return $this->refused('rate_limited', RateLimiter::availableIn($rangeKey));
        }

        $capKey = 'otp:daily-total:'.now()->format('Y-m-d');
        $sentToday = (int) Cache::get($capKey, 0);
        if ($sentToday >= (int) config('kasi.identity.otp.daily_cap')) {
            Log::critical('Daily SMS cap reached - code requests paused', ['cap' => config('kasi.identity.otp.daily_cap')]);
            $this->audit->record('otp.daily_cap_reached', outcome: 'blocked');

            return $this->refused('unavailable');
        }

        foreach ($limits as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
        RateLimiter::hit($rangeKey, 3600);
        Cache::put($capKey, $sentToday + 1, now()->endOfDay());

        $code = $this->generateCode();

        OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        OtpChallenge::query()->create([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => $this->hash($phone, $purpose, $code),
            'expires_at' => now()->addMinutes((int) config('kasi.identity.otp.ttl_minutes')),
            'ip_address' => $ip,
            'device_hash' => $deviceHash,
        ]);

        $minutes = (int) config('kasi.identity.otp.ttl_minutes');
        $this->sms->send($phone, "Your KasiHub code is {$code}. It expires in {$minutes} minutes. Never share this code - KasiHub will never ask for it.");
        $this->audit->record('otp.sent', meta: ['purpose' => $purpose]);

        return ['sent' => true, 'reason' => null, 'retry_after' => null, 'demo_code' => config('kasi.demo.enabled') === true ? $code : null];
    }

    public function verify(string $phone, string $purpose, string $code): bool
    {
        $maxAttempts = (int) config('kasi.identity.otp.max_attempts');

        $challenge = OtpChallenge::query()
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        if ($challenge === null || ! $challenge->isUsable($maxAttempts)) {
            $this->audit->record('otp.failed', outcome: 'failed', meta: ['purpose' => $purpose, 'reason' => 'no_usable_code']);

            return false;
        }

        if (! hash_equals($challenge->code_hash, $this->hash($phone, $purpose, preg_replace('/\D/', '', $code) ?? ''))) {
            $challenge->increment('attempts');
            $this->audit->record('otp.failed', outcome: 'failed', meta: ['purpose' => $purpose, 'reason' => 'wrong_code']);

            return false;
        }

        $challenge->update(['consumed_at' => now()]);

        return true;
    }

    /** Hub connections (trusted IPs) are shared by many people and get a higher limit. */
    private function ipLimit(?string $ip): int
    {
        $trusted = (array) config('kasi.identity.otp.trusted_ips');

        if ($ip !== null && $trusted !== [] && IpUtils::checkIp($ip, $trusted)) {
            return (int) config('kasi.identity.otp.per_trusted_ip_hour');
        }

        return (int) config('kasi.identity.otp.per_ip_hour');
    }

    private function generateCode(): string
    {
        $length = (int) config('kasi.identity.otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$purpose}|{$code}", (string) config('app.key'));
    }

    private function key(string $phone): string
    {
        return hash('sha256', $phone);
    }

    /**
     * @return array{sent: bool, reason: string|null, retry_after: int|null, demo_code: string|null}
     */
    private function refused(string $reason, ?int $retryAfter = null): array
    {
        return ['sent' => false, 'reason' => $reason, 'retry_after' => $retryAfter, 'demo_code' => null];
    }
}
