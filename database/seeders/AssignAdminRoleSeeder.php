<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();
        if (!$user) return;

        $admin = Role::firstOrCreate(['name' => 'Administrator', 'guard_name' => 'web']);
        if (!$user->hasRole($admin->name)) {
            $user->assignRole($admin->name);
        }
    }
}
