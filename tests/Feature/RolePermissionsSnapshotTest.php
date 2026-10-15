<?php

declare(strict_types=1);

/*
| Every role's permissions must match tests/fixtures/role-permissions.json. A change to any role (adding or
| removing a permission) has to update the fixture on purpose, so permissions are never lost by accident -
| as happened in S19 and S20 when module settings were overwritten instead of extended.
*/
it('changes role permissions only on purpose', function (): void {
    $actual = [];
    foreach (glob(base_path('modules/*/module.json')) ?: [] as $file) {
        $manifest = (array) json_decode((string) file_get_contents($file), true);
        foreach ((array) ($manifest['roles'] ?? []) as $role) {
            $permissions = array_values((array) ($role['permissions'] ?? []));
            sort($permissions);
            $actual[(string) $role['key']] = $permissions;
        }
    }
    ksort($actual);

    expect($actual)->toBe(json_decode((string) file_get_contents(base_path('tests/fixtures/role-permissions.json')), true));
});
