<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Production-safe: no demo credentials and no automatic user promotion.
        $this->call(RbacSeeder::class);
    }
}
