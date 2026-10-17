<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Modules\Admin\Support\DemoStories;
use Modules\Core\Identity\Models\User;
use Modules\HubOps\Tests\Staff;

/*
| Demo health check (S21): with the full demo data, every step of every guided story opens for its persona.
| A broken demo link fails CI instead of a presentation.
*/
it('opens every step of every demo story for its persona', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);
    $broken = [];
    foreach (DemoStories::all() as $story) {
        $user = User::query()->where('phone', $story['phone'])->first();
        if ($user === null) {
            $broken[] = "{$story['key']}: no demo account {$story['phone']}";

            continue;
        }
        Staff::signIn($this, $user);
        foreach ($story['steps'] as $step) {
            $status = $this->get($step['url'])->getStatusCode();
            if ($status !== 200) {
                $broken[] = "{$story['key']}: {$step['url']} -> {$status}";
            }
        }
    }

    expect($broken)->toBe([]);
});
