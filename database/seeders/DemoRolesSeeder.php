<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['Administrator', 'Supplier', 'EDIT SERVICE', 'ADDER'];

        foreach ($roles as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                []
            );
        }
    }
}
