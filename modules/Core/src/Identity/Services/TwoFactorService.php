<?php

declare(strict_types=1);

namespace Modules\Core\Identity\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Core\Identity\Models\StaffTwoFactor;
use Modules\Core\Identity\Models\User;
use PragmaRX\Google2FA\Google2FA;

/**
 * Authenticator-app (TOTP) second step for staff, organisation and national roles,
 * with 8 one-time backup codes. SMS is deliberately not used as a second step (SIM swap risk).
 */
final readonly class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    public function isConfirmed(User $user): bool
    {
        return $user->twoFactor?->confirmed_at !== null;
    }

    /** Start (or restart) setup: a new secret that is only active once confirmed. */
    public function begin(User $user): string
    {
        $secret = $this->google2fa->generateSecretKey(32);

        StaffTwoFactor::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['secret' => $secret, 'recovery_codes' => [], 'confirmed_at' => null],
        );

        return $secret;
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $uri = $this->google2fa->getQRCodeUrl((string) config('kasi.brand.name'), $user->phone, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    /**
     * Confirm setup with a first valid code. Returns the backup codes to show once.
     *
     * @return list<string>|null
     */
    public function confirm(User $user, string $code): ?array
    {
        $record = $user->twoFactor()->first();

        if ($record === null || ! $this->google2fa->verifyKey($record->secret, $this->clean($code), 1)) {
            return null;
        }

        $codes = array_map(static fn (): string => Str::upper(Str::random(5).'-'.Str::random(5)), range(1, 8));
        $record->update([
            'confirmed_at' => now(),
            'recovery_codes' => array_map(static fn (string $c): string => Hash::make($c), $codes),
        ]);

        return $codes;
    }

    /** Verify an authenticator code or consume a backup code. */
    public function verify(User $user, string $code): bool
    {
        $record = $user->twoFactor()->first();

        if ($record === null || $record->confirmed_at === null) {
            return false;
        }

        if (preg_match('/^\d{6}$/', $this->clean($code)) === 1) {
            return (bool) $this->google2fa->verifyKey($record->secret, $this->clean($code), 1);
        }

        $remaining = $record->recovery_codes;
        foreach ($remaining as $index => $hash) {
            if (Hash::check(Str::upper(trim($code)), $hash)) {
                unset($remaining[$index]);
                $record->update(['recovery_codes' => array_values($remaining)]);

                return true;
            }
        }

        return false;
    }

    private function clean(string $code): string
    {
        return (string) preg_replace('/\s+/', '', $code);
    }
}
