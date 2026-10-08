<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use App\Support\Format\SaFormat;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Core\Access\RoleAssignments;
use Modules\Core\Access\RoleRegistry;
use Modules\Core\Access\Scope;
use Modules\Core\Identity\Models\User;
use Modules\Core\Structure\Models\RoleAssignment;

/**
 * Role administration from the command line until the admin console arrives (S6).
 *
 *   php artisan kasi:roles assign 0720000020 hub_facilitator hub:LP-GIY-TSU
 *   php artisan kasi:roles revoke 0720000020 hub_facilitator hub:LP-GIY-TSU
 *   php artisan kasi:roles list 0720000020
 *   php artisan kasi:roles available
 */
final class RoleCommand extends Command
{
    protected $signature = 'kasi:roles {action : assign | revoke | list | available} {phone?} {role?} {scope=national}';

    protected $description = 'Assign, revoke and list scoped roles';

    public function handle(RoleAssignments $assignments, RoleRegistry $roles): int
    {
        $action = (string) $this->argument('action');

        if ($action === 'available') {
            $this->table(['Role', 'Label', 'Module', 'Scope', 'Staff'], array_map(
                static fn ($r): array => [$r->key, $r->label, $r->module, $r->scope, $r->staff ? 'yes' : 'no'],
                array_values($roles->all()),
            ));

            return self::SUCCESS;
        }

        $phone = SaFormat::normalisePhone((string) $this->argument('phone'));
        $user = $phone !== null ? User::query()->where('phone', $phone)->first() : null;

        if ($user === null) {
            $this->components->error('No account with that phone number.');

            return self::FAILURE;
        }

        try {
            return match ($action) {
                'assign' => $this->assign($assignments, $user),
                'revoke' => $this->revoke($assignments, $user),
                'list' => $this->list($user),
                default => throw new InvalidArgumentException("Unknown action [{$action}]."),
            };
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function assign(RoleAssignments $assignments, User $user): int
    {
        $scope = Scope::parse((string) $this->argument('scope'));
        $assignments->assign($user, (string) $this->argument('role'), $scope);
        $this->components->info("Assigned {$this->argument('role')} ({$scope->describe()}) to {$user->fullName()}.");

        return self::SUCCESS;
    }

    private function revoke(RoleAssignments $assignments, User $user): int
    {
        $scope = Scope::parse((string) $this->argument('scope'));
        $revoked = $assignments->revoke($user, (string) $this->argument('role'), $scope);
        $revoked ? $this->components->info('Role revoked.') : $this->components->warn('That role was not assigned.');

        return self::SUCCESS;
    }

    private function list(User $user): int
    {
        $this->table(['Role', 'Scope', 'Where', 'Expires'], $user->roleAssignments()->get()->map(static fn (RoleAssignment $a): array => [
            $a->role, $a->scope_type, (new Scope($a->scope_type, $a->scope_id))->describe(), $a->expires_at?->toDateString() ?? '-',
        ])->all());

        return self::SUCCESS;
    }
}
