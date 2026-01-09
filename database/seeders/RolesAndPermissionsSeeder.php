<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $perms = collect([
            'users.view','users.create','users.edit','users.delete',
            'groups.view','groups.create','groups.edit','groups.delete',
            'roles.view','roles.create','roles.edit','roles.delete',
        ])->map(fn($p)=> Permission::firstOrCreate(['name'=>$p,'guard_name'=>'web']));

        $admin = Role::firstOrCreate(['name'=>'Administrator','guard_name'=>'web']);
        $basic = Role::firstOrCreate(['name'=>'Basic','guard_name'=>'web']);

        $admin->syncPermissions(Permission::all());
        $basic->syncPermissions(['users.view','groups.view','roles.view']);
    }
}
