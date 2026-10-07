<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Support\Demo\SouthAfricanFaker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Identity\Models\User;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $sa = new SouthAfricanFaker;

        return [
            'phone' => $sa->mobileNumber(),
            'phone_verified_at' => now(),
            'first_name' => $sa->firstName(),
            'last_name' => $sa->surname(),
            'date_of_birth' => now()->subYears(fake()->numberBetween(18, 35))->subDays(fake()->numberBetween(0, 364))->toDateString(),
            'preferred_locale' => 'en',
            'pin' => '24680',
            'status' => User::STATUS_ACTIVE,
            'age_band' => 'adult',
        ];
    }

    public function minor(): self
    {
        return $this->state(fn (): array => [
            'date_of_birth' => now()->subYears(17)->toDateString(),
            'age_band' => 'minor',
        ]);
    }

    public function staff(): self
    {
        return $this->state(fn (): array => ['two_factor_required' => true]);
    }

    public function pendingGuardian(): self
    {
        return $this->minor()->state(fn (): array => ['status' => User::STATUS_PENDING_GUARDIAN]);
    }
}
