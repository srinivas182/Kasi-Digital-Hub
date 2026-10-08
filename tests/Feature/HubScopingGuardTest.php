<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Access\BelongsToHub;

/*
| Every model whose table has a hub_id (or home_hub_id) column must use BelongsToHub,
| so staff queries can be limited to the hubs they may see (ADR-011).
*/
it('makes every hub-owned model use the hub scoping trait', function (): void {
    $missing = [];
    $checked = 0;

    foreach (glob(base_path('modules/*/src/**/Models/*.php')) ?: [] as $file) {
        $class = 'Modules\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(base_path('modules/'))));
        $class = str_replace('\\src\\', '\\', $class);

        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $table = (new $class)->getTable();
        $hubColumn = Schema::hasColumn($table, 'hub_id') || Schema::hasColumn($table, 'home_hub_id');

        if ($table === 'hubs' || ! $hubColumn) {
            continue;
        }

        $checked++;
        if (! in_array(BelongsToHub::class, class_uses_recursive($class), true)) {
            $missing[] = $class;
        }
    }

    expect($checked)->toBeGreaterThan(0)->and($missing)->toBe([]);
});
