<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Kept for compatibility; account promotion now requires an explicit user ID. */
class AssignAdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('No account was changed. Use php artisan admin:grant USER_ID to select an administrator explicitly.');
    }
}
