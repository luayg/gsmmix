<?php

namespace Database\Seeders;

use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (AdminPermissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate(AdminPermissions::ADMIN_ROLE, 'web')
            ->givePermissionTo(AdminPermissions::all());

        $manager = ['admin.access', 'dashboard.view', 'users.view', 'uploads.create'];
        foreach (['groups', 'services', 'orders', 'store', 'sources', 'replies'] as $module) {
            foreach (['view', 'create', 'edit'] as $action) {
                $manager[] = "{$module}.{$action}";
            }
        }
        Role::findOrCreate('Manager', 'web')->givePermissionTo($manager);
        Role::findOrCreate('Support', 'web')->givePermissionTo([
            'admin.access', 'dashboard.view', 'users.view', 'services.view',
            'orders.view', 'store.view',
        ]);
        Role::findOrCreate('Basic', 'web');

        // Additive and repeatable: never reset customized grants or assign a user.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
