<?php

namespace Tests\Concerns;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait CreatesAdminUsers
{
    protected function adminUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin', 'web'));

        return $user;
    }

    protected function normalUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->create();
    }
}
