<?php

namespace Database\Seeders;                // ← تأكد أنها هكذا بالضبط

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SpatieDemoSeeder extends Seeder
{
    public function run(): void
    {
        // أدوار
        $admin = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        $basic = Role::firstOrCreate(['name' => 'Basic',         'guard_name' => 'web']);

        // صلاحيات مثال
        $p1 = Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
        $p2 = Permission::firstOrCreate(['name' => 'users.edit', 'guard_name' => 'web']);

        // داخل SpatieDemoSeeder::run()
        Permission::firstOrCreate(['name'=>'permissions.manage', 'guard_name'=>'web']);
        Permission::firstOrCreate(['name'=>'roles.manage',        'guard_name'=>'web']);


        // ربط الصلاحيات بالأدوار
        $admin->syncPermissions([$p1, $p2]);
        $basic->syncPermissions([$p1]);
    }
}
