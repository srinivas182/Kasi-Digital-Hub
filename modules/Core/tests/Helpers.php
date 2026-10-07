<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Illuminate\Testing\TestResponse;
use Modules\Core\Identity\Drivers\LogSmsSender;
use Modules\Core\Identity\Models\User;
use Tests\TestCase;

/**
 * Helpers for identity feature tests.
 */
final class Helpers
{
    public static function lastCode(string $phone): string
    {
        $message = LogSmsSender::lastMessage($phone);
        expect($message)->not->toBeNull();
        preg_match('/code is (\d{6})/', (string) $message, $matches);

        return $matches[1];
    }

    /** Phone + SMS code steps; returns the code verification response. */
    public static function verifyPhone(TestCase $test, string $phone): TestResponse
    {
        $test->post('/login', ['phone' => $phone])->assertRedirect('/login/code');

        return $test->post('/login/code', ['code' => self::lastCode($phone)]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function signUp(TestCase $test, string $phone, array $overrides = []): TestResponse
    {
        self::verifyPhone($test, $phone)->assertRedirect('/signup');

        return $test->post('/signup', [
            'pin' => '24680',
            'pin_confirmation' => '24680',
            'date_of_birth' => now()->subYears(22)->toDateString(),
            'first_name' => 'Thandi',
            'last_name' => 'Mabasa',
            'accept_terms' => true,
            'consents' => ['job_matching' => true, 'marketing' => false],
            'remember' => true,
            ...$overrides,
        ]);
    }

    public static function signedIn(TestCase $test, ?User $user = null): User
    {
        $user ??= User::factory()->create();
        $test->post('/login', ['phone' => $user->phone]);
        $test->post('/login/code', ['code' => self::lastCode($user->phone)]);
        $test->post('/login/pin', ['pin' => '24680', 'remember' => false]);
        $test->assertAuthenticatedAs($user);

        return $user;
    }
}
